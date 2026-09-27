<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Reuse isolated fixtures and run the previous scheduling regression suite first.
require __DIR__.'/schedule_policy_test.php';
require_once dirname(__DIR__, 3).'/vendor/autoload.php';
require_once dirname(__DIR__).'/BpjsScheduleReference.php';
require_once dirname(__DIR__).'/ReferenceActivation.php';

use Plugins\JKN_Mobile_FKTP_Dev\BpjsScheduleReference as Client;
use Plugins\JKN_Mobile_FKTP_Dev\ReferenceActivation as Activation;
use Plugins\JKN_Mobile_FKTP_Dev\SchedulePolicy as Policy;

function failure($callback, $label) {
    try { $callback(); } catch (RuntimeException $e) { check(true, $label); return; }
    throw new LogicException('FAIL '.$label);
}
$credentials = ['antrol_url'=>'https://apijkn.bpjs-kesehatan.go.id/antreanfktp/',
    'antrol_consumer_id'=>'TEST_CONS', 'antrol_consumer_secret'=>'TEST_SECRET', 'antrol_user_key'=>'TEST_ANTROL'];
$doctors = [
    ['kodedokter'=>101, 'namadokter'=>'Doctor A', 'jampraktek'=>'08:00-12:00', 'kapasitas'=>24],
    ['kodedokter'=>102, 'namadokter'=>'Doctor B', 'jampraktek'=>'16:30-21:00', 'kapasitas'=>27],
    ['kodedokter'=>102, 'namadokter'=>'Doctor B', 'jampraktek'=>'08:00-12:00', 'kapasitas'=>24],
    ['kodedokter'=>999, 'namadokter'=>'Unmapped', 'jampraktek'=>'17:00-21:00', 'kapasitas'=>24],
    ['kodedokter'=>101, 'namadokter'=>'Zero quota', 'jampraktek'=>'07:00-08:00', 'kapasitas'=>0]
];
$requests = [];
$transport = function ($url, $headers) use (&$requests, $doctors) {
    $requests[] = ['url'=>$url, 'headers'=>$headers];
    $list = strpos($url, '/ref/poli/') !== false ? [['kodepoli'=>'001', 'namapoli'=>'POLI UMUM']] : $doctors;
    return ['http'=>200, 'body'=>json_encode(['metadata'=>['code'=>1], 'response'=>['list'=>$list]])];
};
$client = new Client($credentials, $transport);
$date = date('Y-m-d');
$snapshot = $client->fetch($date);
check(count($requests) === 2 && strpos($requests[1]['url'], '/ref/dokter/kodepoli/001/tanggal/'.$date) !== false, 'FKTP poli then dokter reference paths');
check(count($snapshot['rows']) === 5 && $snapshot['rows'][0]['kodepoli'] === '001', 'reference preserves remote codes and all candidate slots');
check($snapshot['rows'][1]['shift'] === 'sore' && $snapshot['rows'][0]['quota'] === 24, 'shift label and capacity derived from reference');
check(strpos(json_encode($snapshot), 'TEST_SECRET') === false && strpos(json_encode($snapshot), 'TEST_ANTROL') === false, 'snapshot contains no credentials');
check(in_array('user_key: TEST_ANTROL', $requests[0]['headers'], true), 'uses supplied PCare Antrol user key');
$outbound = new Client($credentials, function ($url, $headers, $body) {
    return ['http'=>200, 'body'=>json_encode(['metadata'=>['code'=>200, 'message'=>'OK'], 'response'=>null])];
});
$outboundResult = $outbound->postJson('antrean/add', '{"nomorkartu":"0000000000001"}');
check($outboundResult['metadata_code'] === '200' && $outboundResult['message'] === 'OK',
    'outbound transaction only returns after BPJS metadata success');
failure(function () use ($credentials) {
    (new Client($credentials, function () {
        return ['http'=>200, 'body'=>json_encode(['metadata'=>['code'=>201, 'message'=>'Kuota penuh']])];
    }))->postJson('antrean/add', '{}');
}, 'outbound metadata rejection throws');
failure(function () use ($credentials) {
    (new Client($credentials, function () { return ['http'=>503, 'body'=>'']; }))->postJson('antrean/batal', '{}');
}, 'outbound HTTP rejection throws');
failure(function () use ($credentials) {
    (new Client($credentials, function () { return ['http'=>200, 'body'=>'not-json']; }))->postJson('antrean/panggil', '{}');
}, 'outbound malformed JSON throws');
failure(function () use ($outbound) { $outbound->postJson('ref/poli/tanggal/2026-01-01', '{}'); },
    'outbound POST endpoint allowlist enforced');
