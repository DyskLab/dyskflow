<?php

namespace App\Support;

/**
 * Content is written in the default language at unprefixed URLs. The second
 * language lives in the translation tables at URLs such as /en/blog/slug.
 */
class Locales
{
    public const ALL = ['id' => 'Bahasa Indonesia', 'en' => 'English'];

    public static function default(): string
    {
        $locale = setting('locale', 'id');

        return isset(self::ALL[$locale]) ? $locale : 'id';
    }

    public static function second(): string
    {
        return self::default() === 'id' ? 'en' : 'id';
    }

    public static function isDefault(string $locale): bool
    {
        return $locale === self::default();
    }

    public static function name(string $locale): string
    {
        return self::ALL[$locale] ?? $locale;
    }

    /** A text setting in the language of this request. Empty when not translated, so one page never mixes languages. */
    public static function text(string $key): ?string
    {
        return setting(self::isDefault(app()->getLocale()) ? $key : $key.'_translated');
    }

    public static function url(string $path = '', ?string $locale = null): string
    {
        $prefix = $locale && ! self::isDefault($locale) ? $locale.'/' : '';

        return url(rtrim($prefix.ltrim($path, '/'), '/') ?: '/');
    }
}
