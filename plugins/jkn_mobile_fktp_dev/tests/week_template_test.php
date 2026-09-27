<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 3).'/systems/lib/Templates.php';
if (!defined('ADMIN')) { define('ADMIN', 'admin'); }
if (!defined('THEMES')) { define('THEMES', sys_get_temp_dir().'/no-jkn-theme'); }
if (!function_exists('cv')) { function cv($value) { return $value; } }

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
});
$core = (object) ['settings'=>new class { public function get($key) { return ''; } }];
$dir = sys_get_temp_dir().'/jkn-week-template-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
function renderWeek($core, $dir, array $week) {
    $tpl = new Systems\Lib\Templates($core);
    $tmp = new ReflectionProperty($tpl, 'tmp');
    $tmp->setAccessible(true);
    $tmp->setValue($tpl, $dir.'/');
    $tpl->set('nav', []);
    $tpl->set('week', $week);
    return $tpl->draw(dirname(__DIR__).'/view/admin/week.schedule.html');
}
$base = ['csrf'=>'TEST', 'start_date'=>'2026-09-27', 'max_start'=>'2026-12-20',
    'source'=>'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/',
    'results'=>false, 'grid'=>false, 'can_save'=>false, 'rows'=>[], 'days'=>[], 'allowed'=>[],
    'load_url'=>'/admin/jkn_mobile_fktp_dev/activateWeek',
    'save_url'=>'/admin/jkn_mobile_fktp_dev/saveWeekSchedule',
    'cancel_url'=>'/admin/jkn_mobile_fktp_dev/onlineSchedule',
    'week_url'=>'/admin/jkn_mobile_fktp_dev/weekSchedule',
    'polis'=>[['kd_poli'=>'UMU','nm_poli'=>'Umum','blocked'=>false,'selected'=>true]]];
try {
    $form = renderWeek($core, $dir, $base);
    if (strpos($form, 'action="/admin/jkn_mobile_fktp_dev/activateWeek"') === false || strpos($form, '%7B') !== false) {
        throw new RuntimeException('FAIL: form generate week URL');
    }
    echo "PASS form generate week renders safe URL\n";
    @unlink($dir.'/week.schedule.html');
    $grid = $base;
    $grid['grid'] = true;
    $grid['can_save'] = true;
    $grid['allowed'] = ['UMU'];
    $grid['days'] = [['date'=>'2026-09-27','label'=>'MIN','short'=>'27 Sep','skip'=>false,'error'=>'']];
    $grid['rows'] = [['kd_poli'=>'001','nm_poli'=>'POLI UMUM','in_allowed'=>true,'cells'=>[
        ['date'=>'2026-09-27','skip'=>false,'error'=>'','closed_reason'=>'','slots'=>[
            ['id'=>'candidate','input_name'=>'slots[2026-09-27][]','namadokter'=>'Dokter Uji',
                'choice_group'=>'morning-group','shift'=>'pagi','show_shift_header'=>true,
                'jampraktek'=>'08:00-12:00','quota'=>24,'eligible'=>true,'selected'=>false,'blocked'=>false]
        ]],
        ['date'=>'2026-09-28','skip'=>false,'error'=>'','closed_reason'=>'Jadwal Dokter tidak ditemukan','slots'=>[]]
    ]]];
    $html = renderWeek($core, $dir, $grid);
    if (strpos($html, 'name="slots[2026-09-27][]"') === false
        || strpos($html, 'action="/admin/jkn_mobile_fktp_dev/saveWeekSchedule"') === false
        || strpos($html, 'Tutup/libur') === false || strpos($html, 'Jadwal Dokter tidak ditemukan') === false
        || strpos($html, 'data-jkn-shift="pagi"') === false || strpos($html, 'data-choice-group="morning-group"') === false
        || strpos($html, '{?=url') !== false) {
        throw new RuntimeException('FAIL: nested week checklist rendering');
    }
    echo "PASS seven-day checklist grid renders nested slots and safe save URL\n";
} finally {
    if (is_file($dir.'/week.schedule.html')) { unlink($dir.'/week.schedule.html'); }
    rmdir($dir);
}
