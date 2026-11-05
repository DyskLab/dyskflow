<?php

namespace App\Support;

use App\Models\Media;

/** Completes images in sanitized content from the media library, so pages do not shift while loading. */
class ContentImages
{
    private const TAG = '/<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';

    public static function enhance(string $html): string
    {
        if (! preg_match_all(self::TAG, $html, $tags)) {
            return $html;
        }

        $paths = array_filter(array_map(fn ($tag) => self::uploadPath(self::attributes($tag)['src'] ?? ''), $tags[0]));
        $media = $paths ? Media::whereIn('path', array_unique($paths))->get()->keyBy('path') : collect();

        return preg_replace_callback(self::TAG, function ($match) use ($media) {
            $attributes = self::attributes($match[0]);
            $item = $media[self::uploadPath($attributes['src'] ?? '')] ?? null;

            if (($attributes['alt'] ?? '') === '') {
                $attributes['alt'] = e((string) $item?->alt);
            }
            if ($item?->width && $item?->height && ! isset($attributes['width']) && ! isset($attributes['height'])) {
                $attributes['width'] = (string) $item->width;
                $attributes['height'] = (string) $item->height;
            }
            $attributes['loading'] ??= 'lazy';

            $output = '<img';
            foreach ($attributes as $name => $value) {
                $output .= ' '.$name.'="'.$value.'"';
            }

            return $output.'>';
        }, $html);
    }

    /** Attribute values stay HTML-escaped, exactly as the sanitizer wrote them. */
    private static function attributes(string $tag): array
    {
        preg_match_all('/([a-z-]+)\s*=\s*"([^"]*)"/i', $tag, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = $match[2];
        }

        return $attributes;
    }

    /** Media paths have no domain or subfolder, for example uploads/2026/11/photo.jpg. */
    private static function uploadPath(string $src): string
    {
        $path = rawurldecode((string) parse_url(html_entity_decode($src), PHP_URL_PATH));

        return preg_match('#(?:^|/)(uploads/.+)$#', $path, $match) ? $match[1] : '';
    }
}
