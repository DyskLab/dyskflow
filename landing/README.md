# Landing pages

Landing pages are coded here, separately from the CMS, by hand or with AI.
Updates never overwrite this folder. AI tools should read [docs/AI-GUIDE.md](../docs/AI-GUIDE.md) for the full details.

## Create one

1. Make a folder: `landing/<slug>/` (lowercase letters, numbers, `-`, `_`).
2. Add one entry file: `index.blade.php` or `index.html`.
3. Add `landing.json`: `{ "title": "...", "description": "..." }`.
4. Put CSS, JS, images and fonts in `assets/`.
5. To make it the homepage, choose it in **Settings > Pages**. The default is `dyskflow`.
6. To give it its own address, such as `/promo`, add `"public": true` to `landing.json`. A page with the same slug takes priority.
7. To list it in the site menu, also add `"menu": true`. `title` and `description` go through `__()`, so write them in English and add the translation to `lang/id.json`.

`landing/kontak/` is a ready example: a contact page that reads **Settings > Contact**.

Landing pages are code, not content: admin pages cannot be selected as a landing page, and a landing page has no record in **Pages**.

No build step is needed. Compile anything you need before copying it here.

## index.html

Plain HTML. Must contain `</head>`; the CMS adds SEO tags right before it, so do not write `<title>` or meta description yourself. Links beginning with `assets/` or `./assets/` work as written.

## index.blade.php

Can read CMS data. Always print `{!! $seo !!}` inside `<head>`, and do not write your own `<title>`.

For the same header as the rest of the site, copy what `landing/dyskflow/index.blade.php` does: load `css/ui.css` and `css/site.css`, add `@include('site.partials.theme-script')`, `@include('site.partials.header')` and optionally `@include('site.partials.footer')`, and load `js/site.js`. Use `.dark` on `<html>` for dark mode.

| Available | Purpose |
| --- | --- |
| `$seo` | SEO tags, from the General and SEO settings on the homepage and from `landing.json` elsewhere |
| `landing_asset('assets/style.css')` | URL of a file in this folder |
| `setting('site_title')` | Any setting, such as `tagline`, `contact_email`, `contact_phone`, `contact_whatsapp`, `contact_address` |
| `cms_posts(3)`, `cms_posts(3, 'category-slug')` | Latest published articles in the current language |
| `cms_privacy_page()` | The published privacy policy page in the current language, or null, for a footer link |
| `site_route('blog')`, `site_route('page', 'slug')` | A public URL in the current language |
| `app()->getLocale()` | `id` or `en`, set by the URL |

## Two languages

One folder serves both languages, at `/` and `/en`, or `/slug` and `/en/slug`, and `$seo` holds the `hreflang` tags. To show different text, branch on `app()->getLocale()` or use `__('English text')`. `index.html` has no language logic, so it shows the same text in both.

## Prompt for your AI tool

> Build a landing page in `landing/<slug>/` following `docs/AI-GUIDE.md`. Self-contained, responsive, no build step. Do not edit files outside that folder.
