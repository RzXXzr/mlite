#!/usr/bin/php
<?php
/**
 * cron_satusehat.php — CLI Batch Sender for Satu Sehat FHIR Resources
 *
 * Sends historical (hutang) patient visit data to Satu Sehat platform
 * by calling existing HTTP endpoints on the MLite application.
 *
 * Usage:
 *   php cron_satusehat.php [options]
 *
 * Options:
 *   --tanggal-dari=YYYY-MM-DD    Start date (default: resume from progress file)
 *   --tanggal-sampai=YYYY-MM-DD  End date (default: yesterday)
 *   --jam-mulai=23                Allowed start hour (default: 23, i.e. 11 PM)
 *   --jam-berhenti=5              Stop hour (default: 5, i.e. 5 AM)
 *   --no-time-fence               Ignore time fence (for manual/testing runs)
 *   --delay=500                   Delay between visits in ms (default: 500)
 *   --max-errors=20               Stop after N consecutive errors (default: 20)
 *   --dry-run                     Only show plan, don't send anything
 *   --force                       Resend even if already sent
 *   --limit=N                     Process max N visits then stop (for testing)
 *   --verbose                     Show detailed output per resource
 *   --base-url=URL                Override base URL (default: from config)
 *
 * Crontab example (run every night at 23:00):
 *   0 23 * * * /usr/bin/php /home/slemp/wwwroot/mlite/cron_satusehat.php >> /home/slemp/wwwroot/mlite/tmp/cron_satusehat.log 2>&1
 *
 * @author  MLite Cron Generator
 * @version 1.0.0
 */

// ============================================================================
//  Bootstrap
// ============================================================================

// Ensure CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

define('BASE_DIR', __DIR__);
date_default_timezone_set('Asia/Jakarta');

// Parse command line arguments
$options = parseCliArgs($argv);

$PROGRESS_FILE = BASE_DIR . '/tmp/cron_satusehat_progress.json';
$LOCK_FILE     = BASE_DIR . '/tmp/cron_satusehat.lock';
$SETTINGS_FILE = BASE_DIR . '/tmp/cron_satusehat_settings.json';
$LOG_PREFIX    = '[SatuSehat-Cron]';

// ============================================================================
//  Configuration — merge: saved settings (JSON) < CLI args (override)
// ============================================================================

// Load saved settings from admin panel
$savedSettings = [];
if (file_exists($SETTINGS_FILE)) {
    $savedSettings = json_decode(file_get_contents($SETTINGS_FILE), true) ?: [];
}

$config = [
    'jam_mulai'     => (int)($options['jam-mulai'] ?? $savedSettings['jam_mulai'] ?? 23),
    'jam_berhenti'  => (int)($options['jam-berhenti'] ?? $savedSettings['jam_berhenti'] ?? 5),
    'no_time_fence' => isset($options['no-time-fence']),
    'delay_ms'      => (int)($options['delay'] ?? $savedSettings['delay_ms'] ?? 500),
    'max_errors'    => (int)($options['max-errors'] ?? $savedSettings['max_errors'] ?? 20),
    'dry_run'       => isset($options['dry-run']),
    'force'         => isset($options['force']),
    'limit'         => isset($options['limit']) ? (int)$options['limit'] : null,
    'verbose'       => isset($options['verbose']),
    'base_url'      => $options['base-url'] ?? null,
];

// ============================================================================
//  Lock file — prevent duplicate instances
// ============================================================================

if (file_exists($LOCK_FILE)) {
    $lockPid = (int)file_get_contents($LOCK_FILE);
    if ($lockPid > 0 && file_exists("/proc/$lockPid")) {
        logMsg("ERROR: Another instance is already running (PID $lockPid). Exiting.");
        exit(1);
    }
    logMsg("WARNING: Stale lock file found (PID $lockPid not running). Removing.");
    unlink($LOCK_FILE);
}

@mkdir(BASE_DIR . '/tmp', 0755, true);
file_put_contents($LOCK_FILE, getmypid());

