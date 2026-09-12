<?php
/**
 * EvoDir - Server Dashboard Module
 * A complete, modernized administrative rewrite powered by phpinfo() data backend.
 */

// --- 1. CONFIGURATION & SECURITY GATEWAY ---
$config = [
    'show_env_variables'    => true,
    'show_http_headers'     => true,
    'show_credits'          => false,
    'show_paths'            => true,
    'show_loaded_modules'   => true,
    'hide_secrets'          => true,
    'obfuscate_keywords'    => ['key', 'secret', 'password', 'token', 'pass', 'auth', 'cookie', 'salt', 'cert', 'private']
];

// --- 2. ENGINE PARSER & NORMALIZATION ---
class EvoDirInfoParser {
    public static function captureAndParse(array $config): array {
        // Prevent layout collisions by using explicit constants flags
        $flags = INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES;
        if ($config['show_credits']) $flags |= INFO_CREDITS;
        if ($config['show_env_variables']) $flags |= INFO_ENVIRONMENT | INFO_VARIABLES;

        ob_start();
        @phpinfo($flags);
        $rawHtml = ob_get_clean();

        $data = [
            'dashboard' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'apache_version' => self::getApacheVersion($_SERVER['SERVER_SOFTWARE'] ?? ''),
                'mysql_version' => self::getMysqlVersion(),
                'mysql_online' => self::isMysqlOnline(),
                'sqlite' => self::getSqliteVersion(),
                'os' => PHP_OS,
                'uptime' => self::getSystemUptime(),
                'cpu_load' => self::getSystemCpuLoad(),
                'memory' => self::getSystemMemory(),
                'swap' => self::getSystemSwap(),
                'disk' => self::getDiskMetrics(),
                'disks' => self::getAllDiskMetrics(),
                'process_count' => self::getProcessCount(),
                'opcache' => extension_loaded('Zend OPcache') ? 'Enabled' : 'Disabled',
                'jit' => 'Inactive'
            ],
            'sections' => []
        ];

        // Safely check JIT status string keys if extension matches
        if (extension_loaded('Zend OPcache') && function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (isset($status['jit']['enabled']) && $status['jit']['enabled']) {
                $data['dashboard']['jit'] = 'Active';
            }
        }

        // Fallback gracefully if DOM extension is missing entirely
        if (!class_exists('DOMDocument')) {
            $data['sections']['System Alert'] = [
                ['type' => 'kv', 'key' => 'Missing Dependency', 'value' => 'Please enable the php-dom extension to parse deep configuration arrays.']
            ];
            return $data;
        }

        $dom = new DOMDocument();

        // Suppress errors caused by old unclosed tags in legacy layout tables
        $previousEntityState = libxml_use_internal_errors(true);

