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
$dir = sys_get_temp_dir().'/jkn-local-week-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $tpl = new Systems\Lib\Templates($core);
    $tmp = new ReflectionProperty($tpl, 'tmp');
    $tmp->setAccessible(true);
    $tmp->setValue($tpl, $dir.'/');
    $tpl->set('nav', []);
    $tpl->set('local_week', [
        'csrf'=>'TEST', 'start_date'=>'2026-09-27', 'end_date'=>'2026-10-03',
        'latest_fetched'=>'2026-09-27 10:00:00', 'latest_saved'=>'2026-09-27 10:05:00',
        'previous_url'=>'/previous', 'today_url'=>'/today', 'next_url'=>'/next',
        'view_url'=>'/admin/jkn_mobile_fktp_dev/onlineSchedule',
        'save_url'=>'/admin/jkn_mobile_fktp_dev/saveLocalWeekCapacity', 'generate_url'=>'/generate',
        'days'=>[['date'=>'2026-09-27','label'=>'MIN','short'=>'27 Sep']],
        'rows'=>[['kodepoli'=>'001','namapoli'=>'POLI UMUM','kd_poli'=>'UMU','shift'=>'pagi','shift_label'=>'Pagi',
            'cells'=>[['date'=>'2026-09-27','has_data'=>true,'closed_reason'=>'','has_schedule'=>true,
                'doctor'=>'Dokter Pagi','doctor_code'=>'101','practice'=>'08:00-12:00','quota'=>24,
                'quota_name'=>'quota[7]','assignment_name'=>'assignment[7]',
                'doctor_options'=>[
                    ['id'=>7,'doctor'=>'Dokter Pagi','doctor_code'=>'101','practice'=>'08:00-12:00','quota'=>24,'selected'=>true],
                    ['id'=>8,'doctor'=>'Dokter Pengganti','doctor_code'=>'102','practice'=>'08:00-12:00','quota'=>20,'selected'=>false]
                ]]]]]
    ]);
    $html = $tpl->draw(dirname(__DIR__).'/view/admin/local.week.html');
    if (strpos($html, 'name="quota[7]"') === false || strpos($html, 'name="assignment[7]"') === false
        || strpos($html, 'Dokter Pagi') === false || strpos($html, 'Dokter Pengganti') === false
        || strpos($html, '>Pagi<') === false || strpos($html, 'saveLocalWeekCapacity') === false
        || strpos($html, 'tanpa akses API BPJS') === false || strpos($html, 'Simpan Dokter &amp; Kapasitas') === false) {
        throw new RuntimeException('FAIL local weekly doctor/capacity grid rendering');
    }
    echo "PASS local weekly grid switches stored BPJS doctor and capacity without BPJS fetch\n";
} finally {
    if (is_file($dir.'/local.week.html')) { unlink($dir.'/local.week.html'); }
    rmdir($dir);
}
