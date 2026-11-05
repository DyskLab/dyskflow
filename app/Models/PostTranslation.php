<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PostTranslation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public static function uniqueSlug(string $type, string $locale, string $source, int $postId): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        for ($i = 2; self::where(['type' => $type, 'locale' => $locale, 'slug' => $slug])->where('post_id', '!=', $postId)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
