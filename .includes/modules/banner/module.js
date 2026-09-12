(function() {
  // Zone: content-top. Not currently auto-loaded — footer.html renders
  // the banner directly since [BANNER] isn't part of evodir.conf's
  // [MODULES] block. Kept in sync with that implementation in case this
  // becomes the loading path later.

  var zone = document.getElementById('evodir-zone-content-top');
  if (!zone) return;

  fetch('/.includes/api.php?action=banner')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.enabled) return;
      if (d.dismissible && sessionStorage.getItem('evodir-banner-dismissed')) return;

      zone.innerHTML = '';

      var bar = document.createElement('div');
      bar.className = 'actionbar';

      var notice = document.createElement('div');
      notice.className = 'notice';
      notice.id = 'evodir-banner';

      var icon = document.createElement('i');
      icon.className = 'bi bi-info-circle-fill';
      notice.appendChild(icon);

      var content = document.createElement('div');
      if (d.title) {
        var title = document.createElement('strong');
        title.textContent = d.title;
        content.appendChild(title);
      }
      if (Array.isArray(d.lines)) {
        d.lines.forEach(function(line) {
          if (!line) return;
          var p = document.createElement('p');
          p.innerHTML = line;
          content.appendChild(p);
        });
      }
      notice.appendChild(content);

      if (d.dismissible) {
        var closeBtn = document.createElement('button');
        closeBtn.className = 'notice-dismiss';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Dismiss');
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.addEventListener('click', function() {
          bar.style.display = 'none';
          sessionStorage.setItem('evodir-banner-dismissed', 'true');
        });
        notice.appendChild(closeBtn);
      }

      bar.appendChild(notice);
      zone.appendChild(bar);
    })
    .catch(function() {});
})();
