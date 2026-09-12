# EvoDir

**EvoDir** is a themeable, module-driven dashboard shell that wraps Apache's built-in directory listing (`mod_autoindex`) and turns it into a full homelab/server front page — nav sidebar, live system stats, weather, KPI cards, an announcement banner, and a 15-theme picker — with **no framework, no build step, and no database**. Drop it in a folder, point Apache at it, and every directory under it becomes a styled, navigable page.

It's built for self-hosters who want their server's file index to look like an actual dashboard instead of a bare Apache listing, without giving up the thing that makes `mod_autoindex` great in the first place: it's still just files in folders.

---

## Table of contents

- [Why EvoDir exists](#why-evodir-exists)
- [Feature overview](#feature-overview)
- [How it works](#how-it-works)
  - [It's still Apache's directory listing](#its-still-apaches-directory-listing)
  - [The shell: header.html / footer.html](#the-shell-headerhtml--footerhtml)
  - [api.php — the one backend endpoint](#apiphp--the-one-backend-endpoint)
  - [evodir.conf — the whole config](#evodirconf--the-whole-config)
  - [The module system](#the-module-system)
  - [The theme system](#the-theme-system)
- [Built-in modules](#built-in-modules)
- [Folder descriptions & version tags](#folder-descriptions--version-tags)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration reference](#configuration-reference)
- [Extending EvoDir](#extending-evodir)
  - [Adding a module](#adding-a-module)
  - [Adding a KPI provider](#adding-a-kpi-provider)
  - [Adding a theme](#adding-a-theme)
- [Project structure](#project-structure)
- [Known gaps & things to fix before going public](#known-gaps--things-to-fix-before-going-public)
- [Tech stack](#tech-stack)

---

## Why EvoDir exists

Apache's `mod_autoindex` is genuinely useful — zero-config file browsing straight off the filesystem — but it looks like it's from 1996. EvoDir doesn't replace it or reimplement it; it **decorates** it. Apache still generates the actual listing. EvoDir injects a styled header and footer around that listing via `HeaderName` / `ReadmeName`, then a small script in the footer rewrites the raw `<pre>` output into a proper table with icons, relative timestamps, and per-folder descriptions.

Everything else — the sidebar, the live stats, the weather widget, the theme picker — is layered on top of that same trick, config-driven from a single flat text file.

## Feature overview

- **Modernized directory listings** — Apache's raw autoindex `<pre>` block is rewritten client-side into a proper table: file-type icons (60+ extensions mapped), a clickable breadcrumb trail, relative ("2h ago") or absolute timestamps, and human file sizes.
- **App shell chrome** — collapsible desktop sidebar, mobile off-canvas nav with backdrop, sticky header, live clock, and a footer that reports the site name, Apache version, and PHP version.
- **Zero-database configuration** — the entire site (nav links, theme, modules, location, banner copy) lives in one `evodir.conf` file using a simple `section` + `key|value` format.
- **Pluggable module system** — features are self-contained folders under `modules/`, toggled on/off from `evodir.conf`, each with its own backend (`module.php`) and optional frontend (`module.js`) that only loads when the module is enabled.
- **Auto-discovering KPI bar** — small stat cards (folder/file counts, and easily extended to your own metrics) that are picked up automatically from a `kpis/` folder — no wiring required to add a new one.
- **15 built-in themes across 7 families** (plus a bespoke "Vintage Gamers" brand theme), each self-describing its own name/family/light-or-dark via CSS custom properties, so the picker UI is generated from the theme files themselves rather than a hardcoded list.
- **Live homelab stats** — RAM, swap, CPU load average, process count, per-mount disk usage, and Docker/Plex container status, read straight from `/proc` and `docker ps`.
- **"Live stats" mode** — a second, sandboxed stats module with no shell access and no `/proc` reads, meant for shared hosting: recursive file/folder counts and a storage budget bar instead.
- **Weather widget** — current conditions + today's high/low via [wttr.in](https://wttr.in), cached to disk for 30 minutes with graceful stale-cache fallback if the upstream call fails.
- **Dismissible announcement banner** — HTML-sanitized (`strip_tags` allowlist + event-handler stripping) so it can carry basic markup without becoming an XSS vector.
- **Themed error pages** for 400/403/404/500/502/503, all rendered through one shared template so they get full nav, theme, and chrome instead of Apache's bare defaults.
- **Legal pages** (`/legal/terms`, `/legal/privacy`) with clean URL rewriting, hidden from directory listings via their own `.htaccess`.
- **Per-folder descriptions with version tags** — drop a `description.txt` in any folder and its contents show up as that row's description in the listing; embedding `Ver1.4` anywhere in the text renders it as a separate version badge.
- **A rebuilt `phpinfo()` dashboard** (`phpinfo.php`) — parses `phpinfo()`'s raw HTML into searchable, collapsible sections with gauge widgets for memory/swap/CPU/disk, and masks any config key containing `key`, `secret`, `password`, `token`, etc.

## How it works

### It's still Apache's directory listing

The root `.htaccess` does the real work:

```apache
Options +Indexes
HeaderName /.includes/header.html
ReadmeName /.includes/footer.html
IndexOptions FancyIndexing SuppressRules SuppressHTMLPreamble
IndexIgnore .htaccess .includes .legal
```

`HeaderName`/`ReadmeName` are standard `mod_autoindex` directives — they let you inject arbitrary HTML above and below the generated listing on **every** directory, automatically, with no per-folder setup. `SuppressHTMLPreamble` hands EvoDir full control of the `<head>`/`<body>` wrapper instead of fighting Apache's own. `IndexIgnore` keeps EvoDir's own internals out of the listing. The same file also wires up `AddDescription` file-type labels, `ErrorDocument` mappings to EvoDir's themed error pages, and a `RewriteRule` for clean `/legal/*` URLs.

### The shell: header.html / footer.html

`header.html` opens the page shell (header bar, sidebar skeleton, theme `<link>`) and immediately fetches `/.includes/api.php?action=config`. That one response drives:

- the site name and label in the header/sidebar,
- which theme stylesheet to load,
- the nav sections and links,
- and which modules are enabled — for each enabled module, it injects `<script src="/.includes/modules/{name}/module.js?v={mtime}">` dynamically. The `?v=` is the module's own `module.js` file's modification time, so a redeployed module is always fetched fresh instead of silently running a stale cached copy.

`footer.html` closes the shell and does two more things: it waits for the page to settle, then finds Apache's raw autoindex `<pre>` block and rewrites it into a `<table>` (icons, breadcrumb, relative time, descriptions fetched from `api.php?action=descriptions`); and it fetches `api.php?action=banner` to render the dismissible announcement bar, if one is configured.

### api.php — the one backend endpoint

There's no router, no framework — `api.php` is a single `switch` on `?action=`:

- **`config`** — parses `evodir.conf` and returns the whole site config as JSON (nav, theme, modules, footer links, per-module cache-busting versions).
- **`descriptions`** — given a `?path=`, scans that directory for immediate subfolders containing a `description.txt`, extracts an optional `VerX.X` tag from the text, and returns `{ "FolderName/": { description, version } }`.
- **default (module router)** — anything else is treated as a module name. It's sanitized with `basename()` and checked against `modules/{action}/module.php` before being `require`d, so `?action=../../etc/passwd`-style traversal can't escape the modules folder. A module that isn't a real, existing folder gets a `400` back with the list of valid actions instead of a silent failure.

### evodir.conf — the whole config

A small custom format, not INI or YAML — `[SECTION]` headers, then `key|value` lines (or `icon|label|url` for nav entries):

```ini
[CONFIGURATION]
site_label|Homelab
theme|exploration-light
relative_time|true

[NAVIGATION]
bi-server|CasaOS|http://192.168.1.10:8080/
bi-film|Plex|http://192.168.1.10:32400/web/

[MODULES]
stats|true
weather|true
kpi|true
theme-selector|true

[LOCATION]
lat|38.9717
lon|-95.2353
units|imperial
```

Anything under `[NAVIGATION]` becomes its own heading in the sidebar — the section name is whatever you name it (`bi-*` prefixes there are just [Bootstrap Icons](https://icons.getbootstrap.com/) class names, not a fixed enum). `parse_conf()` in `api.php` is the only place that understands this format.

### The module system

A module is a folder under `modules/` with:

- **`module.php`** (required) — runs inside `api.php`'s scope when its action is requested, so `$conf`, `$webroot`, etc. are already available to it. It must **return** an array shaped `['modulename' => [...data...]]` — `api.php` handles echoing it as JSON.
- **`module.js`** (optional) — only loaded in the browser if the module is `true` in `evodir.conf`'s `[MODULES]` section. Fetches its own `action` from `api.php` and injects into one of the shell's zone `<div>`s.

The shell exposes four injection zones for modules to target:

| Zone ID | Location | Used by |
|---|---|---|
| `evodir-zone-sidebar-top` | Top of sidebar, replaces the clock | `weather` |
| `evodir-zone-sidebar-bottom` | Bottom of sidebar | `stats`, `livestats` |
| `evodir-zone-content-top` | Above the directory listing | `banner`, `kpi` |
| `evodir-zone-content-bottom` | Below the directory listing (created on demand) | *(none yet — reserved)* |

A module that fails doesn't take the page down with it — `kpi`'s provider loop, for instance, wraps each provider in `try/catch` and just skips ones that throw.

### The theme system

Themes are built on **Chameleon CSS**, EvoDir's token-based framework: every color, radius, and spacing value the framework uses traces back to a CSS custom property defined once in `style.css`, and nothing else ever hardcodes a value directly. A theme is just a `:root` block in `themes/*.css` that overrides those tokens — swapping a theme is nothing more than swapping which stylesheet is linked.

The clever part is that themes describe themselves. Each theme file declares its own identity as custom properties:

```css
:root {
  --theme-id:           "retro-dark";
  --theme-family:       "Retro";
  --theme-display-name: "Dummy Terminal";
  /* ...token overrides... */
}
```

`theme-selector/module.php` scans every file in `themes/`, reads those three properties back out with a regex (no naming convention required beyond the properties existing), and infers light/dark mode from an `-id` suffix of `-light`/`-dark`. Two themes sharing a `--theme-family` get paired into a single sun/moon toggle row in the picker automatically; anything without a clean pair is listed individually. A theme file with no metadata block at all still works — it falls back to a title-cased version of its filename, standalone, no toggle. **Adding a theme is: add a CSS file. Nothing else.**

The picker itself is rebuilt from that theme list in `theme-selector/module.js`, which also layers a `localStorage`-persisted user override on top of whatever `evodir.conf` set as the server-side default theme.

## Built-in modules

| Module | What it does |
|---|---|
| **`stats`** | Live homelab metrics: RAM/swap usage and percentages (from `/proc/meminfo`), 1/5/15-minute load average, process count, per-`/mnt/*` disk usage, and Docker container count + Plex container running-state and memory usage (via `docker ps` / `docker inspect` / `docker stats`). Renders into the sidebar. |
| **`livestats`** | A shared-hosting-safe alternative to `stats` — no shell calls, no `/proc` reads. Recursively counts files/folders under the webroot and computes disk usage against a configurable storage budget (`10MB`, `2GB`, etc.). |
| **`weather`** | Current conditions and today's high/low from wttr.in for a configured lat/lon, with imperial or metric units. Cached to `cache.json` for 30 minutes; if the upstream fetch fails, serves the stale cache and flags it as such rather than showing nothing. |
| **`kpi`** | Renders a row of stat cards above the directory listing. See [Adding a KPI provider](#adding-a-kpi-provider) — this module doesn't have hardcoded metrics itself, it's purely the auto-discovery + rendering harness. Ships with `folders` and `files` counters as examples. |
| **`theme-selector`** | Scans `themes/*.css`, builds the theme picker modal, and handles applying/persisting the chosen theme. See [The theme system](#the-theme-system). |
| **`banner`** | A configurable, dismissible announcement bar (title + one or more text lines) driven by `evodir.conf`'s `[BANNER]` section. HTML in the text is passed through an allowlist sanitizer (`strong`, `em`, `a`, `br`, `p`, etc.) with `on*` event-handler attributes stripped. Note: this one isn't toggled through `[MODULES]` — `footer.html` fetches and renders it directly, since a banner needs to appear regardless of which optional modules are active. |

## Folder descriptions & version tags

Any folder in the site can contain a `description.txt`. If it does, that text becomes the description shown next to that folder in its parent's listing:

```
Projects/Active/description.txt:
  Projects currently under development.
```

If the text contains something matching `Ver1.4` (or `Ver03.34`, etc.) anywhere in it, that's pulled out as a separate version badge next to the folder name, and stripped from the visible description text. This is entirely convention over configuration — there's no manifest file, `api.php`'s `descriptions` action just reads whatever's there.

## Requirements

- **Apache** with `mod_rewrite`, `mod_headers`, and `mod_autoindex` enabled. EvoDir leans on Apache-specific directives (`HeaderName`, `ReadmeName`, `IndexOptions`, `IndexIgnore`) — this will not work as-is on nginx or another server without reimplementing the listing-decoration layer some other way.
- **PHP 8.0+** (the codebase uses `match()` expressions).
- `curl` extension (weather module) and `shell_exec`/`/proc` access (stats module) — both degrade gracefully to "N/A" / "unavailable" if unavailable, rather than erroring.
- Optional: Docker CLI reachable from PHP's user, if you want the Docker/Plex status rows in `stats`.

## Installation

1. Copy the whole project (including the `.htaccess` and the hidden `.includes`/`.legal` folders) to your Apache webroot.
2. Confirm `AllowOverride All` (or at least `Indexes`, `FileInfo`, `Rewrite`) is set for that directory in your Apache vhost config, or the root `.htaccess` won't take effect.
3. Set `date.timezone` for your server — it's set at the top of `.htaccess` (`America/Chicago` by default); change it to your own.
4. Edit `.includes/evodir.conf` — at minimum, set `site_label`, a `theme`, your `[NAVIGATION]` links, and your `[LOCATION]` lat/lon if you're using the weather module.
5. Toggle the modules you want in `[MODULES]`.
6. Load the site. `header.html`'s Config Metrics panel in the sidebar will show which modules loaded — if none did, check that `/.includes/api.php?action=config` is reachable directly and returning JSON.

There's no build step, no `composer install`, no dependencies to install — the one external asset pulled at runtime is Bootstrap Icons from a CDN in `header.html`.

## Configuration reference

All of this lives in `.includes/evodir.conf`:

| Section | Keys | Notes |
|---|---|---|
| `[CONFIGURATION]` | `site_name`, `site_label`, `theme`, `relative_time`, `footer_links` | `site_name` falls back to the server hostname if left blank. `theme` is a theme file's slug (filename minus `.css`). |
| `[NAVIGATION]` | *(free-form section names)* `icon\|label\|url` | Each section name becomes its own `<h2>` in the sidebar. `icon` is a Bootstrap Icons class. External URLs (starting with `http`) open in a new tab automatically. |
| `[FOOTER]` | `label\|url` | Only shown if `footer_links` is `true`. |
| `[MODULES]` | `modulename\|true\|false` | Any folder in `modules/` not listed here is treated as disabled by default — see `parse_conf()`. |
| `[LOCATION]` | `lat`, `lon`, `units` | Used by `weather`. `units` is `imperial` or `metric`. |
| `[BANNER]` | `enabled`, `dismissible`, `title`, `text` (repeatable) | Multiple `text|...` lines each become their own paragraph. |
| `[LIVESTATS]` | `storage_budget` | Accepts `GB`/`MB`/`KB` suffixes, e.g. `500MB`. |
| `[KPI]` | `providerid\|true\|false` | Only needed to *disable* a provider — a new file dropped into `kpis/` with no matching key here defaults to **enabled**. |

## Extending EvoDir

### Adding a module

1. Create `modules/yourmodule/module.php`. It runs inside `api.php`'s scope and must `return ['yourmodule' => [ ...your data... ]];`.
2. (Optional) Create `modules/yourmodule/module.js` — fetch `/.includes/api.php?action=yourmodule` and render into whichever zone `<div>` fits (see the [zones table](#the-module-system) above).
3. Add `yourmodule|true` under `[MODULES]` in `evodir.conf`.

That's the entire contract — no registration step, no manifest, no build.

### Adding a KPI provider

1. Drop a file at `modules/kpi/kpis/yourmetric.php`.
2. Return an array with at least `value` (and ideally `label`, `icon`, `order`):

   ```php
   <?php
   // Runs inside kpi/module.php's scope — $absPath, $reqPath, $webroot,
   // and $kpi_conf are already defined for you.
   return [
       'id'    => 'yourmetric',
       'label' => 'Your Metric',
       'icon'  => 'bi-graph-up',
       'order' => 30,
       'value' => 42,
   ];
   ```

3. Done — `kpi/module.php` `glob()`s the `kpis/` folder on every request, so it's picked up immediately. Set `yourmetric|false` under `[KPI]` in `evodir.conf` only if you want to turn it off.

A provider that throws is caught and simply skipped ("fail-closed: one broken provider shouldn't break the bar") — it won't blank out or crash the whole KPI row.

### Adding a theme

1. Copy an existing file in `themes/` as a starting point.
2. Set your own `--theme-id`, `--theme-family` (leave blank for a standalone theme with no light/dark pairing), and `--theme-display-name`, then override whichever Chameleon CSS tokens you want to change.
3. Save it in `themes/` — the theme-selector module will pick it up on the next page load, no other step required. If you used the same `--theme-family` as an existing `-light`/`-dark` counterpart, it'll pair with it automatically in the picker.

## Project structure

```
/                       ← Apache webroot; every folder here is a live, styled listing
├── .htaccess           ← Apache directives that make the whole thing work
├── Experiments/        ← Example content folder (each has its own description.txt)
├── Projects/
│   ├── Active/
│   ├── Archived/
│   ├── Completed/
│   └── Paused/
├── Share/
│   ├── Downloads/
│   ├── Friends/
│   └── Public/
├── Snippets/
├── .includes/           ← Hidden from listings via IndexIgnore; the actual app
│   ├── evodir.conf       ← The one config file
│   ├── api.php            ← The one backend endpoint
│   ├── header.html / footer.html   ← The shell, injected by HeaderName/ReadmeName
│   ├── login.php          ← See "Known gaps" below — not currently wired in
│   ├── phpinfo.php        ← Rebuilt phpinfo() admin dashboard
│   ├── 400/403/404/500/502/503.php + error-page.php   ← Shared themed error template
│   ├── css/               ← Chameleon CSS framework (tokens in style.css)
│   ├── js/                ← responsive.js (nav/sidebar toggling)
│   ├── themes/            ← One CSS file per theme, self-describing
│   └── modules/           ← One folder per module (see above)
└── .legal/
    ├── terms.php
    └── privacy.php
```

`Experiments/`, `Projects/`, `Share/`, and `Snippets/` are demo content — a working example of the `description.txt` convention and a sensible starting folder layout for a homelab; replace or remove them freely.

## Known gaps & things to fix before going public

Being direct about the current state of this build, since a couple of these matter if this ever sits somewhere reachable from outside your LAN:

- **`login.php` has a hardcoded, plaintext credential check (`admin` / `password123`) and isn't currently wired into anything** — no `.htaccess` rule or nav link points at it, and its relative `include` paths (`'includes/header.html'` instead of `'/.includes/header.html'`) suggest it wouldn't even render correctly if it were hit directly. Treat it as an unfinished scaffold, not a working feature. If you want real access control, this needs a proper rewrite (hashed credentials at minimum, ideally sessions backed by something other than a hardcoded string) before it's wired into any route.
- **`phpinfo.php` isn't gated by any authentication** — it masks values whose key contains `key`/`secret`/`password`/`token`/etc., but it's still a detailed server/config dashboard reachable by anyone who requests that exact URL, since `IndexIgnore` only hides it from directory *listings*, not from direct access. If you deploy this outside a trusted LAN, put it behind real auth (HTTP Basic auth via `.htaccess`, or wire it up to `login.php` once that's fixed) or remove it.
- **`style.css` `@import`s two files that aren't present in this copy** — `file-table.css` and `login.css`. Nothing currently breaks visibly (the browser just no-ops on a 404'd `@import`), but the file-table and login-page styling those were presumably meant to hold isn't there. Worth reconstructing or removing the imports.
- **`css/layout - Copy.css` is a stale, unimported duplicate** of an earlier `layout.css` — nothing references it (`style.css` only imports `layout.css`). Safe to delete.
- The three Plex-based KPI providers referenced in `evodir.conf`'s `[KPI]` section (`plex_movies`, `plex_shows`, `plex_songs`) aren't included in `modules/kpi/kpis/` in this copy — only the `folders`/`files` examples are present. That's expected if they were left out deliberately (they'd need a path to your Plex SQLite database), but worth noting so the config doesn't imply providers that aren't there.

## Tech stack

- **PHP 8+** — no framework, no Composer dependencies.
- **Vanilla JavaScript** — no build step, no bundler; every module ships its own small, dependency-free `module.js`.
- **Chameleon CSS** — a custom, token-driven CSS framework built alongside this project; every visual value in the framework traces back to a CSS custom property, which is what makes the theme system possible.
- **Bootstrap Icons** (via CDN) for all iconography.
- **[wttr.in](https://wttr.in)** as the weather data source.
- **Apache** (`mod_rewrite`, `mod_headers`, `mod_autoindex`) as the actual page-serving engine underneath it all.
