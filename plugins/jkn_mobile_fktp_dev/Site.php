<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

use Systems\SiteModule;

require_once __DIR__.'/SchedulePolicy.php';
require_once __DIR__.'/BpjsScheduleReference.php';
require_once __DIR__.'/ScheduleRepository.php';
require_once __DIR__.'/BookingIntegrity.php';
require_once __DIR__.'/PatientRegionResolver.php';

class Site extends SiteModule
{
    private const MODULE = 'jkn_mobile_fktp_dev';
    private const AUDIENCE = 'jkn-mobile-fktp-dev';
    private $auditAction = 'unknown';
    private $auditRequestId = '';
    private $auditNoRawat = '';
    private $audited = false;

    public function init()
    {
        // Rawat Jalan remains decoupled: clean markers whose parent visit was deleted there.
        try { BookingIntegrity::cleanupOrphans($this->db()->pdo()); }
        catch (\Throwable $e) { error_log('[jkn_mobile_fktp_dev][integrity] '.$e->getMessage()); }
    }

    public function routes()
    {
        $this->route('jknmobilefktpdev/auth', 'getAuth');
        $this->route('jknmobilefktpdev/antrean', 'getAntrean');
        $this->route('jknmobilefktpdev/antrean/status/(:str)/(:str)', 'getStatusAntrean');
        $this->route('jknmobilefktpdev/antrean/sisapeserta/(:str)/(:str)/(:str)', 'getSisaAntrean');
        $this->route('jknmobilefktpdev/antrean/batal', 'getBatalAntrean');
        $this->route('jknmobilefktpdev/peserta', 'getPeserta');
    }

    public function getAuth()
    {
        $this->prepareResponse();
        $this->beginAudit('auth');
        $this->requireMethod('GET');
        $username = $this->header('x-username');
        $password = $this->header('x-password');
        if (!$this->credentialsValid($username, $password)) {
            $this->fail('Access denied', 201, 401);
        }
        $this->ok(['token' => $this->makeToken($username)]);
    }

