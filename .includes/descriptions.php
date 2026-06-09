<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Webroot — works on any server without hardcoding
$webroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// Get and sanitize the requested path
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
if (!$entries) {
    echo json_encode($descriptions);
    exit;
}

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;

    $fullPath = $absPath . '/' . $entry;

    // For directories: look inside for description.txt
    if (is_dir($fullPath)) {
        $descFile = $fullPath . '/description.txt';
        if (file_exists($descFile)) {
            $text = trim(file_get_contents($descFile));
            if ($text !== '') {
                $descriptions[$entry . '/'] = $text;
            }
        }
    }
}

echo json_encode($descriptions);
