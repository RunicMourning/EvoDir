<?php
/**
 * error-page.php
 * Reusable error page renderer. Include this file after defining:
 *   $error_code    (string) e.g. "404"
 *   $error_title   (string) e.g. "Not Found"
 *   $error_message (string) e.g. "The requested file could not be located."
 *   $error_detail  (string, optional) secondary detail line
 */
if (!isset($error_code))    $error_code    = '500';
if (!isset($error_title))   $error_title   = 'Internal Server Error';
if (!isset($error_message)) $error_message = 'An unexpected error occurred.';
if (!isset($error_detail))  $error_detail  = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>notflix &mdash; <?php echo $error_code; ?> <?php echo $error_title; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root {
    --blue-dark:   #0a1628;
    --blue-mid:    #0d2045;
    --blue-line:   #1a3a6e;
    --blue-accent: #4a9eff;
    --blue-glow:   #7ec8ff;
    --text-primary:#c8deff;
    --text-muted:  #6a90c0;
    --sidebar-w:   220px;
    --grid-size:   40px;
    --error-color: #a8d4ff;
    --error-glow:  rgba(168,212,255,0.15);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    font-family: 'Rajdhani', sans-serif;
    background-color: var(--blue-dark);
    color: var(--text-primary);
    min-height: 100vh;
  }

  body {
    background-image:
      linear-gradient(rgba(74,158,255,0.07) 1px, transparent 1px),
      linear-gradient(90deg, rgba(74,158,255,0.07) 1px, transparent 1px),
      linear-gradient(rgba(74,158,255,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(74,158,255,0.03) 1px, transparent 1px);
    background-size:
      var(--grid-size) var(--grid-size),
      var(--grid-size) var(--grid-size),
      8px 8px, 8px 8px;
    margin-left: var(--sidebar-w);
    padding: 2rem 2rem 6rem 2rem;
    display: flex;
    align-items: center;
    min-height: 100vh;
  }

  /* Fixed sidebar - identical to main header.html */
  .sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sidebar-w);
    height: 100vh;
    background: rgba(10,22,40,0.97);
    border-right: 1px solid var(--blue-line);
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
  }

  .sidebar-brand {
    padding: 1.25rem 1.25rem 1rem;
    border-bottom: 1px solid var(--blue-line);
  }

  .sidebar-brand::before {
    content: '';
    display: block;
    width: 32px;
    height: 2px;
    background: var(--blue-accent);
    margin-bottom: 8px;
  }

  .sidebar-brand .label {
    font-size: 10px;
    letter-spacing: 0.2em;
    color: var(--text-muted);
    text-transform: uppercase;
    font-family: 'Share Tech Mono', monospace;
  }

  .sidebar-brand .name {
    font-size: 22px;
    font-weight: 700;
    color: var(--blue-glow);
    letter-spacing: 0.05em;
    text-shadow: 0 0 12px rgba(126,200,255,0.4);
    line-height: 1.1;
  }

  .sidebar-brand .clock {
    font-size: 11px;
    font-family: 'Share Tech Mono', monospace;
    color: var(--text-muted);
    margin-top: 6px;
    letter-spacing: 0.1em;
  }

  .sidebar-divider {
    margin: 0 1rem;
    border: none;
    border-top: 1px solid var(--blue-line);
    display: block;
  }

  .sidebar-section { padding: 1rem 1rem 0.5rem; }

  .sidebar-section-label {
    font-size: 10px;
    letter-spacing: 0.15em;
    color: var(--text-muted);
    text-transform: uppercase;
    font-family: 'Share Tech Mono', monospace;
    margin-bottom: 6px;
    padding-left: 4px;
  }

  .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 4px;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
    text-decoration: none;
    border: 1px solid transparent;
    letter-spacing: 0.03em;
    transition: all 0.15s;
    margin-bottom: 2px;
  }

  .nav-link i { font-size: 17px; color: var(--blue-accent); }

  .nav-link:hover {
    background: rgba(74,158,255,0.1);
    border-color: var(--blue-line);
    color: var(--blue-glow);
  }

  .nav-link:hover i { color: var(--blue-glow); }

  /* Error panel */
  .error-container {
    width: 100%;
    max-width: 680px;
  }

  .error-eyebrow {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--error-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .error-eyebrow::before {
    content: '';
    display: inline-block;
    width: 24px;
    height: 1px;
    background: var(--error-color);
  }

  .error-code {
    font-family: 'Share Tech Mono', monospace;
    font-size: clamp(80px, 14vw, 140px);
    font-weight: 400;
    color: var(--error-color);
    line-height: 1;
    text-shadow:
      0 0 20px rgba(168,212,255,0.4),
      0 0 60px rgba(168,212,255,0.15);
    letter-spacing: -0.02em;
    margin-bottom: 0.5rem;
    animation: flicker 6s infinite;
  }

  @keyframes flicker {
    0%, 95%, 100% { opacity: 1; }
    96%            { opacity: 0.7; }
    97%            { opacity: 1; }
    98%            { opacity: 0.6; }
    99%            { opacity: 1; }
  }

  .error-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--blue-glow);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 1.5rem;
  }

  .error-divider {
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, var(--blue-glow), var(--blue-line), transparent);
    margin-bottom: 1.5rem;
  }

  .error-message {
    font-size: 16px;
    color: var(--text-primary);
    line-height: 1.6;
    margin-bottom: 0.75rem;
  }

  .error-detail {
    font-family: 'Share Tech Mono', monospace;
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 2rem;
  }

  .error-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 4px;
    font-family: 'Share Tech Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    transition: all 0.15s;
    cursor: pointer;
    border: none;
  }

  .btn-primary {
    background: rgba(74,158,255,0.15);
    border: 1px solid var(--blue-accent);
    color: var(--blue-glow);
  }

  .btn-primary:hover {
    background: rgba(74,158,255,0.25);
    color: #fff;
    text-decoration: none;
  }

  .btn-ghost {
    background: transparent;
    border: 1px solid var(--blue-line);
    color: var(--text-muted);
  }

  .btn-ghost:hover {
    border-color: var(--blue-accent);
    color: var(--text-primary);
    text-decoration: none;
  }

  .error-meta {
    margin-top: 2.5rem;
    padding: 1rem;
    background: rgba(13,32,69,0.6);
    border: 1px solid var(--blue-line);
    border-left: 3px solid var(--error-color);
    font-family: 'Share Tech Mono', monospace;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.8;
  }

  .error-meta span { color: var(--blue-accent); }

  a { color: var(--blue-accent); text-decoration: none; }
  a:hover { color: var(--blue-glow); }
  address { display: none; }
  hr { display: none; }
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-brand">
    <div class="label">system</div>
    <div class="name" id="sidebar-hostname">NOTFLIX</div>
    <div class="clock" id="sidebar-clock">--:--:--</div>
  </div>
  <div id="sidebar-nav"></div>
