# Builder pages

A builder page is a page (**Pages**) designed visually, the way Elementor works: its layout is JSON in the `builder` column of `posts`, and the server renders it to HTML with the widget views. Title, address, SEO, publishing, the menu and the second-language title and slug are the same as for any page. Visitors and search engines get a finished HTML page; nothing is built in the browser.

Pages coded by hand or with AI in Blade or HTML are still landing pages in `landing/<slug>/` (see `landing/README.md`). The builder never writes Blade.

Read this before writing or changing a layout. When this guide and the code disagree, the code wins: `app/Support/Builder/` and the widget folders in `resources/views/builder/widgets/`.

## Where it lives

| Column | What |
| --- | --- |
| `posts.builder` | the published layout. A page with this column set is a builder page; without it, the page uses the classic editor. |
| `posts.builder_draft` | unpublished changes, or `null` |
| `posts.content` | the text of the published layout, written on publish, so search, feeds and excerpts keep working. Never edit it by hand on a builder page. |
| `post_translations.content` | the same, in the second language |

- **Pages > New builder page** starts from an empty page, a starter template or a copy of another builder page. On a classic page, **Edit with the builder** moves its content into a text widget; **Use the classic editor** goes back.
- The editor opens full screen. **Exit** goes to the page form (title, address, SEO, translation), which has **Edit with the builder** to come back.
- Images uploaded in the editor go to the media library (`uploads/…`), like images in articles.

## Draft and publish

- The editor saves every change to `builder_draft` within a couple of seconds. Visitors keep seeing `builder` until someone presses **Publish**.
- **Publish** checks the draft, keeps the layout it replaces as a revision, and publishes the page (a scheduled publish date stays). A draft with errors cannot be published.
- **Discard draft** removes the draft and keeps it as a revision. **Revisions** lists the last 20 layouts; restoring one puts it in the draft, to check before publishing.
- The editor notices within a few seconds when the page changed elsewhere (an AI tool, another tab). Without unsaved changes it loads the new version; with unsaved changes it asks which to keep.

## For AI tools and scripts

The layout is in the database, so read and write it with these commands instead of a file:

```bash
php artisan builder:list                                     # id, slug, title, status, draft
php artisan builder:export <id|slug> > page.json            # the draft if there is one, else the published layout
php artisan builder:export <id|slug> --published
php artisan builder:import <id|slug> page.json               # saves it as the draft
php artisan builder:import <id|slug> page.json --publish     # publishes it
php artisan builder:import promo page.json --create="Promo"  # a new draft page at /promo
php artisan builder:validate [<id|slug>] [--published]       # errors stop a publish; warnings do not
php artisan builder:schema --all                             # the schema with theme and plugin widgets
```

- **If a page has a draft, export, edit and import the draft.** Someone has unpublished changes, and importing with `--publish` makes your version live.
- `builder:import` checks the file first and saves nothing when there is an error. Missing ids are added.
- Every message names the node, for example `sections[1].columns[0].widgets[2] (wcta1): field "buttons.0.url" "javascript:x" is not allowed`. The editor shows the same messages, and its preview marks broken widgets in place.

## Shape

```json
{
  "version": 1,
  "layout": "site",
  "sections": [
    {
      "id": "shero",
      "type": "section",
      "style": { "padding": { "base": "xl", "mobile": "lg" }, "background": "muted" },
      "columns": [
        {
          "id": "cmain",
          "width": { "base": 7, "tablet": 12 },
          "widgets": [
            { "id": "wtitle", "type": "heading", "tag": "h1", "size": "2xl", "text": { "id": "Judul", "en": "Title" } },
            { "id": "wcta", "type": "button", "buttons": [{ "label": { "id": "Mulai", "en": "Start" }, "url": "#start" }] }
          ]
        }
      ]
    }
  ]
}
```

- **Page** → **containers** or **sections**, mixed freely in `sections`:
  - a **container** (`"type": "container"`, below) is a flex box like Elementor's Container: widgets and other containers in `children`, with free sizes and spacing;
  - a **section** is a 12-column grid: **columns** (out of 12) → **widgets**. A widget of type `inner-section` holds its own `columns`, one level deep. An inner section inside an inner section is an error.
- At most 300 sections, containers, columns and widgets in a page.
- `layout`: `site` adds the site header and footer, `blank` leaves them out.
- `$schema` is optional and ignored: `builder:export` adds it so editors find `docs/builder.schema.json`.

### Ids

Every section, column and widget has an `id`: a lowercase letter, then 2 to 15 lowercase letters or digits, unique in the page. Keep an id when you edit or move a node: the builder, revisions and diffs follow it. A new node may leave `id` out; the editor and `builder:import` add one. A copied node needs a new id.

