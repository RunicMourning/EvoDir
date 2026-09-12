<?php
/**
 * EvoDir Theme Selector Module
 *
 * Scans .includes/themes/*.css and reads each theme's identity from
 * CSS custom properties declared in the file itself:
 *
 *   --theme-id:           "classroom-dark";
 *   --theme-family:       "classroom";   (blank if standalone)
 *   --theme-display-name: "Class Chalkboard";
 *
 * --theme-id is a logical identity, independent of the actual .css
 * filename — it exists purely so the picker can group and pair
 * themes without needing to know each other's filenames. Light/dark
 * mode is inferred from the id's "-light" / "-dark" suffix; ids that
 * don't follow that convention are treated as standalone (no toggle).
 *
 * A theme file with no metadata block still works — it falls back to
 * a title-cased version of its filename, standalone, no toggle.
 */

$themes_dir = __DIR__ . '/../../themes/';
$themes     = [];

function extract_theme_var($css, $var) {
    if (preg_match('/--' . preg_quote($var, '/') . '\s*:\s*"([^"]*)"/', $css, $m)) {
        return $m[1] !== '' ? $m[1] : null;
    }
    return null;
}

function infer_mode($id) {
    if (!$id) return null;
    if (preg_match('/-dark$/', $id))  return 'dark';
    if (preg_match('/-light$/', $id)) return 'light';
    return null;
}

if (is_dir($themes_dir)) {
    foreach (scandir($themes_dir) as $file) {
        if (substr($file, -4) !== '.css') continue;

        $slug = substr($file, 0, -4);
        $css  = @file_get_contents($themes_dir . $file);
        if ($css === false) continue;

        $id = extract_theme_var($css, 'theme-id') ?? $slug;

        $themes[] = [
            'slug'   => $slug,   // actual filename — used to build the <link> href
            'id'     => $id,     // logical identity — used for grouping/pairing only
            'family' => extract_theme_var($css, 'theme-family') ?? '',
            'name'   => extract_theme_var($css, 'theme-display-name') ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
            'mode'   => infer_mode($id),
        ];
    }
}

usort($themes, function($a, $b) {
    $ka = $a['family'] !== '' ? $a['family'] : $a['name'];
    $kb = $b['family'] !== '' ? $b['family'] : $b['name'];
    return strcasecmp($ka, $kb);
});

return ['theme-selector' => $themes];
