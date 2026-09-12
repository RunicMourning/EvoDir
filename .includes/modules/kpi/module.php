<?php
/**
 * EvoDir KPI Module
 * Auto-discovers KPI providers in kpis/*.php and runs the enabled ones.
 *
 * Each provider is a self-contained script that runs in this file's
 * scope (so $webroot/$reqPath/$absPath/$kpi_conf are already available
 * to it) and returns ['id','label','icon','order','value']. Config for
 * an individual KPI (DB paths, section IDs, etc.) lives inside that
 * KPI's own provider file — evodir.conf's [KPI] section is only ever
 * on/off flags, nothing else.
 *
 * Enable/disable individual KPIs from the [KPI] section of evodir.conf,
 * e.g. `plex_movies|false`. A provider with no matching conf key
 * defaults to enabled — dropping a new file into kpis/ is enough to
 * make it show up, no conf edit required.
 *
 * Homelab only — some providers require access to the Plex SQLite database.
 */

global $conf;
$kpi_conf = $conf['kpi'] ?? [];

$webroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$reqPath = isset($_GET['path']) ? $_GET['path'] : '/';
$reqPath = '/' . trim(str_replace('..', '', $reqPath), '/');
$absPath = rtrim($webroot . $reqPath, '/');

$items         = [];
$providerDir   = __DIR__ . '/kpis';
$providerFiles = is_dir($providerDir) ? (glob($providerDir . '/*.php') ?: []) : [];

foreach ($providerFiles as $providerFile) {
    $id      = basename($providerFile, '.php');
    $enabled = filter_var($kpi_conf[$id] ?? true, FILTER_VALIDATE_BOOLEAN);
    if (!$enabled) continue;

    try {
        $result = require $providerFile;
    } catch (Throwable $e) {
        continue; // fail-closed: one broken provider shouldn't break the bar
    }

    if (!is_array($result) || !array_key_exists('value', $result)) continue;
    $result['id'] = $result['id'] ?? $id;
    $items[]      = $result;
}

usort($items, function ($a, $b) {
    return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
});

// Visible fallback if the kpis/ folder itself is missing or empty. This
// is exactly what silently broke last time: glob() found zero provider
// files (wrong path/deploy), the bar quietly rendered blank, and there
// was no signal anywhere pointing at why. Toggling every KPI off in
// [KPI] is a different, legitimate case — $providerFiles is non-empty
// then, so this deliberately does not fire for that.
if (empty($providerFiles)) {
    $items[] = [
        'id'    => 'kpi-error',
        'label' => 'No KPI providers found',
        'icon'  => 'bi-exclamation-triangle',
        'order' => 0,
        'value' => 'check kpis/ folder',
        'error' => true,
    ];
}

return [
    'kpi' => [
        'path'  => $reqPath,
        'items' => $items,
    ],
];
