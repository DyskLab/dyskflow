<?php

namespace App\Support;

use App\Models\Post;

/** Declares every settings form. Fields render and save generically in SettingController. */
class SettingsSchema
{
    public static function sections(): array
    {
        return [
            'general' => [
                'title' => __('General'), 'icon' => 'globe',
                'description' => __('Title, tagline, favicon, address and time.'),
                'fields' => [
                    ['name' => 'site_title', 'label' => __('Site title'), 'type' => 'text', 'required' => true],
                    ['name' => 'tagline', 'label' => __('Tagline'), 'type' => 'text'],
                    ['name' => 'tagline_translated', 'label' => __('Tagline in :language', ['language' => Locales::name(Locales::second())]), 'type' => 'text'],
                    ['name' => 'favicon', 'label' => __('Favicon'), 'type' => 'media', 'help' => __('Leave empty to use the DyskFlow logo.')],
                    ['name' => 'site_url', 'label' => __('Site address (URL)'), 'type' => 'url', 'required' => true],
                    ['name' => 'timezone', 'label' => __('Timezone'), 'type' => 'select', 'options' => array_combine(timezone_identifiers_list(), timezone_identifiers_list()), 'default' => 'Asia/Jakarta'],
                    ['name' => 'date_format', 'label' => __('Date format'), 'type' => 'select', 'default' => 'd M Y H:i', 'options' => ['d M Y H:i' => '05 Nov 2026 19:30', 'd/m/Y H:i' => '05/11/2026 19:30', 'Y-m-d H:i' => '2026-11-05 19:30', 'M j, Y g:i a' => 'Nov 5, 2026 7:30 pm']],
                    ['name' => 'locale', 'label' => __('Default language'), 'type' => 'select', 'options' => Locales::ALL, 'default' => 'id', 'help' => __('Content you write first is in this language and keeps its URLs without a prefix. Translations use the other language, such as /en/blog/slug. Changing this later hides existing translations.')],
                ],
            ],
            'contact' => [
                'title' => __('Contact'), 'icon' => 'phone',
                'description' => __('Contact details shown on the site and landing pages.'),
                'fields' => [
                    ['name' => 'contact_email', 'label' => __('Email'), 'type' => 'email'],
                    ['name' => 'contact_phone', 'label' => __('Phone'), 'type' => 'text'],
                    ['name' => 'contact_whatsapp', 'label' => 'WhatsApp', 'type' => 'text', 'help' => __('Number with country code, for example 6281234567890.')],
                    ['name' => 'contact_address', 'label' => __('Address'), 'type' => 'textarea'],
                    ['name' => 'contact_map_url', 'label' => __('Map URL'), 'type' => 'url'],
                    ['name' => 'social_instagram', 'label' => 'Instagram', 'type' => 'url'],
                    ['name' => 'social_facebook', 'label' => 'Facebook', 'type' => 'url'],
                    ['name' => 'social_x', 'label' => 'X', 'type' => 'url'],
                    ['name' => 'social_linkedin', 'label' => 'LinkedIn', 'type' => 'url'],
                    ['name' => 'social_youtube', 'label' => 'YouTube', 'type' => 'url'],
                    ['name' => 'social_tiktok', 'label' => 'TikTok', 'type' => 'url'],
                ],
            ],
            'email' => [
                'title' => __('Email'), 'icon' => 'mail',
                'description' => __('Send email using .env or the settings below.'),
                'fields' => [
                    ['name' => 'mail_source', 'label' => __('Configuration source'), 'type' => 'select', 'options' => ['env' => __('.env file'), 'settings' => __('These settings')], 'default' => 'env'],
                    ['name' => 'mail_host', 'label' => __('SMTP host'), 'type' => 'text'],
                    ['name' => 'mail_port', 'label' => __('SMTP port'), 'type' => 'number', 'default' => '587'],
                    ['name' => 'mail_scheme', 'label' => __('Connection'), 'type' => 'select', 'options' => ['smtp' => 'STARTTLS / '.__('none'), 'smtps' => 'SSL/TLS'], 'default' => 'smtp'],
                    ['name' => 'mail_username', 'label' => __('Username'), 'type' => 'text'],
                    ['name' => 'mail_password', 'label' => __('Password'), 'type' => 'secret', 'help' => __('Stored encrypted. Leave empty to keep the current password.')],
                    ['name' => 'mail_from_address', 'label' => __('From address'), 'type' => 'email'],
                    ['name' => 'mail_from_name', 'label' => __('From name'), 'type' => 'text'],
                ],
            ],
            'seo' => [
                'title' => __('SEO'), 'icon' => 'search',
                'description' => __('Title format, default meta, sitemap and robots.'),
                'fields' => [
                    ['name' => 'seo_title_format', 'label' => __('Title format'), 'type' => 'text', 'default' => '%title% — %site%', 'help' => __('Use %title% and %site%.')],
                    ['name' => 'seo_description', 'label' => __('Default meta description'), 'type' => 'textarea'],
                    ['name' => 'seo_description_translated', 'label' => __('Default meta description in :language', ['language' => Locales::name(Locales::second())]), 'type' => 'textarea'],
                    ['name' => 'seo_og_image', 'label' => __('Default social image'), 'type' => 'media'],
                    ['name' => 'seo_google_verification', 'label' => __('Google Search Console code'), 'type' => 'text'],
                    ['name' => 'seo_sitemap', 'label' => __('Publish sitemap.xml'), 'type' => 'switch', 'default' => '1'],
                    ['name' => 'seo_noindex', 'label' => __('Ask search engines not to index this site'), 'type' => 'switch'],
                    ['name' => 'seo_robots', 'label' => __('Extra robots.txt rules'), 'type' => 'textarea'],
                ],
            ],
            'media' => [
                'title' => __('Media'), 'icon' => 'image',
                'description' => __('Upload size, file types and image width.'),
                'fields' => [
                    ['name' => 'media_max_mb', 'label' => __('Maximum upload (MB)'), 'type' => 'number', 'default' => '10', 'help' => __('Server limit: :limit', ['limit' => ini_get('upload_max_filesize')])],
                    ['name' => 'media_image_max_width', 'label' => __('Maximum image width (px)'), 'type' => 'number', 'default' => '2000', 'help' => __('Larger images are resized. Use 0 to keep the original.')],
                    ['name' => 'media_allowed', 'label' => __('Allowed file types'), 'type' => 'checkboxes', 'options' => array_combine(config('cms.upload_choices'), config('cms.upload_choices')), 'default' => 'jpg,jpeg,png,webp,gif,pdf'],
                ],
            ],
            'comments' => [
                'title' => __('Comments'), 'icon' => 'comment',
                'description' => __('Who can comment and how comments are moderated.'),
                'fields' => [
                    ['name' => 'comments_enabled', 'label' => __('Allow comments'), 'type' => 'switch', 'default' => '1'],
                    ['name' => 'comments_moderation', 'label' => __('Hold new comments for approval'), 'type' => 'switch', 'default' => '1'],
                    ['name' => 'comments_require_email', 'label' => __('Require an email address'), 'type' => 'switch', 'default' => '1'],
                    ['name' => 'comments_close_after_days', 'label' => __('Close comments after (days)'), 'type' => 'number', 'default' => '0', 'help' => __('Use 0 to keep comments open.')],
                    ['name' => 'comments_per_page', 'label' => __('Comments shown per article'), 'type' => 'number', 'default' => '50'],
                    ['name' => 'comments_blocklist', 'label' => __('Blocked words'), 'type' => 'textarea', 'help' => __('One per line. Matching comments are marked as spam.')],
                ],
            ],
            'pages' => [
                'title' => __('Pages'), 'icon' => 'page',
                'description' => __('Homepage and number of articles per page.'),
                'fields' => [
                    ['name' => 'home_type', 'label' => __('Homepage shows'), 'type' => 'select', 'options' => ['landing' => __('Landing page'), 'posts' => __('Latest articles'), 'page' => __('A page')], 'default' => 'landing'],
                    ['name' => 'home_landing', 'label' => __('Homepage landing page'), 'type' => 'select', 'options' => Landing::options(), 'default' => 'dyskflow', 'help' => __('Landing pages are written as code in the landing folder. Add a folder there to see it in this list.')],
                    ['name' => 'home_page_id', 'label' => __('Homepage page'), 'type' => 'select', 'options' => ['' => '—'] + Post::where('type', 'page')->orderBy('title')->pluck('title', 'id')->all(), 'help' => __('Used only when the homepage shows a page. It shows that page\'s own content.')],
                    ['name' => 'privacy_page_id', 'label' => __('Privacy policy page'), 'type' => 'select', 'options' => ['' => '—'] + Post::where('type', 'page')->orderBy('title')->pluck('title', 'id')->all(), 'help' => __('Once published, it is linked in the site footer.')],
                    ['name' => 'posts_per_page', 'label' => __('Articles per page'), 'type' => 'number', 'default' => '10'],
                    ['name' => 'show_view_count', 'label' => __('Show view count on articles'), 'type' => 'switch', 'default' => '1', 'help' => __('Counts visits by people who are not logged in. Search engine bots are skipped. Visits served from a CDN cache are not counted.')],
                ],
            ],
            'update' => [
                'title' => __('Update'), 'icon' => 'refresh', 'hidden' => true,
                'description' => '',
                'fields' => [
                    ['name' => 'update_repo', 'label' => __('Public GitHub repository'), 'type' => 'text', 'help' => __('Format: username/repository')],
                ],
            ],
        ];
    }

    /** Applies stored values to Laravel's runtime configuration. */
    public static function applyRuntime(): void
    {
        config([
            'app.name' => setting('site_title', config('app.name')),
            'app.url' => setting('site_url', config('app.url')),
        ]);

        if (setting('mail_source', 'env') !== 'settings') {
            return;
        }
        try {
            $password = setting('mail_password') ? decrypt(setting('mail_password')) : null;
        } catch (\Throwable) {
            $password = null;
        }
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => setting('mail_scheme', 'smtp'),
            'mail.mailers.smtp.host' => setting('mail_host'),
            'mail.mailers.smtp.port' => (int) setting('mail_port', 587),
            'mail.mailers.smtp.username' => setting('mail_username'),
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => setting('mail_from_address', config('mail.from.address')),
            'mail.from.name' => setting('mail_from_name', setting('site_title')),
        ]);
    }
}
