(function() {
  // Zone: sidebar-bottom (System + Disks sections)

  function statRow(icon, label, value, valueClass) {
    return '<div class="stat-row">' +
      '<span class="stat-label"><i class="bi ' + icon + '"></i> ' + label + '</span>' +
      '<span class="stat-value' + (valueClass ? ' ' + valueClass : '') + '">' + value + '</span>' +
    '</div>';
  }

  function statBar(pct) {
    var level = pct >= 90 ? 'bad' : pct >= 75 ? 'warn' : 'good';
    return '<div class="stat-bar" data-level="' + level + '">' +
      '<div class="stat-bar-fill" style="--pct:' + Math.min(pct, 100) + '%"></div>' +
    '</div>';
  }

  var zone = document.getElementById('evodir-zone-sidebar-bottom');
  if (!zone) return;

  zone.innerHTML =
    '<hr>' +
    '<h2>System</h2>' +
    '<div class="stat-list" id="module-stats-system">Loading&hellip;</div>' +
    '<hr>' +
    '<h2>Disks</h2>' +
    '<div class="stat-list" id="module-stats-disks">Loading&hellip;</div>';

  fetch('/.includes/api.php?action=stats')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var NA   = '<span class="text-muted">N/A</span>';
      var sys  = '';
      var disk = '';

      var memVal = (d.ram_used !== null && d.ram_total !== null)
        ? d.ram_used + ' / ' + d.ram_total : NA;
      sys += statRow('bi-memory', 'Memory', memVal);
      if (d.ram_pct !== null) sys += statBar(d.ram_pct);

      var swapVal = (d.swap_used !== null && d.swap_total !== null)
        ? d.swap_used + ' / ' + d.swap_total : NA;
      sys += statRow('bi-hdd-rack', 'Swap', swapVal);
      if (d.swap_pct !== null) sys += statBar(d.swap_pct);

      var loadVal = d.cpu_load !== null
        ? d.cpu_load + ' / ' + d.cpu_load_5 + ' / ' + d.cpu_load_15 : NA;
      sys += statRow('bi-cpu', 'Load', loadVal);
      sys += statRow('bi-diagram-3', 'Processes', d.processes !== null ? d.processes : NA);
      sys += statRow('bi-clock', 'Uptime', d.uptime !== null ? d.uptime : NA);

      if (d.docker && d.docker.plex_status) {
        var running = d.docker.plex_status === 'running';
        sys += statRow('bi-play-circle', 'Plex', d.docker.plex_status, running ? 'text-good' : 'text-bad');
      }

      if (d.disks && d.disks.length) {
        d.disks.forEach(function(diskItem) {
          var diskVal = diskItem.error ? NA : diskItem.used + ' / ' + diskItem.total;
          disk += statRow('bi-hdd', diskItem.name, diskVal);
          if (!diskItem.error) disk += statBar(diskItem.pct);
        });
      } else {
        disk = '<span class="text-muted stat-empty">No mounts found</span>';
      }

      document.getElementById('module-stats-system').innerHTML = sys;
      document.getElementById('module-stats-disks').innerHTML  = disk;
    })
    .catch(function() {
      document.getElementById('module-stats-system').innerHTML =
        '<span class="text-muted stat-empty">Stats unavailable</span>';
    });
})();
