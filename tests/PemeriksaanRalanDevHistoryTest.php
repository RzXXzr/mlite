<?php
/** Standalone regression test. Uses only synthetic SQLite memory data, never config.php. */
require_once __DIR__.'/../systems/BaseModule.php';
require_once __DIR__.'/../systems/AdminModule.php';
require_once __DIR__.'/../systems/lib/QueryWrapper.php';
require_once __DIR__.'/../plugins/pemeriksaan_ralan_dev/Admin.php';

use Systems\Lib\QueryWrapper;

function checkHistory($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if ($argc === 1) {
    $responses = [];
    foreach (['first', 'last', 'clamp', 'detail', 'detail_belum', 'empty', 'foreign', 'injection', 'denied_list', 'denied_detail', 'invalid', 'identity_umum', 'identity_bpjs', 'identity_nik', 'identity_missing', 'identity_disabled', 'identity_birth', 'identity_configured', 'identity_denied'] as $scenario) {
        $process = proc_open([PHP_BINARY, __FILE__, $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        checkHistory($exit === 0 && $errors === '', $scenario.' failed: '.$errors);
        $responses[$scenario] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        checkHistory(!$responses[$scenario]['mutated'], $scenario.' wrote to the database');
    }
    $first = $responses['first']['body']['data'];
    $last = $responses['last']['body']['data'];
    checkHistory($first['total'] === 12 && $first['pages'] === 2 && count($first['visits']) === 10, 'Pagination must include all visits');
    checkHistory(count($last['visits']) === 2 && $last['page'] === 2, 'Older visits must remain accessible');
    checkHistory($responses['clamp']['body']['data']['page'] === 2, 'Out-of-range page must be clamped');
    checkHistory(count(array_unique(array_merge(array_column($first['visits'], 'no_rawat'), array_column($last['visits'], 'no_rawat')))) === 12, 'Pages must not overlap');
    checkHistory($first['visits'][0]['no_rawat'] === '2026/09/12/000012', 'History must sort newest first');
    checkHistory(strpos($first['erm_url'], '/RM_A') !== false, 'ERM must use server-derived patient, not posted RM');
    $detail = $responses['detail']['body']['data'];
    checkHistory(count($detail['records']) === 2 && !$detail['records'][0]['can_copy'] && !$detail['records'][1]['can_copy'], 'Terminal active visit must expose history as read-only');
    $detailBelum = $responses['detail_belum']['body']['data'];
    checkHistory(!$detailBelum['records'][0]['can_copy'] && $detailBelum['records'][1]['can_copy'], 'Only ralan history is copyable while active visit is Belum');
    checkHistory($detail['records'][1]['keluhan'] === "Keluhan 'kutip' <b>literal</b>\nBaris dua", 'Clinical text must round-trip intact');
    checkHistory(count($detail['diagnoses']) === 1 && count($detail['procedures']) === 1, 'Clinical context must be returned');
    checkHistory(count($detail['medicines']) === 2, 'Medicines must include all prescriptions');
    checkHistory($responses['empty']['body']['data']['records'] === [], 'Visit without SOAP must be readable');
    checkHistory($responses['foreign']['code'] === 404 && $responses['injection']['code'] === 404, 'Foreign/injected source must be rejected');
    checkHistory($responses['denied_list']['code'] === 403 && $responses['denied_detail']['code'] === 403, 'Both endpoints must enforce active visit CAP');
    checkHistory($responses['invalid']['code'] === 422, 'Invalid active visit must be rejected');
    $identity = $responses['identity_umum']['body']['data'];
    checkHistory($identity['name'] === 'Pasien sintetis' && $identity['no_rkm_medis'] === 'RM_A', 'Identity must derive patient from authorized visit');
    checkHistory($identity['payer'] === 'Umum' && !$identity['is_bpjs'] && $identity['pcare']['url'] === null, 'Non-BPJS visit must not trigger lookup even when card exists');
    checkHistory($identity['birth_date'] === '29-02-2000' && $identity['blood_group'] === 'AB', 'Identity fields must be formatted');
    $diff = (new DateTimeImmutable('2000-02-29'))->diff(new DateTimeImmutable('today'));
    checkHistory($identity['age'] === $diff->y.' th '.$diff->m.' bl '.$diff->d.' hr', 'Age must be calculated from birth date');
    $bpjs = $responses['identity_bpjs']['body']['data'];
    checkHistory($bpjs['is_bpjs'] && strpos($bpjs['pcare']['url'], '/pcare/byjeniskartu/noka/0000000000001') !== false, 'BPJS must reuse PCare card lookup preserving leading zeros');
    checkHistory($responses['identity_nik']['body']['data']['pcare']['type'] === 'nik', 'Missing card must fall back to valid NIK');
    checkHistory($responses['identity_missing']['body']['data']['pcare']['url'] === null, 'Missing identifiers must not trigger lookup');
    checkHistory($responses['identity_disabled']['body']['data']['pcare']['url'] === null, 'Disabled PCare must be handled');
    checkHistory($responses['identity_birth']['body']['data']['age'] === 'Belum tercatat', 'Invalid birth date must not create a misleading age');
    checkHistory($responses['identity_configured']['body']['data']['is_bpjs'], 'Configured payer code must be recognized');
    checkHistory($responses['identity_denied']['code'] === 403, 'Patient identity must enforce CAP');
    echo "PASS: 19 endpoint scenarios; history, copy status gate, identity, BPJS routing, CAP, and no writes.\n";
    exit;
}

define('ADMIN', 'admin');
function url($parts) { return '/'.implode('/', $parts).'?t=synthetic'; }

QueryWrapper::connect('sqlite::memory:');
$pdo = QueryWrapper::pdo();
$schema = [
    'pasien' => 'no_rkm_medis TEXT, nm_pasien TEXT, tgl_lahir TEXT, gol_darah TEXT, no_peserta TEXT, no_ktp TEXT',
    'mlite_settings' => 'module TEXT, field TEXT, value TEXT',
    'reg_periksa' => 'no_rawat TEXT, no_rkm_medis TEXT, kd_poli TEXT, kd_dokter TEXT, kd_pj TEXT, tgl_registrasi TEXT, jam_reg TEXT, status_lanjut TEXT, stts TEXT',
    'poliklinik' => 'kd_poli TEXT, nm_poli TEXT',
    'dokter' => 'kd_dokter TEXT, nm_dokter TEXT',
    'penjab' => 'kd_pj TEXT, png_jawab TEXT',
    'pegawai' => 'nik TEXT, nama TEXT',
    'pemeriksaan_ralan' => 'no_rawat TEXT, tgl_perawatan TEXT, jam_rawat TEXT, nip TEXT, keluhan TEXT, tensi TEXT',
    'pemeriksaan_ranap' => 'no_rawat TEXT, tgl_perawatan TEXT, jam_rawat TEXT, nip TEXT, keluhan TEXT, tensi TEXT',
    'diagnosa_pasien' => 'no_rawat TEXT, kd_penyakit TEXT, prioritas INTEGER',
    'penyakit' => 'kd_penyakit TEXT, nm_penyakit TEXT',
    'prosedur_pasien' => 'no_rawat TEXT, kode TEXT, prioritas INTEGER',
    'icd9' => 'kode TEXT, deskripsi_panjang TEXT',
    'resep_obat' => 'no_rawat TEXT, no_resep TEXT',
    'resep_dokter' => 'no_resep TEXT, kode_brng TEXT, jml INTEGER, aturan_pakai TEXT',
    'databarang' => 'kode_brng TEXT, nama_brng TEXT'
];
foreach ($schema as $table => $columns) { $pdo->exec('CREATE TABLE '.$table.' ('.$columns.')'); }
$insert = $pdo->prepare('INSERT INTO reg_periksa VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 12; $i++) {
    $insert->execute([sprintf('2026/09/%02d/%06d', $i, $i), 'RM_A', $i === 12 ? 'P1' : 'P2', 'D1', 'J1', sprintf('2026-09-%02d', $i), '09:00:00', $i === 1 ? 'Ranap' : 'Ralan', 'Sudah']);
}
$insert->execute(['2026/09/13/000013', 'RM_B', 'P1', 'D1', 'J1', '2026-09-13', '09:00:00', 'Ralan', 'Belum']);
$pdo->exec("INSERT INTO poliklinik VALUES ('P1', 'Poli aktif'), ('P2', 'Poli lain')");
$pdo->exec("INSERT INTO dokter VALUES ('D1', 'Dokter sintetis')");
$pdo->exec("INSERT INTO penjab VALUES ('J1', 'Umum')");
$pdo->exec("INSERT INTO pegawai VALUES ('N1', 'Petugas sintetis')");
$pdo->prepare('INSERT INTO pemeriksaan_ralan VALUES (?, ?, ?, ?, ?, ?)')->execute(['2026/09/01/000001', '2026-09-01', '09:00:00', 'N1', "Keluhan 'kutip' <b>literal</b>\nBaris dua", '120/80']);
$pdo->exec("INSERT INTO pemeriksaan_ranap VALUES ('2026/09/01/000001', '2026-09-02', '10:00:00', 'N1', 'Catatan inap', '110/70')");
$pdo->exec("INSERT INTO penyakit VALUES ('X01', 'Diagnosis sintetis')");
$pdo->exec("INSERT INTO diagnosa_pasien VALUES ('2026/09/01/000001', 'X01', 1)");
$pdo->exec("INSERT INTO icd9 VALUES ('X02', 'Prosedur sintetis')");
$pdo->exec("INSERT INTO prosedur_pasien VALUES ('2026/09/01/000001', 'X02', 1)");
$pdo->exec("INSERT INTO resep_obat VALUES ('2026/09/01/000001', 'RX1'), ('2026/09/01/000001', 'RX2')");
$pdo->exec("INSERT INTO resep_dokter VALUES ('RX1', 'B1', 1, 'Aturan 1'), ('RX2', 'B1', 2, 'Aturan 2')");
$pdo->exec("INSERT INTO databarang VALUES ('B1', 'Obat sintetis')");
$pdo->exec("INSERT INTO pasien VALUES ('RM_A', 'Pasien sintetis', '2000-02-29', 'AB', '0000000000001', '0000000000000001')");
$scenario = $argv[1];
if (strpos($scenario, 'identity_') === 0 && !in_array($scenario, ['identity_umum', 'identity_configured'], true)) {
    $pdo->exec("UPDATE penjab SET png_jawab='BPJS Kesehatan'");
}
if (in_array($scenario, ['identity_nik', 'identity_missing'], true)) { $pdo->exec("UPDATE pasien SET no_peserta=''"); }
if ($scenario === 'identity_missing') { $pdo->exec("UPDATE pasien SET no_ktp=''"); }
if ($scenario === 'identity_birth') { $pdo->exec("UPDATE pasien SET tgl_lahir='0000-00-00'"); }
if ($scenario === 'detail_belum') { $pdo->exec("UPDATE reg_periksa SET stts='Belum' WHERE no_rawat='2026/09/12/000012'"); }

class HistoryTestAdmin extends \Plugins\Pemeriksaan_Ralan_Dev\Admin
{
    public function __construct()
    {
        $this->core = new class {
            public function ActiveModule($module) { return $GLOBALS['scenario'] !== 'identity_disabled'; }
            public function getUserInfo($key, $unused = null, $raw = false) {
                return ['username' => 'N1', 'role' => 'user', 'cap' => 'P1'][$key];
            }
        };
        $this->settings = new class {
            public function get($key) { return $GLOBALS['scenario'] === 'identity_configured' ? 'J1' : ''; }
        };
    }
    protected function db($table = null) { return new QueryWrapper($table); }
}

function historySnapshot($pdo, $schema) {
    $snapshot = [];
    foreach ($schema as $table => $unused) { $snapshot[$table] = $pdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC); }
    return json_encode($snapshot);
}
$before = historySnapshot($pdo, $schema);
http_response_code(200);
ob_start();
register_shutdown_function(function () use ($pdo, $schema, $before) {
    $output = ob_get_clean();
    echo json_encode(['body' => json_decode($output, true), 'code' => http_response_code(), 'mutated' => $before !== historySnapshot($pdo, $schema)]);
});
$_POST = ['no_rawat' => '2026/09/12/000012', 'no_rkm_medis' => 'RM_B', 'source_no_rawat' => '2026/09/01/000001'];
$scenario = $argv[1];
if ($scenario === 'last') { $_POST['page'] = '2'; }
if ($scenario === 'clamp') { $_POST['page'] = '999999'; }
if ($scenario === 'empty') { $_POST['source_no_rawat'] = '2026/09/02/000002'; }
if ($scenario === 'foreign') { $_POST['source_no_rawat'] = '2026/09/13/000013'; }
if ($scenario === 'injection') { $_POST['source_no_rawat'] = "' OR 1=1 --"; }
if (strpos($scenario, 'denied') === 0) { $_POST['no_rawat'] = '2026/09/02/000002'; }
if ($scenario === 'invalid') { $_POST['no_rawat'] = 'invalid'; }
if ($scenario === 'identity_denied') { $_POST['no_rawat'] = '2026/09/02/000002'; }
$admin = new HistoryTestAdmin();
if (strpos($scenario, 'identity_') === 0) {
    $admin->postInformasiPasien();
} elseif (in_array($scenario, ['first', 'last', 'clamp', 'denied_list', 'invalid'], true)) {
    $admin->postRiwayatPasien();
} else {
    $admin->postDetailRiwayat();
}