        // Strip out broken formatting variables before injection
        $cleanedHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $rawHtml);

        // Force raw entity conversion into localized unicode blocks
        @$dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $cleanedHtml . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previousEntityState);

        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h1 | //h2');

        if ($headings) {
            foreach ($headings as $heading) {
                $sectionName = trim($heading->nodeValue);
                if (empty($sectionName) || $sectionName === 'phpinfo()') continue;

                $rowsData = [];
                $next = $heading->nextSibling;

                while ($next) {
                    if ($next->nodeName === 'h1' || $next->nodeName === 'h2') {
                        break;
                    }
                    if ($next->nodeName === 'table') {
                        $trs = $xpath->query('.//tr', $next);
                        foreach ($trs as $tr) {
                            $tds = $xpath->query('.//td | .//th', $tr);
                            if ($tds->length === 2) {
                                $directive = trim($tds->item(0)->nodeValue);
                                $val = trim($tds->item(1)->nodeValue);
                                if (self::shouldMask($directive, $config)) $val = '********';
                                $rowsData[] = ['type' => 'kv', 'key' => $directive, 'value' => $val];
                            } elseif ($tds->length === 3) {
                                $directive = trim($tds->item(0)->nodeValue);
                                $local = trim($tds->item(1)->nodeValue);
                                $master = trim($tds->item(2)->nodeValue);
                                if (self::shouldMask($directive, $config)) {
                                    $local = '********';
                                    $master = '********';
                                }
                                $rowsData[] = ['type' => 'lm', 'key' => $directive, 'local' => $local, 'master' => $master];
                            } elseif ($tds->length === 1) {
                                $val = trim($tds->item(0)->nodeValue);
                                if (!empty($val)) {
                                    $rowsData[] = ['type' => 'single', 'value' => $val];
                                }
                            }
                        }
                    }
                    $next = $next->nextSibling;
                }

                if (!empty($rowsData) && !self::isSectionFiltered($sectionName, $config)) {
                    $data['sections'][$sectionName] = $rowsData;
                }
            }
        }

        return $data;
    }

    private static function shouldMask(string $key, array $config): bool {
        if (!$config['hide_secrets']) return false;
        $lowerKey = strtolower($key);
        foreach ($config['obfuscate_keywords'] as $keyword) {
            if (strpos($lowerKey, $keyword) !== false) return true;
        }
        return false;
    }

    private static function isSectionFiltered(string $name, array $config): bool {
        $name = strtolower($name);
        if (!$config['show_env_variables'] && ($name === 'environment' || $name === 'php variables')) return true;
        if (!$config['show_http_headers'] && strpos($name, 'http headers') !== false) return true;
        return false;
    }

    private static function getApacheVersion(string $serverSoftware): string {
        if (preg_match('#Apache/([\d.]+)#i', $serverSoftware, $m)) return $m[1];
        return 'N/A';
    }

    private static function getMysqlVersion(): string {
        if (function_exists('mysqli_get_client_info')) {
            $info = @mysqli_get_client_info();
            if ($info) return $info;
        }
        if (function_exists('shell_exec')) {
            $out = @shell_exec('mysql -V 2>/dev/null');
            if ($out && preg_match('#Distrib\s+([\d.]+)#i', $out, $m)) return $m[1];
        }
        return 'N/A';
    }

    private static function isMysqlOnline(): bool {
        $conn = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.3);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    private static function getSqliteVersion(): array {
        if (class_exists('SQLite3')) {
            $v = SQLite3::version();
            return ['version' => $v['versionString'] ?? 'N/A', 'available' => true];
        }
        if (extension_loaded('pdo_sqlite')) {
            try {
                $pdo = new PDO('sqlite::memory:');
                $v = $pdo->query('select sqlite_version()')->fetchColumn();
                return ['version' => $v ?: 'N/A', 'available' => true];
            } catch (Throwable $e) {
                // fall through to unavailable
            }
        }
        return ['version' => 'N/A', 'available' => false];
    }

    private static function getSystemUptime(): string {
        if (stristr(PHP_OS, 'win')) return 'N/A';
        if (@is_readable('/proc/uptime')) {
            $str = @file_get_contents('/proc/uptime');
            if ($str !== false) {
                $num = (float)explode(' ', $str)[0];
                $days = floor($num / 86400);
                $hours = floor(($num % 86400) / 3600);
                return "{$days}d {$hours}h";
            }
        }
        return 'Active';
    }

    private static function getSystemCpuLoad(): array {
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if (is_array($load) && isset($load[0], $load[1], $load[2])) {
                return [round($load[0], 2), round($load[1], 2), round($load[2], 2)];
            }
        }
        return [0.00, 0.00, 0.00];
    }

    private static function getSystemMemory(): array {
        $memData = ['total' => 1, 'used' => 0, 'pct' => 0];
        if (@is_readable('/proc/meminfo')) {
            $lines = @file('/proc/meminfo');
            if (is_array($lines)) {
                $mem = [];
                foreach ($lines as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                        $mem[$matches[1]] = (int)$matches[2];
                    }
                }
                if (isset($mem['MemTotal'], $mem['MemAvailable'])) {
                    $memData['total'] = $mem['MemTotal'] * 1024;
                    $memData['used'] = ($mem['MemTotal'] - $mem['MemAvailable']) * 1024;
                    $memData['pct'] = round(($memData['used'] / $memData['total']) * 100, 1);
                }
            }
        }
        return $memData;
    }

    private static function getSystemSwap(): array {
        $swap = ['total' => 0, 'used' => 0, 'pct' => 0];
        if (@is_readable('/proc/meminfo')) {
            $lines = @file('/proc/meminfo');
            $mem = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $mem[$m[1]] = (int)$m[2];
            }
            if (isset($mem['SwapTotal'], $mem['SwapFree']) && $mem['SwapTotal'] > 0) {
                $swap['total'] = $mem['SwapTotal'] * 1024;
                $swap['used']  = ($mem['SwapTotal'] - $mem['SwapFree']) * 1024;
                $swap['pct']   = round(($swap['used'] / $swap['total']) * 100, 1);
            }
        }
        return $swap;
    }

    private static function getDiskMetrics(): array {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
        $total = @disk_total_space($root) ?: 1;
        $free = @disk_free_space($root) ?: 0;
        $used = $total - $free;
        return [
            'total' => $total,
            'used' => $used,
            'pct' => round(($used / $total) * 100, 1)
        ];
    }

    private static function getAllDiskMetrics(): array {
        // Only report drives mounted under /mnt (e.g. /mnt/storage, /mnt/storage2),
        // not every filesystem on the box.
        $disks = [];
        $mountRoot = '/mnt';

        if (is_dir($mountRoot)) {
            $entries = @scandir($mountRoot) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $mountRoot . '/' . $entry;
                if (!is_dir($path)) continue;

                $total = @disk_total_space($path);
                $free  = @disk_free_space($path);
                if (!$total) continue;
                $used = $total - $free;
                $disks[] = [
                    'label' => $entry,
                    'total' => $total,
                    'used'  => $used,
                    'pct'   => round(($used / $total) * 100, 1)
                ];
            }
        }

        return $disks;
    }

    private static function getProcessCount(): int {
        if (stristr(PHP_OS, 'win')) return 0;
        $pids = @glob('/proc/[0-9]*');
        return $pids ? count($pids) : 0;
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function gaugeColor(float $pct): string {
    if ($pct >= 85) return 'var(--ed-danger)';
    if ($pct >= 65) return '#d97706';
    return 'var(--ed-success)';
}

function renderGauge(string $label, float $pct, string $valueText, string $targetText, ?float $thresholdPct = null): string {
    $pct = max(0, min(100, $pct));
    $color = gaugeColor($pct);

    $deltaHtml = '';
    if ($thresholdPct !== null) {
        $headroom = round($thresholdPct - $pct, 1);
        $deltaColor = $headroom <= 0 ? 'var(--ed-danger)' : ($headroom <= 15 ? '#d97706' : 'var(--ed-success)');
        $sign = $headroom > 0 ? '+' : '';
        $deltaHtml = "<div class=\"ed-kpi-delta\" style=\"color:{$deltaColor};\">{$sign}{$headroom}% headroom</div>";
    }

    return <<<HTML
    <div class="ed-kpi-card">
        <div class="ed-kpi-text">
            <div class="ed-kpi-label">{$label}</div>
            <div class="ed-kpi-bigvalue">{$valueText}</div>
            <div class="ed-kpi-target">{$targetText}</div>
        </div>
        <div class="ed-kpi-gaugecol">
            <div class="ed-gauge-box">
                <div class="ed-ring" style="--pct:{$pct}; --ring-color:{$color};">
                    <div class="ed-ring-hole"></div>
                </div>
            </div>
            {$deltaHtml}
        </div>
    </div>
HTML;
}

function renderStatusRow(string $label, string $value, string $state): string {
    // $state: 'running' (green) | 'offline' (red) | 'disabled' (yellow)
    $safeLabel = htmlspecialchars($label);
    $safeValue = htmlspecialchars($value);
    return <<<HTML
    <div class="ed-status-row">
        <span class="ed-status-dot {$state}">&#9679;</span>
        <span class="ed-status-label">{$safeLabel}</span>
        <span class="ed-status-value">{$safeValue}</span>
    </div>
HTML;
}

function renderConfigRow(array $row): string {
    if ($row['type'] === 'lm') {
        $key = htmlspecialchars($row['key']);
        return '<div class="ed-config-row ed-config-row-lm ed-searchable-row">'
             . '<div class="ed-config-key">' . $key . '</div>'
             . '<div class="ed-config-val"><span class="ed-config-sublabel">Local</span>' . renderValueBadge($row['local']) . '</div>'
             . '<div class="ed-config-val"><span class="ed-config-sublabel">Master</span>' . renderValueBadge($row['master']) . '</div>'
             . '</div>';
    }
    if ($row['type'] === 'kv') {
        $key = htmlspecialchars($row['key']);
        return '<div class="ed-config-row ed-searchable-row">'
             . '<div class="ed-config-key">' . $key . '</div>'
             . '<div class="ed-config-val">' . renderValueBadge($row['value']) . '</div>'
             . '</div>';
    }
    // single
    return '<div class="ed-config-row ed-config-row-single ed-searchable-row">'
         . htmlspecialchars($row['value'])
         . '</div>';
}

function renderValueBadge(string $val): string {
    $clean = trim(strtolower($val));
    if (in_array($clean, ['enabled', 'yes', 'on', 'active'])) return '<span class="ed-badge ed-badge-success">' . htmlspecialchars($val) . '</span>';
    if (in_array($clean, ['disabled', 'no', 'off', 'inactive'])) return '<span class="ed-badge ed-badge-danger">' . htmlspecialchars($val) . '</span>';
    return htmlspecialchars($val);
}

$serverData = EvoDirInfoParser::captureAndParse($config);
$dashboard = $serverData['dashboard'];
$sections = $serverData['sections'];

include_once 'header.html';
?>

<style>
:root {
    --ed-font-family: var(--font-body);
    --ed-font-mono: var(--font-mono);
    --ed-bg: transparent;
    --ed-surface: var(--footer-bg);
    --ed-surface-alt: var(--row-hover-bg);
    --ed-border: var(--border-faint);
    --ed-text: var(--text-primary);
    --ed-text-muted: var(--text-muted);
    --ed-primary: var(--accent);
    --ed-success: #009900;
    --ed-danger: #990000;
    --ed-radius: var(--nav-radius);
}
.evodir-panel { font-family: var(--ed-font-family); background-color: var(--ed-bg); color: var(--ed-text); padding: 2rem; }
.evodir-panel * { box-sizing: border-box; }

/* Navigation / Search */
.ed-nav-container { background-color: var(--ed-surface); border: 1px solid var(--ed-border); border-radius: var(--ed-radius); padding: 1.25rem; margin-bottom: 2.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
.ed-search-input { background-color: var(--ed-bg); border: 1px solid var(--ed-border); padding: 0.6rem 0.75rem; border-radius: var(--ed-radius); font-size: 0.875rem; flex: 1; min-width: 200px; }
.ed-nav-menu { list-style: none; padding: 0; margin: 0; display: flex; gap: 0.5rem; flex-wrap: wrap; }
.ed-nav-link { padding: 0.5rem 0.75rem; color: var(--ed-text-muted); text-decoration: none; font-size: 0.875rem; border-radius: var(--ed-radius); cursor: pointer; background: var(--ed-surface-alt); }
.ed-nav-link:hover, .ed-nav-link.active { background-color: var(--ed-primary); color: var(--ed-bg); }

/* KPI dashboard grid */
.ed-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); grid-auto-rows: auto; gap: 1.25rem; margin-bottom: 2.5rem; }

