<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    public const TRANSLATED = ['name', 'slug', 'description', 'meta_title', 'meta_description'];

    /** Set by localize(). Null means the default language, or no translation in the requested one. */
    public ?string $activeLocale = null;

    protected $guarded = ['id'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TermTranslation::class);
    }

    /** Shows this term in a language for the current request. The record must not be saved afterwards. */
    public function localize(string $locale): static
    {
        if (! Locales::isDefault($locale) && ($translation = $this->translations->firstWhere('locale', $locale))) {
            $this->forceFill($translation->only(self::TRANSLATED));
            $this->activeLocale = $locale;
        }

        return $this;
    }

    public function url(): string
    {
        return Locales::url($this->path($this->slug), $this->activeLocale);
    }

    private function path(string $slug): string
    {
        return ($this->taxonomy === 'category' ? 'category/' : 'tag/').$slug;
    }

    /**
     * URLs of the versions with articles to show, by language, for hreflang and the sitemap.
     *
     * @return array<string, string>
     */
    public function versions(): array
    {
        $versions = [];
        if ($this->posts()->liveIn(Locales::default())->exists()) {
            $versions[Locales::default()] = Locales::url($this->path($this->getOriginal('slug')));
        }
        foreach ($this->translations as $translation) {
            if (isset(Locales::ALL[$translation->locale]) && ! Locales::isDefault($translation->locale)
                && $this->posts()->liveIn($translation->locale)->exists()) {
                $versions[$translation->locale] = Locales::url($this->path($translation->slug), $translation->locale);
            }
        }

        return $versions;
    }
}