$dev = $credentials; $dev['antrol_url'] = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/';
check((new Client($dev, $transport))->source() === 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/', 'dev URL passed directly');
$new = $credentials; $new['antrol_url'] = 'https://new-apijkn.bpjs-kesehatan.go.id/antreanfktp/';
check((new Client($new, $transport))->source() === 'https://new-apijkn.bpjs-kesehatan.go.id/antreanfktp/', 'new-apijkn host accepted');
$bad = $credentials; $bad['antrol_url'] = 'https://attacker.invalid/antreanfktp/';
failure(function () use ($bad) { new Client($bad); }, 'arbitrary destination rejected');
$bad = $credentials; $bad['antrol_user_key'] = '';
failure(function () use ($bad) { new Client($bad); }, 'empty antrol user key rejected');
$bad = $credentials; $bad['antrol_consumer_id'] = "foo\r\nHost: attacker";
failure(function () use ($bad) { new Client($bad); }, 'header injection rejected');

// AES + LZ response uses the exact timestamp sent in the signed request.
$encrypted = new Client($credentials, function ($url, $headers) use ($credentials, $doctors) {
    $stamp = '';
    foreach ($headers as $header) { if (strpos($header, 'X-timestamp: ') === 0) { $stamp = substr($header, 13); } }
    $expected = 'X-signature: '.base64_encode(hash_hmac('sha256', $credentials['antrol_consumer_id'].'&'.$stamp, $credentials['antrol_consumer_secret'], true));
    if (!in_array($expected, $headers, true)) { throw new LogicException('incorrect signature'); }
    $list = strpos($url, '/ref/poli/') !== false ? [['kodepoli'=>'001', 'namapoli'=>'UMUM']] : $doctors;
    $compressed = LZCompressor\LZString::compressToEncodedURIComponent(json_encode(['list'=>$list]));
    $key = hex2bin(hash('sha256', $credentials['antrol_consumer_id'].$credentials['antrol_consumer_secret'].$stamp));
    return ['http'=>200, 'body'=>json_encode(['metadata'=>['code'=>1], 'response'=>base64_encode(openssl_encrypt($compressed, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($key, 0, 16)))])];
});
check(count($encrypted->fetch($date)['rows']) === 5, 'encrypted AES/LZ reference decoded with signature timestamp');
foreach ([['http'=>500,'body'=>'oops'], ['http'=>200,'body'=>'not json'],
    ['http'=>200,'body'=>'{"metadata":{"code":1},"response":{"wrong":[]}}']] as $badResponse) {
    failure(function () use ($credentials, $badResponse, $date) { (new Client($credentials, function () use ($badResponse) { return $badResponse; }))->fetch($date); }, 'upstream malformed/error rejected '.substr($badResponse['body'],0,30));
}
$empty = new Client($credentials, function () { return ['http'=>200,'body'=>'{"metadata":{"code":1},"response":{"list":[]}}']; });
check($empty->fetch($date)['rows'] === [], 'successful empty reference distinguished from errors');
$success200 = new Client($credentials, function () { return ['http'=>200,'body'=>'{"metaData":{"code":200},"response":{"list":[]}}']; });
check($success200->fetch($date)['rows'] === [], 'metadata 200 and metaData spelling supported');
$noContent = new Client($credentials, function () {
    return ['http'=>200,'body'=>'{"metadata":{"code":201,"message":"No Content"},"response":null}'];
});
$noContentSnapshot = $noContent->fetch($date);
check($noContentSnapshot['rows'] === [] && $noContentSnapshot['closures'] === []
    && $noContentSnapshot['day_reason'] === 'No Content',
    'metadata 201 No Content is a complete whole-day reference');
$empty201 = new Client($credentials, function () {
    return ['http'=>200,'body'=>'{"metadata":{"code":201},"response":{"list":[]}}'];
});
check(strpos($empty201->fetch($date)['day_reason'], 'No Content') !== false,
    'metadata 201 without message is also a complete empty reference');
$holiday = new Client($credentials, function ($url) {
    if (strpos($url, '/ref/poli/') !== false) {
        return ['http'=>200,'body'=>'{"metadata":{"code":1,"message":"OK"},"response":{"list":[{"kodepoli":"001","namapoli":"POLI UMUM"}]}}'];
    }
    return ['http'=>200,'body'=>'{"metadata":{"code":201,"message":"Jadwal Dokter tidak ditemukan"},"response":null}'];
});
$holidaySnapshot = $holiday->fetch($date);
check($holidaySnapshot['rows'] === [] && count($holidaySnapshot['closures']) === 1
    && strpos($holidaySnapshot['closures'][0]['reason'], 'tidak ditemukan') !== false,
    'doctor no-schedule metadata is a valid closed day, not a credential failure');
$holidayPoli = new Client($credentials, function () {
    return ['http'=>200,'body'=>'{"metadata":{"code":201,"message":"Data tidak ditemukan"},"response":null}'];
});
$holidayPoliSnapshot = $holidayPoli->fetch($date);
check($holidayPoliSnapshot['rows'] === [] && $holidayPoliSnapshot['closures'] === []
    && $holidayPoliSnapshot['day_reason'] === 'Data tidak ditemukan',
    'no-data metadata at poli endpoint becomes a valid whole-day closure');
failure(function () use ($credentials, $date) {
    (new Client($credentials, function () {
        return ['http'=>200,'body'=>'{"metadata":{"code":201,"message":"Signature tidak sesuai"},"response":null}'];
    }))->fetch($date);
}, 'signature metadata remains fatal and is never classified as holiday');
foreach ([['jampraktek'=>'08:00-25:00'], ['kapasitas'=>-1], ['kapasitas'=>1000], ['kodedokter'=>'1e2']] as $mutation) {
    $invalidDoctor = array_merge($doctors[0], $mutation);
    failure(function () use ($credentials, $invalidDoctor, $date) {
        (new Client($credentials, function ($url) use ($invalidDoctor) {
            $list = strpos($url, '/ref/poli/') !== false ? [['kodepoli'=>'001','namapoli'=>'UMUM']] : [$invalidDoctor];
            return ['http'=>200,'body'=>json_encode(['metadata'=>['code'=>1],'response'=>['list'=>$list]])];
        }))->fetch($date);
    }, 'invalid upstream doctor field rejected '.json_encode($mutation));
}
failure(function () use ($credentials, $doctors, $date) {
    (new Client($credentials, function ($url) use ($doctors) {
        $list = strpos($url, '/ref/poli/') !== false ? [['kodepoli'=>'001','namapoli'=>'UMUM']] : [$doctors[0],array_merge($doctors[0],['kapasitas'=>12])];
        return ['http'=>200,'body'=>json_encode(['metadata'=>['code'=>1],'response'=>['list'=>$list]])];
    }))->fetch($date);
}, 'contradictory duplicate schedule rejected');
$partialCalls = 0;
failure(function () use ($credentials, &$partialCalls, $date) {
    (new Client($credentials, function () use (&$partialCalls) {
        $partialCalls++;
        return $partialCalls === 1 ? ['http'=>200,'body'=>'{"metadata":{"code":1},"response":{"list":[{"kodepoli":"001","namapoli":"UMUM"}]}}'] : ['http'=>504,'body'=>''];
    }))->fetch($date);
}, 'partial retrieval fails atomically');

$ids = array_column($snapshot['rows'], 'id');
$active = Activation::approve($config, $snapshot, [$ids[0],$ids[1]], ['UMU'], $catalog, $client->fingerprint());
check($active['source_mode'] === 'bpjs_reference' && count($active['rules']) === 2 && $active['rules'][0]['date'] === $date, 'first approval replaces manual weekly policy with exact date');
check(count(Policy::schedules($active, $catalog, $date, '001')) === 2, 'approved checkbox rows accepted by actual resolver');
check(Policy::schedules($active, $catalog, date('Y-m-d', strtotime('+7 days'))) === [], 'reference approval never becomes weekly schedule');
rejected(function () use ($snapshot, $catalog, $client) { Activation::approve(Policy::emptyConfig(), $snapshot, ['forged-id'], ['UMU'], $catalog, $client->fingerprint()); }, 'forged checkbox ID rejected');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[0],$ids[2]], ['UMU'], $catalog, $client->fingerprint()); }, 'overlapping BPJS doctors same shift rejected');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[3]], ['UMU'], $catalog, $client->fingerprint()); }, 'unmapped doctor cannot activate');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[4]], ['UMU'], $catalog, $client->fingerprint()); }, 'zero capacity cannot activate');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[0]], ['RUJ'], $catalog, $client->fingerprint()); }, 'referral poli forbidden in reference activation');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[0]], ['UMU','USG'], $catalog, $client->fingerprint()); }, 'ambiguous destination forbidden in reference activation');
rejected(function () use ($snapshot, $catalog, $client, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[0]], ['UMU'], $catalog, $client->fingerprint(), $snapshot['fetched_at'] + 900); }, 'approval expires at 15 minutes');
rejected(function () use ($snapshot, $catalog, $ids) { Activation::approve(Policy::emptyConfig(), $snapshot, [$ids[0]], ['UMU'], $catalog, 'other-source'); }, 'source/credential change requires refetch');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$nextSnapshot = $client->fetch($tomorrow);
$twoDays = Activation::approve($active, $nextSnapshot, [$nextSnapshot['rows'][0]['id']], ['UMU'], $catalog, $client->fingerprint());
check(count($twoDays['rules']) === 3 && count($twoDays['bpjs_dates']) === 2, 'saving another date preserves prior approval');
$closed = Activation::approve($twoDays, $snapshot, [], ['UMU'], $catalog, $client->fingerprint());
check(Policy::schedules($closed, $catalog, $date) === [] && count(Policy::schedules($closed, $catalog, $tomorrow)) === 1, 'unchecking all closes only chosen date');
$expired = $active; $expired['bpjs_dates'][$date]['fetched_at'] = time()-86400;
check(Policy::schedules($expired, $catalog, $date) === [], '24 hour reference freshness enforced in API resolver');
$changedCatalog = $catalog; $changedCatalog['doctors'][0]['kd_dokter_pcare'] = '9999';
rejected(function () use ($twoDays, $snapshot, $changedCatalog, $client) { Activation::approve($twoDays, $snapshot, [], ['UMU'], $changedCatalog, $client->fingerprint()); }, 'mapping changes cannot silently rewrite other approved dates');