// Register cleanup
register_shutdown_function(function () use ($LOCK_FILE) {
    @unlink($LOCK_FILE);
});

// Handle signals for graceful shutdown
$GLOBALS['_cron_stop'] = false;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () {
        logMsg("Received SIGTERM. Finishing current visit then stopping...");
        $GLOBALS['_cron_stop'] = true;
    });
    pcntl_signal(SIGINT, function () {
        logMsg("Received SIGINT. Finishing current visit then stopping...");
        $GLOBALS['_cron_stop'] = true;
    });
}

// ============================================================================
//  Database connection
// ============================================================================

require_once BASE_DIR . '/config.php';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DBHOST, DBPORT, DBNAME);
    $pdo = new PDO($dsn, DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
    logMsg("Database connected: " . DBNAME);
} catch (PDOException $e) {
    logMsg("FATAL: Database connection failed: " . $e->getMessage());
    exit(1);
}

// ============================================================================
//  Determine base URL
// ============================================================================

$baseUrl = $config['base_url'] ?? 'http://127.0.0.1';

// Derive the Host header from WEBAPPS_URL (the real domain name)
$parsed = parse_url(WEBAPPS_URL);
$hostHeader = $parsed['host'] ?? 'localhost';
logMsg("Base URL: $baseUrl (Host: $hostHeader)");

// Test connectivity
$testUrl = $baseUrl . '/satu-sehat/batch-plan?no_rawat=__test__';
$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Host: ' . $hostHeader],
    CURLOPT_SSL_VERIFYPEER => false,
]);
$testResp = curl_exec($ch);
$testCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($testCode === 0) {
    logMsg("FATAL: Cannot connect to $baseUrl. Is the web server running?");
    exit(1);
}
logMsg("Web server reachable (HTTP $testCode)");

// ============================================================================
//  Determine date range
// ============================================================================

$progress = loadProgress($PROGRESS_FILE);

$tanggalDari = $options['tanggal-dari'] ?? null;
$tanggalSampai = $options['tanggal-sampai'] ?? null;

if (!$tanggalDari && !empty($savedSettings['tanggal_dari'])) {
    $tanggalDari = $savedSettings['tanggal_dari'];
    logMsg("Using tanggal_dari from saved settings: $tanggalDari");
}

if (!$tanggalDari) {
    if ($progress && !empty($progress['last_completed_date'])) {
        // Resume: next day after last completed
        $tanggalDari = date('Y-m-d', strtotime($progress['last_completed_date'] . ' +1 day'));
        logMsg("Resuming from progress file: $tanggalDari");
    } else {
        $tanggalDari = '2024-01-01';
        logMsg("No progress file. Starting from $tanggalDari");
    }
}

if (!$tanggalSampai) {
    $tanggalSampai = date('Y-m-d', strtotime('-1 day'));
}

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDari) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSampai)) {
    logMsg("FATAL: Invalid date format. Use YYYY-MM-DD.");
    exit(1);
}

if ($tanggalDari > $tanggalSampai) {
    logMsg("All dates up to $tanggalSampai have been processed. Nothing to do.");
    exit(0);
}

logMsg("Date range: $tanggalDari to $tanggalSampai");
logMsg("Config: delay={$config['delay_ms']}ms, max_errors={$config['max_errors']}, " .
       "force=" . ($config['force'] ? 'yes' : 'no') . ", " .
       "dry_run=" . ($config['dry_run'] ? 'yes' : 'no') . ", " .
       "limit=" . ($config['limit'] ?? 'none') . ", " .
       "time_fence=" . ($config['no_time_fence'] ? 'disabled' : "{$config['jam_mulai']}:00-{$config['jam_berhenti']}:00"));

// ============================================================================
//  Time fence check
// ============================================================================

