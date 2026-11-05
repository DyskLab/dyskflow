<?php

namespace App\Support;

use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Throwable;

/**
 * Notifications in the admin header. Nothing here may break the page that triggered it:
 * a comment still saves and the admin still opens if this table is missing, as it is
 * between copying new code and running migrations during an update.
 */
class Notifier
{
    private const CHECK_EVERY_SECONDS = 43200;

    private const KEEP_DAYS = 90;

    /**
     * Sends a notification. Pass the title as English text, translated in lang/id.json.
     * With a fingerprint, a repeat raises the count and makes it unread again instead of adding a row.
     * The url is relative to the site, such as admin/comments.
     */
    public static function send(string $type, string $title, array $params = [], ?string $body = null, ?string $url = null, ?string $capability = null, ?string $fingerprint = null): void
    {
        rescue(function () use ($type, $title, $params, $body, $url, $capability, $fingerprint) {
            AdminNotification::where('updated_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

            $existing = $fingerprint ? AdminNotification::where('fingerprint', $fingerprint)->first() : null;
            if ($existing) {
                $existing->update(['count' => $existing->count + 1, 'body' => $body, 'params' => $params]);
                $existing->readers()->detach();

                return;
            }

            AdminNotification::create(compact('type', 'title', 'params', 'body', 'url', 'capability', 'fingerprint'));
        }, null, false);
    }

    public static function comment(Comment $comment): void
    {
        if ($comment->status === 'spam') {
            return;
        }

        self::send(
            'comment',
            $comment->status === 'pending' ? 'Comment from :name on “:post” is awaiting approval' : 'New comment from :name on “:post”',
            ['name' => $comment->author_name, 'post' => (string) $comment->post?->title],
            str($comment->content)->squish()->limit(140)->toString(),
            'admin/comments',
            'comments.manage',
        );
    }

    /** Fed by every error written to the log. The same error repeats on one row. */
    public static function logged(MessageLogged $event): void
    {
        if (! in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }

        $exception = $event->context['exception'] ?? null;
        $source = $exception instanceof Throwable
            ? get_class($exception).$exception->getFile().$exception->getLine()
            : $event->message;

        self::send(
            'error',
            'An error occurred on the site',
            [],
            str($event->message)->squish()->limit(300)->toString(),
            null,
            'logs.view',
            'error:'.sha1($source),
        );
    }

    /** Hosting often has no cron, so an admin page view asks for the check when one is due. */
    public static function updateCheckDue(User $user): bool
    {
        return $user->can('updates.manage')
            && UpdateService::repo() !== ''
            && time() - (int) setting('update_checked_at', 0) >= self::CHECK_EVERY_SECONDS;
    }

    /** A failed check stays silent and waits for the next one: GitHub being unreachable is not a site error. */
    public static function checkForUpdate(): void
    {
        Setting::write(['update_checked_at' => (string) time()]);

        try {
            self::syncUpdate(UpdateService::check());
        } catch (Throwable) {
        }
    }

    /** Keeps at most one update notification: the newest version, or none once up to date. */
    public static function syncUpdate(array $check): void
    {
        $fingerprint = 'update:'.$check['latest'];
        rescue(fn () => AdminNotification::where('type', 'update')
            ->when($check['available'], fn ($q) => $q->where('fingerprint', '!=', $fingerprint))
            ->delete(), null, false);

        if ($check['available'] && ! rescue(fn () => AdminNotification::where('fingerprint', $fingerprint)->exists(), true, false)) {
            self::send('update', 'Version :version is available', ['version' => $check['latest']], null, 'admin/settings/update?check=1', 'updates.manage', $fingerprint);
        }
    }

    public static function clearUpdates(): void
    {
        rescue(fn () => AdminNotification::where('type', 'update')->delete(), null, false);
    }

    /** @return array{items: \Illuminate\Support\Collection, unread: int} */
    public static function forHeader(User $user): array
    {
        return rescue(fn () => [
            'items' => AdminNotification::visibleTo($user)->withReadState($user)->latest('updated_at')->latest('id')->limit(8)->get(),
            'unread' => AdminNotification::visibleTo($user)->unreadBy($user)->count(),
        ], ['items' => collect(), 'unread' => 0], false);
    }
}
