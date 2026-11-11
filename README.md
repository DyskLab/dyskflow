<p align="center">
  <img src="public/img/logo-mark.svg" width="88" alt="DyskFlow">
</p>

<h1 align="center">DyskFlow</h1>

<p align="center">A Laravel CMS you install with no terminal and no build step: upload the files, open the site, and follow the wizard.</p>

<p align="center">
  <a href="https://github.com/DyskLab/dyskflow/releases/latest"><img src="https://img.shields.io/github/v/release/DyskLab/dyskflow" alt="Latest release"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/DyskLab/dyskflow" alt="MIT License"></a>
  <a href="https://dysklab.fanny.dev"><img src="https://img.shields.io/badge/part%20of-DyskLab-EE4433" alt="Part of DyskLab"></a>
  <a href="https://buymeacoffee.com/fannydevz"><img src="https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support-FFDD00?logo=buymeacoffee&logoColor=000" alt="Buy Me a Coffee"></a>
</p>

![DyskFlow dashboard](docs/screenshots/02-dasbor.png)

## Why DyskFlow

- **No build step:** no Node or Vite, and your hosting doesn't need Composer. `vendor` is installed from a web page.
- **One-click updates:** a backup is made first, then the code comes from the latest GitHub release.
- **Free-form landing pages:** written as separate Blade code, by hand or with AI.

## Install

**Requires:** PHP 8.3+ with the `pdo_mysql` or `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `dom`, and `zip` extensions. Optional: `gd` and `curl`.

1. [Download the source code](https://github.com/DyskLab/dyskflow/releases/latest) and upload it to your hosting without the `vendor` folder.
2. Open the site and press **Install now**.
3. Follow the wizard to set up the database and admin account.

The full steps, including cPanel and running on your own computer, are in [SETUP.md](SETUP.md) (in Indonesian).

## Screenshots

The admin is available in English and Indonesian. These screenshots show Indonesian.

| Landing page | Articles |
| --- | --- |
| ![DyskFlow landing page](docs/screenshots/01-landing.png) | ![Article list](docs/screenshots/03-artikel.png) |
| **Article editor** | **Settings** |
| ![Article editor](docs/screenshots/04-editor.png) | ![Settings](docs/screenshots/05-pengaturan.png) |

## Features

**Content**
- Articles and pages, categories, tags, media, and moderated comments
- Uploaded JPG and PNG photos can be converted to WebP automatically (Settings > Media)
- A trash for articles, pages, and comments, so deleted items can be restored
- View counts for articles and pages, with no cookies
- Two languages (Indonesian and English) with per-language URLs such as `/en/blog/slug`. A page with no translation has no URL in that language

**SEO**
- Meta tags, canonical URLs, Open Graph, JSON-LD, `hreflang`, a search result preview, and a checklist
- `sitemap.xml`, `robots.txt`, and per-language RSS (`/feed`, `/en/feed`)

**Admin**
- A dashboard with what needs attention, key numbers, recent comments, scheduled posts, and site status, based on each user's permissions
- Users and roles, an activity log, backups (with the tables plugins list), and import and export of articles, pages, Theme Builder templates and settings
- The www and bare forms of the domain move to the one in the site address, with a permanent redirect
- Global search (`⌘K` or `Ctrl+K`) and notifications for new comments, new versions, and errors
- Two-step verification (TOTP) with recovery codes
- A Privacy Guide and Site Health
- Dark mode

**Design**
- **Page builder**: design pages visually from sections, columns, and widgets, like Elementor, with a live preview, two languages, drafts, revisions, and Publish. The layout is JSON rendered on the server, so search engines get plain HTML, and AI tools read and write it with `builder:export` and `builder:import` ([docs/BUILDER.md](docs/BUILDER.md))
- **Widgets**: 65 widgets, written for DyskFlow: headings, text, images, buttons, icon boxes, tabs, accordions, counters, progress bars, countdowns, carousels, reviews, price lists, flip boxes, hotspots, video playlists, code blocks, share and social buttons, a table of contents, and more. A search box and folding groups keep the list tidy
- **Forms**: contact, sign-up, or order forms built in the page builder. Messages go to **Content > Forms** (with CSV export) and to your email. No cookies, a honeypot, and rate limits against spam
- **Starter templates**: about us, contact, cafe or restaurant, coming soon, event, freelancer portfolio, product, and service, each with a live preview
- **Theme Builder and Blocks**: design the article, list, header, and footer templates, and save parts of a page to reuse
- **Colors & fonts**: brand colors, Google Fonts (downloaded to the site, so visitors never connect to Google), text size, and corner radius for the whole site
- **Developer > Explorer**: browse, edit, create, rename, delete, and upload files in `theme/`, `landing/`, and `plugins/`, with a code editor (CodeMirror, no CDN), revisions, and conflict detection. Copy a file of the default theme into `theme/` to change it; the copy survives updates, and Reset brings the original back. Custom CSS and JavaScript for every page
- PHP, Blade, and SVG need the *Edit code* permission and a fresh password or TOTP check. See [docs/DESIGN-TOOLS.md](docs/DESIGN-TOOLS.md)

**Development**
- [Landing pages](landing/README.md) as Blade code in the `landing/` folder. The homepage is chosen in Settings, from a landing page, the article list, or a regular page
- [Plugins](plugins/README.md) in the `plugins/` folder, with hooks and automatic settings pages

## Updating a site

Open **Settings > Update > Check for updates > Update now**. A notification appears within 12 hours once an admin opens the admin area.

- A database backup is made first.
- The code comes from the latest release, and `vendor` is replaced only when `composer.lock` changed.
- Database migrations run.
- `.env`, `storage`, `landing`, `plugins`, `theme`, `public/uploads`, and `cms.json` are never overwritten.

## Sample data

After the wizard, this command fills the site with sample categories, tags, articles, comments, and media, plus a draft Privacy Policy. It's safe to run again.

```bash
php artisan db:seed
```

## Locked out of TOTP

Use a recovery code when you log in. Another admin can use **Users > Reset TOTP**. If nobody can log in:

```bash
php artisan cms:totp-reset email@example.com
```

## Development

```bash
composer install
```

```bash
php artisan serve
```

```bash
php artisan test
```

### Releasing a new version

1. Change `VERSION`, for example to `0.3.0`, and commit.
2. Create the matching tag and push it:

```bash
git tag v0.3.0
```

```bash
git push origin main --tags
```

GitHub Actions creates a release with `vendor.zip` ([release.yml](.github/workflows/release.yml)). A fork needs to change `"repo"` in `cms.json` to its own repository.

## Support

DyskFlow is free and open source. If it helps you, you can [buy me a coffee](https://buymeacoffee.com/fannydevz).

## Part of DyskLab

DyskFlow is part of [DyskLab](https://dysklab.fanny.dev), alongside [DyskFiles](https://github.com/DyskLab/dyskfiles), [DyskFocus](https://github.com/DyskLab/dyskfocus), and DyskLink for [Mac](https://github.com/DyskLab/dysklink-mac) and [Android](https://github.com/DyskLab/dysklink-android).

## License

[MIT](LICENSE)
