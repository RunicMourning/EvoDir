# Evolution Directory (EvoDir)

A modern, themeable Apache directory listing interface. EvoDir replaces Apache's default `<pre>` output with a clean, semantic table — complete with file type icons, directory descriptions, system stats, and a fully themed sidebar — while staying entirely within Apache's native `HeaderName` and `ReadmeName` include system.

No framework. No build step. Drop it in and it works.

![Blueprint Theme](/.includes/themes/blueprint.css)

---

## Features

- **Modern directory table** — replaces Apache's raw `<pre>` output with a clean semantic table via JavaScript rewrite
- **File type icons** — Bootstrap Icons mapped to 40+ file extensions
- **Directory descriptions** — add a `description.txt` inside any folder and it appears in the listing automatically
- **Apache AddDescription support** — file type descriptions from `.htaccess` populate the description column
- **System stats footer** — live memory, swap, CPU load, processes, uptime, and disk usage
- **Four built-in themes** — Blueprint (default), Blueprint Light, Terminal (green phosphor), Corporate
- **Live clock** — ticks in the sidebar every second
- **Graceful degradation** — every stat fails independently; unavailable data shows "unavailable" without breaking the page
- **Works on shared hosting** — no SSH required, no hardcoded paths, uses `$_SERVER['DOCUMENT_ROOT']` throughout

---

## Themes

| Theme | Description |
|---|---|
| `blueprint` | Deep navy, blueprint grid, cyan-blue glow (default) |
| `blueprint-light` | Inverted blueprint, pale blue background, daytime feel |
| `terminal` | Pure black, P1 green phosphor, scanline overlay, VT323 font |
| `corporate` | Charcoal/slate, Inter font, clean professional aesthetic |

To switch themes, change the `<link>` tag in `header.html` to point to the desired CSS file.

---

## Requirements

- Apache 2.4+
- PHP 7.4+ (for `stats.php` and `descriptions.php`)
- `mod_autoindex` enabled
- `AllowOverride All` or equivalent for `.htaccess` support

---

## Installation

1. Download or clone the repository
2. Copy the contents into your Apache webroot
3. Ensure `.htaccess` is enabled for the directory
4. Visit your server in a browser

```bash
git clone https://github.com/RunicMourning/evodir.git
cp -r evodir/. /var/www/html/
```

That's it. EvoDir uses `$_SERVER['DOCUMENT_ROOT']` so no path configuration is needed.

---

## Directory Structure

```
/
├── .htaccess                  # Apache config — IndexOptions, AddDescription rules
├── .includes/
│   ├── header.html            # Injected before directory listing
│   ├── footer.html            # Injected after directory listing (stats + JS rewrite)
│   ├── stats.php              # System stats JSON endpoint
│   ├── descriptions.php       # Directory description JSON endpoint
│   └── themes/
│       ├── blueprint.css
│       ├── blueprint-light.css
│       ├── terminal.css
│       └── corporate.css
└── your-project/
    ├── description.txt        # Optional — shows in parent directory listing
    └── index.php
```

---

## Directory Descriptions

To add a description to any directory, create a `description.txt` file inside it:

```
your-project/
├── description.txt   ← "My project description here"
├── index.php
└── ...
```

The description appears automatically in the parent directory listing. No configuration required.

---

## System Stats

Stats are collected by `stats.php` and displayed in the footer. Each stat is collected independently — if one fails, the rest still render.

| Stat | Source |
|---|---|
| Memory / Swap | `shell_exec('cat /proc/meminfo')` |
| CPU Load | `sys_getloadavg()` |
| Uptime | `shell_exec('cat /proc/uptime')` |
| Processes | `shell_exec('ps aux \| wc -l')` |
| Disk Usage | `disk_free_space()` / `disk_total_space()` |
| Docker/Plex | `docker ps` / `docker stats` |

> **Note:** On systems with systemd `ProtectProc=invisible` (common on Ubuntu with hardened Apache), `/proc` filesystem access via PHP's `file_get_contents()` may be restricted. EvoDir uses `shell_exec` to work around this. See [Troubleshooting](#troubleshooting) for details.

---

## Troubleshooting

### Stats showing "unavailable"
If memory, swap, or uptime show as unavailable, your system likely has `ProtectProc=invisible` set in the Apache systemd service. Fix it with a systemd override:

```bash
sudo systemctl edit apache2
```

Add:
```ini
[Service]
ProtectProc=default
ProcSubset=all
```

Then:
```bash
sudo systemctl daemon-reload
sudo systemctl restart apache2
```

### Descriptions not loading
Verify `descriptions.php` is reachable:
```
http://yourserver/.includes/descriptions.php?path=/
```
It should return a JSON object. If it returns `{"error":"Directory not found"}`, check that `$_SERVER['DOCUMENT_ROOT']` resolves correctly for your server configuration.

---

## Roadmap

Version 2 is planned with the following changes:

- Central `config.php` for all settings (theme, nav links, stat visibility, site name)
- Convert `header.html` → `header.php` — PHP-driven nav and theme injection
- Move stats to sidebar — grouped by category, natural scroll
- Repurpose footer for static content (copyright, legal links)
- Reduce JavaScript to clock and directory rewrite only
- Service health indicators
- Theme switcher UI
- Directory size calculation

See `ROADMAP_V2.md` for full details.

---

## License

© 2026 The Vintage Gamers. All rights reserved.

Project by Scott — [thevintagegamers.com](https://thevintagegamers.com)

---

## Contributing

This is a personal project but contributions and suggestions are welcome. Open an issue or pull request on GitHub.
