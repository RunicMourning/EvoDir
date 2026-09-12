<?php
/**
 * EvoDir Weather Module - Powered by wttr.in
 * Fetches current conditions and daily highs/lows.
 * Called by api.php router — must return array, not echo.
 */

global $conf;
$location = $conf['location'] ?? [];
$lat   = $location['lat']    ?? '38.9717';
$lon   = $location['lon']    ?? '-95.2353';
$units = $location['units'] ?? 'imperial';

$temp_unit = ($units === 'imperial') ? 'F' : 'C';
$wind_unit = ($units === 'imperial') ? 'Miles' : 'Kmph';

$cache_file = __DIR__ . '/cache.json';
$cache_ttl  = 1800; // 30 minutes

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached) return ['weather' => $cached];
}

try {
    // Construct the URL using coordinates and request JSON format
    $url = "https://wttr.in/{$lat},{$lon}?format=j1";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EvoDir-Dashboard/1.0');
    
    // Bypass strict SSL checks common on shared hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $json = curl_exec($ch);
    curl_close($ch);
    
    if (!$json) throw new Exception('fetch failed');

    $data = json_decode($json, true);
    if (!$data || !isset($data['current_condition'][0])) throw new Exception('bad response');

    $current = $data['current_condition'][0];
    $today = $data['weather'][0];

    // Extract location name from nearest_area
    $area_name = $data['nearest_area'][0]['areaName'][0]['value'] ?? null;
    $region_name = $data['nearest_area'][0]['region'][0]['value'] ?? null;
    $city_display = implode(', ', array_filter([$area_name, $region_name])) ?: 'Current Location';

    // Determine values based on units
    $temp_val = $current["temp_{$temp_unit}"];
    $feels_val = $current["FeelsLike{$temp_unit}"];
    $high_val = $today["maxtemp{$temp_unit}"];
    $low_val = $today["mintemp{$temp_unit}"];
    $wind_val = $current["windspeed{$wind_unit}"];
    
    // Map wttr.in condition text to Bootstrap Icons
    $condition = $current['weatherDesc'][0]['value'];
    $icon = match(strtolower($condition)) {
        'clear', 'sunny' => 'bi-sun',
        'partly cloudy' => 'bi-cloud-sun',
        'cloudy', 'overcast' => 'bi-clouds',
        'rain', 'light rain', 'moderate rain', 'heavy rain', 'showers' => 'bi-cloud-rain',
        'snow', 'light snow', 'moderate snow', 'heavy snow' => 'bi-cloud-snow',
        'thunderstorm', 'thundery outbreaks possible' => 'bi-cloud-lightning-rain',
        'fog', 'mist' => 'bi-cloud-fog',
        default => 'bi-cloud-sun' // Default fallback
    };

    $result = [
        'city'       => $city_display, // e.g., "Lawrence, Kansas"
        'temp'       => $temp_val . '°' . $temp_unit,
        'feels_like' => $feels_val . '°' . $temp_unit,
        'high'       => $high_val . '°' . $temp_unit,
        'low'        => $low_val . '°' . $temp_unit,
        'humidity'   => $current['humidity'] . '%',
        'wind'       => $wind_val . ' ' . strtolower($wind_unit) . ' ' . $current['winddir16Point'],
        'condition'  => $condition,
        'icon'       => $icon,
        'updated'    => date('g:i A'),
        'error'      => false,
    ];

    file_put_contents($cache_file, json_encode($result));
    return ['weather' => $result];

} catch (Throwable $e) {
    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached) {
            $cached['stale'] = true;
            return ['weather' => $cached];
        }
    }
    return ['weather' => ['error' => true]];
}