### Text in two languages

A field marked *per language* takes a plain string (the same in every language) or `{"id": "…", "en": "…"}`. A missing language shows **nothing**, never the other language, the same rule as articles. The validator warns about it. A widget whose only text is empty disappears.

### Containers

```json
{
  "id": "xhero", "type": "container",
  "style": { "direction": { "base": "row", "mobile": "column" }, "align": "center", "gap": "48px", "min_height": "80vh", "background": "muted" },
  "children": [
    { "id": "xtext", "type": "container", "style": { "width": { "base": "55%", "mobile": "auto" } }, "children": [
      { "id": "wtitle", "type": "heading", "tag": "h1", "text": { "id": "Judul", "en": "Title" } }
    ] },
    { "id": "wphoto", "type": "image", "src": "uploads/hero.webp" }
  ]
}
```

- `children` holds widgets and containers, nested up to 6 levels. It is required, and may be empty. Inner sections do not go in containers: use a container with `direction: row`.
- A top-level container is **boxed**: its content sits in a centered box (1120px, or `boxed_width`) and it gets the same padding as a section. A nested container is full width with no padding. `content_width` changes either.
- `direction` is `column` by default. In a `row`, inner containers without a `width` share the space equally; give one a `width` such as `33%` to size it. Set `direction.mobile` to `column` to stack them on phones (the editor's presets do).
- Sizes take `px`, `%`, `rem`, `em`, `vh`, `vw`, `dvh` or `svh`, or `0`. `padding` and `margin` take one to four of them, like CSS (`"16px 32px"`); `margin` may be negative or `auto`.
- Colors are `#hex`, `rgb()`/`hsl()`, `transparent`, `currentColor` or a theme color such as `var(--primary)`, which follows dark mode.

| Container `style` key | Values | Per screen |
| --- | --- | --- |
| `direction` | `column` `row` | yes |
| `justify` | `start` `center` `end` `between` `around` `evenly` | yes |
| `align` (items) | `stretch` `start` `center` `end` | yes |
| `wrap` | `nowrap` `wrap` | yes |
| `gap`, `width`, `min_height`, `boxed_width` | a size (`width` may be `auto`) | yes |
| `padding`, `margin` | one to four sizes | yes |
| `content_width` | `boxed` `full` | no |
| `background` | `default` `muted` `primary` `dark` `image` (with `image`: `uploads/…` or `https://…`) | no |
| `overlay` | `none` `light` `dark` | no |
| `background_color`, `color`, `border_color` | a color | no |
| `radius`, `border_width` | a size | no |
| `shadow` | `none` `sm` `md` `lg` | no |
| `overflow` | `visible` `hidden` | no |
| `tag` | `div` `section` `header` `footer` `article` `aside` `nav` (top level is `section` by default) | no |

Any widget, in a container or a column, may have a `style` with `width`, `align_self`, `padding`, `margin`, `background_color`, `color` and `radius`, with the same rules.

Each value is checked against these patterns and written as CSS for that one node (`.b-n-<id>`) in the page's `<style data-b-layout-css>`, with media queries for tablet and mobile. A value that does not match is an error and is never printed.

### Grid section tokens

Sections, columns and widget `margin` use fixed tokens instead of sizes. Values outside these lists are errors, and the renderer drops them.

| Where | Key | Values | Per screen |
| --- | --- | --- | --- |
| section `style` | `padding` | `none` `xs` `sm` `md` `lg` `xl` `2xl` | yes |
| | `gap` (between columns) | `none` `sm` `md` `lg` `xl` | yes |
| | `background` | `default` `muted` `primary` `dark` `image` | no |
| | `image` | with `image` background: `uploads/…` or `https://…` | no |
| | `overlay` | `none` `light` `dark` | no |
| | `width` (of the content) | `narrow` `normal` `wide` `full` | no |
| | `valign` (columns) | `start` `center` `end` `stretch` | yes |
| | `text` | `start` `center` `end` | yes |
| column | `width` | `1` to `12` | yes |
| | `align` | `start` `center` `end` | yes |
| | `gap` (between widgets) | `none` `sm` `md` `lg` | no |
| widget | `margin` (above) | as `padding` | yes |
| any | `hide` | `{"mobile": true}` | |
| any | `anchor` | an HTML id such as `pricing`, for `#pricing` links | |
| any | `class` | class names from the theme CSS | |

*Per screen* means a value or `{"base": …, "tablet": …, "mobile": …}`. Tablet is 1023px and below, mobile 767px and below. On phones every column is full width unless `width.mobile` says otherwise.

Colors and fonts come from the site theme (`css/ui.css` tokens), so builder pages follow dark mode and the theme. For a look neither tokens nor container values can give, use the page's custom CSS (below), add classes to the theme (`theme/css/site.css`, see docs/DESIGN-TOOLS.md) and name them in `class`, or write a custom widget.

### Custom CSS and JavaScript

When the tokens are not enough, the page has its own code, set in the editor under **Page** (click an empty spot in the preview), or as top-level keys:

```json
{ "version": 1, "css": ".promo { border-radius: 24px; }", "js": "document.documentElement.classList.add('ready');", "sections": [] }
```

- `css` goes in a `<style>` in `<head>`, for this page only. Give an element a `class` or `anchor` (the Advanced tab), then target it: `.promo { … }`, `#pricing { … }`. A `<` is written as the CSS escape `\3C`, so the code cannot close the element. Anyone who may edit the page may change it, and the editor preview updates as you type.
- `js` goes in a `<script>` at the end of `<body>`. It runs on the site only, never in the editor preview (which shares the admin's origin): publish and press **View** to try it. Adding or changing it needs `code.manage`, like the `html` widget.
- Each is at most 64 KB. For code every page needs, use the theme instead (docs/DESIGN-TOOLS.md).

### Links and images

- Links: `https://…`, `http://…`, `mailto:`, `tel:`, `/path` on this site, or `#anchor`. Anything else, such as `javascript:`, is an error and is never printed. A link can be per language: `{"id": "/blog", "en": "/en/blog"}`.
- Images and files: `uploads/…` from the media library, or `https://…`. A missing `uploads/` file is a warning.
- Rich text (`text`, `html`) goes through the same cleaner as articles: no scripts, styles, forms or event handlers.

## Templates (Theme Builder)

**Design > Templates** designs parts of the theme with the same builder, like Elementor's Theme Builder:

| Template | Replaces | Dynamic widgets |
| --- | --- | --- |
| Single article | `/blog/<slug>` | `post-title`, `post-meta`, `post-image`, `post-content`, `post-tags`, `related-posts`, `post-comments` |
| Article list | `/blog`, the homepage list, search results | `archive-title`, `archive-posts`, `archive-pagination` |
| Category and tag | `/category/<slug>`, `/tag/<slug>` (falls back to Article list) | as Article list |
| Header | `site.partials.header` everywhere it is used | `site-logo`, `site-menu`, `site-tools`, `site-copyright` |
| Footer | `site.partials.footer` | as Header |

- A template is a `posts` row of type `template` whose slug is its kind (`single`, `archive`, `taxonomy`, `header`, `footer`), one per kind. It is active while published; **Turn off** or a draft-only template leaves the theme's own view. `App\Support\Builder\Templates` reads and renders them.
- A new template starts as a draft that already looks like the theme, built from containers and the dynamic widgets.
- Dynamic widgets read what the page shows from `App\Support\Builder\Context` (the article, its cleaned content and comments, or the paginated list and its heading). A widget.json with `"context": "single"` or `"archive"` is offered only in that kind of template; `site-*` widgets work anywhere.
- The editor previews a single template with the newest article and a list template with the newest articles; the comment form shows only on the site. SEO tags, canonical and hreflang are the same as the theme's views.
- Header and footer have no layout of their own. Their CSS (container values and custom CSS) is printed with them, and builder.css is linked, so landing pages that include the header get it too.
- Templates need `appearance.manage`; their builder lives at `admin/design/templates/<id>/builder`. Revisions work as for pages.

## Widgets

The editor lists widgets by group, with a search box; groups fold and the editor remembers which are closed. The dynamic widgets of templates are listed under [Templates](#templates-theme-builder).

**Layout**

| Type | What it is | Fields |
| --- | --- | --- |
| `divider` | A horizontal line. | `line` (one of `solid`, `dashed`; default `"solid"`) |
| `spacer` | Empty space. | `size` (one of `xs`, `sm`, `md`, `lg`, `xl`, `2xl`; per screen, default `"md"`) |

**Basic**

| Type | What it is | Fields |
| --- | --- | --- |
| `alert` | A colored note: information, success, warning or danger. Visitors can close it if you allow. | `kind` (one of `info`, `success`, `warning`, `danger`; default `"info"`)<br>`title` (text; per language)<br>`text` (textarea; per language)<br>`dismissible` (toggle; default `false`) |
| `animated-headline` | A title whose key word changes, types itself or gets underlined. | `before` (text; per language)<br>`words` (textarea; per language, required)<br>`after` (text; per language)<br>`effect` (one of `rotate`, `typing`, `highlight`; default `"rotate"`)<br>`tag` (one of `h1`, `h2`, `h3`; default `"h2"`)<br>`size` (one of `md`, `lg`, `xl`, `2xl`; per screen, default `"xl"`) |
| `blockquote` | A highlighted quote with who said it. | `quote` (textarea; per language, required)<br>`author` (text)<br>`role` (text; per language)<br>`image` (image)<br>`variant` (one of `line`, `mark`, `card`; default `"line"`)<br>`size` (one of `md`, `lg`, `xl`; default `"lg"`) |
| `button` | One or more links that look like buttons. | `buttons` (list of {`label`, `url`, `variant`, `new_tab`})<br>`size` (one of `md`, `lg`; default `"lg"`) |
| `card` | A grid of cards, each with an optional image, title, text and link. | `columns` (one of `1`, `2`, `3`, `4`; per screen, default `"3"`)<br>`cards` (list of {`image`, `kicker`, `title`, `text`, `url`}) |
| `heading` | A title. Use one h1 per page. | `text` (text; per language, required)<br>`tag` (one of `h1`, `h2`, `h3`, `h4`; default `"h2"`)<br>`size` (one of `sm`, `md`, `lg`, `xl`, `2xl`; per screen, default `"lg"`)<br>`kicker` (text; per language) |
| `icon-box` | Features or services, each with an icon, a title, a short text and an optional link. | `columns` (one of `1`, `2`, `3`, `4`; per screen, default `"3"`)<br>`position` (one of `top`, `left`; default `"top"`)<br>`variant` (one of `plain`, `filled`, `outline`; default `"filled"`)<br>`boxed` (toggle; default `false`)<br>`items` (list of {`icon`, `title`, `text`, `url`}) |
| `icon-list` | Short points, each with an optional title, number or check mark. | `marker` (one of `check`, `number`, `dot`, `none`; default `"check"`)<br>`items` (list of {`title`, `text`}) |
| `icon` | One icon, optionally a link. | `icon` (one of `check`, `star`, `heart`, `zap`, `rocket`, `shield`, `lock`, `users`, `user`, `phone`, `mail`, `chat`, `globe`, `map-pin`, `clock`, `calendar`, `truck`, `award`, `gift`, `credit-card`, `cart`, `home`, `briefcase`, `book`, `coffee`, `smile`, `target`, `trending-up`, `image`, `video`, `play`, `code`, `settings`, `search`, `bell`, `download`, `link`, `thumbs-up`, `leaf`, `sun`; default `"star"`)<br>`size` (one of `sm`, `md`, `lg`, `xl`; default `"md"`)<br>`variant` (one of `plain`, `filled`, `outline`; default `"filled"`)<br>`label` (text; per language)<br>`url` (url; per language) |
| `image-box` | An image with a title, text and a button, side by side or stacked. | `image` (image; required)<br>`alt` (text; per language)<br>`position` (one of `top`, `left`, `right`; default `"left"`)<br>`ratio` (one of `auto`, `1:1`, `4:3`, `16:9`, `3:4`; default `"4:3"`)<br>`kicker` (text; per language)<br>`title` (text; per language)<br>`text` (textarea; per language)<br>`button_label` (text; per language)<br>`url` (url; per language) |
| `image` | An image from the media library (uploads/) or from an https address. | `src` (image; required)<br>`alt` (text; per language)<br>`caption` (text; per language)<br>`url` (url; per language)<br>`ratio` (one of `auto`, `1:1`, `4:3`, `16:9`, `3:4`; default `"auto"`)<br>`rounded` (toggle; default `true`)<br>`width` (number; default `0`)<br>`height` (number; default `0`)<br>`lazy` (toggle; default `true`) |
| `social-icons` | Links to your social media, from Settings > Contact or your own list. | `source` (one of `settings`, `custom`; default `"settings"`)<br>`items` (list of {`network`, `url`})<br>`variant` (one of `plain`, `circle`, `square`; default `"circle"`)<br>`size` (one of `sm`, `md`, `lg`; default `"md"`)<br>`brand_colors` (toggle; default `false`) |
| `star-rating` | Stars out of five, such as 4.5 from 120 reviews. | `rating` (one of `0.5`, `1`, `1.5`, `2`, `2.5`, `3`, `3.5`, `4`, `4.5`, `5`; default `"5"`)<br>`label` (text; per language)<br>`show_number` (toggle; default `true`)<br>`size` (one of `sm`, `md`, `lg`; default `"md"`) |
| `text` | Paragraphs with bold, italic, links and lists. HTML is cleaned before it is shown. | `html` (richtext; per language, required)<br>`size` (one of `sm`, `md`, `lg`; default `"md"`) |

**Content**

| Type | What it is | Fields |
| --- | --- | --- |
| `accordion` | Sections that open and close, with formatted text inside. For questions and answers, the FAQ widget can also tell search engines. | `items` (list of {`title`, `body`})<br>`open_first` (toggle; default `true`)<br>`one_open` (toggle; default `true`)<br>`variant` (one of `lines`, `boxed`; default `"boxed"`) |
| `call-to-action` | An image with a title, text and buttons, to invite visitors to act. | `image` (image)<br>`layout` (one of `cover`, `left`, `right`, `top`; default `"cover"`)<br>`kicker` (text; per language)<br>`title` (text; per language, required)<br>`text` (textarea; per language)<br>`button_label` (text; per language)<br>`url` (url; per language)<br>`button2_label` (text; per language)<br>`url2` (url; per language)<br>`ribbon` (text; per language)<br>`align` (one of `start`, `center`; default `"center"`) |
| `contact` | WhatsApp, email, phone and address from Settings > Contact, so they stay the same everywhere. | `show_whatsapp` (toggle; default `true`)<br>`show_email` (toggle; default `true`)<br>`show_phone` (toggle; default `true`)<br>`show_address` (toggle; default `true`)<br>`layout` (one of `list`, `cards`; default `"list"`) |
| `countdown` | Days, hours, minutes and seconds until a date, such as an event or the end of a promotion. | `date` (text; required)<br>`labels` (toggle; default `true`)<br>`seconds` (toggle; default `true`)<br>`variant` (one of `boxes`, `plain`; default `"boxes"`)<br>`ended` (text; per language) |
| `counter` | Numbers that count up when they come into view, such as 1,200+ clients. | `columns` (one of `1`, `2`, `3`, `4`; per screen, default `"3"`)<br>`items` (list of {`number`, `prefix`, `suffix`, `label`})<br>`duration` (number; default `2`) |
| `faq` | Questions that open to show their answers. Can also tell search engines they are an FAQ. | `items` (list of {`question`, `answer`})<br>`open_first` (toggle; default `false`)<br>`schema` (toggle; default `false`) |
| `flip-box` | A card that turns over on hover or tap to show more, such as a service and its details. | `icon` (one of `check`, `star`, `heart`, `zap`, `rocket`, `shield`, `lock`, `users`, `user`, `phone`, `mail`, `chat`, `globe`, `map-pin`, `clock`, `calendar`, `truck`, `award`, `gift`, `credit-card`, `cart`, `home`, `briefcase`, `book`, `coffee`, `smile`, `target`, `trending-up`, `image`, `video`, `play`, `code`, `settings`, `search`, `bell`, `download`, `link`, `thumbs-up`, `leaf`, `sun`; default `"zap"`)<br>`front_title` (text; per language, required)<br>`front_text` (textarea; per language)<br>`front_image` (image)<br>`back_title` (text; per language)<br>`back_text` (textarea; per language)<br>`button_label` (text; per language)<br>`url` (url; per language)<br>`effect` (one of `flip`, `fade`, `slide`; default `"flip"`)<br>`height` (one of `sm`, `md`, `lg`; default `"md"`) |
| `form` | A contact, sign-up or order form. Messages are kept in Content > Forms and can be sent to your email. | `name` (text; default `"Contact form"`)<br>`fields` (list of {`label`, `kind`, `required`, `placeholder`, `options`, `width`})<br>`submit` (text; per language)<br>`button_width` (one of `auto`, `full`; default `"auto"`)<br>`success` (textarea; per language)<br>`email_to` (text)<br>`redirect` (url; per language)<br>`consent` (text; per language) |
| `portfolio` | Articles with their image in a grid, with buttons to filter by category. | `count` (number; default `9`)<br>`category` (category)<br>`filter` (toggle; default `true`)<br>`columns` (one of `2`, `3`, `4`; per screen, default `"3"`)<br>`ratio` (one of `1:1`, `4:3`, `16:9`, `3:4`; default `"4:3"`)<br>`show_title` (toggle; default `true`) |
| `posts` | The newest published articles in the language of the page. | `count` (number; default `3`)<br>`category` (category)<br>`columns` (one of `1`, `2`, `3`; per screen, default `"3"`)<br>`show_excerpt` (toggle; default `true`) |
| `price-list` | Items with their price, such as a menu or a list of services. | `items` (list of {`title`, `price`, `description`, `image`, `url`})<br>`leader` (toggle; default `true`)<br>`columns` (one of `1`, `2`; per screen, default `"1"`) |
| `pricing` | Plans side by side, each with a price, features and a button. | `columns` (one of `1`, `2`, `3`, `4`; per screen, default `"3"`)<br>`plans` (list of {`name`, `price`, `period`, `description`, `features`, `button_label`, `url`, `featured`, `badge`}) |
| `progress` | Bars that fill to a percentage, such as skills or goals. | `items` (list of {`label`, `percent`, `color`})<br>`show_percent` (toggle; default `true`)<br>`height` (one of `sm`, `md`, `lg`; default `"md"`) |
| `reviews` | Customer reviews with stars, sliding sideways. | `items` (list of {`text`, `name`, `role`, `image`, `rating`})<br>`per_view` (one of `1`, `2`, `3`, `4`, `5`; per screen, default `"3"`)<br>`autoplay` (number; default `0`)<br>`arrows` (toggle; default `true`)<br>`dots` (toggle; default `true`) |
| `share-buttons` | Buttons to share the page on WhatsApp, social media or by email. Plain links: no tracking scripts. | `networks` (one of `all`, `messaging`, `social`; default `"all"`)<br>`labels` (toggle; default `true`)<br>`variant` (one of `filled`, `outline`, `icon`; default `"filled"`)<br>`title` (text; per language) |
| `slides` | A full-width slider, each slide with a background image, a title, text and a button. | `items` (list of {`image`, `kicker`, `title`, `text`, `button_label`, `url`})<br>`height` (one of `sm`, `md`, `lg`, `screen`; default `"md"`)<br>`align` (one of `start`, `center`; default `"center"`)<br>`overlay` (one of `none`, `light`, `medium`, `strong`; default `"medium"`)<br>`first_heading` (toggle; default `false`)<br>`autoplay` (number; default `6`)<br>`arrows` (toggle; default `true`)<br>`dots` (toggle; default `true`) |
| `stats` | Big numbers with a short label, such as 10,000+ users. | `columns` (one of `2`, `3`, `4`; per screen, default `"4"`)<br>`items` (list of {`value`, `label`}) |
| `table-of-contents` | Links to the headings of the page, built when the page loads. | `title` (text; per language)<br>`levels` (one of `h2`, `h2-h3`, `h2-h4`; default `"h2-h3"`)<br>`numbered` (toggle; default `false`)<br>`collapsible` (toggle; default `true`)<br>`sticky` (toggle; default `false`) |
| `tabs` | Content split into tabs: only one shows at a time. | `items` (list of {`title`, `body`})<br>`layout` (one of `top`, `left`, `pills`; default `"top"`) |
| `testimonials` | Quotes from customers, with their name, role and photo. | `columns` (one of `1`, `2`, `3`; per screen, default `"3"`)<br>`items` (list of {`quote`, `name`, `role`, `image`}) |

**Media**

| Type | What it is | Fields |
| --- | --- | --- |
| `audio` | An audio file such as a podcast episode or a song, with the browser's own player. | `file` (file: mp3, m4a, ogg, wav; required)<br>`title` (text; per language)<br>`artist` (text)<br>`cover` (image)<br>`download` (toggle; default `false`) |
| `gallery` | Images in a grid, each with an optional caption. Pressing an image can open it full size. | `images` (list of {`src`, `alt`, `caption`})<br>`columns` (one of `2`, `3`, `4`; per screen, default `"3"`)<br>`ratio` (one of `auto`, `1:1`, `4:3`, `16:9`, `3:4`; default `"1:1"`)<br>`open` (toggle; default `false`) |
| `hotspot` | An image with numbered points that show a note when pressed, such as the parts of a product. | `image` (image; required)<br>`alt` (text; per language)<br>`points` (list of {`x`, `y`, `title`, `text`, `url`})<br>`marker` (one of `number`, `plus`, `dot`; default `"plus"`) |
| `image-carousel` | Images sliding sideways, such as client logos or a portfolio. | `images` (list of {`src`, `alt`, `caption`, `url`})<br>`ratio` (one of `auto`, `1:1`, `4:3`, `16:9`, `3:4`; default `"4:3"`)<br>`logos` (toggle; default `false`)<br>`per_view` (one of `1`, `2`, `3`, `4`, `5`; per screen, default `"3"`)<br>`autoplay` (number; default `0`)<br>`arrows` (toggle; default `true`)<br>`dots` (toggle; default `true`) |
| `map` | A Google map of an address. It loads only after the visitor presses the button, so the page sets no third-party cookies. | `address` (text)<br>`zoom` (number; default `15`)<br>`height` (one of `sm`, `md`, `lg`; default `"md"`)<br>`title` (text; per language) |
| `video-playlist` | Several YouTube or Vimeo videos with a list to pick from. Nothing loads from YouTube until the visitor presses play. | `items` (list of {`title`, `url`, `duration`, `thumb`})<br>`layout` (one of `side`, `below`; default `"side"`)<br>`title` (text; per language) |
| `video` | A YouTube or Vimeo video, or an mp4/webm file. YouTube and Vimeo load only after the visitor presses play, so the page sets no third-party cookies. | `url` (url)<br>`file` (file: mp4, webm)<br>`title` (text; per language)<br>`poster` (image)<br>`loop` (toggle; default `false`) |

**Site**

| Type | What it is | Fields |
| --- | --- | --- |
| `reading-progress` | A thin bar at the top of the screen that fills as the visitor scrolls down the page. | `position` (one of `top`, `bottom`; default `"top"`)<br>`height` (one of `sm`, `md`, `lg`; default `"sm"`)<br>`color` (one of `primary`, `brand-secondary`, `brand-accent`, `foreground`; default `"primary"`) |
| `search-form` | A search field for the articles on the site. | `placeholder` (text; per language)<br>`button` (one of `icon`, `text`, `none`; default `"icon"`)<br>`button_label` (text; per language)<br>`size` (one of `md`, `lg`; default `"md"`) |
| `site-title` | The site title and tagline from Settings > General. | `tag` (one of `p`, `h1`, `div`; default `"p"`)<br>`tagline` (toggle; default `false`)<br>`link` (toggle; default `true`)<br>`size` (one of `sm`, `md`, `lg`; default `"sm"`) |
| `sitemap` | A list of the site's pages, categories and newest articles, for visitors. | `pages` (toggle; default `true`)<br>`categories` (toggle; default `true`)<br>`articles` (number; default `10`)<br>`columns` (one of `1`, `2`, `3`; per screen, default `"3"`) |

**Template parts**

| Type | What it is | Fields |
| --- | --- | --- |
| `breadcrumbs` | Home > Category > Article, with structured data for search engines. | `home` (text; per language)<br>`current` (toggle; default `true`)<br>`schema` (toggle; default `true`) |

**Advanced**

| Type | What it is | Fields |
| --- | --- | --- |
| `code-highlight` | A block of code shown as text, with line numbers and a copy button. It is never run. | `code` (textarea; required)<br>`language` (text)<br>`line_numbers` (toggle; default `true`)<br>`copy` (toggle; default `true`)<br>`theme` (one of `dark`, `light`; default `"dark"`)<br>`wrap` (toggle; default `false`) |
| `html` | Your own HTML. It goes through the same cleaner as articles, so scripts, styles and forms are removed. For anything more, write a custom widget. | `html` (richtext; per language, required) |

Fields left out take their default. A widget type that does not exist is kept in the layout, shown as a warning, and left off the page.

## Forms

The `form` widget collects messages without a plugin. Its fields are a repeater (`label`, `kind`: text, email, tel, textarea, select, radio, checkbox, number or date; `required`, `placeholder`, `options` one per line, `width` full or half).

- The form posts to `/form` with the widget's settings sealed by `Crypt::encryptString`, so a visitor cannot change the fields, the recipient or the redirect. The route has no CSRF token, so the page sends no cookies and stays cacheable.
- Spam is stopped by a hidden honeypot field (`website_url`), a limit of 5 messages a minute per address, more than 3 links in a message, and the comment blocklist in Settings > Comments.
- Messages are kept in the `form_submissions` table and listed in **Content > Forms** (capability `forms.manage`), with read/new, delete and CSV export. Each one is an admin notification and, when `email_to` or the email in Settings > Contact is set, an email.
- With JavaScript the form sends with `fetch` and shows the success text or the errors in place; without it, the page reloads with the message. `redirect` sends the visitor to another page instead.

## Blocks

A block is a part of a page kept to reuse, such as a call to action or a pricing section: a `posts` row of type `block` with its own builder at `admin/design/blocks/<id>/builder`. **Design > Blocks > New block** starts one with an empty container; any container can also be saved as a block from the editor. In the editor's Add panel, **Saved blocks** inserts a copy, which is then independent of the block.

## Colors and fonts

**Design > Colors & fonts** (settings section `design`, `App\Support\Brand`) sets the primary, secondary and accent colors, the heading and body fonts, the body text size and the corner radius for the whole site. They are CSS variables printed in a `<style id="cms-brand">` on every public page, before the theme's custom CSS:

| Variable | From |
| --- | --- |
| `--primary`, `--primary-foreground`, `--ring` | Primary color. The text on it is black or white, whichever reads better |
| `--brand-secondary`, `--brand-accent` (and `-foreground`) | Secondary and accent colors, offered in the builder's color choices |
| `--font-heading`, `--font-body` | Fonts. Google Fonts are downloaded once to `public/uploads/fonts/<name>/` (Latin only) when the settings are saved, so visitors never connect to Google |
| `--radius` | Corner radius |

Widgets and containers use these variables, so a layout follows the brand without changes. Empty settings keep the theme's own values.

## The editor on a phone

Under 860px wide the editor shows the preview full screen with a bar of four tabs at the bottom: Add, Navigator, Preview and Edit. Add and Navigator open as sheets from below; adding a widget or choosing one in the preview opens Edit. The top bar keeps undo, redo and Publish; the rest is in the **⋯** menu.

## Custom widgets

When the core widgets are not enough, write a widget instead of switching the page to code.

```
theme/widgets/team/              every builder page
plugins/<plugin>/widgets/team/   from a plugin: add_filter('builder.widgets', fn ($folders) => [...$folders, __DIR__.'/widgets']);
```

`widget.json`:

```json
{
  "label": "Team",
  "description": "People with a photo, name and role.",
  "fields": [
    { "key": "title", "type": "text", "translatable": true, "required": true },
    { "key": "people", "type": "repeater", "max_items": 12, "fields": [
      { "key": "name", "type": "text" },
      { "key": "role", "type": "text", "translatable": true },
      { "key": "photo", "type": "image" },
      { "key": "url", "type": "url" },
      { "key": "lead", "type": "toggle", "default": false }
    ] }
  ]
}
```

Field types: `text`, `textarea`, `richtext`, `url`, `image`, `file` (with `accept`, a list of extensions such as `["mp4", "webm"]`), `select` (with `options`, optionally `"responsive": true`), `toggle`, `number` (`min`, `max`), `category` (a category slug), `repeater` (`fields`, `min_items`, `max_items`; not nested). Options: `label`, `help` (a hint under the field), `translatable`, `required`, `default`. The keys `id`, `type`, `class`, `hide`, `anchor`, `style` and `margin` are reserved.

`view.blade.php` gets `$w`: every field, already resolved. Text is in the language of the request, links and images are checked (a bad one is `''`), rich text is cleaned, and defaults are filled in. Print with `{{ }}`. Rich text is already safe, so `{{ $w['body'] }}` prints its HTML. Return nothing when there is nothing to show; the wrapper then disappears too.

```blade
@if ($w['title'] !== '')
<div class="team">
    <h2>{{ $w['title'] }}</h2>
    @foreach ($w['people'] as $person)
        <a class="person{{ $person['lead'] ? ' person-lead' : '' }}" href="{{ $person['url'] ?: '#' }}">
            @if ($person['photo'] !== '')<img src="{{ $person['photo'] }}" alt="">@endif
            {{ $person['name'] }} · {{ $person['role'] }}
        </a>
    @endforeach
</div>
@endif
```

A custom widget cannot take the name of a core widget. If its view throws, the error is logged, the widget is left off the public page, and the preview shows the message in its place.

## Images and HTML in the editor

- Move elements by dragging (from the palette, in the navigator, or in the preview), or select one and press **Alt + ↑ / ↓**: it trades places inside its column, and at the edge of a column moves on into the previous or next column.
- An image field has **Choose** and **Upload**, both for people with the media library (`media.manage`); uploads go to the media library as `uploads/…`. A video file field (`file`) has **Upload** only, since the picker lists images, and the file type must be allowed in Settings > Media.
- The `html` widget needs the `code.manage` capability to add or change. Without it, the editor, a copy, a restored revision and Import refuse a layout that adds or changes HTML; moving, copying or deleting an existing one is fine. Console commands have no user and are not limited.

## Schema

`docs/builder.schema.json` describes every core widget and its fields, for completion in editors and AI tools. `php artisan builder:schema --all` prints the schema with theme and plugin widgets too. After changing a core widget, run `php artisan builder:schema` to refresh the file in `docs/`; a test fails when it is out of date.

## Starter templates

**New builder page** can start from a ready-made layout in `resources/builder-templates/`: about us, contact, cafe or restaurant, coming soon (without the site header and footer), event or webinar, freelancer portfolio, product or app, and service business. The list shows a live thumbnail of each, drawn by `admin/pages/builder/starter?from=template:<name>` without saving anything; **Preview** opens it full size. Each is an ordinary layout in both languages, plus a `"template"` object with the `title` and `description` shown in the list; that object is dropped from the new page. To add one, put another file there and check it with the test `test_builder_pages_are_created_from_a_template_or_a_copy`. These files are part of core and are replaced on update.

## Moving from landing/<slug>/page.json

Before builder pages moved into Pages, they were `landing/<slug>/page.json` files. The migration `add_builder_to_posts` copied each one into a page (the published file, its draft, title, description, public and menu), copied its `assets/` images to `public/uploads/builder/<slug>/`, and moved the homepage setting along. The old folders are left as they were; delete them once the pages look right.

## Prompt for your AI tool

> Run `php artisan builder:export <slug> > page.json`, then edit `page.json` following `docs/BUILDER.md`. Keep every existing `id`; leave `id` out on new nodes. Prefer containers for new layout; use only the style keys, tokens and widgets listed there, and give per-language text as `{"id": "…", "en": "…"}`. Save it with `php artisan builder:import <slug> page.json` and fix every error it reports.
