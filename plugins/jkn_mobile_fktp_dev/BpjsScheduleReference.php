<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

require_once __DIR__.'/SchedulePolicy.php';

/** Antrean FKTP adapter. Its credentials are supplied from the PCare settings namespace. */
class BpjsScheduleReference
{
    private $config;
    private $transport;
    private $base;

    const VALID_HOSTS = ['apijkn.bpjs-kesehatan.go.id', 'new-apijkn.bpjs-kesehatan.go.id', 'apijkn-dev.bpjs-kesehatan.go.id'];

    public function __construct(array $config, callable $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport;
        $rawUrl = trim((string) ($config['antrol_url'] ?? ''));
        $url = parse_url($rawUrl);
        $host = $url['host'] ?? '';
        if (($url['scheme'] ?? '') !== 'https' || !in_array($host, self::VALID_HOSTS, true)) {
            throw new \RuntimeException('URL Antrol belum valid. Isi URL HTTPS BPJS pada Pengaturan JKN Mobile FKTP Dev, contoh: https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/');
        }
        $this->base = rtrim($rawUrl, '/').'/';
        foreach (['antrol_consumer_id', 'antrol_consumer_secret', 'antrol_user_key'] as $key) {
            if (!is_string($config[$key] ?? null) || trim($config[$key]) === '' || preg_match('/[\r\n]/', $config[$key])) {
                throw new \RuntimeException('Lengkapi Consumer ID, Secret dan User Key Antrol pada Pengaturan PCare.');
            }
        }
    }

    public function source() { return $this->base; }

    public function fingerprint()
    {
        return hash_hmac('sha256', $this->base.'|'.$this->config['antrol_consumer_id'].'|'.$this->config['antrol_user_key'], $this->config['antrol_consumer_secret']);
    }

