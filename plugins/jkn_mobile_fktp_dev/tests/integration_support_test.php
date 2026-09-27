<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/PatientRegionResolver.php';
require_once dirname(__DIR__).'/BookingIntegrity.php';

use Plugins\JKN_Mobile_FKTP_Dev\PatientRegionResolver;
use Plugins\JKN_Mobile_FKTP_Dev\BookingIntegrity;

$passed = 0;
function integrationCheck($condition, $label) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL '.$label); }
    $passed++;
    echo 'PASS '.$label.PHP_EOL;
}
function integrationRejects(callable $callback, $label) {
    try { $callback(); } catch (DomainException $e) { integrationCheck(true, $label); return; }
    throw new RuntimeException('FAIL '.$label);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE propinsi (kd_prop INTEGER PRIMARY KEY, nm_prop TEXT NOT NULL)');
$pdo->exec('CREATE TABLE kabupaten (kd_kab INTEGER PRIMARY KEY, nm_kab TEXT NOT NULL)');
$pdo->exec('CREATE TABLE kecamatan (kd_kec INTEGER PRIMARY KEY, nm_kec TEXT NOT NULL)');
$pdo->exec('CREATE TABLE kelurahan (kd_kel TEXT PRIMARY KEY, nm_kel TEXT NOT NULL)');
$pdo->exec("INSERT INTO propinsi VALUES (33, 'Jawa Tengah')");
$pdo->exec("INSERT INTO kabupaten VALUES (7, 'Kabupaten UAT')");
$pdo->exec("INSERT INTO kecamatan VALUES (8, 'Kecamatan UAT')");
$pdo->exec("INSERT INTO kelurahan VALUES ('9', 'Kelurahan UAT')");
$regionInput = ['kodeprop'=>'33', 'namaprop'=>'Jawa Tengah', 'kodedati2'=>'3320',
    'namadati2'=>'Kabupaten UAT', 'kodekec'=>'001', 'namakec'=>'Kecamatan UAT',
    'kodekel'=>'0001', 'namakel'=>'Kelurahan UAT'];
$resolved = PatientRegionResolver::resolve($pdo, $regionInput);
integrationCheck($resolved === ['kd_prop'=>33, 'kd_kab'=>7, 'kd_kec'=>8, 'kd_kel'=>'9'],
    'BPJS region code/name maps to exact local master without default fallback');
$missing = $regionInput; $missing['namakel'] = 'Tidak Ada';
integrationRejects(function () use ($pdo, $missing) { PatientRegionResolver::resolve($pdo, $missing); },
    'missing BPJS region is rejected instead of silently using first master row');
$pdo->exec("INSERT INTO kelurahan VALUES ('10', 'Kelurahan UAT')");
integrationRejects(function () use ($pdo, $regionInput) { PatientRegionResolver::resolve($pdo, $regionInput); },
    'ambiguous BPJS region name is rejected');

$pdo->exec('CREATE TABLE reg_periksa (no_rawat TEXT PRIMARY KEY)');
$pdo->exec('CREATE TABLE mlite_jkn_mobile_fktp_dev_booking (no_rawat TEXT PRIMARY KEY, kodepoli TEXT, kodedokter TEXT, jampraktek TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE mlite_jkn_mobile_fktp_dev_log (id INTEGER PRIMARY KEY AUTOINCREMENT, request_id TEXT, action TEXT, endpoint TEXT, method TEXT, no_rawat TEXT, metadata_code INTEGER, http_code INTEGER, outcome TEXT, message TEXT, created_at TEXT)');
$pdo->exec("INSERT INTO reg_periksa VALUES ('2026/09/27/000001')");
$pdo->exec("INSERT INTO mlite_jkn_mobile_fktp_dev_booking VALUES ('2026/09/27/000001','001','101','08:00-12:00','2026-09-27 08:00:00')");
$pdo->exec("INSERT INTO mlite_jkn_mobile_fktp_dev_booking VALUES ('2026/09/27/000002','001','101','08:00-12:00','2026-09-27 08:01:00')");
integrationCheck(BookingIntegrity::cleanupOrphans($pdo) === 1, 'all orphan markers are removed without Rawat Jalan changes');
integrationCheck((int) $pdo->query('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_booking')->fetchColumn() === 1,
    'valid booking marker is preserved');
integrationCheck((int) $pdo->query("SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_log WHERE action='integrity_cleanup'")->fetchColumn() === 1,
    'orphan cleanup is visible in Dev transaction log');
integrationCheck(BookingIntegrity::cleanupOrphans($pdo) === 0, 'orphan reconciliation is idempotent');

$rawatTemplate = file_get_contents(dirname(__DIR__, 2).'/rawat_jalan/view/admin/display.html');
integrationCheck(strpos($rawatTemplate, "rawat_jalan.antrol_button") !== false
    && strpos($rawatTemplate, "rawat_jalan.antrol_scripts") !== false,
    'Rawat Jalan exposes additive Antrol Dev hooks without replacing production actions');
integrationCheck(strpos($rawatTemplate, "onclick=\"addAntrian('") !== false
    && strpos($rawatTemplate, "onclick=\"panggilAntrian('") !== false
    && strpos($rawatTemplate, "onclick=\"batalAntrian('") !== false,
    'existing production Antrol actions remain present');

$siteSource = file_get_contents(dirname(__DIR__).'/Site.php');
$adminSource = file_get_contents(dirname(__DIR__).'/Admin.php');
foreach (['pcare.consumerID', 'pcare.consumerSecret', 'pcare.consumerUserKeyAntrol'] as $settingKey) {
    integrationCheck(strpos($siteSource, "settings->get('".$settingKey."')") !== false
        && strpos($adminSource, "settings->get('".$settingKey."')") !== false,
        'Site and Admin read outbound credential '.$settingKey.' directly');
}
integrationCheck(strpos($siteSource, "setting('pcare.") === false
    && strpos($adminSource, "settings(self::MODULE, 'pcare.") === false,
    'outbound credentials are not looked up through the Dev namespace');
integrationCheck(strpos($adminSource, "Kunjungan lokal belum Batal; antrean/batal Dev tidak boleh dikirim") !== false,
    'manual Antrol Dev cancellation cannot diverge from local visit status');
integrationCheck(strpos($adminSource, 'Panggil JKN Dev') !== false
    && strpos($adminSource, 'data-jkn-dev-antrol="panggil"') !== false,
    'Rawat Jalan renders a direct call button for Dev-origin visits');
$manageTemplate = file_get_contents(dirname(__DIR__).'/view/admin/manage.html');
integrationCheck(strpos($manageTemplate, 'jkn_mobile_fktp_dev[kd_prop]') === false
    && strpos($manageTemplate, 'Mengikuti payload BPJS') !== false,
    'patient region defaults are removed from Admin UI in favor of BPJS payload');

echo $passed.' integration support checks passed; SQLite memory only.'.PHP_EOL;