    public function getAntrean()
    {
        $this->prepareResponse();
        $this->beginAudit('booking');
        $this->requireMethod('POST');
        $this->authorize();
        $input = $this->jsonBody();
        $this->validateBookingInput($input);
        $date = $input['tanggalperiksa'];
        $schedule = $this->schedule($input['kodepoli'], $input['kodedokter'], $date, $input['jampraktek']);
        if (!$schedule) {
            try { $reason = ScheduleRepository::replacementMessage($this->db()->pdo(), $date, $input['kodepoli'], $input['kodedokter'], $input['jampraktek']); }
            catch (\Throwable $e) { $reason = 'Jadwal penerimaan online belum dikonfigurasi. Silakan hubungi administrator.'; }
            $this->fail($reason, 201, 422);
        }
        $patient = $this->patientByCard($input['nomorkartu']);
        if (!$patient) {
            $this->fail('Pasien belum terdaftar. Silakan kirim data pasien baru.', 202, 202);
        }
        if ((string) $patient['no_ktp'] !== $input['nik'] || (string) $patient['no_rkm_medis'] !== $input['norm']) {
            $this->fail('Nomor kartu, NIK, dan nomor RM tidak sesuai data pasien.', 201, 422);
        }
        $payer = trim((string) $this->setting('payer_code', ''));
        if ($payer === '') {
            $this->fail('Kode penjamin BPJS belum dikonfigurasi.', 201, 503);
        }

        $pdo = $this->db()->pdo();
        $antrolAttempted = false;
        $antrolSucceeded = false;
        try {
            $pdo->beginTransaction();
            $this->lockPatient($pdo, $patient['no_rkm_medis']);
            $lockedSchedule = $this->lockSchedule($pdo, $schedule);
            if (!$lockedSchedule) {
                throw new \DomainException('Jadwal layanan tidak tersedia.');
            }
            $visits = $pdo->prepare('SELECT no_rawat, stts FROM reg_periksa WHERE no_rkm_medis = ? AND kd_poli = ? AND tgl_registrasi = ? ORDER BY no_rawat ASC FOR UPDATE');
            $visits->execute([$patient['no_rkm_medis'], $lockedSchedule['kd_poli'], $date]);
            $cancelledNoRawat = null;
            foreach ($visits->fetchAll(\PDO::FETCH_ASSOC) as $visit) {
                if ($visit['stts'] !== 'Batal') {
                    throw new \DomainException('Nomor antrean hanya dapat diambil satu kali pada tanggal dan poli yang sama.');
                }
                $cancelledNoRawat = $visit['no_rawat'];
            }
            $count = $pdo->prepare('SELECT COUNT(*) FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat WHERE r.kd_dokter = ? AND r.kd_poli = ? AND r.tgl_registrasi = ? AND b.jampraktek = ? AND r.stts <> ?');
            $count->execute([$lockedSchedule['kd_dokter'], $lockedSchedule['kd_poli'], $date, $input['jampraktek'], 'Batal']);
            $total = (int) $count->fetchColumn();
            if ($total >= (int) $lockedSchedule['kuota']) {
                throw new \DomainException('Kuota antrean dokter sudah habis.');
            }

            $noReg = $this->core->setNoRegSafe($pdo, $lockedSchedule['kd_dokter'], $lockedSchedule['kd_poli'], $date);
            $reopened = $cancelledNoRawat !== null;
            if ($reopened) {
                $noRawat = $cancelledNoRawat;
                $restore = $pdo->prepare('UPDATE reg_periksa SET no_reg = ?, jam_reg = ?, kd_dokter = ?, p_jawab = ?, almt_pj = ?, hubunganpj = ?, stts = ?, status_lanjut = ?, kd_pj = ?, umurdaftar = ?, sttsumur = ?, status_bayar = ? WHERE no_rawat = ? AND stts = ?');
                $restore->execute([
                    $noReg, date('H:i:s'), $lockedSchedule['kd_dokter'], $patient['namakeluarga'] ?: '-',
                    $patient['alamatpj'] ?: $patient['alamat'], $patient['keluarga'] ?: 'AYAH', 'Belum', 'Ralan',
                    $payer, $this->ageValue($patient['tgl_lahir'], $date), $this->ageUnit($patient['tgl_lahir'], $date),
                    'Belum Bayar', $noRawat, 'Batal'
                ]);
                if ($restore->rowCount() !== 1) {
                    throw new \DomainException('Status antrean berubah, silakan cek kembali.');
                }
                $slotExists = $pdo->prepare('SELECT no_rawat FROM mlite_jkn_mobile_fktp_dev_booking WHERE no_rawat = ? FOR UPDATE');
                $slotExists->execute([$noRawat]);
                if ($slotExists->fetchColumn()) {
                    $slot = $pdo->prepare('UPDATE mlite_jkn_mobile_fktp_dev_booking SET kodepoli = ?, kodedokter = ?, jampraktek = ?, created_at = ? WHERE no_rawat = ?');
                    $slot->execute([$input['kodepoli'], (string) $input['kodedokter'], $input['jampraktek'], date('Y-m-d H:i:s'), $noRawat]);
                } else {
                    $slot = $pdo->prepare('INSERT INTO mlite_jkn_mobile_fktp_dev_booking (no_rawat, kodepoli, kodedokter, jampraktek, created_at) VALUES (?, ?, ?, ?, ?)');
                    $slot->execute([$noRawat, $input['kodepoli'], (string) $input['kodedokter'], $input['jampraktek'], date('Y-m-d H:i:s')]);
                }
            } else {
                $noRawat = $this->core->setNoRawatSafe($pdo, $date);
                $history = $this->patientHistory($pdo, $patient['no_rkm_medis'], $lockedSchedule['kd_poli']);
                $fee = $this->registrationFee($pdo, $lockedSchedule['kd_poli'], $history['registered_before']);
                $insert = $pdo->prepare('INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, p_jawab, almt_pj, hubunganpj, biaya_reg, stts, stts_daftar, status_lanjut, kd_pj, umurdaftar, sttsumur, status_bayar, status_poli) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([
                    $noReg, $noRawat, $date, date('H:i:s'), $lockedSchedule['kd_dokter'], $patient['no_rkm_medis'],
                    $lockedSchedule['kd_poli'], $patient['namakeluarga'] ?: '-', $patient['alamatpj'] ?: $patient['alamat'],
                    $patient['keluarga'] ?: 'AYAH', $fee, 'Belum', $history['registered_before'] ? 'Lama' : 'Baru',
                    'Ralan', $payer, $this->ageValue($patient['tgl_lahir'], $date), $this->ageUnit($patient['tgl_lahir'], $date),
                    'Belum Bayar', $history['poli_before'] ? 'Lama' : 'Baru'
                ]);
                $slot = $pdo->prepare('INSERT INTO mlite_jkn_mobile_fktp_dev_booking (no_rawat, kodepoli, kodedokter, jampraktek, created_at) VALUES (?, ?, ?, ?, ?)');
                $slot->execute([$noRawat, $input['kodepoli'], (string) $input['kodedokter'], $input['jampraktek'], date('Y-m-d H:i:s')]);
            }
            $antrolAttempted = $this->setting('antrol_enabled', '0') === '1';
            $antrolResult = $this->notifyAntrolAdd($patient, $input, $lockedSchedule, $noReg, $noRawat, $reopened);
            $antrolSucceeded = $antrolResult !== null;
            $pdo->commit();

            $number = $this->queueNumber($input['kodepoli'], $noReg);
            $response = [
                'nomorantrean' => $number,
                'angkaantrean' => (int) $noReg,
                'namapoli' => $lockedSchedule['nm_poli'],
                'sisaantrean' => max(0, (int) $lockedSchedule['kuota'] - $total - 1),
                'antreanpanggil' => $this->calledNumber($pdo, $lockedSchedule, $date, $input['kodepoli']),
                'keterangan' => $reopened ? 'Antrean dibuka kembali. Silakan datang sesuai jadwal dan konfirmasi ke pendaftaran.' : 'Silakan datang sesuai jadwal dan konfirmasi ke pendaftaran.',
                'kodedokter' => is_numeric($input['kodedokter']) ? (int) $input['kodedokter'] : $input['kodedokter'],
                'namadokter' => $lockedSchedule['nm_dokter'],
                'jampraktek' => $this->practiceTime($lockedSchedule)
            ];
            if ($antrolSucceeded) {
                $this->audit('antrol_add', $noRawat, 200, 'BPJS menerima antrean/add: '.($antrolResult['message'] ?: 'OK'));
            }
            $this->audit('booking', $noRawat, 200, $reopened ? 'Antrean dibuka kembali dengan nomor antrean baru' : 'Antrean berhasil dibuat');
            $this->ok($response);
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->audit('booking', '', 201, $e->getMessage());
            $this->fail($e->getMessage(), 201, 422);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($antrolAttempted && !$antrolSucceeded) {
                $message = $this->safeOutboundMessage($e->getMessage());
                $this->audit('antrol_add', isset($noRawat) ? $noRawat : '', 201, $message, 502, 'error');
                $this->audit('booking', isset($noRawat) ? $noRawat : '', 201, 'Booking dibatalkan karena sinkronisasi BPJS gagal.', 502, 'error');
                $this->fail('Antrean belum disimpan karena sinkronisasi BPJS gagal: '.$message, 201, 502);
            }
            error_log('[jkn_mobile_fktp_dev][booking] '.get_class($e).' code='.$e->getCode().' '.$e->getMessage());
            $this->audit('booking', '', 201, 'Kegagalan penyimpanan antrean ('.basename(str_replace('\\', '/', get_class($e))).' '.$e->getCode().')', 500, 'error');
            $this->fail('Antrean tidak dapat diproses. Silakan hubungi administrator.', 201, 500);
        }
    }

    public function getStatusAntrean($kodePoli, $tanggal)
    {
        $this->prepareResponse();
        $this->beginAudit('status');
        $this->requireMethod('GET');
        $this->authorize();
        $this->validateDate($tanggal);
        $rows = $this->schedulesForPoli($kodePoli, $tanggal);
        if (!$rows) {
            $this->fail('Poli atau jadwal tidak tersedia pada tanggal tersebut.', 201, 404);
        }
        $pdo = $this->db()->pdo();
        $response = [];
        foreach ($rows as $row) {
            $count = $pdo->prepare('SELECT COUNT(*) FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat WHERE r.kd_dokter = ? AND r.kd_poli = ? AND r.tgl_registrasi = ? AND b.jampraktek = ? AND r.stts <> ?');
            $count->execute([$row['kd_dokter'], $row['kd_poli'], $tanggal, $this->practiceTime($row), 'Batal']);
            $total = (int) $count->fetchColumn();
            $response[] = [
                'namapoli' => $row['nm_poli'],
                'totalantrean' => (string) $total,
                'sisaantrean' => max(0, (int) $row['kuota'] - $total),
                'antreanpanggil' => $this->calledNumber($pdo, $row, $tanggal, $kodePoli),
                'keterangan' => '',
                'kodedokter' => is_numeric($row['kd_dokter_pcare']) ? (int) $row['kd_dokter_pcare'] : $row['kd_dokter_pcare'],
                'namadokter' => $row['nm_dokter'],
                'jampraktek' => $this->practiceTime($row)
            ];
        }
        $this->ok($response);
    }

    public function getSisaAntrean($nomorKartu, $kodePoli, $tanggal)
    {
        $this->prepareResponse();
        $this->beginAudit('participant_queue');
        $this->requireMethod('GET');
        $this->authorize();
        if (!preg_match('/^\\d{13}$/', $nomorKartu)) { $this->fail('Format nomor kartu tidak sesuai.', 201, 422); }
        $this->validateDate($tanggal, true, false);
        $pdo = $this->db()->pdo();
        $stmt = $pdo->prepare('SELECT r.no_reg, r.kd_dokter, r.kd_poli, p.nm_poli, b.kodepoli AS kd_poli_pcare, b.kodedokter AS kd_dokter_pcare, b.jampraktek FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat JOIN pasien ps ON ps.no_rkm_medis = r.no_rkm_medis JOIN poliklinik p ON p.kd_poli = r.kd_poli WHERE ps.no_peserta = ? AND b.kodepoli = ? AND r.tgl_registrasi = ? AND r.stts <> ? ORDER BY r.no_rawat DESC LIMIT 1');
        $stmt->execute([$nomorKartu, $kodePoli, $tanggal, 'Batal']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $this->fail('Peserta belum mengambil antrean pada poli dan tanggal tersebut.', 201, 404); }
        $row['jam_mulai'] = substr($row['jampraktek'], 0, 5);
        $row['jam_selesai'] = substr($row['jampraktek'], 6, 5);
        $row['kuota'] = 0;
        // Existing bookings remain readable even if credentials/reference become unavailable.
        $availableSchedules = [];
        try { $availableSchedules = ScheduleRepository::schedules($pdo, SchedulePolicy::catalogs($pdo), $tanggal, $kodePoli); }
        catch (\RuntimeException $e) { /* Keep the booked slot, with no remaining capacity advertised. */ }
        foreach ($availableSchedules as $available) {
            if ($available['kd_poli'] === $row['kd_poli'] && $available['kd_dokter'] === $row['kd_dokter'] && $this->practiceTime($available) === $row['jampraktek']) {
                $row['kuota'] = $available['kuota'];
            }
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat WHERE r.kd_dokter = ? AND r.kd_poli = ? AND r.tgl_registrasi = ? AND b.jampraktek = ? AND r.stts <> ?');
        $count->execute([$row['kd_dokter'], $row['kd_poli'], $tanggal, $row['jampraktek'], 'Batal']);
        $total = (int) $count->fetchColumn();
        $this->ok([
            'nomorantrean' => $this->queueNumber($kodePoli, $row['no_reg']),
            'namapoli' => $row['nm_poli'],
            'sisaantrean' => max(0, (int) $row['kuota'] - $total),
            'antreanpanggil' => $this->calledNumber($pdo, $row, $tanggal, $kodePoli),
            'keterangan' => ''
        ]);
    }

    public function getBatalAntrean()
    {
        $this->prepareResponse();
        $this->beginAudit('cancel');
        $this->requireMethod('PUT');
        $this->authorize();
        $input = $this->jsonBody();
        foreach (['nomorkartu', 'kodepoli', 'tanggalperiksa'] as $key) {
            if ($this->value($input, $key) === '') { $this->fail($key.' tidak boleh kosong.', 201, 422); }
        }
        if (!preg_match('/^\\d{13}$/', $input['nomorkartu'])) { $this->fail('Format nomor kartu tidak sesuai.', 201, 422); }
        $this->validateDate($input['tanggalperiksa'], true, false);
        $pdo = $this->db()->pdo();
        $antrolAttempted = false;
        $antrolSucceeded = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT r.no_rawat, r.stts FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat JOIN pasien ps ON ps.no_rkm_medis = r.no_rkm_medis WHERE ps.no_peserta = ? AND b.kodepoli = ? AND r.tgl_registrasi = ? ORDER BY (r.stts = \'Belum\') DESC, r.no_rawat DESC LIMIT 1 FOR UPDATE');
            $stmt->execute([$input['nomorkartu'], $input['kodepoli'], $input['tanggalperiksa']]);
            $visit = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$visit) { throw new \DomainException('Antrean tidak ditemukan.'); }
            if ($visit['stts'] !== 'Belum') { throw new \DomainException('Pasien sudah diproses, antrean tidak dapat dibatalkan.'); }
            $update = $pdo->prepare('UPDATE reg_periksa SET stts = ? WHERE no_rawat = ? AND stts = ?');
            $update->execute(['Batal', $visit['no_rawat'], 'Belum']);
            if ($update->rowCount() !== 1) { throw new \DomainException('Status antrean berubah, silakan cek kembali.'); }
            $antrolAttempted = $this->setting('antrol_enabled', '0') === '1';
            $antrolResult = $this->notifyAntrolBatal($input);
            $antrolSucceeded = $antrolResult !== null;
            $pdo->commit();
            if ($antrolSucceeded) {
                $this->audit('antrol_batal', $visit['no_rawat'], 200, 'BPJS menerima antrean/batal: '.($antrolResult['message'] ?: 'OK'));
            }
            $this->audit('cancel', $visit['no_rawat'], 200, 'Antrean dibatalkan');
            $this->ok(null);
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->audit('cancel', '', 201, $e->getMessage());
            $this->fail($e->getMessage(), 201, 422);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($antrolAttempted && !$antrolSucceeded) {
                $message = $this->safeOutboundMessage($e->getMessage());
                $this->audit('antrol_batal', isset($visit['no_rawat']) ? $visit['no_rawat'] : '', 201, $message, 502, 'error');
                $this->audit('cancel', isset($visit['no_rawat']) ? $visit['no_rawat'] : '', 201, 'Pembatalan lokal diurungkan karena sinkronisasi BPJS gagal.', 502, 'error');
                $this->fail('Pembatalan belum disimpan karena sinkronisasi BPJS gagal: '.$message, 201, 502);
            }
            $this->fail('Pembatalan antrean tidak dapat diproses.', 201, 500);
        }
    }

    public function getPeserta()
    {
        $this->prepareResponse();
        $this->beginAudit('patient');
        $this->requireMethod('POST');
        $this->authorize();
        $input = $this->jsonBody();
        $this->validatePatientInput($input);
        $pdo = $this->db()->pdo();
        $settings = $this->allSettings();
        try { $references = $this->patientReferences($pdo, $input); }
        catch (\DomainException $e) { $this->fail($e->getMessage(), 201, 422); }
        try {
            $pdo->beginTransaction();
            $duplicate = $pdo->prepare('SELECT no_rkm_medis FROM pasien WHERE no_peserta = ? OR no_ktp = ? FOR UPDATE');
            $duplicate->execute([$input['nomorkartu'], $input['nik']]);
            if ($duplicate->fetch(\PDO::FETCH_ASSOC)) { throw new \DomainException('Data pasien ini sudah terdaftar.'); }
            $counter = $pdo->query('SELECT no_rkm_medis FROM set_no_rkm_medis LIMIT 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
            if (!$counter || !preg_match('/^\\d+$/', (string) $counter['no_rkm_medis'])) { throw new \RuntimeException('Counter nomor RM tidak tersedia.'); }
            $norm = sprintf('%06d', ((int) $counter['no_rkm_medis']) + 1);
            $insert = $pdo->prepare('INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, nm_ibu, alamat, gol_darah, pekerjaan, stts_nikah, agama, tgl_daftar, no_tlp, umur, pnd, keluarga, namakeluarga, kd_pj, no_peserta, kd_kel, kd_kec, kd_kab, pekerjaanpj, alamatpj, kelurahanpj, kecamatanpj, kabupatenpj, perusahaan_pasien, suku_bangsa, bahasa_pasien, cacat_fisik, email, nip, kd_prop, propinsipj) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$norm, $input['nama'], $input['nik'], $input['jeniskelamin'], '-', $input['tanggallahir'], '-', $input['alamat'], '-', '-', 'BELUM MENIKAH', '-', date('Y-m-d'), $input['nohp'], $this->ageText($input['tanggallahir']), '-', 'AYAH', '-', $this->setting('payer_code', 'BPJ'), $input['nomorkartu'], $references['kd_kel'], $references['kd_kec'], $references['kd_kab'], '-', $input['alamat'], $input['namakel'], $input['namakec'], $input['namadati2'], $settings['perusahaan_pasien'], (int) $settings['suku_bangsa'], (int) $settings['bahasa_pasien'], (int) $settings['cacat_fisik'], '', '', $references['kd_prop'], $input['namaprop']]);
            $update = $pdo->prepare('UPDATE set_no_rkm_medis SET no_rkm_medis = ?');
            $update->execute([$norm]);
            $pdo->commit();
            $this->audit('patient', '', 200, 'Pasien baru terdaftar');
            $this->ok(['norm' => $norm]);
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->fail($e->getMessage(), 201, 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->fail('Pendaftaran pasien tidak dapat diproses.', 201, 500);
        }
    }

    private function prepareResponse()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $origin = trim((string) $this->setting('allowed_origin', ''));
        if ($origin !== '' && $this->header('origin') === $origin) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Vary: Origin');
        }
    }

    private function requireMethod($method)
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== $method) {
            header('Allow: '.$method);
            $this->fail('Method tidak diizinkan.', 201, 405);
        }
    }

    private function authorize()
    {
        $username = $this->header('x-username');
        if (!hash_equals((string) $this->setting('username', ''), $username) || !$this->verifyToken($this->header('x-token'), $username)) {
            $this->fail('Access denied', 201, 401);
        }
    }

    private function credentialsValid($username, $password)
    {
        $configuredUser = (string) $this->setting('username', '');
        $configuredPassword = (string) $this->setting('password', '');
        return $configuredUser !== '' && $configuredPassword !== ''
            && hash_equals($configuredUser, $username) && hash_equals($configuredPassword, $password);
    }

    private function header($name)
    {
        $wanted = strtolower($name);
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $wanted) { return trim((string) $value); }
        }
        $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$serverKey]) ? trim((string) $_SERVER[$serverKey]) : '';
    }

    private function jsonBody()
    {
        $raw = trim((string) file_get_contents('php://input'));
        $data = json_decode($raw, true);
        if ($raw === '' || !is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            $this->fail('Request harus berupa JSON yang valid.', 201, 400);
        }
        return $data;
    }

    private function ok($response)
    {
        $body = ['metadata' => ['message' => 'Ok', 'code' => 200]];
        if ($response !== null) { $body = ['response' => $response] + $body; }
        if (!$this->audited) {
            $this->audit($this->auditAction, $this->auditNoRawat, 200, 'Permintaan berhasil diproses', 200, 'success');
        }
        http_response_code(200);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function fail($message, $metadataCode = 201, $httpCode = 400)
    {
        if (!$this->audited) {
            $outcome = $httpCode >= 500 ? 'error' : 'rejected';
            $this->audit($this->auditAction, $this->auditNoRawat, $metadataCode, $message, $httpCode, $outcome);
        }
        http_response_code($httpCode);
        echo json_encode(['metadata' => ['message' => $message, 'code' => $metadataCode]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function makeToken($username)
    {
        $now = time();
        $ttl = min(3600, max(60, (int) $this->setting('token_ttl_seconds', '300')));
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64Url(json_encode(['sub' => $username, 'aud' => self::AUDIENCE, 'iat' => $now, 'exp' => $now + $ttl]));
        $signature = $this->base64Url(hash_hmac('sha256', $header.'.'.$payload, $this->tokenSecret(), true));
        return $header.'.'.$payload.'.'.$signature;
    }

    private function verifyToken($token, $username)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $this->tokenSecret() === '') { return false; }
        $expected = $this->base64Url(hash_hmac('sha256', $parts[0].'.'.$parts[1], $this->tokenSecret(), true));
        if (!hash_equals($expected, $parts[2])) { return false; }
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return is_array($payload)
            && ($payload['aud'] ?? '') === self::AUDIENCE
            && ($payload['sub'] ?? '') === $username
            && isset($payload['iat'], $payload['exp'])
            && (int) $payload['iat'] <= time()
            && (int) $payload['exp'] >= time();
    }

    private function tokenSecret()
    {
        return trim((string) $this->setting('token_secret', ''));
    }

    private function base64Url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode($value)
    {
        $padding = strlen($value) % 4;
        if ($padding) { $value .= str_repeat('=', 4 - $padding); }
        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }

    private function validateBookingInput($input)
    {
        foreach (['nomorkartu', 'nik', 'nohp', 'kodepoli', 'tanggalperiksa', 'keluhan', 'kodedokter', 'jampraktek', 'norm'] as $key) {
            if ($this->value($input, $key) === '') { $this->fail($key.' tidak boleh kosong.', 201, 422); }
        }
        if (!preg_match('/^\\d{13}$/', $input['nomorkartu'])) { $this->fail('Nomor kartu harus 13 digit.', 201, 422); }
        if (!preg_match('/^\\d{16}$/', $input['nik'])) { $this->fail('NIK harus 16 digit.', 201, 422); }
        if (!preg_match('/^[0-9+() -]{6,40}$/', $input['nohp'])) { $this->fail('Format nomor HP tidak sesuai.', 201, 422); }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $input['kodepoli'])) { $this->fail('Format kode poli tidak sesuai.', 201, 422); }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $input['kodedokter'])) { $this->fail('Format kode dokter tidak sesuai.', 201, 422); }
        if (!preg_match('/^((?:[01][0-9]|2[0-3]):[0-5][0-9])-((?:[01][0-9]|2[0-3]):[0-5][0-9])$/', $input['jampraktek'], $clock)
            || $clock[1] >= $clock[2]) {
            $this->fail('Jam praktek harus HH:MM-HH:MM dengan waktu mulai sebelum waktu selesai.', 201, 422);
        }
        $this->validateDate($input['tanggalperiksa']);
        if (strlen($input['keluhan']) > 255) { $this->fail('Keluhan maksimal 255 karakter.', 201, 422); }
    }

    private function validatePatientInput($input)
    {
        foreach (['nomorkartu', 'nik', 'nomorkk', 'nama', 'jeniskelamin', 'tanggallahir', 'alamat', 'kodeprop', 'namaprop', 'kodedati2', 'namadati2', 'kodekec', 'namakec', 'kodekel', 'namakel', 'rw', 'rt', 'nohp'] as $key) {
            if ($this->value($input, $key) === '') { $this->fail($key.' tidak boleh kosong.', 201, 422); }
        }
        foreach (['nomorkartu' => 13, 'nik' => 16, 'nomorkk' => 16] as $field => $length) {
            if (!preg_match('/^\\d{'.$length.'}$/', $input[$field])) { $this->fail($field.' harus '.$length.' digit.', 201, 422); }
        }
        if (!in_array($input['jeniskelamin'], ['L', 'P'], true)) { $this->fail('Jenis kelamin harus L atau P.', 201, 422); }
        $this->validateDate($input['tanggallahir'], false);
        if ($input['tanggallahir'] > date('Y-m-d')) { $this->fail('Tanggal lahir tidak boleh di masa depan.', 201, 422); }
        if (!preg_match('/^[0-9A-Za-z .\\/-]{1,10}$/', $input['rw']) || !preg_match('/^[0-9A-Za-z .\\/-]{1,10}$/', $input['rt'])) {
            $this->fail('Format RW atau RT tidak sesuai.', 201, 422);
        }
        foreach (['kodeprop', 'kodedati2', 'kodekec', 'kodekel'] as $field) {
            if (!preg_match('/^\d{1,12}$/', $input[$field])) { $this->fail($field.' harus berupa kode angka BPJS.', 201, 422); }
        }
        foreach (['namaprop', 'namadati2', 'namakec', 'namakel'] as $field) {
            if (mb_strlen($input[$field]) > 60) { $this->fail($field.' maksimal 60 karakter.', 201, 422); }
        }
    }

    private function validateDate($date, $notPast = true, $enforceBookingHorizon = true)
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $parsed->format('Y-m-d') !== $date) {
            $this->fail('Format tanggal harus yyyy-mm-dd.', 201, 422);
        }
        if ($notPast && $date < date('Y-m-d')) { $this->fail('Tanggal periksa tidak boleh mundur.', 201, 422); }
        if ($notPast && $enforceBookingHorizon) {
            $limit = date('Y-m-d', strtotime('+'.max(0, (int) $this->setting('booking_open_days', '30')).' days'));
            if ($date > $limit) { $this->fail('Tanggal periksa melewati batas booking.', 201, 422); }
        }
    }

    private function schedule($kodePoli, $kodeDokter, $date, $practice)
    {
        return SchedulePolicy::selectSlot($this->schedulesForPoli($kodePoli, $date), $kodeDokter, $practice);
    }

    private function schedulesForPoli($kodePoli, $date)
    {
        try {
            $pdo = $this->db()->pdo();
            if (!ScheduleRepository::tableExists($pdo)) { throw new \RuntimeException('Tabel jadwal Dev belum terpasang.'); }
            return ScheduleRepository::schedules($pdo, SchedulePolicy::catalogs($pdo), $date, $kodePoli);
        } catch (\RuntimeException $e) {
            $pdo = $this->db()->pdo();
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->fail('Pengaturan jadwal online tidak tersedia. Hubungi administrator.', 201, 503);
        }
    }

    private function antrolClient()
    {
        $config = $this->antrolConfig();
        return new BpjsScheduleReference($config);
    }

    private function antrolConfig()
    {
        // Primary: PCare module. Override: dev module settings.
        $consumerID     = trim((string) $this->settings->get('pcare.consumerID'));
        $consumerSecret = trim((string) $this->settings->get('pcare.consumerSecret'));
        $userKeyAntrol  = trim((string) $this->settings->get('pcare.consumerUserKeyAntrol'));
        $url            = trim((string) $this->setting('antrol_url', ''));
        
        if (!$url) {
            $pcareUrl = trim((string) $this->settings->get('pcare.PCareApiUrl'));
            if ($pcareUrl && strpos($pcareUrl, 'dev') !== false) {
                $url = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/';
            } else {
                $url = 'https://apijkn.bpjs-kesehatan.go.id/antreanfktp/';
            }
        }
        
        return [
            'antrol_url'             => $url,
            'antrol_consumer_id'     => $consumerID,
            'antrol_consumer_secret' => $consumerSecret,
            'antrol_user_key'        => $userKeyAntrol,
        ];
    }

    /** When enabled, BPJS acceptance is mandatory before the local booking transaction commits. */
    private function notifyAntrolAdd($patient, $input, $lockedSchedule, $noReg, $noRawat, $reopened)
    {
        if ($this->setting('antrol_enabled', '0') !== '1') { return null; }
        $client = $this->antrolClient();
        $payload = json_encode([
                'nomorkartu'   => $input['nomorkartu'],
                'nik'          => $input['nik'],
                'nohp'         => $input['nohp'],
                'kodepoli'     => $input['kodepoli'],
                'namapoli'     => $lockedSchedule['nm_poli'],
                'norm'         => $patient['no_rkm_medis'],
                'tanggalperiksa' => $input['tanggalperiksa'],
                'kodedokter'   => is_numeric($input['kodedokter']) ? (int) $input['kodedokter'] : $input['kodedokter'],
                'namadokter'   => $lockedSchedule['nm_dokter'],
                'jampraktek'   => $this->practiceTime($lockedSchedule),
                'nomorantrean' => $this->queueNumber($input['kodepoli'], $noReg),
                'angkaantrean' => (int) $noReg,
                'keterangan'   => $reopened ? 'Antrean dibuka kembali.' : ''
            ], JSON_UNESCAPED_UNICODE);
        return $client->postJson('antrean/add', $payload);
    }

    /** When enabled, BPJS acceptance is mandatory before the local cancellation commits. */
    private function notifyAntrolBatal($input)
    {
        if ($this->setting('antrol_enabled', '0') !== '1') { return null; }
        $client = $this->antrolClient();
        $payload = json_encode([
                'tanggalperiksa' => $input['tanggalperiksa'],
                'kodepoli'       => $input['kodepoli'],
                'nomorkartu'     => $input['nomorkartu'],
                'alasan'         => 'Pembatalan melalui Mobile JKN'
            ], JSON_UNESCAPED_UNICODE);
        return $client->postJson('antrean/batal', $payload);
    }

    private function lockSchedule(\PDO $pdo, $schedule)
    {
        // All online shifts of this poli share a stable lock without editing master jadwal.
        $stmt = $pdo->prepare('SELECT kd_poli FROM poliklinik WHERE kd_poli = ? AND status = ? FOR UPDATE');
        $stmt->execute([$schedule['kd_poli'], '1']);
        if (!$stmt->fetchColumn()) { return null; }
        foreach ($this->schedulesForPoli($schedule['kd_poli_pcare'], $schedule['tanggal']) as $current) {
            if ($current['kd_poli'] === $schedule['kd_poli'] && $current['kd_dokter'] === $schedule['kd_dokter'] && $this->practiceTime($current) === $this->practiceTime($schedule)) { return $current; }
        }
        return null;
    }

    private function lockPatient(\PDO $pdo, $norm)
    {
        $stmt = $pdo->prepare('SELECT no_rkm_medis FROM pasien WHERE no_rkm_medis = ? FOR UPDATE');
        $stmt->execute([$norm]);
        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) { throw new \DomainException('Data pasien tidak ditemukan.'); }
    }

    private function patientByCard($card)
    {
        $stmt = $this->db()->pdo()->prepare('SELECT * FROM pasien WHERE no_peserta = ? LIMIT 1');
        $stmt->execute([$card]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function patientHistory(\PDO $pdo, $norm, $poli)
    {
        $history = $pdo->prepare('SELECT COUNT(*) AS registered_before, SUM(kd_poli = ?) AS poli_before FROM reg_periksa WHERE no_rkm_medis = ?');
        $history->execute([$poli, $norm]);
        $row = $history->fetch(\PDO::FETCH_ASSOC);
        return ['registered_before' => (int) $row['registered_before'] > 0, 'poli_before' => (int) $row['poli_before'] > 0];
    }

    private function registrationFee(\PDO $pdo, $poli, $old)
    {
        $stmt = $pdo->prepare('SELECT registrasi, registrasilama FROM poliklinik WHERE kd_poli = ?');
        $stmt->execute([$poli]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? ($old ? $row['registrasilama'] : $row['registrasi']) : 0;
    }

    private function calledNumber(\PDO $pdo, $schedule, $date, $kodePoli)
    {
        $stmt = $pdo->prepare('SELECT r.no_reg FROM reg_periksa r JOIN mlite_jkn_mobile_fktp_dev_booking b ON b.no_rawat = r.no_rawat WHERE r.kd_dokter = ? AND r.kd_poli = ? AND r.tgl_registrasi = ? AND b.jampraktek = ? AND r.stts = ? ORDER BY r.no_reg ASC LIMIT 1');
        $stmt->execute([$schedule['kd_dokter'], $schedule['kd_poli'], $date, $this->practiceTime($schedule), 'Berkas Diterima']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $this->queueNumber($kodePoli, $row['no_reg'] ?? '0');
    }

    private function queueNumber($kodePoli, $noReg)
    {
        $format = $this->setting('queue_format', '{kodepoli}-{nomor}');
        return str_replace(['{kodepoli}', '{nomor}'], [$kodePoli, (string) ((int) $noReg)], $format);
    }

    private function practiceTime($row)
    {
        return substr((string) $row['jam_mulai'], 0, 5).'-'.substr((string) $row['jam_selesai'], 0, 5);
    }

    private function dayName($date)
    {
        return ['Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU'][date('D', strtotime($date))];
    }

    private function ageValue($birth, $date)
    {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $birth) || $birth === '0000-00-00') { return 0; }
        $diff = (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable($date));
        return $diff->y > 0 ? $diff->y : ($diff->m > 0 ? $diff->m : $diff->d);
    }

    private function ageUnit($birth, $date)
    {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $birth) || $birth === '0000-00-00') { return 'Th'; }
        $diff = (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable($date));
        return $diff->y > 0 ? 'Th' : ($diff->m > 0 ? 'Bl' : 'Hr');
    }

    private function ageText($birth)
    {
        $diff = (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable('today'));
        return $diff->y.' Th '.$diff->m.' Bl '.$diff->d.' Hr';
    }

    private function value($input, $key, $default = '')
    {
        return isset($input[$key]) && !is_array($input[$key]) ? trim((string) $input[$key]) : $default;
    }

    private function setting($field, $default)
    {
        $value = $this->settings->get(self::MODULE.'.'.$field);
        return $value === null || $value === false || $value === '' ? $default : $value;
    }

    private function allSettings()
    {
        return array_merge(['perusahaan_pasien' => '-', 'suku_bangsa' => 1, 'bahasa_pasien' => 1, 'cacat_fisik' => 1], (array) $this->settings(self::MODULE));
    }

    private function patientReferences(\PDO $pdo, array $input)
    {
        return PatientRegionResolver::resolve($pdo, $input);
    }

    private function safeOutboundMessage($message)
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', trim((string) $message));
        return mb_substr($message !== '' ? $message : 'BPJS tidak memberikan alasan penolakan.', 0, 220);
    }

    private function beginAudit($action)
    {
        $this->auditAction = $action;
        $this->auditNoRawat = '';
        $this->audited = false;
        try {
            $this->auditRequestId = bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            $this->auditRequestId = uniqid('', true);
        }
    }

    private function audit($action, $noRawat, $metadataCode, $message, $httpCode = null, $outcome = null)
    {
        $this->audited = true;
        $this->auditNoRawat = (string) $noRawat;
        $httpCode = $httpCode === null ? ((int) $metadataCode === 200 ? 200 : 422) : (int) $httpCode;
        $outcome = $outcome ?: ($httpCode === 200 ? 'success' : ($httpCode >= 500 ? 'error' : 'rejected'));
        try {
            $stmt = $this->db()->pdo()->prepare('INSERT INTO mlite_jkn_mobile_fktp_dev_log (request_id, action, endpoint, method, no_rawat, metadata_code, http_code, outcome, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                substr($this->auditRequestId, 0, 32), $action, $this->endpointForAction($action),
                strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')), substr((string) $noRawat, 0, 25),
                (int) $metadataCode, $httpCode, $outcome, substr((string) $message, 0, 255), date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Audit must not alter the public API result.
        }
    }

    private function endpointForAction($action)
    {
        return [
            'auth' => '/auth', 'patient' => '/peserta', 'booking' => '/antrean',
            'status' => '/antrean/status', 'participant_queue' => '/antrean/sisapeserta',
            'cancel' => '/antrean/batal', 'antrol_add' => '/bpjs/antrean/add',
            'antrol_panggil' => '/bpjs/antrean/panggil', 'antrol_batal' => '/bpjs/antrean/batal',
            'integrity_cleanup' => '/integrity/booking'
        ][$action] ?? '';
    }

}
