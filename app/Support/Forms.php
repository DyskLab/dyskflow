<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The builder's Form widget. The form's settings (its fields, who is notified, the message after
 * sending) travel with the form, encrypted with the app key, so the server trusts them without
 * looking the page up and public pages need no session or CSRF cookie (see CacheablePublicPages).
 * Spam is held back by a hidden field, a per-visitor rate limit and the comment blocklist.
 */
class Forms
{
    public const FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'number', 'date'];

    /** The hidden field bots fill in. Named like a real field on purpose. */
    public const HONEYPOT = 'website_url';

    public static function seal(array $config): string
    {
        return Crypt::encryptString(json_encode($config, JSON_UNESCAPED_UNICODE));
    }

    public static function open(string $sealed): ?array
    {
        try {
            $config = json_decode(Crypt::decryptString($sealed), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($config) && is_array($config['fields'] ?? null) ? $config : null;
    }

    /** @return list<string> options of a select or radio field, one per line */
    public static function options(string $lines): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $lines) ?: []), fn ($o) => $o !== '')));
    }
}
