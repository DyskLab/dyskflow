# Landing pages

Landing pages are coded here, separately from the CMS, by hand or with AI.
Updates never overwrite this folder.

## Create one

1. Make a folder: `landing/<slug>/` (lowercase letters, numbers, `-`, `_`).
2. Add one entry file: `index.blade.php` or `index.html`.
3. Add `landing.json`: `{ "title": "...", "description": "..." }`.
4. Put CSS, JS, images and fonts in `assets/`.
5. In the admin, attach it to a page in **Pages**. To make it the homepage, go to **Settings > Pages**.

No build step is needed. Compile anything you need before copying it here.

## index.html

Plain HTML. Must contain `</head>`; the CMS adds SEO tags right before it, so do not write `<title>` or meta description yourself. Links beginning with `assets/` or `./assets/` work as written.

## index.blade.php

Can read CMS data. Always print `{!! $seo !!}` inside `<head>`, and do not write your own `<title>`.

| Available | Purpose |
| --- | --- |
| `$seo` | SEO tags from the attached page |
| `$page` | The attached page, or null in preview |
| `landing_asset('assets/style.css')` | URL of a file in this folder |
| `setting('site_title')` | Any setting, such as `tagline`, `contact_email`, `contact_phone`, `contact_whatsapp`, `contact_address` |
| `cms_posts(3)`, `cms_posts(3, 'category-slug')` | Latest published articles in the current language |
| `site_route('blog')`, `site_route('page', 'slug')` | A public URL in the current language |
| `app()->getLocale()` | `id` or `en`, set by the URL |

## Two languages

One folder serves both languages. A translated page at `/en/slug` renders the same landing page with `$page` holding the translated title, and `$seo` holding its `hreflang` tags. To show different text, branch on `app()->getLocale()` or use `__('English text')`. `index.html` has no language logic, so it shows the same text in both.

## Prompt for your AI tool

> Build a landing page in `landing/<slug>/` following `landing/README.md`. Self-contained, responsive, no build step. Do not edit files outside that folder.