    /** POST JSON to Antrol and only return after HTTP and BPJS metadata both confirm success. */
    public function postJson($path, $jsonBody)
    {
        if (!is_string($jsonBody) || json_decode($jsonBody, true) === null || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Payload Antrol internal tidak valid.');
        }
        $path = ltrim((string) $path, '/');
        if (!preg_match('#^antrean/(?:add|panggil|batal)$#', $path)) {
            throw new \RuntimeException('Endpoint Antrol tidak diizinkan.');
        }
        $stamp = (string) time();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-cons-id: '.$this->config['antrol_consumer_id'],
            'X-timestamp: '.$stamp,
            'X-signature: '.base64_encode(hash_hmac('sha256', $this->config['antrol_consumer_id'].'&'.$stamp, $this->config['antrol_consumer_secret'], true)),
            'user_key: '.$this->config['antrol_user_key']
        ];
        if ($this->transport) {
            $response = call_user_func($this->transport, $this->base.$path, $headers, $jsonBody);
        } else {
            if (!function_exists('curl_init')) { throw new \RuntimeException('Ekstensi cURL belum tersedia.'); }
            $curl = curl_init($this->base.$path);
            curl_setopt_array($curl, [CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$jsonBody, CURLOPT_FOLLOWLOCATION=>false]);
            $body = curl_exec($curl);
            $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            if ($body === false) {
                throw new \RuntimeException('Koneksi Antrol BPJS gagal'.($curlError !== '' ? ': '.self::safeMessage($curlError) : '.'));
            }
            $response = ['http'=>$http, 'body'=>$body];
        }
        if (!is_array($response) || (int) ($response['http'] ?? 0) !== 200
            || !is_string($response['body'] ?? null) || strlen($response['body']) > 2000000) {
            throw new \RuntimeException('Antrol BPJS gagal (HTTP '.(int) ($response['http'] ?? 0).').');
        }
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respons Antrol BPJS bukan JSON yang valid.');
        }
        $metadata = $decoded['metadata'] ?? $decoded['metaData'] ?? null;
        if (!is_array($metadata)) {
            throw new \RuntimeException('Respons Antrol BPJS tidak memiliki metadata.');
        }
        $code = (string) ($metadata['code'] ?? '');
        $message = self::safeMessage($metadata['message'] ?? '');
        if (!in_array($code, ['1', '200'], true)) {
            throw new \RuntimeException('BPJS menolak transaksi Antrol (metadata '.$code.'): '.
                ($message !== '' ? $message : 'pesan tidak tersedia').'.');
        }
        return ['http'=>200, 'metadata_code'=>$code, 'message'=>$message, 'response'=>$decoded['response'] ?? null];
    }

    public function fetch($date)
    {
        if (!SchedulePolicy::validDate($date)) { throw new \DomainException('Tanggal referensi tidak valid.'); }
        $poliClosedReason = '';
        $polis = $this->getList('ref/poli/tanggal/'.$date, true, $poliClosedReason);
        $dayReason = !$polis ? ($poliClosedReason ?: 'BPJS tidak mengembalikan poli/jadwal pada tanggal ini (hari libur/tanggal merah atau jadwal belum tersedia).') : '';
        if (count($polis) > 20) { throw new \RuntimeException('Referensi melebihi batas 20 poli; tidak ada konfigurasi yang diubah.'); }
        $result = [];
        $closures = [];
        $seenPolis = [];
        $started = microtime(true);
        foreach ($polis as $p) {
            if (!is_array($p)) { throw new \RuntimeException('Format referensi poli BPJS tidak valid.'); }
            $code = self::field($p, 'kodepoli');
            if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $code)) { throw new \RuntimeException('Kode poli referensi BPJS tidak valid.'); }
            if (isset($seenPolis[$code])) { continue; }
            $seenPolis[$code] = true;
            if (microtime(true) - $started > 20) { throw new \RuntimeException('Pengambilan referensi terlalu lama. Coba kembali.'); }
            $closedReason = '';
            $doctorList = $this->getList('ref/dokter/kodepoli/'.rawurlencode($code).'/tanggal/'.$date, true, $closedReason);
            if (!$doctorList) {
                $closures[$code] = ['kodepoli'=>$code, 'namapoli'=>self::field($p, 'namapoli'),
                    'reason'=>$closedReason ?: 'BPJS tidak menyediakan jadwal dokter pada tanggal ini (hari libur/tanggal merah atau jadwal belum tersedia).'];
            }
            foreach ($doctorList as $doctor) {
                if (!is_array($doctor)) { throw new \RuntimeException('Format referensi dokter BPJS tidak valid.'); }
                $d = self::field($doctor, 'kodedokter');
                $time = self::field($doctor, 'jampraktek');
                $capacity = self::field($doctor, 'kapasitas');
                if (!ctype_digit($d) || !preg_match('/^((?:[01][0-9]|2[0-3]):[0-5][0-9])-((?:[01][0-9]|2[0-3]):[0-5][0-9])$/', $time, $match)
                    || $match[1] >= $match[2] || !ctype_digit($capacity) || (int) $capacity > 999) {
                    throw new \RuntimeException('Dokter, jam atau kapasitas dari BPJS tidak valid/tidak didukung. Pengaturan lama tetap berlaku.');
                }
                $id = hash('sha256', $date.'|'.$code.'|'.$d.'|'.$time);
                $row = ['id'=>$id, 'date'=>$date, 'kodepoli'=>$code, 'namapoli'=>self::field($p, 'namapoli'),
                    'kodedokter'=>$d, 'namadokter'=>self::field($doctor, 'namadokter'), 'jampraktek'=>$time,
                    'start'=>$match[1], 'end'=>$match[2], 'quota'=>(int) $capacity, 'shift'=>$match[1] < '12:00' ? 'pagi' : 'sore'];
                if (isset($result[$id]) && $result[$id] !== $row) { throw new \RuntimeException('Referensi BPJS ganda memiliki data berbeda.'); }
                $result[$id] = $row;
                if (count($result) > 300) { throw new \RuntimeException('Referensi melebihi batas 300 jadwal.'); }
            }
        }
        return ['date'=>$date, 'fetched_at'=>time(), 'source'=>$this->base, 'fingerprint'=>$this->fingerprint(),
            'rows'=>array_values($result), 'closures'=>array_values($closures), 'day_reason'=>$dayReason];
    }

    private static function field(array $row, $key)
    {
        return isset($row[$key]) && is_scalar($row[$key]) ? trim((string) $row[$key]) : '';
    }

    private function getList($path, $allowNoSchedule = false, &$emptyReason = null)
    {
        $stamp = (string) time();
        $headers = ['Accept: application/json', 'X-cons-id: '.$this->config['antrol_consumer_id'], 'X-timestamp: '.$stamp,
            'X-signature: '.base64_encode(hash_hmac('sha256', $this->config['antrol_consumer_id'].'&'.$stamp, $this->config['antrol_consumer_secret'], true)),
            'user_key: '.$this->config['antrol_user_key']];
        if ($this->transport) {
            $response = call_user_func($this->transport, $this->base.$path, $headers);
        } else {
            if (!function_exists('curl_init')) { throw new \RuntimeException('Ekstensi cURL belum tersedia.'); }
            $curl = curl_init($this->base.$path);
            curl_setopt_array($curl, [CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_FOLLOWLOCATION=>false]);
            $body = curl_exec($curl);
            $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $response = ['http'=>$http, 'body'=>$body];
        }
        if ((int) ($response['http'] ?? 0) !== 200 || !is_string($response['body'] ?? null) || strlen($response['body']) > 2000000) {
            throw new \RuntimeException('Referensi BPJS gagal diambil (koneksi/TLS/HTTP). Periksa URL dan kredensial Antrol; konfigurasi aktif tidak diubah.');
        }
        $json = json_decode($response['body'], true);
        $metadata = $json['metadata'] ?? $json['metaData'] ?? [];
        $metadataCode = (string) ($metadata['code'] ?? '');
        $metadataMessage = self::safeMessage($metadata['message'] ?? '');
        if (!in_array($metadataCode, ['1', '200'], true)) {
            // Antrean FKTP uses metadata 201/"No Content" for dates without a
            // published schedule (Sunday, public holiday, or another closed
            // day). It is a complete reference response, not a partial-week
            // failure. Keep explicit authentication/security failures fatal so
            // an invalid signature can never be saved as a holiday.
            $completeEmptySchedule = $metadataCode === '201' && !self::authenticationMessage($metadataMessage);
            if ($allowNoSchedule && ($completeEmptySchedule || self::noScheduleMessage($metadataMessage))) {
                $emptyReason = $metadataMessage !== '' ? $metadataMessage : 'No Content (BPJS tidak menyediakan jadwal pada tanggal ini).';
                return [];
            }
            throw new \RuntimeException('BPJS menolak referensi (metadata '.$metadataCode.'): '.
                ($metadataMessage !== '' ? $metadataMessage : 'pesan tidak tersedia').'. Periksa jenis error sebelum mengubah kredensial Antrol.');
        }
        $data = $json['response'] ?? null;
        if (is_string($data)) {
            $key = hex2bin(hash('sha256', $this->config['antrol_consumer_id'].$this->config['antrol_consumer_secret'].$stamp));
            $cipher = base64_decode($data, true);
            $decrypted = $cipher === false ? false : openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($key, 0, 16));
            if (!is_string($decrypted) || !class_exists('LZCompressor\\LZString')) { throw new \RuntimeException('Dekripsi referensi BPJS gagal.'); }
            try { $data = json_decode(\LZCompressor\LZString::decompressFromEncodedURIComponent($decrypted), true); }
            catch (\Throwable $e) { throw new \RuntimeException('Dekompresi referensi BPJS gagal.'); }
        }
        // BPJS returns null response when no schedules for the given date
        if ($data === null) {
            if ($allowNoSchedule) { $emptyReason = 'BPJS tidak menyediakan jadwal dokter pada tanggal ini.'; }
            return [];
        }
        // Resolve list: either {list:[...]} wrapper or response is the list directly
        if (is_array($data)) {
            if (array_key_exists('list', $data)) {
                $list = $data['list'];
            } elseif (count($data) === 0 || isset($data[0])) {
                $list = $data; // response IS the sequential list
            } else {
                throw new \RuntimeException('Format daftar referensi BPJS tidak dikenali; pengaturan aktif tidak diubah.');
            }
        } else {
            throw new \RuntimeException('Format daftar referensi BPJS tidak dikenali; pengaturan aktif tidak diubah.');
        }
        if ($list === null || $list === []) {
            if ($allowNoSchedule) { $emptyReason = 'BPJS mengembalikan daftar jadwal dokter kosong pada tanggal ini.'; }
            return [];
        }
        if (!is_array($list) || ($list !== [] && array_keys($list) !== range(0, count($list) - 1))) {
            throw new \RuntimeException('Format daftar referensi BPJS tidak dikenali; pengaturan aktif tidak diubah.');
        }
        return $list;
    }

    private static function noScheduleMessage($message)
    {
        $message = strtolower((string) $message);
        if ($message === '') { return false; }
        if (self::authenticationMessage($message)) { return false; }
        if (strpos($message, 'no content') !== false) { return true; }
        $subject = strpos($message, 'jadwal') !== false || strpos($message, 'dokter') !== false;
        $absence = strpos($message, 'tidak ditemukan') !== false || strpos($message, 'tidak tersedia') !== false
            || strpos($message, 'tidak ada') !== false || strpos($message, 'kosong') !== false
            || strpos($message, 'libur') !== false;
        $genericEmptyData = strpos($message, 'data') !== false && $absence;
        return ($subject || $genericEmptyData) && $absence;
    }

    private static function authenticationMessage($message)
    {
        $message = strtolower((string) $message);
        foreach (['consumer', 'secret', 'signature', 'user key', 'user_key', 'kredensial', 'otorisasi', 'unauthorized', 'akses', 'user tidak'] as $authTerm) {
            if (strpos($message, $authTerm) !== false) { return true; }
        }
        return false;
    }

    private static function safeMessage($message)
    {
        if (!is_scalar($message)) { return ''; }
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', trim((string) $message));
        return mb_substr($message, 0, 180);
    }
}
