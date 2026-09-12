/**
 * Theme Picker — official CSS framework component.
 *
 * Portable, dependency-free version for projects with no backend to
 * scan a themes folder server-side. Reads each theme's own declared
 * identity straight out of its CSS — same contract as any PHP/backend
 * version of this idea would use, just fetched and parsed client-side:
 *
 *   --theme-id:           "architecture-dark";
 *   --theme-family:       "Architecture";   (blank/omitted = standalone)
 *   --theme-display-name: "Blueprint";
 *
 * Light/dark mode is inferred from --theme-id's "-light"/"-dark" suffix.
 * A file with no metadata block still works — falls back to a
 * title-cased version of its own filename, standalone, no toggle.
 *
 * Usage — one button, two data attributes, nothing else to hand-author:
 *
 *   <button id="themeToggle" class="theme-toggle"
 *           data-theme-dir="themes/"
 *           data-theme-files="architecture-light,architecture-dark,
 *                              classroom-light,classroom-dark,
 *                              seasonal-2026">
 *     🎨
 *   </button>
 *   <script src="theme-picker.js"></script>
 *
 * data-theme-dir defaults to "themes/" if omitted.
 * data-theme-files is required — this version has no way to discover
 * files on its own, since that needs either a backend or a directory
 * listing to read. List every file you want offered, by slug (the
 * filename minus .css).
 *
 * Multiple picker buttons on one page each track their own state
 * independently — fine for the near-universal case of one picker per
 * page, but two won't stay in sync with each other if you add a second.
 *
 * NOT for use alongside a project that already has its own
 * backend-driven theme module (e.g. EvoDir's theme-selector) — both
 * would claim the same button.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var STORAGE_KEY = 'ui-theme';

  function extractVar(css, name) {
    var m = css.match(new RegExp('--' + name + '\\s*:\\s*"([^"]*)"'));
    return m && m[1] !== '' ? m[1] : null;
  }

  function inferMode(id) {
    if (!id) return null;
    if (/-dark$/.test(id)) return 'dark';
    if (/-light$/.test(id)) return 'light';
    return null;
  }

  function titleCase(slug) {
    return slug.replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function initPicker(toggleBtn) {
    var dir = toggleBtn.dataset.themeDir || 'themes/';
    var files = (toggleBtn.dataset.themeFiles || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    if (!files.length) return;

    var currentSlug = '';
    var overlay = null;

    function applyTheme(slug, persist) {
      var link = document.getElementById('theme-stylesheet');
      if (!link) {
        link = document.createElement('link');
        link.id = 'theme-stylesheet';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
      }
      link.href = dir + slug + '.css';
      currentSlug = slug;
      if (persist !== false) {
        try { localStorage.setItem(STORAGE_KEY, slug); } catch (e) {}
      }
      updateActiveStates();
    }

    function updateActiveStates() {
      if (!overlay) return;
      overlay.querySelectorAll('.theme-modal-row').forEach(function (row) {
        row.classList.toggle('active', row.dataset.slug === currentSlug);
      });
      overlay.querySelectorAll('.theme-modal-toggle').forEach(function (toggle) {
        var lightBtn = toggle.querySelector('[data-mode="light"]');
        var darkBtn = toggle.querySelector('[data-mode="dark"]');
        if (lightBtn) lightBtn.classList.toggle('on', toggle.dataset.lightSlug === currentSlug);
        if (darkBtn) darkBtn.classList.toggle('on', toggle.dataset.darkSlug === currentSlug);
      });
    }

    function buildRow(theme) {
      var row = document.createElement('div');
      row.className = 'theme-modal-row';
      row.dataset.slug = theme.slug;
      row.textContent = theme.name;
      row.addEventListener('click', function () { applyTheme(theme.slug); });
      return row;
    }

    function buildPairedRow(light, dark) {
      var row = document.createElement('div');
      row.className = 'theme-modal-row';
      row.dataset.slug = light.slug;

      var label = document.createElement('span');
      label.textContent = light.family;
      row.appendChild(label);

      var toggle = document.createElement('div');
      toggle.className = 'theme-modal-toggle';
      toggle.dataset.lightSlug = light.slug;
      toggle.dataset.darkSlug = dark.slug;

      var sunBtn = document.createElement('button');
      sunBtn.type = 'button';
      sunBtn.dataset.mode = 'light';
      sunBtn.textContent = '☀';
      sunBtn.title = light.name;
      sunBtn.addEventListener('click', function (e) { e.stopPropagation(); applyTheme(light.slug); });

      var moonBtn = document.createElement('button');
      moonBtn.type = 'button';
      moonBtn.dataset.mode = 'dark';
      moonBtn.textContent = '☾';
      moonBtn.title = dark.name;
      moonBtn.addEventListener('click', function (e) { e.stopPropagation(); applyTheme(dark.slug); });

      toggle.appendChild(sunBtn);
      toggle.appendChild(moonBtn);
      row.appendChild(toggle);

      // Clicking the row (not the toggle) keeps whichever mode is
      // currently active, or defaults to light.
      row.addEventListener('click', function () {
        applyTheme(currentSlug === dark.slug ? dark.slug : light.slug);
      });

      return row;
    }

    function buildModal(themeList) {
      var ov = document.createElement('div');
      ov.className = 'theme-modal-overlay';

      var modal = document.createElement('div');
      modal.className = 'theme-modal';

      var header = document.createElement('div');
      header.className = 'theme-modal-header';
      header.innerHTML = '<span>Choose a theme</span>';
      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'theme-modal-close';
      closeBtn.setAttribute('aria-label', 'Close');
      closeBtn.textContent = '\u00d7';
      closeBtn.addEventListener('click', function () { toggleModal(false); });
      header.appendChild(closeBtn);
      modal.appendChild(header);

      var body = document.createElement('div');
      body.className = 'theme-modal-body';

      var families = {};
      var standalone = [];
      themeList.forEach(function (t) {
        if (t.family) {
          (families[t.family] = families[t.family] || []).push(t);
        } else {
          standalone.push(t);
        }
      });

      Object.keys(families).sort().forEach(function (familyName) {
        var members = families[familyName];
        if (members.length === 2) {
          var light = members.filter(function (t) { return t.mode === 'light'; })[0];
          var dark = members.filter(function (t) { return t.mode === 'dark'; })[0];
          if (light && dark) {
            body.appendChild(buildPairedRow(light, dark));
            return;
          }
        }
        // Family declared but not a clean light/dark pair — list
        // members individually under a heading so nothing gets hidden.
        var heading = document.createElement('div');
        heading.className = 'theme-modal-family';
        heading.textContent = familyName;
        body.appendChild(heading);
        members.forEach(function (t) { body.appendChild(buildRow(t)); });
      });

      if (standalone.length) {
        var sHeading = document.createElement('div');
        sHeading.className = 'theme-modal-family';
        sHeading.textContent = 'Standalone';
        body.appendChild(sHeading);
        standalone.forEach(function (t) { body.appendChild(buildRow(t)); });
      }

      modal.appendChild(body);
      ov.appendChild(modal);

      ov.addEventListener('click', function (e) {
        if (e.target === ov) toggleModal(false);
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ov.classList.contains('is-open')) toggleModal(false);
      });

      document.body.appendChild(ov);
      return ov;
    }

    function toggleModal(open) {
      var isOpen = typeof open === 'boolean' ? open : !overlay.classList.contains('is-open');
      overlay.classList.toggle('is-open', isOpen);
      toggleBtn.setAttribute('aria-expanded', String(isOpen));
    }

    Promise.all(files.map(function (slug) {
      return fetch(dir + slug + '.css')
        .then(function (r) { return r.text(); })
        .then(function (css) {
          var id = extractVar(css, 'theme-id') || slug;
          return {
            slug: slug,
            id: id,
            family: extractVar(css, 'theme-family') || '',
            name: extractVar(css, 'theme-display-name') || titleCase(slug),
            mode: inferMode(id)
          };
        })
        .catch(function () { return null; }); // one bad/missing file shouldn't sink the rest
    })).then(function (results) {
      var themeList = results.filter(Boolean);

      // No point showing a picker for zero or one theme.
      if (themeList.length < 2) {
        toggleBtn.style.display = 'none';
        return;
      }

      var savedSlug = null;
      try { savedSlug = localStorage.getItem(STORAGE_KEY); } catch (e) {}
      var validSlugs = themeList.map(function (t) { return t.slug; });
      if (savedSlug && validSlugs.indexOf(savedSlug) !== -1) {
        applyTheme(savedSlug, false);
      }

      overlay = buildModal(themeList);
      toggleBtn.setAttribute('aria-haspopup', 'dialog');
      toggleBtn.setAttribute('aria-expanded', 'false');
      toggleBtn.addEventListener('click', function () {
        updateActiveStates();
        toggleModal();
      });
    });
  }

  document.querySelectorAll('[data-theme-files]').forEach(initPicker);
});
