<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Landing pages no longer attach to pages: they are served from their folder.
 * posts.landing stays so backups made before this change can still be restored, but nothing reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A new install has no users yet and starts on the default landing page.
        if (! DB::table('users')->exists()) {
            return;
        }

        $setting = fn (string $key) => DB::table('settings')->where('key', $key)->value('value');
        $write = fn (string $key, ?string $value) => DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);

        // The old default was the article list, so an install that never chose keeps it.
        if (! $setting('home_type')) {
            $write('home_type', 'posts');
        }

        $pages = DB::table('posts')->where('type', 'page')->whereNotNull('landing')->whereNull('deleted_at')->get(['id', 'landing', 'content']);
        foreach ($pages as $page) {
            if ($setting('home_type') === 'page' && (string) $setting('home_page_id') === (string) $page->id) {
                $write('home_type', 'landing');
                $write('home_landing', $page->landing);
                $write('home_page_id', null);
            }
            // Without its landing page this one is empty. The trash keeps it recoverable.
            if (trim(strip_tags((string) $page->content)) === '') {
                DB::table('posts')->where('id', $page->id)->update(['deleted_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
