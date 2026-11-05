<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Allow-list cleaner for article and page HTML. Removes scripts, event handlers and unsafe URLs. */
class HtmlSanitizer
{
    private const TAGS = [
        'p', 'br', 'hr', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote',
        'pre', 'code', 'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span',
    ];

    private const ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'link', 'meta', 'base'];

    public static function clean(?string $html): string
    {
        if (trim((string) $html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('cms-root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::walk($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private static function walk(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::walk($node);

            if (! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }
            self::cleanAttributes($node, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $node, string $tag): void
    {
        $allowed = self::ATTRIBUTES[$tag] ?? [];
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $isUrl = in_array($name, ['href', 'src'], true);
            if (! in_array($name, $allowed, true) || ($isUrl && ! self::safeUrl($attribute->value))) {
                $node->removeAttribute($attribute->name);
            }
        }
        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeUrl(string $url): bool
    {
        // Browsers ignore control characters and spaces inside the scheme, so strip them first.
        $url = preg_replace('/[\x00-\x20]+/', '', $url);

        return preg_match('#^(https?:|mailto:|tel:|/|\#|\.{0,2}/)#i', $url) === 1
            || ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url);
    }
}
