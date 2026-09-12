(function() {
  // Zone: sidebar-top (replaces clock when enabled)

  var zone = document.getElementById('evodir-zone-sidebar-top');
  if (!zone) return;

  zone.innerHTML =
    '<div id="module-weather" class="weather-widget">' +
      '<div class="weather-message">Loading weather&hellip;</div>' +
    '</div>';

  fetch('/.includes/api.php?action=weather')
    .then(function(r) { return r.json(); })
    .then(function(res) {
      // Un-nest weather object if API returned ['weather' => $result]
      var w = res.weather || res;

      if (!w || w.error) {
        document.getElementById('module-weather').innerHTML =
          '<div class="weather-message">Weather unavailable</div>';
        return;
      }

      var staleNote = w.stale
        ? '<div class="weather-stale"><i class="bi bi-exclamation-triangle"></i> Cached data</div>'
        : '';

      var cityMarkup = w.city
        ? '<div class="weather-location"><i class="bi bi-geo-alt" aria-hidden="true"></i> ' + w.city + '</div>'
        : '';

      document.getElementById('module-weather').innerHTML =
        cityMarkup +
        '<div class="weather-top">' +
          '<div>' +
            '<div class="weather-temp">' + w.temp + '</div>' +
            '<div class="weather-condition">' + w.condition + '</div>' +
          '</div>' +
          '<i class="bi ' + w.icon + ' weather-icon" aria-hidden="true"></i>' +
        '</div>' +

        '<div class="weather-stats">' +
          '<div class="weather-stat">' +
            '<div class="weather-stat-label">High</div>' +
            '<div class="weather-stat-value">' + w.high + '</div>' +
          '</div>' +
          '<div class="weather-divider"></div>' +
          '<div class="weather-stat">' +
            '<div class="weather-stat-label">Low</div>' +
            '<div class="weather-stat-value">' + w.low + '</div>' +
          '</div>' +
          '<div class="weather-divider"></div>' +
          '<div class="weather-stat">' +
            '<div class="weather-stat-label">Feels</div>' +
            '<div class="weather-stat-value">' + w.feels_like + '</div>' +
          '</div>' +
        '</div>' +

        '<div class="weather-details">' +
          '<div class="weather-detail">' +
            '<div class="weather-detail-label">' +
              '<i class="bi bi-droplet weather-detail-icon" aria-hidden="true"></i>' +
              '<span>Humidity</span>' +
            '</div>' +
            '<span class="weather-detail-value">' + w.humidity + '</span>' +
          '</div>' +
          '<div class="weather-detail">' +
            '<div class="weather-detail-label">' +
              '<i class="bi bi-wind weather-detail-icon" aria-hidden="true"></i>' +
              '<span>Wind</span>' +
            '</div>' +
            '<span class="weather-detail-value">' + w.wind + '</span>' +
          '</div>' +
        '</div>' +

        '<div class="weather-updated-row">' +
          '<span class="weather-updated">Updated ' + w.updated + '</span>' +
        '</div>' +
        staleNote;
    })
    .catch(function() {
      document.getElementById('module-weather').innerHTML =
        '<div class="weather-message">Weather unavailable</div>';
    });
})();