<?php
/**
 * EvoDir Banner Module
 * Displays configurable disclaimer/banner text before directory listing.
 * Reads from [BANNER] section in evodir.conf via $GLOBALS['conf_banner']
 */

// Sanitize HTML: allow specific tags only
function sanitize_banner_html(string $html): string {
    $allowed = '<a><strong><em><u><b><i><br><p>';
    $clean = strip_tags($html, $allowed);
    // Remove dangerous attributes (onclick, onerror, etc)
    $clean = preg_replace('/\s+on\w+\s*=/i', ' ', $clean);
    return $clean;
}

// Read config from global (set by api.php)
$banner_config = $GLOBALS['conf_banner'] ?? [];
$banner_enabled = (bool)($banner_config['enabled'] ?? false);
$banner_title = trim($banner_config['title'] ?? '');
$banner_lines = [];
$banner_dismissible = (bool)($banner_config['dismissible'] ?? false);

// Collect all 'text' lines (multiple text entries)
if (isset($banner_config['text']) && is_array($banner_config['text'])) {
    foreach ($banner_config['text'] as $text) {
        if (!empty($text)) {
            $banner_lines[] = sanitize_banner_html($text);
        }
    }
}

return [
    'banner' => [
        'enabled' => $banner_enabled && (!empty($banner_title) || !empty($banner_lines)),
        'title' => $banner_title,
        'dismissible' => $banner_dismissible,
        'lines' => $banner_lines,
    ]
];
