<?php
/**
 * error-page.php
 * Reusable error page renderer. Include this file after defining:
 *   $error_code    (string) e.g. "404"
 *   $error_title   (string) e.g. "Not Found"
 *   $error_message (string) e.g. "The requested file could not be located."
 *   $error_detail  (string, optional) secondary detail line
 *   $error_icon    (string, optional) bootstrap-icons class, e.g. "bi-question-circle-fill"
 *
 * Renders inside the same header.html / footer.html shell every other
 * page uses — full theme, nav, weather, and KPI/banner zones all work
 * exactly as normal, since header.html/footer.html don't assume a
 * directory listing exists (footer.html's directory-rewrite script
 * already no-ops when it can't find a <pre> to rewrite).
 *
 * Severity color (warn vs bad) is derived from the status code's first
 * digit rather than set per-page — 4xx is a client-side/request issue,
 * 5xx is a real server fault.
 */
if (!isset($error_code))    $error_code    = '500';
if (!isset($error_title))   $error_title   = 'Internal Server Error';
if (!isset($error_message)) $error_message = 'An unexpected error occurred.';
if (!isset($error_detail))  $error_detail  = '';
if (!isset($error_icon))    $error_icon    = 'bi-exclamation-triangle-fill';

$severity = ($error_code !== '' && $error_code[0] === '5') ? 'alert-bad' : 'alert-warn';

require __DIR__ . '/header.html';
?>

      <div class="card">
        <h1><?php echo htmlspecialchars($error_code); ?> &mdash; <?php echo htmlspecialchars($error_title); ?></h1>

        <div class="actionbar">
          <div class="<?php echo $severity; ?>">
            <i class="bi <?php echo htmlspecialchars($error_icon); ?>"></i>
            <div>
              <strong><?php echo htmlspecialchars($error_message); ?></strong>
              <?php if ($error_detail): ?>
              <p><?php echo htmlspecialchars($error_detail); ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <p>
          <a href="/" class="btn"><i class="bi bi-house"></i> Return Home</a>
          <a href="javascript:history.back()" class="btn"><i class="bi bi-arrow-left"></i> Go Back</a>
        </p>

        <p class="text-muted">Requested <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></p>
      </div>

<?php
require __DIR__ . '/footer.html';
