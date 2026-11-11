<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    /** Each language has its own copy of these. Dates, images, terms and comments are shared. */
    public const TRANSLATED = ['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'noindex'];

    /** Set by localize(). Null means the default language. */
    public ?string $activeLocale = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'allow_comments' => 'boolean',
            'show_in_menu' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->terms()->where('taxonomy', 'category');
    }

    public function tags(): BelongsToMany
    {
        return $this->terms()->where('taxonomy', 'tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** Live in this language: the original must be live, and the second language also needs a published translation. */
    public function scopeLiveIn(Builder $query, string $locale): Builder
    {
        return $query->live()->when(! Locales::isDefault($locale), fn ($q) => $q->whereHas(
            'translations',
            fn ($t) => $t->where('locale', $locale)->where('status', 'published'),
        ));
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    /**
     * Shows this post in a language for the current request. The record must not be saved afterwards.
     * Categories and tags without a translation in that language are left out.
     */
    public function localize(string $locale): static
    {
        if (Locales::isDefault($locale)) {
            return $this;
        }

        if ($translation = $this->translations->firstWhere('locale', $locale)) {
            $this->forceFill($translation->only(self::TRANSLATED));
            // A published translation of a draft is still not public.
            $this->status = $this->getOriginal('status') === 'published' ? $translation->status : 'draft';
            $this->activeLocale = $locale;
        }
        foreach (['categories', 'tags'] as $relation) {
            if ($this->relationLoaded($relation)) {
                $this->setRelation($relation, $this->getRelation($relation)->each->localize($locale)->filter(fn ($term) => $term->activeLocale)->values());
            }
        }

        return $this;
    }

    /**
     * A builder page keeps its layout as JSON in "builder" (see App\Support\Builder\Builder); others use the classic editor.
     * Templates (Design > Templates) and saved blocks (Design > Blocks) are builder layouts too.
     */
    public function isBuilder(): bool
    {
        return in_array($this->type, ['page', 'template', 'block'], true) && $this->builder !== null;
    }

    /** The published layout, or with $draft the unpublished changes when there are any. */
    public function builderSpec(bool $draft = false): ?array
    {
        $json = $draft && $this->builder_draft !== null ? $this->builder_draft : $this->builder;
        $spec = is_string($json) ? json_decode($json, true) : null;

        return is_array($spec) ? $spec : null;
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && (! $this->published_at || $this->published_at->isPast());
    }

    public function isHomePage(): bool
    {
        return $this->type === 'page' && setting('home_type') === 'page' && (string) setting('home_page_id') === (string) $this->id;
    }

    public function url(): string
    {
        return Locales::url($this->path($this->slug), $this->activeLocale);
    }

    private function path(string $slug): string
    {
        return match (true) {
            $this->isHomePage() => '',
            $this->type === 'article' => 'blog/'.$slug,
            default => $slug,
        };
    }

    /**
     * URLs of the public versions by language.
     * For hreflang and the sitemap, a hidden version or one that points its canonical elsewhere is left out.
     *
     * @return array<string, string>
     */
    public function versions(bool $indexableOnly = true): array
    {
        $isLive = $this->getOriginal('status') === 'published' && (! $this->published_at || $this->published_at->isPast());
        if (! $isLive) {
            return [];
        }

        $versions = [];
        if (! $indexableOnly || (! $this->getOriginal('noindex') && ! $this->getOriginal('canonical_url'))) {
            $versions[Locales::default()] = Locales::url($this->path($this->getOriginal('slug')));
        }
        foreach ($this->translations as $translation) {
            if (isset(Locales::ALL[$translation->locale]) && ! Locales::isDefault($translation->locale)
                && $translation->status === 'published' && (! $indexableOnly || (! $translation->noindex && ! $translation->canonical_url))) {
                $versions[$translation->locale] = Locales::url($this->path($translation->slug), $translation->locale);
            }
        }

        return $versions;
    }

    /** Comments are open only if the site, the post and the optional age limit all allow it. */
    public function commentsOpen(): bool
    {
        if ($this->type !== 'article' || ! $this->allow_comments || ! setting('comments_enabled', '1')) {
            return false;
        }
        $days = (int) setting('comments_close_after_days', 0);

        return $days === 0 || ! $this->published_at || $this->published_at->gt(now()->subDays($days));
    }
}
