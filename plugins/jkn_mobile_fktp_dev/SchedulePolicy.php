<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

/** Pure acceptance rules + file storage. This class never writes SQL. */
class SchedulePolicy
{
    const HEADER = "<?php http_response_code(404); exit; ?>\n";
    const DAYS = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'AKHAD'];

    public static function path()
    {
        return dirname(__DIR__, 2).'/tmp/jkn_mobile_fktp_dev_schedule.php';
    }

    public static function emptyConfig()
    {
        return ['version' => 1, 'revision' => 0, 'updated_at' => '', 'policies' => [], 'rules' => []];
    }

    public static function read($path = null)
    {
        $path = $path ?: self::path();
        if (!file_exists($path)) { return self::emptyConfig(); }
        $raw = @file_get_contents($path);
        if ($raw === false || substr($raw, 0, strlen(self::HEADER)) !== self::HEADER) {
            throw new \RuntimeException('Konfigurasi jadwal online tidak dapat dibaca.');
        }
        $data = json_decode(substr($raw, strlen(self::HEADER)), true);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_int($data['revision'] ?? null) || $data['revision'] < 0
            || !is_string($data['updated_at'] ?? null)
            || !is_array($data['policies'] ?? null) || !is_array($data['rules'] ?? null)) {
            throw new \RuntimeException('Konfigurasi jadwal online rusak. Pulihkan salinan konfigurasi.');
        }
        foreach ($data['policies'] as $poli => $bpjs) {
            if (!is_string($bpjs) || self::referral((string) $poli)) { throw new \RuntimeException('Konfigurasi poli tidak valid.'); }
        }
        if (count(array_unique($data['policies'])) !== count($data['policies']) || count($data['rules']) > 80) {
            throw new \RuntimeException('Konfigurasi poli ganda atau terlalu banyak shift.');
        }
        foreach ($data['rules'] as $rule) {
            if (!is_array($rule)) { throw new \RuntimeException('Konfigurasi shift tidak valid.'); }
            foreach (['kd_poli', 'kodepoli', 'kd_dokter', 'kodedokter', 'day', 'date', 'shift', 'start', 'end'] as $key) {
                if (!isset($rule[$key]) || !is_string($rule[$key])) { throw new \RuntimeException('Konfigurasi shift tidak lengkap.'); }
            }
            if (!is_int($rule['quota'] ?? null)) { throw new \RuntimeException('Konfigurasi kuota tidak valid.'); }
            if (($data['policies'][$rule['kd_poli']] ?? null) !== $rule['kodepoli']
                || !in_array($rule['day'], self::DAYS, true)
                || ($rule['date'] !== '' && (!self::validDate($rule['date']) || self::day($rule['date']) !== $rule['day']))
                || !in_array($rule['shift'], ['pagi', 'sore', 'tutup'], true)) {
                throw new \RuntimeException('Konfigurasi tanggal/poli/shift tidak valid.');
            }
            if ($rule['shift'] === 'tutup') {
                if ($rule['date'] === '' || $rule['quota'] !== 0) { throw new \RuntimeException('Konfigurasi libur tidak valid.'); }
            } elseif ($rule['quota'] < 1 || $rule['quota'] > 999 || $rule['kd_dokter'] === '' || $rule['kodedokter'] === ''
                || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $rule['start'])
                || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $rule['end']) || $rule['start'] >= $rule['end']) {
                throw new \RuntimeException('Konfigurasi dokter/jam/kuota tidak valid.');
            }
        }
        if (isset($data['source_mode'])) {
            if ($data['source_mode'] !== 'bpjs_reference' || !is_array($data['bpjs_dates'] ?? null)) {
                throw new \RuntimeException('Sumber konfigurasi jadwal tidak valid.');
            }
            foreach ($data['bpjs_dates'] as $date => $snapshot) {
                if (!self::validDate((string) $date) || !is_array($snapshot) || ($snapshot['date'] ?? null) !== $date
                    || !is_int($snapshot['fetched_at'] ?? null) || !is_string($snapshot['fingerprint'] ?? null)
                    || !is_array($snapshot['rows'] ?? null) || !is_array($snapshot['selected'] ?? null)) {
                    throw new \RuntimeException('Snapshot referensi tersimpan tidak valid.');
                }
            }
            foreach ($data['rules'] as $r) {
                if ($r['date'] === '' || !isset($data['bpjs_dates'][$r['date']])) { throw new \RuntimeException('Aturan tanpa persetujuan tanggal BPJS.'); }
            }
        }
        return $data;
    }

    public static function save(array $config, $expectedRevision, $path = null)
    {
        $path = $path ?: self::path();
        $lock = @fopen($path.'.lock.php', 'c+');
        if (!$lock) { throw new \RuntimeException('Folder konfigurasi tidak dapat ditulis oleh PHP.'); }
        $tmp = null;
        try {
            if (!flock($lock, LOCK_EX)) { throw new \RuntimeException('Konfigurasi sedang digunakan. Coba lagi.'); }
            if (self::read($path)['revision'] !== (int) $expectedRevision) {
                throw new \RuntimeException('Konfigurasi telah berubah di sesi lain. Muat ulang halaman sebelum menyimpan.');
            }
            $config['version'] = 1;
            $config['revision'] = (int) $expectedRevision + 1;
            $config['updated_at'] = date('Y-m-d H:i:s');
            $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $tmp = tempnam(dirname($path), '.jkn-schedule-');
            if ($tmp === false || file_put_contents($tmp, self::HEADER.$encoded) !== strlen(self::HEADER.$encoded) || !chmod($tmp, 0600) || !rename($tmp, $path)) {
                throw new \RuntimeException('Konfigurasi gagal disimpan. Pengaturan sebelumnya tetap berlaku.');
            }
            $tmp = null;
        } finally {
            if ($tmp && file_exists($tmp)) { unlink($tmp); }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function catalogs(\PDO $pdo)
    {
        return [
            'polis' => $pdo->query('SELECT p.kd_poli, p.nm_poli, p.status, m.kd_poli_pcare FROM poliklinik p JOIN maping_poliklinik_pcare m ON m.kd_poli_rs = p.kd_poli ORDER BY p.nm_poli')->fetchAll(\PDO::FETCH_ASSOC),
            'doctors' => $pdo->query('SELECT d.kd_dokter, d.nm_dokter, d.status, m.kd_dokter_pcare FROM dokter d JOIN maping_dokter_pcare m ON m.kd_dokter = d.kd_dokter ORDER BY d.nm_dokter')->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }

    public static function referral($code, $name = '')
    {
        return preg_match('/^RUJ/i', trim($code)) === 1 || preg_match('/^(?:POLI\s+)?RUJ/i', trim($name)) === 1;
    }

    private static function index(array $rows, $local, $remote)
    {
        $index = [];
        foreach ($rows as $row) {
            if (isset($index[$row[$local]])) { $index[$row[$local]]['_ambiguous'] = true; continue; }
            $row['_ambiguous'] = false;
            $index[$row[$local]] = $row;
        }
        return $index;
    }

    private static function text(array $input, $key)
    {
        if (!isset($input[$key]) || !is_scalar($input[$key])) { return ''; }
        return trim((string) $input[$key]);
    }

    public static function validDate($date)
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public static function day($date)
    {
        return self::DAYS[(int) (new \DateTimeImmutable($date))->format('N') - 1];
    }

    /** Validate submitted policy as a whole; never pick the first mapping silently. */
    public static function validate(array $allowed, array $rows, array $catalog)
    {
        if (count($rows) > 80) { throw new \DomainException('Maksimal 80 baris jadwal.'); }
        $polis = self::index($catalog['polis'], 'kd_poli', 'kd_poli_pcare');
        $doctors = self::index($catalog['doctors'], 'kd_dokter', 'kd_dokter_pcare');
        $config = self::emptyConfig();
        $bpjsUsed = [];
        foreach ($allowed as $local) {
            if (!is_string($local) || !isset($polis[$local])) { throw new \DomainException('Pilih poli yang memiliki mapping.'); }
            $p = $polis[$local];
            if (self::referral($local, $p['nm_poli'])) { throw new \DomainException('Poli RUJ/rujukan tidak dapat menerima Mobile JKN.'); }
            if ((string) $p['status'] !== '1' || $p['_ambiguous'] || trim((string) $p['kd_poli_pcare']) === '') {
                throw new \DomainException('Poli tidak aktif atau mapping ambigu: '.$local);
            }
            $bpjs = (string) $p['kd_poli_pcare'];
            if (isset($bpjsUsed[$bpjs])) { throw new \DomainException('Kode BPJS '.$bpjs.' hanya boleh memiliki satu poli penerimaan.'); }
            $bpjsUsed[$bpjs] = true;
            $config['policies'][$local] = $bpjs;
        }
        foreach ($rows as $i => $input) {
            if (!is_array($input)) { throw new \DomainException('Format baris jadwal tidak valid.'); }
            $row = [];
            foreach (['kd_poli', 'kd_dokter', 'day', 'date', 'shift', 'start', 'end'] as $field) { $row[$field] = self::text($input, $field); }
            $label = 'Baris '.($i + 1).': ';
            if (!isset($config['policies'][$row['kd_poli']])) { throw new \DomainException($label.'poli belum diizinkan.'); }
            if ($row['date'] !== '') {
                if (!self::validDate($row['date'])) { throw new \DomainException($label.'tanggal tidak valid.'); }
                $row['day'] = self::day($row['date']);
            } elseif (!in_array($row['day'], self::DAYS, true)) { throw new \DomainException($label.'pilih hari.'); }
            if (!in_array($row['shift'], ['pagi', 'sore', 'tutup'], true)) { throw new \DomainException($label.'shift tidak valid.'); }
            $row['kodepoli'] = $config['policies'][$row['kd_poli']];
            if ($row['shift'] === 'tutup') {
                if ($row['date'] === '') { throw new \DomainException($label.'Tutup hanya untuk pengecualian tanggal. Hapus shift mingguan untuk libur rutin.'); }
                $row['kd_dokter'] = $row['kodedokter'] = $row['start'] = $row['end'] = '';
                $row['quota'] = 0;
            } else {
                $doctor = $doctors[$row['kd_dokter']] ?? null;
                if (!$doctor || (string) $doctor['status'] !== '1' || $doctor['_ambiguous'] || trim((string) $doctor['kd_dokter_pcare']) === '') {
                    throw new \DomainException($label.'dokter tidak aktif atau mapping tidak tersedia/ambigu.');
                }
                $row['kodedokter'] = (string) $doctor['kd_dokter_pcare'];
                $sameCode = array_filter($doctors, function ($d) use ($row) { return (string) $d['kd_dokter_pcare'] === $row['kodedokter'] && (string) $d['status'] === '1'; });
                if (count($sameCode) !== 1) { throw new \DomainException($label.'kode BPJS dokter dipakai lebih dari satu dokter lokal.'); }
                if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $row['start']) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $row['end']) || $row['start'] >= $row['end']) {
                    throw new \DomainException($label.'jam harus HH:MM dan selesai setelah mulai.');
                }
                $quota = self::text($input, 'quota');
                if (!ctype_digit($quota) || (int) $quota < 1 || (int) $quota > 999) { throw new \DomainException($label.'kuota harus 1–999.'); }
                $row['quota'] = (int) $quota;
            }
            $config['rules'][] = $row;
        }
        // Weekly baseline plus every exceptional date cover all effective combinations.
        foreach (self::DAYS as $day) {
            self::checkConflicts(array_values(array_filter($config['rules'], function ($r) use ($day) { return $r['date'] === '' && $r['day'] === $day; })));
        }
        $dates = array_unique(array_column($config['rules'], 'date'));
        foreach ($dates as $date) {
            if ($date !== '') { self::checkConflicts(self::effective($config, $date)); }
        }
        return $config;
    }

    private static function checkConflicts(array $rules)
    {
        foreach ($rules as $i => $a) {
            foreach (array_slice($rules, $i + 1) as $b) {
                $samePoli = $a['kd_poli'] === $b['kd_poli'];
                if ($samePoli && ($a['shift'] === $b['shift'] || $a['shift'] === 'tutup' || $b['shift'] === 'tutup')) {
                    throw new \DomainException('Jadwal ganda untuk poli/hari/shift yang sama: '.$a['kd_poli'].' '.$a['day'].'.');
                }
                if ($a['shift'] === 'tutup' || $b['shift'] === 'tutup') { continue; }
                if (($samePoli || $a['kd_dokter'] === $b['kd_dokter']) && $a['start'] < $b['end'] && $b['start'] < $a['end']) {
                    throw new \DomainException('Jam tumpang tindih pada poli atau dokter yang sama: '.$a['day'].'.');
                }
            }
        }
    }

    public static function effective(array $config, $date)
    {
        if (!self::validDate($date)) { return []; }
        $day = self::day($date);
        $overridden = [];
        foreach ($config['rules'] as $row) { if ($row['date'] === $date) { $overridden[$row['kd_poli']] = true; } }
        return array_values(array_filter($config['rules'], function ($r) use ($date, $day, $overridden) {
            return $r['date'] === $date || ($r['date'] === '' && $r['day'] === $day && !isset($overridden[$r['kd_poli']]));
        }));
    }

    public static function schedules(array $config, array $catalog, $date, $bpjs = null)
    {
        if (($config['source_mode'] ?? '') === 'bpjs_reference') {
            $snapshot = $config['bpjs_dates'][$date] ?? [];
            if (!is_int($snapshot['fetched_at'] ?? null) || $snapshot['fetched_at'] > time() || time() - $snapshot['fetched_at'] >= 86400) { return []; }
        }
        $polis = self::index($catalog['polis'], 'kd_poli', 'kd_poli_pcare');
        $doctors = self::index($catalog['doctors'], 'kd_dokter', 'kd_dokter_pcare');
        $result = [];
        foreach (self::effective($config, $date) as $r) {
            if ($r['shift'] === 'tutup' || ($bpjs !== null && $r['kodepoli'] !== (string) $bpjs)) { continue; }
            $p = $polis[$r['kd_poli']] ?? null;
            $d = $doctors[$r['kd_dokter']] ?? null;
            $sameCode = array_filter($doctors, function ($doctor) use ($r) {
                return (string) $doctor['kd_dokter_pcare'] === $r['kodedokter'] && (string) $doctor['status'] === '1';
            });
            if (!$p || !$d || $p['_ambiguous'] || $d['_ambiguous'] || (string) $p['status'] !== '1' || (string) $d['status'] !== '1'
                || count($sameCode) !== 1
                || self::referral($r['kd_poli'], $p['nm_poli']) || ($config['policies'][$r['kd_poli']] ?? '') !== $r['kodepoli']
                || (string) $p['kd_poli_pcare'] !== $r['kodepoli'] || (string) $d['kd_dokter_pcare'] !== $r['kodedokter']) { continue; }
            $result[] = [
                'kd_poli' => $r['kd_poli'], 'kd_dokter' => $r['kd_dokter'], 'kd_poli_pcare' => $r['kodepoli'], 'kd_dokter_pcare' => $r['kodedokter'],
                'nm_poli' => $p['nm_poli'], 'nm_dokter' => $d['nm_dokter'], 'hari_kerja' => $r['day'],
                'jam_mulai' => $r['start'].':00', 'jam_selesai' => $r['end'].':00', 'kuota' => $r['quota'], 'shift' => $r['shift'], 'tanggal' => $date
            ];
        }
        usort($result, function ($a, $b) { return [$a['kd_poli'], $a['jam_mulai']] <=> [$b['kd_poli'], $b['jam_mulai']]; });
        return $result;
    }

    public static function selectSlot(array $schedules, $doctor, $practice)
    {
        foreach ($schedules as $row) {
            $time = substr($row['jam_mulai'], 0, 5).'-'.substr($row['jam_selesai'], 0, 5);
            if ((string) $row['kd_dokter_pcare'] === (string) $doctor && $time === $practice) { return $row; }
        }
        return null;
    }
}
