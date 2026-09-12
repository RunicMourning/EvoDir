<?php
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// -----------------------------------------------------------------------
// Parse evodir.conf
// -----------------------------------------------------------------------
function parse_conf($file) {
    $result  = ['config' => [], 'nav' => [], 'footer' => [], 'modules' => [], 'location' => [], 'banner' => [], 'livestats' => [], 'kpi' => []];
    $current = null;

    if (!file_exists($file)) return $result;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#') continue;

        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $current = strtoupper($m[1]);
            if (!in_array($current, ['CONFIGURATION', 'FOOTER', 'MODULES', 'LOCATION', 'BANNER', 'LIVESTATS', 'KPI'])) {
                $result['nav'][$current] = [];
            }
            continue;
        }

        if (strpos($line, '|') === false) continue;

        if ($current === 'CONFIGURATION') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $result['config'][strtolower($key)] = $val;

        } elseif ($current === 'FOOTER') {
            [$lbl, $url] = array_map('trim', explode('|', $line, 2));
            $result['footer'][] = ['label' => $lbl, 'url' => $url];

        } elseif ($current === 'MODULES') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $result['modules'][strtolower($key)] = filter_var($val, FILTER_VALIDATE_BOOLEAN);

        } elseif ($current === 'LOCATION') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $result['location'][strtolower($key)] = $val;

        } elseif ($current === 'BANNER') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $lkey = strtolower($key);
            // Multiple 'text' lines: append to array
            if ($lkey === 'text') {
                $result['banner']['text'][] = $val;
            } elseif (in_array($lkey, ['enabled', 'dismissible'], true)) {
                $result['banner'][$lkey] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } else {
                $result['banner'][$lkey] = $val;
            }

        } elseif ($current === 'LIVESTATS') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $result['livestats'][strtolower($key)] = $val;

        } elseif ($current === 'KPI') {
            [$key, $val] = array_map('trim', explode('|', $line, 2));
            $result['kpi'][strtolower($key)] = $val;

        } elseif ($current && substr_count($line, '|') >= 2) {
            [$icon, $lbl, $url] = array_map('trim', explode('|', $line, 3));
            $result['nav'][$current][] = ['icon' => $icon, 'label' => $lbl, 'url' => $url];
        }
    }

    return $result;
}

$conf       = parse_conf(__DIR__ . '/evodir.conf');
$config     = $conf['config'];
$site_name  = !empty($config['site_name']) ? $config['site_name'] : gethostname();
$label      = $config['site_label']  ?? 'Homelab';
$theme      = $config['theme']       ?? 'blueprint';
$show_legal = isset($config['footer_links'])
    ? filter_var($config['footer_links'], FILTER_VALIDATE_BOOLEAN)
    : false;

switch ($action) {

// -----------------------------------------------------------------------
// CONFIG
// -----------------------------------------------------------------------
case 'config':
    $apache_ver = '';
    $php_ver    = phpversion();
    if (!empty($_SERVER['SERVER_SOFTWARE'])) {
        preg_match('/Apache\/([0-9.]+)/', $_SERVER['SERVER_SOFTWARE'], $av);
        $apache_ver = $av[1] ?? '';
    }

    $relative_time = isset($config['relative_time'])
        ? filter_var($config['relative_time'], FILTER_VALIDATE_BOOLEAN)
        : false;

    // Per-module cache-busting: mtime of each enabled module's module.js.
    // header.html appends this as ?v= on the dynamically injected script
    // tag so a redeployed module.js is always fetched fresh instead of
    // silently running whatever the browser had cached.
    $module_versions = [];
    foreach ($conf['modules'] as $name => $enabled) {
        if (!$enabled) continue;
        $js_path = __DIR__ . '/modules/' . $name . '/module.js';
        $module_versions[$name] = file_exists($js_path) ? filemtime($js_path) : 0;
    }

    echo json_encode([
        'siteName'       => strtoupper($site_name),
        'label'          => $label,
        'theme'          => $theme,
        'showLegal'      => $show_legal,
        'nav'            => $conf['nav'],
        'footer'         => $show_legal ? $conf['footer'] : [],
        'apacheVer'      => $apache_ver,
        'phpVer'         => $php_ver,
        'modules'        => $conf['modules'],
        'moduleVersions' => $module_versions,
        'relativeTime'   => $relative_time,
    ]);
    break;

// -----------------------------------------------------------------------
// DESCRIPTIONS
// -----------------------------------------------------------------------
case 'descriptions':
    $webroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $reqPath = isset($_GET['path']) ? $_GET['path'] : '/';
    $reqPath = '/' . trim(str_replace('..', '', $reqPath), '/');
    $absPath = rtrim($webroot . $reqPath, '/');

    if (!is_dir($absPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Directory not found']);
        exit;
    }

    $descriptions = [];
    $entries = @scandir($absPath);
    if ($entries) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $fullPath = $absPath . '/' . $entry;
            if (is_dir($fullPath)) {
                $descFile = $fullPath . '/description.txt';
                if (file_exists($descFile)) {
                    $text = trim(file_get_contents($descFile));
                    if ($text !== '') {
                        // Extract version tag — matches Ver followed by digits and dots
                        // e.g. "Directory 1 - Ver03.34" -> version: "03.34", desc: "Directory 1"
                        $version = null;
                        if (preg_match('/\bVer([\d][\d.]*)/i', $text, $vm)) {
                            $version = $vm[1];
                            // Remove the version part and clean up separators
                            $text = trim(preg_replace('/\s*[-–]?\s*Ver[\d][\d.]*/i', '', $text));
                        }
                        $descriptions[$entry . '/'] = [
                            'description' => $text,
                            'version'     => $version,
                        ];
                    }
                }
            }
        }
    }

    echo json_encode($descriptions);
    break;

// -----------------------------------------------------------------------
// DYNAMIC MODULE ROUTER & DEFAULT FALLBACK
// -----------------------------------------------------------------------
default:
    // Sanitize the action input to prevent directory traversal attacks
    $clean_action = basename($action);
    $module_path  = __DIR__ . '/modules/' . $clean_action . '/module.php';

    if (!empty($action) && $action === $clean_action && file_exists($module_path)) {
        // For banner, pass config to module via global
        if ($clean_action === 'banner') {
            $conf_banner = isset($conf['banner']) ? $conf['banner'] : [];
            $GLOBALS['conf_banner'] = $conf_banner;
        }
        
        $result = require $module_path;
        // Flatten: each module returns ['modulename' => [...]]
        echo json_encode($result[$clean_action] ?? $result);
        break;
    }

    // If no module or core route was matched, throw a 400 error with a dynamic checklist
    http_response_code(400);
    
    $available_actions = ['config', 'descriptions'];
    $modules_dir = __DIR__ . '/modules/';
    if (is_dir($modules_dir)) {
        foreach (scandir($modules_dir) as $dir) {
            if ($dir !== '.' && $dir !== '..' && file_exists($modules_dir . $dir . '/module.php')) {
                $available_actions[] = $dir;
            }
        }
    }

    echo json_encode([
        'error' => 'Unknown action. Valid: ' . implode(', ', $available_actions)
    ]);
    break;
}