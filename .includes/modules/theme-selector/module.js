(function() {
  // Trigger lives in the header (#themeToggle, already in the page's
  // static markup) rather than a self-built footer button, so no need
  // to poll for it. The current theme's name is already shown in the
  // sidebar's Config Metrics panel via pure CSS (.themename reading
  // --theme-display-name), so this module doesn't duplicate that here.

  var STORAGE_KEY = 'evodir-theme-override';
  var themeList   = [];
  var currentSlug = '';

  function applyTheme(slug, persist) {
    var link = document.getElementById('evodir-theme');
    if (link) link.href = '/.includes/themes/' + slug + '.css';
    currentSlug = slug;
    if (persist !== false) {
      try { localStorage.setItem(STORAGE_KEY, slug); } catch (e) {}
    }
    updateActiveStates();
  }

  function updateActiveStates() {
    var modal = document.getElementById('evodir-theme-modal');
    if (!modal) return;
    modal.querySelectorAll('.theme-modal-row').forEach(function(row) {
      row.classList.toggle('active', row.dataset.slug === currentSlug);
    });
    modal.querySelectorAll('.theme-modal-toggle').forEach(function(toggle) {
      var lightBtn = toggle.querySelector('[data-mode="light"]');
      var darkBtn  = toggle.querySelector('[data-mode="dark"]');
      var isDark   = toggle.dataset.darkSlug === currentSlug;
      var isLight  = toggle.dataset.lightSlug === currentSlug;
      if (lightBtn) lightBtn.classList.toggle('on', isLight);
      if (darkBtn)  darkBtn.classList.toggle('on', isDark);
    });
  }

  function buildRow(theme) {
    var row = document.createElement('div');
    row.className = 'theme-modal-row';
    row.dataset.slug = theme.slug;
    row.textContent = theme.name;
    row.addEventListener('click', function() { applyTheme(theme.slug); });
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
    toggle.dataset.darkSlug  = dark.slug;

    var sunBtn = document.createElement('button');
    sunBtn.type = 'button';
    sunBtn.dataset.mode = 'light';
    sunBtn.innerHTML = '<i class="bi bi-sun"></i>';
    sunBtn.title = light.name;
    sunBtn.addEventListener('click', function(e) { e.stopPropagation(); applyTheme(light.slug); });

    var moonBtn = document.createElement('button');
    moonBtn.type = 'button';
    moonBtn.dataset.mode = 'dark';
    moonBtn.innerHTML = '<i class="bi bi-moon-stars"></i>';
    moonBtn.title = dark.name;
    moonBtn.addEventListener('click', function(e) { e.stopPropagation(); applyTheme(dark.slug); });

    toggle.appendChild(sunBtn);
    toggle.appendChild(moonBtn);
    row.appendChild(toggle);

    // Clicking the row (not the toggle) keeps whichever mode is
    // currently active, or defaults to light.
    row.addEventListener('click', function() {
      applyTheme(currentSlug === dark.slug ? dark.slug : light.slug);
    });

    return row;
  }

  function buildModal() {
    var overlay = document.createElement('div');
    overlay.id = 'evodir-theme-modal-overlay';
    overlay.className = 'theme-modal-overlay';

    var modal = document.createElement('div');
    modal.id = 'evodir-theme-modal';
    modal.className = 'theme-modal';

    var header = document.createElement('div');
    header.className = 'theme-modal-header';
    header.innerHTML = '<span>Choose a theme</span>';
    var closeBtn = document.createElement('button');
    closeBtn.className = 'theme-modal-close';
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
    closeBtn.addEventListener('click', function() { overlay.classList.remove('is-open'); });
    header.appendChild(closeBtn);
    modal.appendChild(header);

    var body = document.createElement('div');
    body.className = 'theme-modal-body';

    var families   = {};
    var standalone = [];
    themeList.forEach(function(t) {
      if (t.family) {
        (families[t.family] = families[t.family] || []).push(t);
      } else {
        standalone.push(t);
      }
    });

    Object.keys(families).sort().forEach(function(familyName) {
      var members = families[familyName];
      if (members.length === 2) {
        var light = members.find(function(t) { return t.mode === 'light'; });
        var dark  = members.find(function(t) { return t.mode === 'dark'; });
        if (light && dark) {
          body.appendChild(buildPairedRow(light, dark));
          return;
        }
      }
      // Family declared but not a clean light/dark pair — list members
      // individually under a heading so nothing gets hidden.
      var heading = document.createElement('div');
      heading.className = 'theme-modal-family';
      heading.textContent = familyName;
      body.appendChild(heading);
      members.forEach(function(t) { body.appendChild(buildRow(t)); });
    });

    if (standalone.length) {
      var heading = document.createElement('div');
      heading.className = 'theme-modal-family';
      heading.textContent = 'Standalone';
      body.appendChild(heading);
      standalone.forEach(function(t) { body.appendChild(buildRow(t)); });
    }

    modal.appendChild(body);
    overlay.appendChild(modal);

    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.classList.remove('is-open');
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') overlay.classList.remove('is-open');
    });

    document.body.appendChild(overlay);
    return overlay;
  }

  var toggleBtn = document.getElementById('themeToggle');
  if (!toggleBtn) return;

  Promise.all([
    fetch('/.includes/api.php?action=theme-selector').then(function(r) { return r.json(); }),
    fetch('/.includes/api.php?action=config').then(function(r) { return r.json(); })
  ]).then(function(results) {
    themeList = results[0] || [];
    var confSlug = (results[1] && results[1].theme) || '';

    var savedSlug = null;
    try { savedSlug = localStorage.getItem(STORAGE_KEY); } catch (e) {}

    var validSlugs = themeList.map(function(t) { return t.slug; });
    currentSlug = (savedSlug && validSlugs.indexOf(savedSlug) !== -1) ? savedSlug : confSlug;

    // Only apply an override if it differs from the server-selected
    // theme already loaded via evodir.conf.
    if (currentSlug && currentSlug !== confSlug) applyTheme(currentSlug, false);

    // No point showing a picker for zero or one theme.
    if (themeList.length < 2) {
      toggleBtn.style.display = 'none';
      return;
    }

    var overlay = buildModal();
    toggleBtn.addEventListener('click', function() {
      updateActiveStates();
      overlay.classList.add('is-open');
    });
  }).catch(function() {});
})();
