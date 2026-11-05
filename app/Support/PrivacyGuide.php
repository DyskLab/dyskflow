<?php

namespace App\Support;

use App\Models\Plugin;

/** Describes what this site really collects, so a privacy policy can start from the facts and not a template. */
class PrivacyGuide
{
    /** @return list<array{title: string, text: string}> */
    public static function facts(?string $locale = null): array
    {
        $t = fn (string $text, array $params = []) => __($text, $params, $locale);

        $facts = [
            ['title' => $t('Cookies'), 'text' => $t('Reading pages sets no cookies for new visitors. Pages with a comment form set a session cookie and a security cookie, and so does logging in. The light or dark theme choice stays in the browser.')],
        ];
        if (setting('comments_enabled', '1')) {
            $facts[] = ['title' => $t('Comments'), 'text' => $t('People who comment give a name, an email address and optionally a website. The site also stores their IP address and browser details to fight spam. Comments stay until they are permanently deleted, and comments in the trash are kept too.')];
        }
        $facts[] = ['title' => $t('Admin accounts'), 'text' => $t('Each account stores a name, an email address, a hashed password and the last login time. The activity log records who changed what, with the IP address.')];
        $facts[] = ['title' => $t('Media'), 'text' => $t('Uploaded files can be opened by anyone who has the link.')];
        $facts[] = ['title' => $t('Backup'), 'text' => $t('Backups hold a copy of the database, including accounts and comments. Whoever downloads one holds that data.')];
        $host = setting('mail_source', 'env') === 'settings' ? setting('mail_host') : (config('mail.default') === 'smtp' ? config('mail.mailers.smtp.host') : null);
        if ($host = trim((string) $host)) {
            $facts[] = ['title' => $t('Email'), 'text' => $t('Email is sent through :host, so messages pass through that provider.', ['host' => $host])];
        }
        $facts[] = ['title' => $t('Update'), 'text' => $t('Checking for updates connects this server to GitHub. No visitor data is sent.')];

        $plugins = Plugin::where('active', true)->pluck('slug')
            ->map(fn ($slug) => Plugins::manifest($slug)['name'] ?? $slug)->implode(', ');
        if ($plugins !== '') {
            $facts[] = ['title' => $t('Plugins'), 'text' => $t('Active plugins: :list. Check what each one collects, because plugins can add their own data and cookies.', ['list' => $plugins])];
        }

        return $facts;
    }

    /** @return list<string> */
    public static function checklist(): array
    {
        return [
            __('Who runs this site and how to contact them.'),
            __('What data you collect, and why you need it.'),
            __('How long you keep each kind of data.'),
            __('Who else receives the data, such as hosting and email providers.'),
            __('How people can see, correct or delete their data.'),
            __('Which cookies the site uses.'),
            __('The date the policy last changed.'),
        ];
    }

    /** The first draft of the policy, in the site's default language. Words in square brackets are for the owner to replace. */
    public static function draft(string $locale): string
    {
        $t = fn (string $text, array $params = []) => __($text, $params, $locale);
        $site = (string) setting('site_title', 'DyskFlow');
        $contact = trim((string) setting('contact_email')) ?: $t('[your email address]');

        $html = '<p>'.e($t('This page explains what data :site collects and how it is used.', ['site' => $site])).'</p>';
        $html .= '<h2>'.e($t('Who we are')).'</h2><p>'.e($t(':site is run by [name of person or company]. Contact: :email.', ['site' => $site, 'email' => $contact])).'</p>';
        $html .= '<h2>'.e($t('What we collect')).'</h2>';
        foreach (self::facts($locale) as $fact) {
            $html .= '<h3>'.e($fact['title']).'</h3><p>'.e($fact['text']).'</p>';
        }
        $html .= '<h2>'.e($t('How long we keep it')).'</h2><p>'.e($t('[Write how long you keep each kind of data.]')).'</p>';
        $html .= '<h2>'.e($t('Your rights')).'</h2><p>'.e($t('You can ask to see, correct or delete your data by contacting :email.', ['email' => $contact])).'</p>';
        $html .= '<h2>'.e($t('Changes to this policy')).'</h2><p>'.e($t('Last changed: :date.', ['date' => now()->format('d/m/Y')])).'</p>';

        return HtmlSanitizer::clean($html);
    }
}
