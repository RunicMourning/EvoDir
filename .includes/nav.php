<?php
// nav.php - Parse nav.txt and output JSON on demand
header('Content-Type: application/json');

$nav_file = __DIR__ . '/nav.txt';

if (!file_exists($nav_file)) {
    echo json_encode(['error' => 'nav.txt not found']);
    exit;
}

$sections = [];
$current_section = null;
$lines = file($nav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    // Check for section header
    if (preg_match('/^\[(.+)\]$/', $line, $m)) {
        $section_name = $m[1];
        $sections[$section_name] = [];
        $current_section = $section_name;
    } elseif ($current_section && preg_match('/^([^|]+)\|([^|]+)\|(.+)$/', $line, $m)) {
        // Parse: icon|label|url
        $sections[$current_section][] = [
            'icon' => trim($m[1]),
            'label' => trim($m[2]),
            'url' => trim($m[3]),
        ];
    }
}

echo json_encode($sections);
?>
