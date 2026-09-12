(function() {
  // Zone: sidebar-bottom — alternate to stats/ for shared hosting (no
  // shell_exec/proc access). Mutually exclusive with stats/ in evodir.conf.

  var zone = document.getElementById('evodir-zone-sidebar-bottom');
  if (!zone) return;

  function statRow(icon, label, value, valueClass) {
    return '<div class="stat-row">' +
      '<span class="stat-label"><i class="bi ' + icon + '"></i> ' + label + '</span>' +
      '<span class="stat-value' + (valueClass ? ' ' + valueClass : '') + '">' + value + '</span>' +
    '</div>';
  }

  function storageBar(pct, overBudget) {
    var level = overBudget ? 'critical' : pct >= 90 ? 'bad' : pct >= 75 ? 'warn' : 'good';
    return '<div class="stat-bar" data-level="' + level + '">' +
      '<div class="stat-bar-fill" style="--pct:' + (overBudget ? 100 : Math.min(pct, 100)) + '%"></div>' +
    '</div>';
  }

  zone.innerHTML =
    '<hr>' +
    '<h2>Server</h2>' +
    '<div class="stat-list" id="module-livestats">Loading&hellip;</div>';

  fetch('/.includes/api.php?action=livestats')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var NA   = '<span class="text-muted">N/A</span>';
      var html = '';

      var pct        = d.used_pct !== null ? d.used_pct : 0;
      var overBudget = d.over_budget || false;
      var storageVal = d.used_fmt + ' / ' + d.budget_fmt;
      var pctLabel   = overBudget
        ? '<strong class="text-bad stat-pct">OVER BUDGET</strong>'
        : '<span class="text-muted stat-pct">(' + pct + '%)</span>';

      html += statRow('bi-hdd', 'Storage', storageVal);
      html += storageBar(pct, overBudget);
      html += '<div class="stat-pct-row">' + pctLabel + '</div>';

      html += statRow('bi-filetype-php', 'PHP', d.php_version || NA);
      html += statRow('bi-file-earmark', 'Files', d.files !== null ? d.files.toLocaleString() : NA);
      html += statRow('bi-folder2', 'Folders', d.folders !== null ? d.folders.toLocaleString() : NA);

      document.getElementById('module-livestats').innerHTML = html;
    })
    .catch(function() {
      document.getElementById('module-livestats').innerHTML =
        '<span class="text-muted stat-empty">Stats unavailable</span>';
    });
})();
