<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TermTranslation extends Model
{
    protected $guarded = ['id'];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public static function uniqueSlug(string $taxonomy, string $locale, string $source, int $termId): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        for ($i = 2; self::where(['taxonomy' => $taxonomy, 'locale' => $locale, 'slug' => $slug])->where('term_id', '!=', $termId)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