</div>

<div class="error-container">
  <div class="error-eyebrow">system fault</div>
  <div class="error-code"><?php echo htmlspecialchars($error_code); ?></div>
  <div class="error-title"><?php echo htmlspecialchars($error_title); ?></div>
  <div class="error-divider"></div>
  <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
  <?php if ($error_detail): ?>
  <div class="error-detail"><?php echo htmlspecialchars($error_detail); ?></div>
  <?php endif; ?>

  <div class="error-actions">
    <a href="/" class="btn btn-primary"><i class="bi bi-house"></i> Return Home</a>
    <a href="javascript:history.back()" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Go Back</a>
  </div>

  <div class="error-meta">
    <div><span>timestamp</span> &nbsp; <span id="meta-time">--</span></div>
    <div><span>requested</span> &nbsp; <span id="meta-path"><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></span></div>
    <div><span>host</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span id="meta-host">--</span></div>
    <div><span>status</span> &nbsp;&nbsp;&nbsp; <?php echo htmlspecialchars($error_code); ?> <?php echo htmlspecialchars($error_title); ?></div>
  </div>
</div>

<script>
// Sidebar nav
fetch('/.includes/nav.php')
  .then(r => r.json())
  .then(sections => {
    var html = '';
    Object.entries(sections).forEach(([sectionName, links]) => {
      html += '<div class="sidebar-section">';
      html += '<div class="sidebar-section-label">' + sectionName + '</div>';
      links.forEach(link => {
        var target = link.url.startsWith('http') ? ' target="_blank"' : '';
        html += '<a class="nav-link" href="' + link.url + '"' + target + '>';
        html += '<i class="bi ' + link.icon + '"></i> ' + link.label;
        html += '</a>';
      });
      html += '</div>';
      html += '<hr class="sidebar-divider">';
    });
    document.getElementById('sidebar-nav').innerHTML = html;
  })
  .catch(() => {
    document.getElementById('sidebar-nav').innerHTML = '<div style="padding:1rem;color:#6a90c0;font-size:12px;">Nav unavailable</div>';
  });

// Hostname
fetch('/.includes/stats.php')
  .then(r => r.json())
  .then(d => {
    if (d.hostname) document.getElementById('sidebar-hostname').textContent = d.hostname.toUpperCase();
    document.getElementById('meta-host').textContent = d.hostname || window.location.hostname;
  })
  .catch(() => {
    document.getElementById('meta-host').textContent = window.location.hostname;
  });

// Clock
(function() {
  function tick() {
    var el = document.getElementById('sidebar-clock');
    if (!el) return;
    var now = new Date();
    el.textContent = String(now.getHours()).padStart(2,'0') + ':' +
                     String(now.getMinutes()).padStart(2,'0') + ':' +
                     String(now.getSeconds()).padStart(2,'0');
  }
  tick();
  setInterval(tick, 1000);
})();

// Meta timestamp
document.getElementById('meta-time').textContent = new Date().toISOString();
</script>
</body>
</html>
