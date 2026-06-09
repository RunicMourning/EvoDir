# Homelab Landing Page — Version 2 Roadmap

## Overview
Version 2 is a architectural refactor that moves from a hybrid HTML/JS approach to a proper PHP-driven system. The core goal is to eliminate unnecessary JavaScript, introduce a central config file, and lay the groundwork for a proper theme system and sidebar stats.

---

## Core Architecture Changes

### 1. Convert header.html → header.php
- Read `config.php` on every request
- Output correct theme `<link>` tag from config
- Build sidebar navigation from config array server-side
- Set site name from config or fall back to `gethostname()`
- Set sidebar label from config
- No more hardcoded nav links in the HTML

### 2. Convert footer.html → footer.php
- Read `config.php` for footer content
- Output copyright, legal links, version number from config
- Repurpose footer as static site footer (copyright, terms, contact)
- Remove stats from footer entirely

### 3. Central Config File (/.includes/config.php)
- Site identity (name, label — blank = hostname)
- Theme selection
- Navigation links
- Stats visibility toggles (show/hide per stat)
- Footer content (copyright, legal links)
- Stat refresh interval

```php
<?php
return [
    'site_name'    => '',        // blank = hostname
    'site_label'   => 'Homelab',
    'theme'        => 'blueprint',

    'nav_links' => [
        ['label' => 'CasaOS',        'icon' => 'bi-server',    'url' => 'http://192.168.40.56:8080', 'target' => '_blank'],
        ['label' => 'Plex',          'icon' => 'bi-film',      'url' => 'http://192.168.40.56:32400/web', 'target' => '_blank'],
        ['label' => 'AudioBookShelf','icon' => 'bi-book-half', 'url' => 'http://192.168.40.56:8081', 'target' => '_blank'],
    ],

    'file_links' => [
        ['label' => 'Root', 'icon' => 'bi-hdd-network', 'url' => '/'],
    ],

    'stats' => [
        'memory'    => true,
        'swap'      => true,
        'load'      => true,
        'processes' => true,
        'uptime'    => true,
        'disks'     => true,
        'docker'    => true,
    ],

    'footer' => [
        'copyright' => '',   // blank = site_name
        'links' => [
            // ['label' => 'Terms',   'url' => '/.legal/terms.html'],
            // ['label' => 'Privacy', 'url' => '/.legal/privacy.html'],
        ],
        'version' => '2.0',
    ],

    'stats_refresh' => 30,  // seconds
];
```

---

## Sidebar Evolution

### 4. Move Stats to Sidebar
- System stats (memory, swap, load, processes, uptime) as a sidebar section
- Disk stats as a separate sidebar section
- Docker/service stats as a separate sidebar section
- Sidebar already has overflow-y: auto — scrolling handled automatically
- Stats grouped logically with section labels matching existing sidebar style

### 5. Sidebar Additions
- **Service health dots** — green/red indicator per nav link (ping check)
- **PHP/Apache version** — at a glance, useful on dev and homelab
- **Day/date** — alongside existing clock
- **Server IP** — local IP display
- **Disk warning indicator** — flag if any mount exceeds threshold
- **Theme switcher** — clickable indicator showing active theme

---

## JavaScript Reduction
Only two JS functions remain:
- **Clock** — real-time tick, genuinely needs JS
- **Directory listing rewrite** — manipulates Apache's `<pre>` output post-render

Everything else moves to PHP:
- Navigation rendering
- Stats collection and output
- Theme injection
- Site name/label
- Footer content

---

## Legal & Identity

### 6. Legal Directory (/.legal/)
- Hidden from directory listing via IndexIgnore
- `terms.html` — terms of use, no warranty disclaimer
- `privacy.html` — privacy policy
- Linked from footer via config

---

## Theme System Improvements

### 7. Theme-Aware Warning Colors
- Add `--warn-color` and `--critical-color` CSS variables per theme
- Terminal theme: bright green / amber instead of red
- All other themes: keep existing orange/red

### 8. Theme Switcher UI
- Clickable in sidebar
- Saves preference to cookie or localStorage
- Reads on page load, applies correct theme link tag

---

## Stats Enhancements (v2 additions)
- **Configurable stat bar** — show/hide per stat via config
- **Network I/O** — bandwidth in/out from `/proc/net/dev`
- **Temperature monitoring** — CPU temps from thermal zones
- **All Docker containers** — not just Plex
- **Active Plex sessions** — stream count from Plex API

---

## Definition of Done
- [ ] `config.php` drives all site identity, nav, and stat visibility
- [ ] `header.php` replaces `header.html` — no hardcoded nav or theme
- [ ] `footer.php` replaces `footer.html` — static footer content only
- [ ] Stats rendered in sidebar by PHP
- [ ] Only clock and directory rewrite remain as JS
- [ ] Works on fresh install with zero config editing (hostname fallback)
- [ ] Works identically on homelab and shared hosting (Ionos)
- [ ] All four themes updated for new structure
- [ ] Legal directory in place with terms and copyright
- [ ] Feature wishlist items addressed where applicable

## Directory Listing Enhancements
- [ ] **Relative Timestamps** – Show "2 days ago" style dates instead of raw timestamps (e.g. "3 hours ago", "1 week ago"). Port `getRelativeTime()` from legacy solution. Raw timestamp shown on hover as a tooltip.

## Navigation & Config
- [ ] **PHP-driven nav** – Move nav parsing from JS to `header.php`. Keep existing pipe-delimited `nav.txt` format — simple enough for non-developers to edit safely.
- [ ] **[FOOTER] section in nav.txt** – Add footer links (terms, privacy, etc.) as a new section at the bottom of `nav.txt`. Two-column format (label|url), no icon needed. Parsed by `footer.php` and rendered as plain text links.

Example nav.txt:
```
[NAVIGATION]
bi-server|CasaOS|http://192.168.40.56:8080/
bi-film|Plex|http://192.168.40.56:32400/web/
[FILES]
bi-hdd-network|Root|/
[FOOTER]
Terms of Use|/.legal/terms.html
Privacy Policy|/.legal/privacy.html
```
