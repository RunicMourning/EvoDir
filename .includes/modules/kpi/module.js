(function() {
  // Zone: content-top (shared with the banner module, which renders
  // first when enabled). Renders as .actionbar > .kpi — same component
  // every other status readout on the page uses (see banner, stats),
  // so no module-local CSS of its own and full theme integration for
  // free via actionbar.css's --kpi-1-bg..--kpi-6-bg palette.

  function waitForZone(cb, attempts) {
    attempts = attempts || 0;
    var zone = document.getElementById('evodir-zone-content-top');
    if (zone) { cb(zone); return; }
    if (attempts < 20) setTimeout(function() { waitForZone(cb, attempts + 1); }, 150);
  }

  function kpiCard(item) {
    var value = typeof item.value === 'number' ? item.value.toLocaleString() : item.value;
    var valueClass = item.error ? ' class="text-bad"' : '';
    return '<div class="kpi">' +
      '<div class="kpi-header"><i class="bi ' + item.icon + '"></i> ' + item.label + '</div>' +
      '<div class="kpi-body"><h3' + valueClass + '>' + value + '</h3></div>' +
    '</div>';
  }

  waitForZone(function(zone) {
    fetch('/.includes/api.php?action=kpi&path=' + encodeURIComponent(window.location.pathname))
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var items = (d && d.items) || [];
        var html  = '';

        items.forEach(function(item) {
          if (item.value === null || item.value === undefined) return;
          html += kpiCard(item);
        });

        if (!html) return;

        var bar = document.createElement('div');
        bar.className = 'actionbar';
        bar.id = 'evodir-kpi-bar';
        bar.innerHTML = html;

        // Whatever's already in the zone (a rendered banner) stays first;
        // the KPI bar goes right after it rather than displacing it.
        var existingChild = zone.firstElementChild;
        if (existingChild) {
          zone.insertBefore(bar, existingChild.nextSibling);
        } else {
          zone.appendChild(bar);
        }
      })
      .catch(function() {});
  });
})();
