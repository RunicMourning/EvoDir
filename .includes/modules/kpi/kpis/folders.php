<?php
/**
 * KPI: Folders
 * Counts subfolders in the current directory (non-recursive).
 *
 * Runs inside module.php's scope — $absPath, $reqPath, $webroot,
 * and $kpi_conf are already defined. Return an array with at least
 * 'value' (null if unavailable) for module.php to pick up.
 */

$skip  = ['.htaccess', '.includes', '.legal', '.git'];
$count = null;

try {
    $count   = 0;
    $entries = @scandir($absPath);
    if ($entries) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (in_array($entry, $skip, true)) continue;
            if (is_dir($absPath . '/' . $entry)) $count++;
        }
    }
} catch (Throwable $e) {
    $count = null;
}

return [
    'id'    => 'folders',
    'label' => 'Folders',
    'icon'  => 'bi-folder2',
    'order' => 10,
    'value' => $count,
];