$testDir = sys_get_temp_dir().'/jkn-reference-test-'.bin2hex(random_bytes(8));
mkdir($testDir, 0700);
try {
    Policy::save($active, 0, $testDir.'/policy.php');
    $loaded = Policy::read($testDir.'/policy.php');
    check($loaded['bpjs_dates'][$date]['selected'] === [$ids[0],$ids[1]], 'approved reference roundtrips in file, no database');
    $tpl = new Systems\Lib\Templates($core);
    $tmp = new ReflectionProperty($tpl, 'tmp'); $tmp->setAccessible(true); $tmp->setValue($tpl, $testDir.'/');
    $rows = $snapshot['rows'];
    foreach ($rows as &$row) { $row['local_doctor']='D1'; $row['blocked']=false; $row['selected']=true; $row['reason']=''; } unset($row);
    $tpl->set('nav', []);
    $preview = Policy::schedules($active,$catalog,$date);
    foreach ($preview as &$previewRow) {
        $previewRow['jampraktek'] = substr($previewRow['jam_mulai'], 0, 5).'-'.substr($previewRow['jam_selesai'], 0, 5);
    }
    unset($previewRow);
    $tpl->set('reference', ['date'=>$date,'min_date'=>$date,'max_date'=>date('Y-m-d', strtotime('+90 days')),
        'error'=>'','source'=>$client->source(),'csrf'=>'TEST_ONLY','nonce'=>'test-snapshot',
        'can_save'=>true,'fetched_at'=>'test','legacy'=>true,'has_snapshot'=>true,'rows'=>$rows,'polis'=>$display,
        'preview'=>$preview,'dates'=>[],'closures'=>[],
        'fetch_url'=>'/admin/jkn_mobile_fktp_dev/fetchBpjsSchedule',
        'save_url'=>'/admin/jkn_mobile_fktp_dev/saveOnlineSchedule',
        'week_url'=>'/admin/jkn_mobile_fktp_dev/weekSchedule',
        'settings_url'=>'/admin/jkn_mobile_fktp_dev/manage']);
    $html = $tpl->draw(dirname(__DIR__).'/view/admin/bpjs.schedule.html');
    check(strpos($html, '08:00-12:00') !== false && strpos($html, 'name="selected[]"') !== false, 'real BPJS checkbox template renders');
    check(strpos($html, 'name="confirm_migration"') === false, 'legacy file-migration confirmation removed from table workflow');
    check(strpos($html, '%7B') === false && strpos($html, '{?=url') === false, 'rendered actions contain no uncompiled template URL');
    check(substr_count($html, 'name="form_complete"') === 2 && substr_count($html, 'name="schedule_csrf"') === 2, 'fetch and save both include CSRF and truncation guard');
    check(preg_match('/value="RUJ"[^>]*disabled/', $html) === 1, 'referral checkbox disabled in new template');
} finally {
    foreach (['policy.php','policy.php.lock.php','bpjs.schedule.html'] as $file) { if (is_file($testDir.'/'.$file)) { unlink($testDir.'/'.$file); } }
    rmdir($testDir);
}
echo $passed.' total checks passed (mock BPJS only; no live DB/config changes).'.PHP_EOL;
