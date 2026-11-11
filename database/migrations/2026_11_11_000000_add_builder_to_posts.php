<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builder pages are pages, like Elementor: the layout is JSON in posts.builder, and unpublished
 * changes are in posts.builder_draft. A page without builder JSON uses the classic editor.
 * Builder pages made earlier as landing/<slug>/page.json are copied into pages; their folders
 * stay as they were, as a backup, and images in assets/ are copied to public/uploads/builder/<slug>/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->longText('builder')->nullable()->after('content');
            $table->longText('builder_draft')->nullable()->after('builder');
        });

        // A new install has no users and no builder pages yet.
        if (! DB::table('users')->exists()) {
            return;
        }

        $setting = fn (string $key) => DB::table('settings')->where('key', $key)->value('value');
        $write = fn (string $key, ?string $value) => DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        $reserved = (array) config('cms.reserved_slugs');

        foreach (glob(base_path('landing/*/page.json')) ?: [] as $file) {
            $folder = dirname($file);
            $slug = basename($folder);
            // A code entry always won over page.json, so that folder was never a builder page.
            if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) || is_file($folder.'/index.blade.php') || is_file($folder.'/index.html')) {
                continue;
            }
            $published = json_decode((string) file_get_contents($file), true);
            if (! is_array($published)) {
                continue;
            }
            $draft = is_file($folder.'/page.draft.json') ? json_decode((string) file_get_contents($folder.'/page.draft.json'), true) : null;
            $meta = json_decode((string) @file_get_contents($folder.'/landing.json'), true) ?: [];

            $assets = $this->copyAssets($folder, $slug);
            $prepare = function (array $spec) use ($assets) {
                unset($spec['$schema']);
                if (($spec['layout'] ?? 'site') === 'custom') {
                    $spec['layout'] = 'site';
                }

                return json_encode($this->rewrite($spec, $assets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            };

            $pageSlug = $slug;
            for ($i = 2; in_array($pageSlug, $reserved, true) || DB::table('posts')->where('type', 'page')->where('slug', $pageSlug)->exists(); $i++) {
                $pageSlug = $slug.'-'.$i;
            }
            $public = ($meta['public'] ?? false) === true;
            $id = DB::table('posts')->insertGetId([
                'type' => 'page',
                'title' => Str::limit((string) ($meta['title'] ?? $slug), 250, ''),
                'slug' => $pageSlug,
                'content' => '',
                'builder' => $prepare($published),
                'builder_draft' => is_array($draft) ? $prepare($draft) : null,
                'meta_description' => ($meta['description'] ?? '') !== '' ? (string) $meta['description'] : null,
                'status' => $public ? 'published' : 'draft',
                'published_at' => $public ? now() : null,
                'show_in_menu' => ($meta['menu'] ?? false) === true && $public,
                'allow_comments' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($setting('home_type') === 'landing' && $setting('home_landing') === $slug) {
                $write('home_type', 'page');
                $write('home_page_id', (string) $id);
            }
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['builder', 'builder_draft']);
        });
    }

    /** @return array<string, string> "assets/x.png" => "uploads/builder/<slug>/x.png" */
    private function copyAssets(string $folder, string $slug): array
    {
        $map = [];
        $images = ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'mp4', 'webm'];
        foreach (is_dir($folder.'/assets') ? File::allFiles($folder.'/assets') : [] as $asset) {
            $relative = str_replace('\\', '/', $asset->getRelativePathname());
            if (! in_array(strtolower($asset->getExtension()), $images, true) || str_contains($relative, '..')) {
                continue;
            }
            $target = 'uploads/builder/'.$slug.'/'.$relative;
            File::ensureDirectoryExists(dirname(public_path($target)));
            if (! is_file(public_path($target))) {
                File::copy($asset->getPathname(), public_path($target));
            }
            $map['assets/'.$relative] = $target;
        }

        return $map;
    }

    private function rewrite(mixed $value, array $assets): mixed
    {
        if (is_string($value)) {
            return $assets[ltrim($value, '/')] ?? $value;
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->rewrite($item, $assets), $value);
        }

        return $value;
    }
};