if (!$config['no_time_fence']) {
    $currentHour = (int)date('G');
    if (!isWithinTimeFence($currentHour, $config['jam_mulai'], $config['jam_berhenti'])) {
        logMsg("Outside allowed time window ({$config['jam_mulai']}:00 - {$config['jam_berhenti']}:00). Current hour: $currentHour. Exiting.");
        exit(0);
    }
}

// ============================================================================
//  Main processing loop — iterate dates
// ============================================================================

$stats = [
    'dates_processed'  => 0,
    'visits_total'     => 0,
    'visits_sent'      => 0,
    'visits_success'   => 0,
    'visits_partial'   => 0,
    'visits_failed'    => 0,
    'visits_skipped'   => 0,
    'consecutive_errors' => 0,
    'start_time'       => time(),
];

$currentDate = $tanggalDari;

while ($currentDate <= $tanggalSampai) {
    // Check signals
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    if ($GLOBALS['_cron_stop']) {
        logMsg("Graceful shutdown requested. Stopping.");
        break;
    }

    // Time fence check (stop 10 min before boundary)
    if (!$config['no_time_fence'] && !isWithinTimeFence((int)date('G'), $config['jam_mulai'], $config['jam_berhenti'], 10)) {
        logMsg("Approaching time boundary ({$config['jam_berhenti']}:00). Stopping for tonight.");
        break;
    }

    // Max consecutive error check
    if ($stats['consecutive_errors'] >= $config['max_errors']) {
        logMsg("STOPPING: {$stats['consecutive_errors']} consecutive errors reached max ({$config['max_errors']}). Possible server/API issue.");
        break;
    }

    // Limit check
    if ($config['limit'] !== null && $stats['visits_sent'] >= $config['limit']) {
        logMsg("Limit reached ({$config['limit']} visits). Stopping.");
        break;
    }

    // Get visits for this date
    $visits = getVisitsForDate($pdo, $currentDate);

    if (empty($visits)) {
        logMsg("[$currentDate] No visits found. Skipping.");
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        saveProgress($PROGRESS_FILE, $currentDate, $progress, $stats);
        $stats['dates_processed']++;
        continue;
    }

    logMsg("[$currentDate] Found " . count($visits) . " visits.");

    $dateSuccess = 0;
    $dateFail = 0;
    $dateSkip = 0;

    foreach ($visits as $visit) {
        // Signal check
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($GLOBALS['_cron_stop']) break;

        // Time fence
        if (!$config['no_time_fence'] && !isWithinTimeFence((int)date('G'), $config['jam_mulai'], $config['jam_berhenti'], 10)) {
            logMsg("Time boundary approaching. Stopping mid-date.");
            break 2; // exit both loops
        }

        // Limit check
        if ($config['limit'] !== null && $stats['visits_sent'] >= $config['limit']) {
            break 2;
        }

        $no_rawat = $visit['no_rawat'];
        $status_lanjut = $visit['status_lanjut'];
        $stats['visits_total']++;

        // Check if already fully sent (quick check — has encounter + condition at minimum)
        if (!$config['force']) {
            $existing = getExistingResponse($pdo, $no_rawat);
            if ($existing && !empty($existing['id_encounter'])) {
                // Already has encounter — check if we should skip
                // We'll let the batch-process endpoint handle the detailed check
            }
        }

        if ($config['dry_run']) {
            // In dry run, fetch plan only
            $plan = fetchPlan($baseUrl, $no_rawat, $status_lanjut, $config['force']);
            if ($plan !== null) {
                $toSend = 0;
                $alreadySent = 0;
                $noData = 0;
                foreach ($plan as $key => $info) {
                    if (is_array($info) && isset($info['action'])) {
                        if ($info['action'] === 'send') $toSend++;
                        elseif ($info['action'] === 'skip_exists') $alreadySent++;
                        elseif ($info['action'] === 'skip_nodata') $noData++;
                    }
                }
                $logLine = "  [DRY] $no_rawat: to_send=$toSend, already=$alreadySent, no_data=$noData";
                if ($config['verbose']) {
                    foreach ($plan as $key => $info) {
                        if (is_array($info) && isset($info['action'])) {
                            $logLine .= "\n    $key: {$info['action']}";
                        }
                    }
                }
                logMsg($logLine);
            } else {
                logMsg("  [DRY] $no_rawat: Failed to fetch plan");
            }
            $stats['visits_skipped']++;
            continue;
        }

        // Send via batch-process endpoint
        $result = processSingleVisit($baseUrl, $no_rawat, $status_lanjut, $config['force']);

        if ($result === null) {
            logMsg("  FAIL $no_rawat: HTTP error (no response)");
            $stats['visits_failed']++;
            $dateFail++;
            $stats['consecutive_errors']++;
            usleep($config['delay_ms'] * 1000);
            continue;
        }

        $status = $result['status'] ?? 'unknown';
        $sent = $result['sent'] ?? 0;
        $success = $result['success'] ?? 0;
        $failed = $result['failed'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        $logLine = "  $no_rawat [$status] sent=$sent ok=$success fail=$failed skip=$skipped";

        if ($config['verbose'] && !empty($result['resources'])) {
            foreach ($result['resources'] as $key => $res) {
                $action = $res['action'] ?? '?';
                $id = $res['id'] ?? '-';
                $err = $res['error'] ?? '';
                $logLine .= "\n    $key: $action" . ($id !== '-' && $id !== null ? " id=$id" : "") . ($err ? " err=$err" : "");
            }
        }

        logMsg($logLine);

        $stats['visits_sent']++;

        if ($status === 'success') {
            $stats['visits_success']++;
            $dateSuccess++;
            $stats['consecutive_errors'] = 0;
        } elseif ($status === 'partial') {
            $stats['visits_partial']++;
            $dateSuccess++;
            $stats['consecutive_errors'] = 0;
        } elseif ($status === 'skipped') {
            $stats['visits_skipped']++;
            $dateSkip++;
            $stats['consecutive_errors'] = 0;
        } else {
            $stats['visits_failed']++;
            $dateFail++;
            $stats['consecutive_errors']++;
        }

        // Delay between visits
        usleep($config['delay_ms'] * 1000);
    }

    logMsg("[$currentDate] Done: success=$dateSuccess, failed=$dateFail, skipped=$dateSkip");

    // Save progress after each date
    $stats['dates_processed']++;
    saveProgress($PROGRESS_FILE, $currentDate, $progress, $stats);

    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// ============================================================================
//  Summary
// ============================================================================

$elapsed = time() - $stats['start_time'];
$elapsedStr = gmdate('H:i:s', $elapsed);

logMsg("=== SESSION COMPLETE ===");
logMsg("Duration:       $elapsedStr");
logMsg("Dates processed: {$stats['dates_processed']}");
logMsg("Visits total:    {$stats['visits_total']}");
logMsg("Visits sent:     {$stats['visits_sent']}");
logMsg("  - Success:     {$stats['visits_success']}");
logMsg("  - Partial:     {$stats['visits_partial']}");
logMsg("  - Failed:      {$stats['visits_failed']}");
logMsg("  - Skipped:     {$stats['visits_skipped']}");
logMsg("========================");

exit(0);

// ============================================================================
//  Helper Functions
// ============================================================================

/**
 * Parse CLI arguments into associative array.
 * Supports --key=value and --flag formats.
 */
function parseCliArgs(array $argv): array
{
    $options = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (str_starts_with($arg, '--')) {
            $arg = substr($arg, 2);
            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', $arg, 2);
                $options[$key] = $value;
            } else {
                $options[$arg] = true;
            }
        }
    }
    return $options;
}

