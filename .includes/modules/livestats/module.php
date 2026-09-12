<?php
/**
 * EvoDir LiveStats Module
 * Safe stats for shared hosting / live environments.
 * No system calls, no /proc, no sensitive info.
 */

global $conf;
$livestats_conf = $conf['livestats'] ?? [];

// Parse storage budget — supports GB, MB, KB suffixes
function parse_budget(string $val): int {
    $val = trim(strtoupper($val));
    if (preg_match('/^([\d.]+)\s*(GB|MB|KB|B)?$/', $val, $m)) {
        $num  = (float)$m[1];
        $unit = $m[2] ?? 'GB';
        switch ($unit) {
            case 'GB': return (int)($num * 1073741824);
            case 'MB': return (int)($num * 1048576);
            case 'KB': return (int)($num * 1024);
            default:   return (int)$num;
        }
    }
    return 10 * 1073741824; // default 10GB
}

function fmt_bytes(int $bytes): string {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824)    return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)       return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

// Count files and folders recursively in webroot
function count_entries(string $dir, int &$files, int &$folders, array $skip = []): void {
    $items = @scandir($dir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skip)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $folders++;
            count_entries($path, $files, $folders, $skip);
        } else {
            $files++;
        }
    }
}

// Calculate disk usage recursively
function dir_size(string $dir, array $skip = []): int {
    $size  = 0;
    $items = @scandir($dir);
    if (!$items) return $size;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skip)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $size += dir_size($path, $skip);
        } else {
            $size += (int)@filesize($path);
        }
    }
    return $size;
}

$webroot     = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$budget_str  = $livestats_conf['storage_budget'] ?? '10GB';
$budget_bytes = parse_budget($budget_str);
$skip        = ['.htaccess', '.includes', '.legal', '.git'];

// File/folder counts
$file_count   = 0;
$folder_count = 0;
try {
    count_entries($webroot, $file_count, $folder_count, $skip);
} catch (Throwable $e) {}

// Disk usage
$used_bytes = 0;
try {
    $used_bytes = dir_size($webroot, $skip);
} catch (Throwable $e) {}

$used_pct = $budget_bytes > 0 ? min(round(($used_bytes / $budget_bytes) * 100, 1), 999) : 0;

return [
    'livestats' => [
        'php_version'   => phpversion(),
        'files'         => $file_count,
        'folders'       => $folder_count,
        'used_bytes'    => $used_bytes,
        'used_fmt'      => fmt_bytes($used_bytes),
        'budget_bytes'  => $budget_bytes,
        'budget_fmt'    => fmt_bytes($budget_bytes),
        'budget_str'    => $budget_str,
        'used_pct'      => $used_pct,
        'over_budget'   => $used_bytes > $budget_bytes,
    ]
];
