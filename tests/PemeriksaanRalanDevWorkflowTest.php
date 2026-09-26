<?php
/** Standalone workflow regression test. Uses synthetic SQLite data only. */
require_once __DIR__.'/../systems/BaseModule.php';
require_once __DIR__.'/../systems/AdminModule.php';
require_once __DIR__.'/../systems/lib/QueryWrapper.php';
require_once __DIR__.'/../plugins/pemeriksaan_ralan_dev/Admin.php';

use Systems\Lib\QueryWrapper;

function checkWorkflow($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if ($argc === 1) {
    $responses = [];
    foreach (['save_new', 'edit_sent', 'save_terminal', 'cancel', 'cancel_stale', 'delete_sent', 'queue_disabled'] as $scenario) {
        $process = proc_open([PHP_BINARY, __FILE__, $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        checkWorkflow($exit === 0 && $errors === '', $scenario.' failed: '.$errors);
        $responses[$scenario] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $saved = $responses['save_new'];
    checkWorkflow($saved['code'] === 200 && $saved['state']['visit_status'] === 'Berkas Dikirim', 'First save must send the file');
    checkWorkflow(count($saved['state']['examinations']) === 1, 'First save must create one examination');
    checkWorkflow(count($saved['state']['mutations']) === 1 && $saved['state']['mutations'][0]['status'] === 'Sudah Dikirim', 'First save must create file mutation');
    checkWorkflow(count($saved['state']['logs']) === 1 && $saved['state']['logs'][0]['to_status'] === 'Berkas Dikirim', 'First save must audit transition');

    $edited = $responses['edit_sent'];
    checkWorkflow($edited['code'] === 200 && $edited['state']['visit_status'] === 'Berkas Dikirim', 'Correction must preserve sent status');
    checkWorkflow($edited['state']['mutations'][0]['dikirim'] === '2026-09-26 08:00:00', 'Correction must not overwrite sent time');
    checkWorkflow($edited['state']['examinations'][0]['keluhan'] === 'Keluhan diperbarui', 'Authorized correction must update record');

    checkWorkflow($responses['save_terminal']['code'] === 409 && count($responses['save_terminal']['state']['examinations']) === 0, 'Terminal status must reject a new SOAP');
    checkWorkflow($responses['cancel']['code'] === 200 && $responses['cancel']['state']['visit_status'] === 'Batal', 'Belum visit must be cancellable');
    checkWorkflow(count($responses['cancel']['state']['examinations']) === 0 && $responses['cancel']['state']['logs'][0]['reason'] === 'Pasien tidak datang', 'Cancel must not create SOAP and must retain reason');
    checkWorkflow($responses['cancel_stale']['code'] === 409 && $responses['cancel_stale']['state']['visit_status'] === 'Berkas Dikirim', 'Stale cancel must not overwrite status');
    checkWorkflow($responses['delete_sent']['code'] === 409 && count($responses['delete_sent']['state']['examinations']) === 1, 'Sent record must not be deleted');
    checkWorkflow($responses['queue_disabled']['code'] === 422 && count($responses['queue_disabled']['state']['queue_operations']) === 0, 'Disabled FKTP module must not create or send a queue operation');
    echo "PASS: 7 workflow scenarios; atomic status, mutation, correction, cancellation, stale-request, and FKTP activation gates.\n";
    exit;
}

define('ADMIN', 'admin');
function url($parts) { return '/'.implode('/', $parts).'?t=synthetic'; }

QueryWrapper::connect('sqlite::memory:');
$pdo = QueryWrapper::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE reg_periksa (
    no_rawat TEXT PRIMARY KEY, no_rkm_medis TEXT, kd_poli TEXT, kd_dokter TEXT, kd_pj TEXT,
    tgl_registrasi TEXT, jam_reg TEXT, no_reg TEXT, status_lanjut TEXT, stts TEXT
)');
$pdo->exec('CREATE TABLE pegawai (nik TEXT PRIMARY KEY, nama TEXT)');
$pdo->exec('CREATE TABLE mlite_settings (module TEXT, field TEXT, value TEXT)');
$pdo->exec('CREATE TABLE pemeriksaan_ralan (
    no_rawat TEXT, tgl_perawatan TEXT, jam_rawat TEXT, nip TEXT,
    tensi TEXT, suhu_tubuh TEXT, nadi TEXT, respirasi TEXT, tinggi TEXT, berat TEXT,
    spo2 TEXT, gcs TEXT, kesadaran TEXT, lingkar_perut TEXT, keluhan TEXT,
    pemeriksaan TEXT, alergi TEXT, rtl TEXT, penilaian TEXT, instruksi TEXT, evaluasi TEXT,
    PRIMARY KEY (no_rawat, tgl_perawatan, jam_rawat)
)');
$pdo->exec('CREATE TABLE mutasi_berkas (
    no_rawat TEXT PRIMARY KEY, status TEXT, dikirim TEXT, diterima TEXT, kembali TEXT,
    tidakada TEXT, ranap TEXT NOT NULL
)');
$pdo->exec("INSERT INTO pegawai VALUES ('N1', 'Paramedis sintetis')");

$scenario = $argv[1];
$status = in_array($scenario, ['edit_sent', 'cancel_stale', 'delete_sent'], true) ? 'Berkas Dikirim'
    : ($scenario === 'save_terminal' ? 'Sudah' : 'Belum');
$insertVisit = $pdo->prepare('INSERT INTO reg_periksa VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insertVisit->execute(['2026/09/26/000001', 'RM1', 'P1', 'D1', 'J1', '2026-09-26', '08:00:00', '001', 'Ralan', $status]);

if (in_array($scenario, ['edit_sent', 'delete_sent'], true)) {
    $pdo->exec("INSERT INTO pemeriksaan_ralan VALUES (
        '2026/09/26/000001','2026-09-26','08:15:00','N1','120/80','36.5','80','20','165','60','98','15','Compos Mentis','80',
        'Keluhan awal','Temuan awal','Tidak Ada','-','-','-','-'
    )");
    $pdo->exec("INSERT INTO mutasi_berkas VALUES ('2026/09/26/000001','Sudah Dikirim','2026-09-26 08:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00')");
}

class WorkflowTestAdmin extends \Plugins\Pemeriksaan_Ralan_Dev\Admin
{
    public function __construct()
    {
        $this->core = new class {
            public function ActiveModule($module) { return false; }
            public function getUserInfo($key, $unused = null, $raw = false) {
                return ['username' => 'N1', 'role' => 'user', 'cap' => 'P1', 'fullname' => 'Paramedis sintetis'][$key] ?? '';
            }
        };
        $this->settings = new class { public function get($key) { return ''; } };
    }
    protected function db($table = null) { return new QueryWrapper($table); }
}

function examinationPayload()
{
    return [
        'no_rawat' => '2026/09/26/000001', 'tensi' => '120/80', 'suhu_tubuh' => '36.5',
        'nadi' => '80', 'respirasi' => '20', 'tinggi' => '165', 'berat' => '60',
        'spo2' => '98', 'gcs' => '15', 'kesadaran' => 'Compos Mentis',
        'lingkar_perut' => '80', 'keluhan' => 'Keluhan baru', 'pemeriksaan' => 'Temuan awal',
        'alergi' => 'Tidak Ada'
    ];
}

http_response_code(200);
ob_start();
register_shutdown_function(function () use ($pdo) {
    $output = ob_get_clean();
    $status = $pdo->query("SELECT stts FROM reg_periksa WHERE no_rawat='2026/09/26/000001'")->fetchColumn();
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $state = [
        'visit_status' => $status,
        'examinations' => $pdo->query('SELECT * FROM pemeriksaan_ralan')->fetchAll(PDO::FETCH_ASSOC),
        'mutations' => $pdo->query('SELECT * FROM mutasi_berkas')->fetchAll(PDO::FETCH_ASSOC),
        'logs' => in_array('mlite_pemeriksaan_ralan_dev_status_log', $tables, true)
            ? $pdo->query('SELECT * FROM mlite_pemeriksaan_ralan_dev_status_log')->fetchAll(PDO::FETCH_ASSOC) : [],
        'queue_operations' => in_array('mlite_pemeriksaan_ralan_dev_antrean', $tables, true)
            ? $pdo->query('SELECT * FROM mlite_pemeriksaan_ralan_dev_antrean')->fetchAll(PDO::FETCH_ASSOC) : []
    ];
    echo json_encode(['body' => json_decode($output, true), 'code' => http_response_code(), 'state' => $state]);
});

$admin = new WorkflowTestAdmin();
if ($scenario === 'queue_disabled') {
    $_POST = ['no_rawat' => '2026/09/26/000001'];
    $admin->postAntreanTambah();
} elseif ($scenario === 'cancel' || $scenario === 'cancel_stale') {
    $_POST = ['no_rawat' => '2026/09/26/000001', 'alasan' => 'Pasien tidak datang'];
    $admin->postBatalPeriksa();
} elseif ($scenario === 'delete_sent') {
    $_POST = ['no_rawat' => '2026/09/26/000001', 'tgl_perawatan' => '2026-09-26', 'jam_rawat' => '08:15:00'];
    $admin->postHapusPemeriksaan();
} else {
    $_POST = examinationPayload();
    if ($scenario === 'edit_sent') {
        $_POST['original_tgl_perawatan'] = '2026-09-26';
        $_POST['original_jam_rawat'] = '08:15:00';
        $_POST['keluhan'] = 'Keluhan diperbarui';
    }
    $admin->postSavePemeriksaan();
}
