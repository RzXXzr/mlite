<?php
// Standalone: no application bootstrap, connection credentials, or live database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/SchedulePolicy.php';
require_once dirname(__DIR__, 3).'/systems/lib/Templates.php';

use Plugins\JKN_Mobile_FKTP_Dev\SchedulePolicy as Policy;

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
});
$passed = 0;
function check($condition, $label) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: '.$label); }
    $passed++;
    echo 'PASS '.$label.PHP_EOL;
}
function rejected($callback, $label) {
    try { $callback(); } catch (DomainException $e) { check(true, $label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
function rule($changes = []) {
    return array_merge(['kd_poli' => 'UMU', 'kd_dokter' => 'D1', 'day' => 'SENIN', 'date' => '',
        'shift' => 'pagi', 'start' => '08:00', 'end' => '12:00', 'quota' => '24'], $changes);
}
$catalog = [
    'polis' => [
        ['kd_poli' => 'UMU', 'nm_poli' => 'Umum', 'status' => '1', 'kd_poli_pcare' => '001'],
        ['kd_poli' => 'RUJ', 'nm_poli' => 'Rujukan', 'status' => '1', 'kd_poli_pcare' => '001'],
        ['kd_poli' => 'RUJGG', 'nm_poli' => 'RUJ Gigi', 'status' => '1', 'kd_poli_pcare' => '002'],
        ['kd_poli' => 'USG', 'nm_poli' => 'USG', 'status' => '1', 'kd_poli_pcare' => '001'],
        ['kd_poli' => 'GIGI', 'nm_poli' => 'Gigi', 'status' => '1', 'kd_poli_pcare' => '002']
    ],
    'doctors' => [
        ['kd_dokter' => 'D1', 'nm_dokter' => 'Dokter Pagi', 'status' => '1', 'kd_dokter_pcare' => '101'],
        ['kd_dokter' => 'D2', 'nm_dokter' => 'Dokter Sore', 'status' => '1', 'kd_dokter_pcare' => '102']
    ]
];
$morning = rule();
$evening = rule(['kd_dokter' => 'D2', 'shift' => 'sore', 'start' => '16:30', 'end' => '21:00', 'quota' => '27']);
$config = Policy::validate(['UMU'], [$morning, $evening], $catalog);
$slots = Policy::schedules($config, $catalog, '2026-09-28', '001');
check(count($slots) === 2 && $slots[0]['kd_poli'] === 'UMU' && $slots[1]['kd_dokter'] === 'D2', 'explicit UMU selection, correct doctors morning/evening');
check($slots[0]['kd_dokter_pcare'] === '101' && $slots[1]['jam_mulai'] === '16:30:00' && $slots[1]['kuota'] === 27, 'exact remote identity, time, quota');
check(Policy::selectSlot($slots, '101', '08:00-12:00')['kd_dokter'] === 'D1', 'API slot resolver accepts exact chosen doctor');
check(Policy::selectSlot($slots, '102', '08:00-12:00') === null, 'API slot resolver rejects evening doctor in morning');
check(Policy::selectSlot($slots, '101', '16:30-21:00') === null, 'API slot resolver rejects morning doctor in evening');
check(Policy::selectSlot($slots, '102', '17:00-21:00') === null, 'API slot resolver rejects near-overlapping BPJS time');
check(Policy::selectSlot($slots, '999', '08:00-12:00') === null, 'API slot resolver never substitutes unknown doctor');
check(Policy::schedules(Policy::emptyConfig(), $catalog, '2026-09-28') === [], 'unconfigured policy fails closed');
check(Policy::schedules($config, $catalog, '2026-09-27') === [], 'Sunday without shift closed');
check(Policy::schedules($config, $catalog, '2026-02-30') === [], 'invalid date closed');
check(Policy::schedules($config, $catalog, '2026-09-28', '002') === [], 'non-selected remote poli closed');
check(Policy::schedules($config, $catalog, '2026-09-28', 'RUJ') === [], 'local referral code never resolves as remote poli');
foreach (['RUJ', 'RUJGG'] as $code) { rejected(function () use ($code, $catalog) { Policy::validate([$code], [], $catalog); }, $code.' forbidden'); }
rejected(function () use ($catalog) { Policy::validate(['UMU', 'USG'], [], $catalog); }, 'two local destinations for 001 forbidden');
rejected(function () use ($catalog) { Policy::validate(['UNKNOWN'], [], $catalog); }, 'unknown local poli forbidden');
rejected(function () use ($catalog) { Policy::validate([], [rule()], $catalog); }, 'rule for disabled poli forbidden');
rejected(function () use ($catalog, $morning) { Policy::validate(['UMU'], [$morning, rule(['kd_dokter' => 'D2'])], $catalog); }, 'two doctors in same shift forbidden');
rejected(function () use ($catalog, $morning) { Policy::validate(['UMU'], [$morning, rule(['kd_dokter' => 'D2', 'shift' => 'sore', 'start' => '11:30'])], $catalog); }, 'overlapping shifts forbidden');
rejected(function () use ($catalog, $morning) { Policy::validate(['UMU', 'GIGI'], [$morning, rule(['kd_poli' => 'GIGI'])], $catalog); }, 'same doctor in overlapping polis forbidden');
$adjacent = Policy::validate(['UMU'], [$morning, rule(['shift' => 'sore', 'start' => '12:00', 'end' => '13:00'])], $catalog);
check(count(Policy::schedules($adjacent, $catalog, '2026-09-28')) === 2, 'adjacent shifts accepted');
foreach ([['start'=>'25:00'], ['end'=>'07:00'], ['quota'=>'0'], ['quota'=>'1000'], ['quota'=>'2.5'], ['kd_dokter'=>'UNKNOWN'], ['day'=>'MINGGU'], ['date'=>'2026-02-30'], ['shift'=>'malam']] as $change) {
    rejected(function () use ($catalog, $change) { Policy::validate(['UMU'], [rule($change)], $catalog); }, 'bad input rejected: '.json_encode($change));
}
$closed = rule(['date'=>'2026-09-28', 'shift'=>'tutup']);
$configClosed = Policy::validate(['UMU'], [$morning, $evening, $closed], $catalog);
check(Policy::schedules($configClosed, $catalog, '2026-09-28') === [], 'dated closure replaces both weekly shifts');
check(count(Policy::schedules($configClosed, $catalog, '2026-10-05')) === 2, 'dated closure does not affect next week');
$override = rule(['date'=>'2026-09-28', 'kd_dokter'=>'D2']);
$configOverride = Policy::validate(['UMU'], [$morning, $evening, $override], $catalog);
$overrideSlots = Policy::schedules($configOverride, $catalog, '2026-09-28');
check(count($overrideSlots) === 1 && $overrideSlots[0]['kd_dokter'] === 'D2', 'dated doctor replacement replaces entire poli day');
rejected(function () use ($catalog, $closed, $override) { Policy::validate(['UMU'], [$closed, $override], $catalog); }, 'closure plus open shift rejected');
rejected(function () use ($catalog) { Policy::validate(['UMU'], [rule(['shift'=>'tutup'])], $catalog); }, 'weekly closure marker forbidden');
rejected(function () use ($catalog, $override) { Policy::validate(['UMU', 'GIGI'], [rule(['kd_poli'=>'GIGI','kd_dokter'=>'D2']), $override], $catalog); }, 'date override checked against other poli weekly doctor');
rejected(function () use ($catalog) { Policy::validate(['UMU'], array_fill(0, 81, rule()), $catalog); }, 'oversized form rejected');
$changed = $catalog;
$changed['doctors'][0]['status'] = '0';
check(count(Policy::schedules($config, $changed, '2026-09-28')) === 1, 'deactivated doctor stops new acceptance');
$changed['doctors'][0]['status'] = '1';
$changed['doctors'][0]['kd_dokter_pcare'] = '999';
check(count(Policy::schedules($config, $changed, '2026-09-28')) === 1, 'changed doctor mapping cannot silently reassign');
$changed = $catalog;
$changed['doctors'][] = ['kd_dokter'=>'D3','nm_dokter'=>'Duplicate','status'=>'1','kd_dokter_pcare'=>'101'];
check(count(Policy::schedules($config, $changed, '2026-09-28')) === 1, 'new remote doctor ambiguity fails closed at runtime');
rejected(function () use ($changed) { Policy::validate(['UMU'], [rule()], $changed); }, 'ambiguous remote doctor rejected on save');
$changed = $catalog;
$changed['polis'][0]['kd_poli_pcare'] = '003';
check(Policy::schedules($config, $changed, '2026-09-28') === [], 'changed poli mapping stops acceptance until configured again');

// Temporary fixtures only; no runtime config is ever created or overwritten.
$testDir = sys_get_temp_dir().'/jkn-policy-test-'.bin2hex(random_bytes(8));
mkdir($testDir, 0700);
$testPath = $testDir.'/policy.php';
try {
    check(Policy::read($testPath)['revision'] === 0, 'missing file default');
    Policy::save($config, 0, $testPath);
    check(Policy::read($testPath)['revision'] === 1 && Policy::read($testPath)['rules'] === $config['rules'], 'atomic file save/read roundtrip');
    check((fileperms($testPath) & 0777) === 0600, 'config file private permissions');
    try { Policy::save($configClosed, 0, $testPath); throw new LogicException('lost update allowed'); }
    catch (RuntimeException $e) { check(Policy::read($testPath)['revision'] === 1, 'stale revision cannot overwrite config'); }
    Policy::save($configClosed, 1, $testPath);
    check(Policy::read($testPath)['revision'] === 2, 'fresh revision saves');

    // Test-only damaged files created in the dedicated temporary directory.
    file_put_contents($testDir.'/invalid.php', Policy::HEADER.'{broken');
    try { Policy::read($testDir.'/invalid.php'); throw new LogicException('corrupt JSON accepted'); }
    catch (RuntimeException $e) { check(true, 'corrupt config fails closed'); }
    $invalid = $config;
    $invalid['rules'][0]['start'] = '25:00';
    file_put_contents($testDir.'/invalid.php', Policy::HEADER.json_encode($invalid));
    try { Policy::read($testDir.'/invalid.php'); throw new LogicException('invalid clock accepted'); }
    catch (RuntimeException $e) { check(true, 'invalid saved clock fails closed'); }

    // Render real mLITE template using only in-memory data and isolated compile cache.
    if (!defined('ADMIN')) { define('ADMIN', 'admin'); }
    if (!defined('THEMES')) { define('THEMES', $testDir.'/no-themes'); }
    function cv($value) { return $value; }
    function url($parts = []) { return '/'.implode('/', $parts); }
    $core = (object) ['settings' => new class { public function get($key) { return ''; } }];
    $tpl = new Systems\Lib\Templates($core);
    $tmp = new ReflectionProperty($tpl, 'tmp');
    $tmp->setAccessible(true);
    $tmp->setValue($tpl, $testDir.'/');
    $display = $catalog['polis'];
    foreach ($display as &$p) { $p['blocked'] = Policy::referral($p['kd_poli']); $p['selected'] = $p['kd_poli'] === 'UMU'; } unset($p);
    $tpl->set('nav', [['active'=>true, 'url'=>'/admin/test', 'icon'=>'calendar', 'label'=>'Poli & Jadwal Online']]);
    $legacyPreview = $slots;
    foreach ($legacyPreview as &$legacyRow) {
        $legacyRow['jampraktek'] = substr($legacyRow['jam_mulai'], 0, 5).'-'.substr($legacyRow['jam_selesai'], 0, 5);
    }
    unset($legacyRow);
    $tpl->set('schedule', ['error'=>'', 'revision'=>1, 'updated_at'=>'2026-09-27', 'csrf'=>'TEST_ONLY', 'date'=>'2026-09-28',
        'save_url'=>'/admin/jkn_mobile_fktp_dev/saveOnlineSchedule', 'preview_url'=>'/admin/jkn_mobile_fktp_dev/onlineSchedule',
        'polis'=>$display, 'doctors'=>$catalog['doctors'], 'days'=>Policy::DAYS, 'preview'=>$legacyPreview, 'reference'=>[]]);
    $tpl->set('schedule_rules_json', json_encode($config['rules'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    $html = $tpl->draw(dirname(__DIR__).'/view/admin/online.schedule.html');
    check(strpos($html, '08:00-12:00') !== false && strpos($html, '16:30-21:00') !== false, 'real template compiles and renders exact practice times');
    check(preg_match('/value="RUJ"[^>]*disabled/', $html) === 1, 'referral checkbox disabled in real UI');
    check(strpos($html, 'name="form_complete"') !== false, 'truncation sentinel present');
} finally {
    foreach ([$testPath, $testPath.'.lock.php', $testDir.'/online.schedule.html', $testDir.'/invalid.php'] as $fixture) { if (is_file($fixture)) { unlink($fixture); } }
    rmdir($testDir);
}
echo $passed.' checks passed; no live database or runtime policy touched.'.PHP_EOL;
