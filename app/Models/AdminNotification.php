<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Title is stored as the English text with its params, so each admin reads it in their own language. */
class AdminNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_notification_reads', 'notification_id', 'user_id');
    }

    /** Notifications with no capability are for every admin user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $allowed = self::query()->whereNotNull('capability')->distinct()->pluck('capability')
            ->filter(fn ($capability) => $user->can($capability))->values();

        return $query->where(fn ($q) => $q->whereNull('capability')->orWhereIn('capability', $allowed));
    }

    public function scopeUnreadBy(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id));
    }

    public function scopeWithReadState(Builder $query, User $user): Builder
    {
        return $query->withExists(['readers as is_read' => fn ($q) => $q->where('users.id', $user->id)]);
    }

    public function titleText(): string
    {
        return __($this->title, $this->params ?? []);
    }

    public function iconName(): string
    {
        return ['comment' => 'comment', 'update' => 'refresh', 'error' => 'alert'][$this->type] ?? 'bell';
    }

    public function markReadBy(User $user): void
    {
        $this->readers()->syncWithoutDetaching([$user->id]);
    }
}
