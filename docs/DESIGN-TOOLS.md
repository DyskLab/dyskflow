# Design tools

The code tools live in one screen, **Developer > Explorer** (`admin/design/files`). The Developer group is hidden until **Settings > General > Show the developer menu** is on (setting `developer_menu`); Explorer still opens at its address. **Design** keeps the visual tools: Templates, Blocks and Colors & fonts (see [BUILDER.md](BUILDER.md)). All need the `appearance.manage` permission ("Edit design and files"). Administrators have it.

Explorer's top level shows the three folders and the site's custom CSS and JavaScript:

| Item | What it does |
| --- | --- |
| `landing/` | Code pages, one folder each ([landing/README.md](../landing/README.md)). Pages designed visually are in **Pages** (see [BUILDER.md](BUILDER.md)) |
| `theme/` | Overrides of the default theme. **Theme files** (`admin/design/theme`) lists every file of the default theme: **Copy to theme** puts a copy in `theme/` and opens it in the editor, **Reset to original** removes the copy |
| `plugins/` | Plugins ([plugins/README.md](../plugins/README.md)) |
| Custom CSS, Custom JavaScript | `theme/assets/css/custom.css` and `theme/assets/js/custom.js`, loaded on every public page after the theme (the script at the end). Opening one creates the file; an empty file adds nothing to the pages |

Inside a folder: edit, create, rename, delete and upload files, and restore earlier versions. The old address `admin/design/landing` redirects to Explorer.

## Where edits live

Updates overwrite `app/`, `resources/`, `public/` and the other core folders, so the design tools never write there. They write only to:

| Folder | Holds |
| --- | --- |
| `theme/views/site/...` | Copies of `resources/views/site/...`. A copy is used instead of the original |
| `theme/assets/css/site.css`, `css/ui.css`, `js/site.js` | Copies of the files in `public/`, served at `/_theme/...` |
| `theme/assets/css/custom.css`, `js/custom.js` | The site's custom CSS and JavaScript |
| `landing/<slug>/` | Landing pages ([landing/README.md](../landing/README.md)) |
| `plugins/<slug>/` | Plugins ([plugins/README.md](../plugins/README.md)) |

Only `site.*` views can be overridden. A file such as `theme/views/admin/layout.blade.php` is ignored, so a broken override can break the public site but never the admin you need to fix it.

Full backups (**Settings > Backup > Full backup**) include `theme/` and `landing/`. Files over 20 MB are left out.

## Code and permissions

PHP and Blade run on the server, and SVG can run script in a visitor's browser. Editing, creating, renaming, deleting, uploading or restoring these files needs:

1. the `code.manage` permission ("Edit code (PHP and Blade)"),
2. `CMS_CODE_EDITOR` not set to `false`, and
3. a password check, or a TOTP code when two-step verification is on, in the last 15 minutes.

CSS, JavaScript, JSON, HTML, Markdown and plain text need only `appearance.manage`. In the page builder, adding or changing an `html` widget needs `code.manage` too ([BUILDER.md](BUILDER.md#images-and-html-in-the-editor)).

Before saving, JSON must parse, and PHP and Blade must pass `php -l` (Blade is compiled first). When the server does not allow `exec`, PHP and Blade are saved unchecked and the editor says so.

## Revisions and conflicts

- Each save keeps the previous content as a revision. The last 20 per file are kept, in the `file_revisions` table, which database backups include.
- Deleting a file and resetting a theme file also keep a revision, so they can be undone from **Revisions**.
- The editor sends the hash of the file it loaded. If the file changed on disk since then, for example because an AI tool or another admin edited it, the save is refused and the editor offers **Load the version on disk** or **Overwrite with mine**.

Every change is written to the activity log: `file.saved`, `file.created`, `folder.created`, `file.renamed`, `file.deleted`, `file.uploaded`, `file.restored`, `theme.overridden`, `theme.reset`, and `code.confirmed`. The page builder logs `page.published`, `page.discarded`, `page.restored`, `page.builder` and `page.classic`.

## When something breaks

| Problem | Fix |
| --- | --- |
| A theme override breaks the public site | **Explorer > theme/ > Theme files > Reset to original**, or set `CMS_THEME_OVERRIDES=false` in `.env` to ignore all of `theme/` |
| Someone should not be able to edit code at all right now | Set `CMS_CODE_EDITOR=false` in `.env` |
| A saved file is wrong | **Explorer**, open the file, **Revisions > Restore** |
| A folder cannot be written | Explorer names it. Fix the folder permissions on the server |