.ed-info-panel { grid-row: span 2; border: 1px solid var(--ed-border); background-color: var(--bg-sidebar); color: var(--text-muted); border-radius: var(--ed-radius); padding: 1.5rem; }
.ed-info-panel h4 { margin: 0 0 1rem 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; font-weight: 700; }
.ed-status-list { display: flex; flex-direction: column; }
.ed-status-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.15); font-size: 0.85rem; }
.ed-status-row:last-child { border-bottom: none; }
.ed-status-dot { font-size: 1.6rem; line-height: 1; flex-shrink: 0; }
.ed-status-dot.running { color: #22c55e; }
.ed-status-dot.offline { color: #ef4444; }
.ed-status-dot.disabled { color: #eab308; }
.ed-status-dot.neutral { color: #ffffff; }
.ed-status-label { flex: 1; min-width: 0; opacity: 0.75; }
.ed-status-value { flex-shrink: 1; min-width: 0; font-family: var(--ed-font-mono); font-weight: 700; text-align: right; word-break: break-word; overflow-wrap: break-word; }

.ed-kpi-card { background-color: var(--ed-surface); border: 1px solid var(--ed-border); border-radius: var(--ed-radius); padding: 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
.ed-kpi-text { min-width: 0; overflow-wrap: break-word; }
.ed-kpi-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ed-text-muted); font-weight: 700; margin-bottom: 0.4rem; }
.ed-kpi-bigvalue { font-size: 1.6rem; font-weight: 700; color: var(--ed-text); line-height: 1.1; overflow-wrap: break-word; }
.ed-kpi-target { font-size: 0.75rem; color: var(--ed-text-muted); margin-top: 0.35rem; }

.ed-kpi-gaugecol { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
.ed-gauge-box { width: 68px; height: 68px; background-color: var(--ed-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.ed-ring {
  position: relative; width: 48px; height: 48px; border-radius: 50%;
  background: conic-gradient(var(--ring-color) calc(var(--pct) * 3.6deg), var(--ed-border) 0deg);
}
.ed-ring-hole { position: absolute; inset: 7px; border-radius: 50%; background-color: var(--ed-bg); }
.ed-kpi-delta { font-size: 0.75rem; font-weight: 700; margin-top: 0.5rem; white-space: nowrap; }

@media (max-width: 600px) {
  .ed-info-panel { grid-row: span 1; }
}

/* Config sections */
.ed-section-block { background-color: var(--ed-surface); border: 1px solid var(--ed-border); border-radius: var(--ed-radius); margin-bottom: 2rem; overflow: hidden; }
.ed-section-header { background-color: var(--ed-surface-alt); padding: 1rem 1.25rem; border-bottom: 1px solid var(--ed-border); display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.ed-section-title { margin: 0; font-size: 1.1rem; color: var(--ed-text); }
.ed-collapse-toggle { background: none; border: none; color: var(--ed-text-muted); font-size: 1rem; }
.ed-section-block.collapsed .ed-section-body { display: none; }

.ed-config-list { display: flex; flex-direction: column; }
.ed-config-row { display: grid; grid-template-columns: minmax(160px, 32%) 1fr; gap: 1rem; padding: 0.65rem 1.25rem; border-bottom: 1px solid var(--ed-border); align-items: center; font-size: 0.85rem; }
.ed-config-row:last-child { border-bottom: none; }
.ed-config-row:nth-child(even) { background-color: var(--ed-surface-alt); }
.ed-config-row:hover { background-color: var(--ed-surface-alt); }
.ed-config-row-lm { grid-template-columns: minmax(160px, 28%) 1fr 1fr; }
.ed-config-row-single { grid-template-columns: 1fr; font-family: var(--ed-font-mono); color: var(--ed-text-muted); font-size: 0.8rem; }
.ed-config-key { font-family: var(--ed-font-family); color: var(--ed-text-muted); font-weight: 600; font-size: 0.8rem; min-width: 0; overflow-wrap: break-word; }
.ed-config-val { font-family: var(--ed-font-mono); color: var(--ed-text); word-break: break-all; overflow-wrap: break-word; min-width: 0; font-size: 0.85rem; }
.ed-config-sublabel { display: inline-block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ed-text-muted); font-family: var(--ed-font-family); margin-right: 0.4rem; }
.ed-badge { display: inline-flex; padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.ed-badge-success { background-color: rgba(16, 185, 129, 0.15); color: var(--ed-success); }
.ed-badge-danger { background-color: rgba(239, 68, 68, 0.15); color: var(--ed-danger); }

</style>

<div class="evodir-panel">
    <!-- KPIs -->
    <div class="ed-dashboard-grid">
        <div class="ed-info-panel">
            <h4>Server Info</h4>
            <div class="ed-status-list">
                <?php echo renderStatusRow('PHP', $dashboard['php_version'], 'running'); ?>
                <?php echo renderStatusRow('Apache', $dashboard['apache_version'], $dashboard['apache_version'] !== 'N/A' ? 'running' : 'offline'); ?>
                <?php echo renderStatusRow('MySQL', $dashboard['mysql_version'], $dashboard['mysql_online'] ? 'running' : 'offline'); ?>
                <?php echo renderStatusRow('SQLite', $dashboard['sqlite']['version'], $dashboard['sqlite']['available'] ? 'running' : 'offline'); ?>
                <?php echo renderStatusRow('OPcache', $dashboard['opcache'], $dashboard['opcache'] === 'Enabled' ? 'running' : 'disabled'); ?>
                <?php echo renderStatusRow('JIT', $dashboard['jit'], $dashboard['jit'] === 'Active' ? 'running' : 'disabled'); ?>
                <?php echo renderStatusRow('OS', $dashboard['os'], 'neutral'); ?>
                <?php echo renderStatusRow('Uptime', $dashboard['uptime'], 'neutral'); ?>
            </div>
        </div>

        <?php echo renderGauge(
            'Memory',
            $dashboard['memory']['pct'],
            $dashboard['memory']['pct'] . '%',
            'Threshold: 80%',
            80
        ); ?>

        <?php echo renderGauge(
            'Swap',
            $dashboard['swap']['pct'],
            $dashboard['swap']['total'] > 0 ? $dashboard['swap']['pct'] . '%' : 'None',
            $dashboard['swap']['total'] > 0 ? 'Threshold: 50%' : 'No swap configured',
            $dashboard['swap']['total'] > 0 ? 50 : null
        ); ?>

        <?php echo renderGauge(
            'CPU Load (1m)',
            min(100, $dashboard['cpu_load'][0] * 25),
            (string)$dashboard['cpu_load'][0],
            '5m: ' . $dashboard['cpu_load'][1] . ' &middot; 15m: ' . $dashboard['cpu_load'][2],
            null
        ); ?>

        <?php
        // Soft reference ceiling for a homelab box — adjust once you know your normal baseline.
        $processRefMax = 400;
        $processPct = min(100, ($dashboard['process_count'] / $processRefMax) * 100);
        echo renderGauge(
            'Processes',
            $processPct,
            (string)$dashboard['process_count'],
            'Reference: ' . $processRefMax,
            80
        );
        ?>

        <?php foreach ($dashboard['disks'] as $disk): ?>
            <?php echo renderGauge(
                $disk['label'],
                $disk['pct'],
                $disk['pct'] . '%',
                formatBytes($disk['used']) . ' / ' . formatBytes($disk['total']),
                85
            ); ?>
        <?php endforeach; ?>
    </div>

    <!-- Navigation / Search -->
    <div class="ed-nav-container">
        <input type="text" class="ed-search-input" id="edGlobalSearch" placeholder="Filter parameters...">
        <ul class="ed-nav-menu">
            <li><a class="ed-nav-link active" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">Overview</a></li>
            <?php foreach(array_keys($sections) as $secTitle): ?>
                <li><a class="ed-nav-link" href="#sec-<?php echo md5($secTitle); ?>"><?php echo htmlspecialchars($secTitle); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Main Content -->
    <div id="edConfigurationContainer">
        <?php foreach($sections as $secTitle => $rows): $slug = 'sec-' . md5($secTitle); ?>
            <section class="ed-section-block" id="<?php echo $slug; ?>" data-section-name="<?php echo htmlspecialchars(strtolower($secTitle)); ?>">
                <header class="ed-section-header" onclick="toggleBlock('<?php echo $slug; ?>')">
                    <h3 class="ed-section-title"><?php echo htmlspecialchars($secTitle); ?></h3>
                    <button class="ed-collapse-toggle">&#9660;</button>
                </header>
                <div class="ed-section-body">
                    <div class="ed-config-list">
                        <?php foreach($rows as $row): ?>
                            <?php echo renderConfigRow($row); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleBlock(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('collapsed');
    localStorage.setItem('evodir_collapse_' + id, el.classList.contains('collapsed'));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ed-section-block').forEach(block => {
        if (localStorage.getItem('evodir_collapse_' + block.id) === 'true') block.classList.add('collapsed');
    });

    document.getElementById('edGlobalSearch').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('.ed-section-block').forEach(sec => {
            let hasMatch = false;
            sec.querySelectorAll('.ed-searchable-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                if (query === '' || text.includes(query)) {
                    row.style.display = '';
                    hasMatch = true;
                } else {
                    row.style.display = 'none';
                }
            });
            sec.style.display = hasMatch ? '' : 'none';
        });
    });
});
</script>

<?php include_once 'footer.html'; ?>
