<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/ScheduleRepository.php';

use Plugins\JKN_Mobile_FKTP_Dev\ScheduleRepository as Repository;

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
});

$passed = 0;
function repoCheck($condition, $label) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: '.$label); }
    $passed++;
    echo 'PASS '.$label.PHP_EOL;
}
function repoRejected(callable $callback, $label) {
    try { $callback(); } catch (DomainException $e) { repoCheck(true, $label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
function repoRow($date, $doctor, $name, $time, $shift, $quota = 24) {
    list($start, $end) = explode('-', $time);
    return ['id'=>hash('sha256', $date.'|001|'.$doctor.'|'.$time), 'date'=>$date,
        'kodepoli'=>'001', 'namapoli'=>'POLI UMUM', 'kodedokter'=>$doctor,
        'namadokter'=>$name, 'jampraktek'=>$time, 'start'=>$start, 'end'=>$end,
        'quota'=>$quota, 'shift'=>$shift];
}
function repoSnapshot($date, $now) {
    return ['date'=>$date, 'fetched_at'=>$now, 'source'=>'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/',
        'fingerprint'=>'fixture-fingerprint', 'rows'=>[
            repoRow($date, '101', 'Dokter Aktif Pagi', '08:00-12:00', 'pagi'),
            repoRow($date, '102', 'Dokter Tidak Dipilih', '08:00-12:00', 'pagi'),
            repoRow($date, '102', 'Dokter Aktif Sore', '16:30-21:00', 'sore', 27)
        ]];
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE mlite_jkn_mobile_fktp_dev_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_date TEXT NOT NULL, candidate_id TEXT NOT NULL,
    kodepoli TEXT NOT NULL, namapoli TEXT NOT NULL, kd_poli TEXT NOT NULL,
    kodedokter TEXT NOT NULL, namadokter TEXT NOT NULL, kd_dokter TEXT NOT NULL,
    shift_name TEXT NOT NULL, jam_mulai TEXT NOT NULL, jam_selesai TEXT NOT NULL,
    quota INTEGER NOT NULL, is_active INTEGER NOT NULL, source_url TEXT NOT NULL,
    source_fingerprint TEXT NOT NULL, source_fetched_at TEXT NOT NULL, saved_at TEXT NOT NULL,
    UNIQUE(service_date,candidate_id))');
$catalog = [
    'polis'=>[['kd_poli'=>'UMU','nm_poli'=>'Umum','status'=>'1','kd_poli_pcare'=>'001']],
    'doctors'=>[
        ['kd_dokter'=>'D1','nm_dokter'=>'Dokter Lokal Pagi','status'=>'1','kd_dokter_pcare'=>'101'],
        ['kd_dokter'=>'D2','nm_dokter'=>'Dokter Lokal Sore','status'=>'1','kd_dokter_pcare'=>'102']
    ]
];
$now = time();
$date = date('Y-m-d', $now + 86400);
$snapshot = repoSnapshot($date, $now);
$selected = [$snapshot['rows'][0]['id'], $snapshot['rows'][2]['id']];
$summary = Repository::replaceBatch($pdo, [$date=>$snapshot], [$date=>$selected], ['UMU'], $catalog, 'fixture-fingerprint', $now);
repoCheck($summary === ['dates'=>1,'candidates'=>3,'active'=>2], 'all BPJS candidates stored while only checked schedules become active');
repoCheck(count(Repository::rowsForDate($pdo, $date)) === 3 && count(Repository::schedules($pdo, $catalog, $date, '001')) === 2,
    'runtime reads only active persistent table rows');
$storedRows = Repository::rowsForDate($pdo, $date);
$activeIds = [];
$inactiveId = 0;
foreach ($storedRows as $storedRow) {
    if ((int) $storedRow['is_active']) { $activeIds[] = (int) $storedRow['id']; }
    else { $inactiveId = (int) $storedRow['id']; }
}
$updated = Repository::updateCapacities($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')),
    [$activeIds[0]=>'31', $activeIds[1]=>'33'], $now);
$updatedSchedules = Repository::schedules($pdo, $catalog, $date, '001');
repoCheck($updated === 2 && array_column($updatedSchedules, 'kuota') === [31, 33],
    'weekly local editor updates active capacities without BPJS snapshot');
$morningActiveId = 0;
$soreActiveId = 0;
foreach (Repository::rowsForDate($pdo, $date) as $storedRow) {
    if (!(int) $storedRow['is_active']) { continue; }
    if ($storedRow['shift_name'] === 'pagi') { $morningActiveId = (int) $storedRow['id']; }
    if ($storedRow['shift_name'] === 'sore') { $soreActiveId = (int) $storedRow['id']; }
}
$switched = Repository::updateAssignments($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')),
    [$morningActiveId=>$inactiveId], [$morningActiveId=>'29'], $catalog, $now + 1);
$afterSwitch = Repository::schedules($pdo, $catalog, $date, '001');
repoCheck($switched === 1 && $afterSwitch[0]['kd_dokter_pcare'] === '102' && $afterSwitch[0]['kuota'] === 29,
    'weekly editor switches morning doctor to another stored BPJS candidate without refetch');
repoRejected(function () use ($pdo, $date, $inactiveId, $soreActiveId, $catalog, $now) {
    Repository::updateAssignments($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')),
        [$inactiveId=>$soreActiveId], [$inactiveId=>'20'], $catalog, $now + 2);
}, 'doctor replacement cannot cross shift');
Repository::updateAssignments($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')),
    [$inactiveId=>$morningActiveId], [$inactiveId=>'31'], $catalog, $now + 3);
repoCheck(Repository::schedules($pdo, $catalog, $date, '001')[0]['kd_dokter_pcare'] === '101',
    'doctor assignment can be switched back atomically');
repoRejected(function () use ($pdo, $date, $activeIds) {
    Repository::updateCapacities($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')), [$activeIds[0]=>'0']);
}, 'manual capacity outside 1-999 rejected');
repoRejected(function () use ($pdo, $date, $inactiveId) {
    Repository::updateCapacities($pdo, $date, date('Y-m-d', strtotime($date.' +6 days')), [$inactiveId=>'20']);
}, 'inactive candidate capacity cannot be edited through weekly local editor');
$message = Repository::replacementMessage($pdo, $date, '001', '102', '08:00-12:00');
repoCheck(strpos($message, 'Dokter Aktif Pagi') !== false, 'inactive BPJS choice names the active replacement doctor');
repoCheck(count(Repository::schedules($pdo, $catalog, $date, '001')) === 2, 'saved activation has no 24-hour runtime expiry');

$week = [];
$weekSelected = [];
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', $now + ($i + 1) * 86400);
    $week[$day] = repoSnapshot($day, $now);
    $weekSelected[$day] = [$week[$day]['rows'][0]['id'], $week[$day]['rows'][2]['id']];
}
$weekSummary = Repository::replaceBatch($pdo, $week, $weekSelected, ['UMU'], $catalog, 'fixture-fingerprint', $now);
repoCheck($weekSummary['dates'] === 7 && $weekSummary['active'] === 14, 'seven dates save as one batch independent of patient booking-day setting');
repoCheck((int) $pdo->query('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_schedule')->fetchColumn() === 21,
    'seven-day batch persists every reference candidate');

$before = (int) $pdo->query('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_schedule')->fetchColumn();
$badSelections = $weekSelected;
$badSelections[array_keys($week)[6]][] = 'forged-id';
repoRejected(function () use ($pdo, $week, $badSelections, $catalog, $now) {
    Repository::replaceBatch($pdo, $week, $badSelections, ['UMU'], $catalog, 'fixture-fingerprint', $now);
}, 'forged choice rejects entire week');
repoCheck((int) $pdo->query('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_schedule')->fetchColumn() === $before,
    'failed week leaves all previously saved dates unchanged');
repoRejected(function () use ($pdo, $snapshot, $selected, $catalog, $now, $date) {
    Repository::replaceBatch($pdo, [$date=>$snapshot], [$date=>$selected], ['UMU'], $catalog, 'fixture-fingerprint', $now + 1800);
}, 'stale 30-minute form snapshot cannot overwrite persistent checklist');

$closedDate = date('Y-m-d', $now + 8 * 86400);
$closedSnapshot = ['date'=>$closedDate, 'fetched_at'=>$now, 'source'=>'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/',
    'fingerprint'=>'fixture-fingerprint', 'rows'=>[], 'closures'=>[
        ['kodepoli'=>'001','namapoli'=>'POLI UMUM','reason'=>'Jadwal Dokter tidak ditemukan']
    ], 'day_reason'=>''];
$closedSummary = Repository::replaceBatch($pdo, [$closedDate=>$closedSnapshot], [$closedDate=>[]], ['UMU'], $catalog, 'fixture-fingerprint', $now);
repoCheck($closedSummary['candidates'] === 1 && Repository::rowsForDate($pdo, $closedDate)[0]['shift_name'] === 'tutup',
    'BPJS no-schedule response persists as an explicit closed-poli marker');
repoCheck(strpos(Repository::replacementMessage($pdo, $closedDate, '001', '101', '08:00-12:00'), 'hari libur/tanggal merah') !== false,
    'booking on BPJS closed date receives holiday/unavailable explanation');
$wholeClosedDate = date('Y-m-d', $now + 9 * 86400);
$wholeClosed = ['date'=>$wholeClosedDate, 'fetched_at'=>$now, 'source'=>'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/',
    'fingerprint'=>'fixture-fingerprint', 'rows'=>[], 'closures'=>[], 'day_reason'=>'Data tidak ditemukan'];
Repository::replaceBatch($pdo, [$wholeClosedDate=>$wholeClosed], [$wholeClosedDate=>[]], ['UMU'], $catalog, 'fixture-fingerprint', $now);
repoCheck(Repository::rowsForDate($pdo, $wholeClosedDate)[0]['kodepoli'] === '*'
    && strpos(Repository::replacementMessage($pdo, $wholeClosedDate, '001', '101', '08:00-12:00'), 'hari libur/tanggal merah') !== false,
    'whole-day no-poli response closes every requested poli with an explicit marker');

echo $passed.' repository checks passed; SQLite memory only, no live database changes.'.PHP_EOL;
