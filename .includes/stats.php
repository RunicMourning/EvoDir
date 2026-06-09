<?php
header('Content-Type: application/json');

function fmt_kb(int $kb): string {
    if ($kb >= 1048576) return round($kb / 1048576, 1) . ' GB';
    if ($kb >= 1024)    return round($kb / 1024) . ' MB';
    return $kb . ' KB';
}

function fmt_bytes(int $bytes): string {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 1) . ' TB';
    if ($bytes >= 1073741824)    return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)       return round($bytes / 1048576) . ' MB';
    return round($bytes / 1024) . ' KB';
}

// --- Memory & Swap ---
$ram_used_kb = $ram_total_kb = $swap_used_kb = $swap_total_kb = null;
$ram_pct = $swap_pct = null;
try {
    $meminfo = shell_exec('cat /proc/meminfo 2>/dev/null');
    if (!$meminfo) throw new Exception('unreadable');
    preg_match('/MemTotal:\s+(\d+)/',     $meminfo, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $ma);
    preg_match('/SwapTotal:\s+(\d+)/',    $meminfo, $st);
    preg_match('/SwapFree:\s+(\d+)/',     $meminfo, $sf);
    if (!isset($mt[1])) throw new Exception('no MemTotal');
    $ram_total_kb  = (int)$mt[1];
    $ram_used_kb   = $ram_total_kb - (isset($ma[1]) ? (int)$ma[1] : 0);
    $ram_pct       = $ram_total_kb > 0 ? round(($ram_used_kb / $ram_total_kb) * 100) : 0;
    $swap_total_kb = isset($st[1]) ? (int)$st[1] : 0;
    $swap_used_kb  = (isset($st[1]) && isset($sf[1])) ? ((int)$st[1] - (int)$sf[1]) : 0;
    $swap_pct      = $swap_total_kb > 0 ? round(($swap_used_kb / $swap_total_kb) * 100) : 0;
} catch (Throwable $e) {}

// --- Load Average ---
$cpu_load = $cpu_load_5 = $cpu_load_15 = null;
try {
    $load = sys_getloadavg();
    if ($load === false) throw new Exception('failed');
    $cpu_load    = round($load[0], 2);
    $cpu_load_5  = round($load[1], 2);
    $cpu_load_15 = round($load[2], 2);
} catch (Throwable $e) {}

// --- Uptime ---
$uptime_str = null;
try {
    $raw = shell_exec('cat /proc/uptime 2>/dev/null');
    if (!$raw) throw new Exception('unreadable');
    $secs       = (int)explode(' ', trim($raw))[0];
    $days       = floor($secs / 86400);
    $hours      = floor(($secs % 86400) / 3600);
    $minutes    = floor(($secs % 3600) / 60);
    $uptime_str = ($days  > 0 ? $days  . 'd ' : '')
                . ($hours > 0 ? $hours . 'h ' : '')
                . $minutes . 'm';
} catch (Throwable $e) {}

// --- Processes ---
$procs = null;
try {
    $raw = shell_exec('ps aux 2>/dev/null | wc -l');
    if ($raw === null) throw new Exception('failed');
    $procs = max(0, (int)trim($raw) - 1); // subtract header line only
} catch (Throwable $e) {}

// --- Disks ---
$disks = [];
try {
    $mnt_dirs = glob('/mnt/*', GLOB_ONLYDIR) ?: [];
    foreach ($mnt_dirs as $mount) {
        $df = @disk_free_space($mount);
        $dt = @disk_total_space($mount);
        if ($df !== false && $dt !== false && $dt > 0) {
            $used    = $dt - $df;
            $disks[] = [
                'name'  => basename($mount),
                'used'  => fmt_bytes((int)$used),
                'total' => fmt_bytes((int)$dt),
                'pct'   => round(($used / $dt) * 100),
                'error' => false,
            ];
        } else {
            $disks[] = ['name' => basename($mount), 'error' => true];
        }
    }
} catch (Throwable $e) {}

// --- Docker & Plex ---
$docker_info = null;
try {
    $docker_ps = @shell_exec('docker ps --format "{{.Names}}" 2>/dev/null');
    if ($docker_ps !== null && trim($docker_ps) !== '') {
        $docker_lines = array_filter(explode("\n", trim($docker_ps)));
        $docker_info  = ['running' => count($docker_lines), 'plex_status' => null, 'plex_memory' => null];
        $plex_running = @shell_exec('docker inspect plex --format="{{.State.Running}}" 2>/dev/null');
        if (trim((string)$plex_running) === 'true') {
            $plex_stats  = @shell_exec('docker stats plex --no-stream --format "{{.MemUsage}}" 2>/dev/null');
            $plex_memory = ($plex_stats !== null && trim($plex_stats) !== '')
                ? trim(explode('/', trim($plex_stats))[0])
                : 'N/A';
            $docker_info['plex_status'] = 'running';
            $docker_info['plex_memory'] = $plex_memory;
        } else {
            $docker_info['plex_status'] = 'stopped';
            $docker_info['plex_memory'] = null;
        }
    }
} catch (Throwable $e) {}

// --- Hostname ---
$hostname = null;
try {
    $h = gethostname();
    $hostname = $h !== false ? $h : null;
} catch (Throwable $e) {}

echo json_encode([
    'hostname'    => $hostname,
    'ram_used'    => $ram_used_kb  !== null ? fmt_kb($ram_used_kb)  : null,
    'ram_total'   => $ram_total_kb !== null ? fmt_kb($ram_total_kb) : null,
    'ram_pct'     => $ram_pct,
    'swap_used'   => $swap_used_kb  !== null ? fmt_kb($swap_used_kb)  : null,
    'swap_total'  => $swap_total_kb !== null ? fmt_kb($swap_total_kb) : null,
    'swap_pct'    => $swap_pct,
    'cpu_load'    => $cpu_load,
    'cpu_load_5'  => $cpu_load_5,
    'cpu_load_15' => $cpu_load_15,
    'processes'   => $procs,
    'uptime'      => $uptime_str,
    'disks'       => $disks,
    'docker'      => $docker_info,
    'time'        => gmdate('Y-m-d H:i:s'),
]);