/**
 * Log a message with timestamp.
 */
function logMsg(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] $msg\n";
}

/**
 * Check if current hour is within the allowed time fence.
 * Handles overnight windows (e.g., 23:00 - 05:00).
 *
 * @param int $hour       Current hour (0-23)
 * @param int $startHour  Start hour (e.g., 23)
 * @param int $stopHour   Stop hour (e.g., 5)
 * @param int $marginMin  Minutes before stopHour to stop early
 */
function isWithinTimeFence(int $hour, int $startHour, int $stopHour, int $marginMin = 0): bool
{
    // If margin > 0, also check minutes
    if ($marginMin > 0) {
        $currentMin = (int)date('i');
        $currentTotal = $hour * 60 + $currentMin;
        $stopTotal = $stopHour * 60 - $marginMin;

        if ($startHour > $stopHour) {
            // Overnight: e.g., 23:00 to 04:50
            return ($currentTotal >= $startHour * 60) || ($currentTotal < $stopTotal);
        } else {
            return ($currentTotal >= $startHour * 60) && ($currentTotal < $stopTotal);
        }
    }

    if ($startHour > $stopHour) {
        // Overnight window: hour >= start OR hour < stop
        return ($hour >= $startHour) || ($hour < $stopHour);
    } else {
        return ($hour >= $startHour) && ($hour < $stopHour);
    }
}

