<?php

namespace App\Support\Builder;

use App\Models\Post;
use App\Models\Term;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * What a template is showing, for the dynamic widgets (post-title, archive-posts, …):
 * the article of a single template, or the list of an archive. Set only while a template
 * renders; a widget with no context shows nothing on the site and a note in the editor.
 */
class Context
{
    public static ?Post $post = null;

    /** The article's HTML, already cleaned and filtered like the theme's article view. */
    public static string $content = '';

    public static ?Collection $comments = null;

    public static ?Paginator $posts = null;

    public static ?string $heading = null;

    public static ?string $intro = null;

    public static ?Term $term = null;

    /** The other language's address of the page being shown (Seo::languageLink), for the language switch. */
    public static ?array $languageLink = null;

    /** True in the editor preview, where the article or list is an example. */
    public static bool $sample = false;

    public static function set(array $values): void
    {
        self::reset();
        foreach ($values as $key => $value) {
            if (property_exists(self::class, $key)) {
                self::${$key} = $value;
            }
        }
    }

    public static function reset(): void
    {
        self::$post = null;
        self::$content = '';
        self::$comments = null;
        self::$posts = null;
        self::$heading = null;
        self::$intro = null;
        self::$term = null;
        self::$sample = false;
        self::$languageLink = null;
    }
}