/**
 * Get all non-cancelled visits for a specific date.
 */
function getVisitsForDate(PDO $pdo, string $date): array
{
    $stmt = $pdo->prepare("
        SELECT rp.no_rawat, rp.no_rkm_medis, rp.kd_dokter, rp.kd_poli,
               rp.status_lanjut, rp.tgl_registrasi
        FROM reg_periksa rp
        WHERE rp.stts != 'Batal'
          AND rp.tgl_registrasi = ?
        ORDER BY rp.no_rawat ASC
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

/**
 * Get existing Satu Sehat response for a no_rawat.
 */
function getExistingResponse(PDO $pdo, string $no_rawat): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM mlite_satu_sehat_response WHERE no_rawat = ?");
    $stmt->execute([$no_rawat]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Fetch the plan for a single no_rawat from the batch-plan endpoint.
 */
function fetchPlan(string $baseUrl, string $no_rawat, string $status_lanjut, bool $force): ?array
{
    global $hostHeader;
    $url = $baseUrl . '/satu-sehat/batch-plan?' . http_build_query([
        'no_rawat'      => $no_rawat,
        'status_lanjut' => $status_lanjut,
        'force'         => $force ? '1' : '',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Host: ' . $hostHeader],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 500) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Process a single visit via the batch-process HTTP endpoint.
 * Returns the JSON result or null on failure.
 */
function processSingleVisit(string $baseUrl, string $no_rawat, string $status_lanjut, bool $force): ?array
{
    global $hostHeader;
    $url = $baseUrl . '/satu-sehat/batch-process?' . http_build_query([
        'no_rawat'      => $no_rawat,
        'status_lanjut' => $status_lanjut,
        'force'         => $force ? '1' : '',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300, // 5 min timeout for full processing
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Host: ' . $hostHeader],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logMsg("    curl error: $curlError");
        return null;
    }

    if ($httpCode >= 500) {
        logMsg("    HTTP $httpCode: " . mb_substr($response, 0, 200));
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        logMsg("    Non-JSON response (HTTP $httpCode): " . mb_substr($response, 0, 200));
        return null;
    }

    return $data;
}

/**
 * Load progress from JSON file.
 */
function loadProgress(string $file): ?array
{
    if (!file_exists($file)) {
        return null;
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/**
 * Save progress to JSON file.
 */
function saveProgress(string $file, string $lastDate, ?array $prev, array $stats): void
{
    $data = [
        'last_completed_date' => $lastDate,
        'start_date'          => $prev['start_date'] ?? $lastDate,
        'end_date'            => date('Y-m-d', strtotime('-1 day')),
        'last_run'            => date('Y-m-d H:i:s'),
        'total_processed'     => ($prev['total_processed'] ?? 0) + $stats['visits_sent'],
        'total_success'       => ($prev['total_success'] ?? 0) + $stats['visits_success'],
        'total_failed'        => ($prev['total_failed'] ?? 0) + $stats['visits_failed'],
        'sessions'            => ($prev['sessions'] ?? 0) + 1,
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
