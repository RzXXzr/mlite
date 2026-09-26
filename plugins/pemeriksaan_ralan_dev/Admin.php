<?php
namespace Plugins\Pemeriksaan_Ralan_Dev;

use Systems\AdminModule;
use Systems\Lib\PcareService;

class Admin extends AdminModule
{
    public function navigation()
    {
        return [
            'Beranda' => 'index',
            'Antrean Pemeriksaan' => 'manage'
        ];
    }

    public function getIndex()
    {
        $this->addStyleFile();
        $fktpActive = $this->fktpModuleActive();
        $fktpPayer = $fktpActive ? trim((string) $this->settings->get('jkn_mobile_fktp.kd_pj')) : '';
        $pcareActive = false;
        try {
            $pcareActive = (bool) $this->core->ActiveModule('pcare');
        } catch (\Throwable $e) {
            $pcareActive = false;
        }
        if (!$fktpActive) {
            $fktpState = ['class' => 'inactive', 'label' => 'Tidak aktif',
                'note' => 'Alur pemeriksaan lokal tetap dapat digunakan.'];
        } elseif ($fktpPayer === '') {
            $fktpState = ['class' => 'attention', 'label' => 'Perlu konfigurasi',
                'note' => 'Kode penjamin FKTP belum ditentukan di modul JKN Mobile FKTP.'];
        } else {
            $fktpState = ['class' => 'ready', 'label' => 'Siap',
                'note' => 'Penjamin '.$fktpPayer.' terhubung ke alur Antrean FKTP.'];
        }
        return $this->draw('index.html', [
            'manage_url' => url([ADMIN, 'pemeriksaan_ralan_dev', 'manage']),
            'fktp' => $fktpState,
            'pcare_active' => $pcareActive,
            'websocket_enabled' => $this->settings->get('settings.websocket') === 'ya',
            'jkn_settings_url' => $this->isAdmin() && $fktpActive
                ? url([ADMIN, 'jkn_mobile_fktp', 'settings']) : ''
        ]);
    }

    public function anyManage()
    {
        $this->addHeaderFiles();
        return $this->draw('manage.html', $this->viewData());
    }

    public function anyDisplay()
    {
        echo $this->draw('display.html', $this->viewData());
        exit();
    }

    public function postStatusKunjungan()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $hasExamination = (bool) $this->db('pemeriksaan_ralan')->where('no_rawat', $visit['no_rawat'])->oneArray();
        $this->respond(['status' => 'success', 'data' => [
            'visit_status' => $visit['stts'],
            'needs_arrival_decision' => $visit['stts'] === 'Belum',
            'has_examination' => $hasExamination,
            'can_create' => $visit['stts'] === 'Belum',
            'can_edit' => in_array($visit['stts'], ['Belum', 'Berkas Dikirim'], true),
            'can_cancel' => $visit['stts'] === 'Belum'
        ]]);
    }

    public function postInformasiPasien()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $patient = $this->db('pasien')->select(['no_rkm_medis', 'nm_pasien', 'tgl_lahir', 'gol_darah', 'no_peserta', 'no_ktp'])
            ->where('no_rkm_medis', $visit['no_rkm_medis'])->oneArray();
        if (!$patient) {
            $this->respondError('Identitas pasien tidak ditemukan.', 404);
        }
        $payer = $this->db('penjab')->where('kd_pj', $visit['kd_pj'])->oneArray();
        $payerName = $payer['png_jawab'] ?? '';
        $bpjsCode = $this->fktpModuleActive() ? trim((string) $this->settings->get('jkn_mobile_fktp.kd_pj')) : '';
        $isBpjs = $bpjsCode !== '' ? $visit['kd_pj'] === $bpjsCode
            : ($visit['kd_pj'] === 'BPJ' || preg_match('/BPJS|JKN/i', $payerName) === 1);
        $birth = (string) ($patient['tgl_lahir'] ?? '');
        $age = 'Belum tercatat';
        $birthLabel = 'Belum tercatat';
        if ($this->isValidDate($birth) && $birth <= date('Y-m-d')) {
            $dob = new \DateTimeImmutable($birth);
            $diff = $dob->diff(new \DateTimeImmutable('today'));
            $age = $diff->y.' th '.$diff->m.' bl '.$diff->d.' hr';
            $birthLabel = $dob->format('d-m-Y');
        }
        $card = trim((string) ($patient['no_peserta'] ?? ''));
        $nik = trim((string) ($patient['no_ktp'] ?? ''));
        $lookup = ['url' => null, 'type' => null, 'message' => 'Penjamin kunjungan ini bukan BPJS.'];
        if ($isBpjs) {
            if (!$this->core->ActiveModule('pcare')) {
                $lookup['message'] = 'Modul PCare belum aktif. Kepesertaan belum diperiksa.';
            } elseif (!preg_match('/^\d{13}$/', $card) && !preg_match('/^\d{16}$/', $nik)) {
                $lookup['message'] = 'Nomor kartu BPJS / NIK belum lengkap. Perbarui data pasien untuk pengecekan.';
            } else {
                // Reuse the PCare route, including its authentication, configuration and decryption.
                $type = preg_match('/^\d{13}$/', $card) ? 'noka' : 'nik';
                $number = $type === 'noka' ? $card : $nik;
                $lookup = ['url' => url([ADMIN, 'pcare', 'byjeniskartu', $type, $number]),
                    'type' => $type, 'message' => 'Belum diperiksa'];
            }
        }
        header('Cache-Control: no-store');
        $this->respond(['status' => 'success', 'data' => [
            'no_rkm_medis' => $patient['no_rkm_medis'], 'name' => $patient['nm_pasien'],
            'birth_date' => $birthLabel, 'age' => $age,
            'blood_group' => empty($patient['gol_darah']) || $patient['gol_darah'] === '-' ? 'Belum diketahui' : $patient['gol_darah'],
            'payer' => $payerName !== '' ? $payerName : 'Belum tercatat', 'payer_code' => $visit['kd_pj'],
            'card_number' => $card !== '' && $card !== '-' ? $card : 'Belum tercatat',
            'is_bpjs' => $isBpjs, 'pcare' => $lookup
        ]]);
    }

    public function postRiwayatPemeriksaan()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $records = $this->db('pemeriksaan_ralan')
            ->leftJoin('pegawai', 'pegawai.nik=pemeriksaan_ralan.nip')
            ->where('no_rawat', $visit['no_rawat'])
            ->desc('tgl_perawatan')
            ->desc('jam_rawat')
            ->toArray();
        $username = $this->username();
        $isAdmin = $this->isAdmin();
        foreach ($records as &$record) {
            $ownsRecord = $isAdmin || $record['nip'] === $username;
            $record['can_edit'] = $ownsRecord && in_array($visit['stts'], ['Belum', 'Berkas Dikirim'], true);
            $record['can_delete'] = $ownsRecord && $visit['stts'] === 'Belum';
        }
        echo $this->draw('riwayat.html', ['records' => $records]);
        exit();
    }

    public function postRiwayatPasien()
    {
        // Authorize the active encounter, then derive the patient on the server.
        // Longitudinal history is read-only across departments and encounter types.
        $current = $this->requireVisitAccess($this->post('no_rawat'));
        $page = max(1, (int) $this->post('page', '1'));
        $pageSize = 10;
        $pdo = $this->db()->pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = :rm');
        $count->execute([':rm' => $current['no_rkm_medis']]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $offset = ($page - 1) * $pageSize;
        $query = $pdo->prepare("SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.status_lanjut, r.stts,
                p.nm_poli, d.nm_dokter, j.png_jawab
            FROM reg_periksa r
            LEFT JOIN poliklinik p ON p.kd_poli=r.kd_poli
            LEFT JOIN dokter d ON d.kd_dokter=r.kd_dokter
            LEFT JOIN penjab j ON j.kd_pj=r.kd_pj
            WHERE r.no_rkm_medis=:rm
            ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC, r.no_rawat DESC
            LIMIT $pageSize OFFSET $offset");
        $query->execute([':rm' => $current['no_rkm_medis']]);
        $this->respond(['status' => 'success', 'data' => [
            'visits' => $query->fetchAll(\PDO::FETCH_ASSOC),
            'page' => $page, 'pages' => $pages, 'total' => $total,
            'erm_url' => url([ADMIN, 'pasien', 'riwayatperawatan', $current['no_rkm_medis']])
        ]]);
    }

    public function postDetailRiwayat()
    {
        $current = $this->requireVisitAccess($this->post('no_rawat'));
        $source = $this->db('reg_periksa')
            ->where('no_rawat', $this->post('source_no_rawat'))
            ->where('no_rkm_medis', $current['no_rkm_medis'])->oneArray();
        if (!$source) {
            $this->respondError('Kunjungan riwayat pasien tidak ditemukan.', 404);
        }
        $records = [];
        foreach (['pemeriksaan_ralan' => 'Rawat jalan', 'pemeriksaan_ranap' => 'Rawat inap'] as $table => $label) {
            $rows = $this->db($table)->select($table.'.*')->select('pegawai.nama AS nama_petugas')
                ->leftJoin('pegawai', 'pegawai.nik='.$table.'.nip')
                ->where('no_rawat', $source['no_rawat'])->desc('tgl_perawatan')->desc('jam_rawat')->toArray();
            foreach ($rows as $row) {
                $row['jenis'] = $label;
                $row['can_copy'] = $table === 'pemeriksaan_ralan' && $current['stts'] === 'Belum';
                $records[] = $row;
            }
        }
        usort($records, function ($a, $b) {
            return strcmp($b['tgl_perawatan'].' '.$b['jam_rawat'], $a['tgl_perawatan'].' '.$a['jam_rawat']);
        });
        $diagnoses = $this->db('diagnosa_pasien')->select('diagnosa_pasien.kd_penyakit')->select('penyakit.nm_penyakit')
            ->leftJoin('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
            ->where('no_rawat', $source['no_rawat'])->asc('prioritas')->toArray();
        $procedures = $this->db('prosedur_pasien')->select('prosedur_pasien.kode')->select('icd9.deskripsi_panjang')
            ->leftJoin('icd9', 'icd9.kode=prosedur_pasien.kode')
            ->where('no_rawat', $source['no_rawat'])->asc('prioritas')->toArray();
        // Include regular medicines from every prescription, not only the first one.
        $medicines = $this->db('resep_obat')->select('resep_obat.no_resep')->select('databarang.nama_brng')
            ->select('resep_dokter.jml')->select('resep_dokter.aturan_pakai')
            ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
            ->leftJoin('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
            ->where('resep_obat.no_rawat', $source['no_rawat'])->toArray();
        $this->respond(['status' => 'success', 'data' => [
            'no_rawat' => $source['no_rawat'], 'records' => $records,
            'diagnoses' => $diagnoses, 'procedures' => $procedures, 'medicines' => $medicines
        ]]);
    }

    public function postSavePemeriksaan()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $username = $this->username();
        $originalDate = $this->post('original_tgl_perawatan');
        $originalTime = $this->post('original_jam_rawat');
        $isEdit = $originalDate !== '' && $originalTime !== '';

        if ($isEdit) {
            if (!$this->isValidDate($originalDate) || !$this->isValidTime($originalTime)) {
                $this->respondError('Identitas catatan pemeriksaan tidak valid.');
            }
            $existing = $this->db('pemeriksaan_ralan')
                ->where('no_rawat', $visit['no_rawat'])
                ->where('tgl_perawatan', $originalDate)
                ->where('jam_rawat', $originalTime)
                ->oneArray();
            if (!$existing) {
                $this->respondError('Catatan pemeriksaan tidak ditemukan.', 404);
            }
            if (!$this->isAdmin() && $existing['nip'] !== $username) {
                $this->respondError('Anda hanya dapat mengubah catatan milik sendiri.', 403);
            }
            $date = $originalDate;
            $time = $originalTime;
            $nip = $existing['nip'];
        } else {
            $date = date('Y-m-d');
            $time = date('H:i:s');
            $nip = $username;
        }

        $data = $this->validatedPemeriksaan($visit['no_rawat'], $date, $time, $nip);
        $this->ensureWorkflowTables();
        $pdo = $this->db()->pdo();
        $transitioned = false;
        try {
            $pdo->beginTransaction();
            $lockedVisit = $this->lockVisit($visit['no_rawat']);
            if (!$lockedVisit) {
                throw new \DomainException('Kunjungan rawat jalan tidak ditemukan.');
            }
            $allowed = $isEdit ? ['Belum', 'Berkas Dikirim'] : ['Belum'];
            if (!in_array($lockedVisit['stts'], $allowed, true)) {
                throw new \DomainException($isEdit
                    ? 'Catatan hanya dapat dikoreksi saat status Belum atau Berkas Dikirim.'
                    : 'Pemeriksaan baru hanya dapat disimpan saat status kunjungan Belum.');
            }
            if ($isEdit) {
                $lockedRecord = $this->db('pemeriksaan_ralan')
                    ->where('no_rawat', $visit['no_rawat'])
                    ->where('tgl_perawatan', $date)
                    ->where('jam_rawat', $time)->oneArray();
                if (!$lockedRecord) {
                    throw new \DomainException('Catatan pemeriksaan tidak ditemukan.');
                }
                if (!$this->isAdmin() && $lockedRecord['nip'] !== $username) {
                    throw new \DomainException('Anda hanya dapat mengubah catatan milik sendiri.');
                }
                $this->db('pemeriksaan_ralan')
                    ->where('no_rawat', $visit['no_rawat'])
                    ->where('tgl_perawatan', $date)
                    ->where('jam_rawat', $time)->save($data);
            } else {
                $this->db('pemeriksaan_ralan')->save($data);
            }
            if ($lockedVisit['stts'] === 'Belum') {
                $this->db('reg_periksa')->where('no_rawat', $visit['no_rawat'])->save(['stts' => 'Berkas Dikirim']);
                $this->markFileSent($visit['no_rawat']);
                $this->logStatusTransition($visit['no_rawat'], 'Belum', 'Berkas Dikirim', 'Pemeriksaan awal paramedis disimpan', 'save:'.$visit['no_rawat'].':'.$date.':'.$time);
                $transitioned = true;
            }
            $pdo->commit();
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError('Pemeriksaan tidak dapat disimpan. Pastikan skema status kunjungan mendukung Berkas Dikirim dan coba kembali.', 500);
        }
        $this->respond(['status' => 'success', 'message' => $transitioned
            ? 'Pemeriksaan awal tersimpan dan berkas dikirim ke antrean poli.'
            : 'Koreksi pemeriksaan awal berhasil disimpan.', 'data' => [
            'tgl_perawatan' => $date, 'jam_rawat' => $time, 'visit_status' => 'Berkas Dikirim'
        ]]);
    }

    public function postHapusPemeriksaan()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $date = $this->post('tgl_perawatan');
        $time = $this->post('jam_rawat');
        if (!$this->isValidDate($date) || !$this->isValidTime($time)) {
            $this->respondError('Identitas catatan pemeriksaan tidak valid.');
        }
        $existing = $this->db('pemeriksaan_ralan')
            ->where('no_rawat', $visit['no_rawat'])
            ->where('tgl_perawatan', $date)
            ->where('jam_rawat', $time)
            ->oneArray();
        if (!$existing) {
            $this->respondError('Catatan pemeriksaan tidak ditemukan.', 404);
        }
        if ($visit['stts'] !== 'Belum') {
            $this->respondError('Catatan yang sudah dikirim ke poli tidak dapat dihapus. Gunakan koreksi catatan.', 409);
        }
        if (!$this->isAdmin() && $existing['nip'] !== $this->username()) {
            $this->respondError('Anda hanya dapat menghapus catatan milik sendiri.', 403);
        }
        $pdo = $this->db()->pdo();
        try {
            $pdo->beginTransaction();
            $lockedVisit = $this->lockVisit($visit['no_rawat']);
            if (!$lockedVisit || $lockedVisit['stts'] !== 'Belum') {
                throw new \DomainException('Catatan yang sudah dikirim ke poli tidak dapat dihapus. Gunakan koreksi catatan.');
            }
            $lockedRecord = $this->db('pemeriksaan_ralan')
                ->where('no_rawat', $visit['no_rawat'])
                ->where('tgl_perawatan', $date)
                ->where('jam_rawat', $time)->oneArray();
            if (!$lockedRecord) {
                throw new \DomainException('Catatan pemeriksaan sudah tidak tersedia.');
            }
            if (!$this->isAdmin() && $lockedRecord['nip'] !== $this->username()) {
                throw new \DomainException('Kepemilikan catatan berubah; penghapusan ditolak.');
            }
            $this->db('pemeriksaan_ralan')
                ->where('no_rawat', $visit['no_rawat'])
                ->where('tgl_perawatan', $date)
                ->where('jam_rawat', $time)
                ->delete();
            $pdo->commit();
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError('Catatan pemeriksaan tidak dapat dihapus. Tidak ada perubahan yang disimpan.', 500);
        }
        $this->respond(['status' => 'success', 'message' => 'Catatan pemeriksaan dihapus.']);
    }

    public function postBatalPeriksa()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $reason = trim($this->post('alasan'));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
            $this->respondError('Alasan batal wajib diisi 5 sampai 255 karakter.', 422, ['field' => 'alasan']);
        }
        $this->ensureWorkflowTables();
        $pdo = $this->db()->pdo();
        try {
            $pdo->beginTransaction();
            $lockedVisit = $this->lockVisit($visit['no_rawat']);
            if (!$lockedVisit || $lockedVisit['stts'] !== 'Belum') {
                throw new \DomainException('Hanya kunjungan berstatus Belum yang dapat dibatalkan dari halaman ini.');
            }
            $this->db('reg_periksa')->where('no_rawat', $visit['no_rawat'])->save(['stts' => 'Batal']);
            $this->logStatusTransition($visit['no_rawat'], 'Belum', 'Batal', $reason, 'cancel-status:'.$visit['no_rawat'].':'.str_replace('.', '', (string) microtime(true)));
            $pdo->commit();
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->respondError('Status kunjungan tidak dapat dibatalkan. Tidak ada perubahan yang disimpan.', 500);
        }

        $visit['stts'] = 'Batal';
        $eligibility = $this->fktpEligibility($visit, 'cancel');
        $fktp = ['status' => 'not_required', 'message' => $eligibility['message']];
        if ($eligibility['eligible']) {
            $fktp = $this->sendFktpQueueOperation('cancel', $visit, 'cancel:'.$visit['no_rawat'], $reason);
        }
        $this->respond(['status' => 'success', 'message' => 'Kunjungan dibatalkan.', 'data' => [
            'visit_status' => 'Batal', 'fktp' => $fktp
        ]]);
    }

    public function postAntreanStatus()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $this->ensureWorkflowTables();
        $this->respond(['status' => 'success', 'data' => [
            'integration' => $this->fktpEligibility($visit, 'add'),
            'operations' => $this->queueOperationStates($visit['no_rawat'])
        ]]);
    }

    public function postAntreanTambah()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        if ($visit['stts'] !== 'Belum') {
            $this->respondError('Antrean FKTP hanya dapat ditambahkan saat status kunjungan Belum.', 409);
        }
        $result = $this->sendFktpQueueOperation('add', $visit, 'add:'.$visit['no_rawat']);
        $this->respondQueueResult($result, 'Antrean FKTP berhasil ditambahkan.');
    }

    public function postAntreanPanggil()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        if ($visit['stts'] !== 'Belum') {
            $this->respondError('Panggilan FKTP hanya dapat dikirim saat status kunjungan Belum.', 409);
        }
        if (!$this->hasSuccessfulQueueOperation($visit['no_rawat'], 'add')) {
            $this->respondError('Tambahkan antrean FKTP sebelum mengirim panggilan.', 409);
        }
        $messageId = $this->post('message_id');
        if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $messageId)) {
            $this->respondError('Identitas panggilan tidak valid.');
        }
        $result = $this->sendFktpQueueOperation('call', $visit, 'call:'.$visit['no_rawat'].':'.$messageId);
        $this->respondQueueResult($result, 'Panggilan FKTP berhasil dikirim.');
    }

    public function postAntreanBatalUlang()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        if ($visit['stts'] !== 'Batal') {
            $this->respondError('Pengiriman ulang batal hanya tersedia untuk kunjungan berstatus Batal.', 409);
        }
        $existing = $this->latestQueueOperation($visit['no_rawat'], 'cancel');
        if (!$existing || !in_array($existing['status'], ['failed', 'needs_review'], true)) {
            $this->respondError('Tidak ada pembatalan FKTP yang perlu dikirim ulang.', 409);
        }
        $reason = trim((string) $existing['reason']);
        $result = $this->sendFktpQueueOperation('cancel', $visit, $existing['event_key'], $reason);
        $this->respondQueueResult($result, 'Pembatalan FKTP berhasil dikirim ulang.');
    }

    public function postGetAlergi()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $alergi = $this->db('alergi_pasien')->where('no_rkm_medis', $visit['no_rkm_medis'])->oneArray();
        $this->respond(['status' => 'success', 'data' => $alergi ?: $this->emptyAlergi()]);
    }

    public function postSaveAlergi()
    {
        $this->requireStaff();
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        if (!in_array($visit['stts'], ['Belum', 'Berkas Dikirim'], true)) {
            $this->respondError('Profil alergi tidak dapat diubah dari kunjungan yang sudah ditutup.', 409);
        }
        $data = $this->validatedAlergi();
        $username = $this->username();
        $now = date('Y-m-d H:i:s');
        $existing = $this->db('alergi_pasien')->where('no_rkm_medis', $visit['no_rkm_medis'])->oneArray();
        if ($existing) {
            $data['tgl_update'] = $now;
            $data['nip_update'] = $username;
            $this->db('alergi_pasien')->where('no_rkm_medis', $visit['no_rkm_medis'])->save($data);
        } else {
            $data['no_rkm_medis'] = $visit['no_rkm_medis'];
            $data['tgl_input'] = $now;
            $data['nip_input'] = $username;
            $this->db('alergi_pasien')->save($data);
        }
        $this->respond(['status' => 'success', 'message' => 'Data alergi berhasil disimpan.', 'summary' => $this->alergiSummary($data)]);
    }

    public function postKandidatProlanis()
    {
        $visit = $this->requireVisitAccess($this->post('no_rawat'));
        $rm = $visit['no_rkm_medis'];
        $pdo = $this->db()->pdo();
        $htQ = $pdo->prepare(
            "SELECT DISTINCT dp.kd_penyakit FROM diagnosa_pasien dp
             JOIN reg_periksa r ON r.no_rawat=dp.no_rawat
             WHERE r.no_rkm_medis=:rm AND (dp.kd_penyakit='I10' OR dp.kd_penyakit LIKE 'I11%')
             ORDER BY dp.kd_penyakit LIMIT 20"
        );
        $htQ->execute([':rm' => $rm]);
        $htCodes = $htQ->fetchAll(\PDO::FETCH_COLUMN);
        $dmQ = $pdo->prepare(
            "SELECT DISTINCT dp.kd_penyakit FROM diagnosa_pasien dp
             JOIN reg_periksa r ON r.no_rawat=dp.no_rawat
             WHERE r.no_rkm_medis=:rm
             AND (dp.kd_penyakit LIKE 'E10%' OR dp.kd_penyakit LIKE 'E11%'
               OR dp.kd_penyakit LIKE 'E12%' OR dp.kd_penyakit LIKE 'E13%'
               OR dp.kd_penyakit LIKE 'E14%')
             ORDER BY dp.kd_penyakit LIMIT 20"
        );
        $dmQ->execute([':rm' => $rm]);
        $dmCodes = $dmQ->fetchAll(\PDO::FETCH_COLUMN);
        $this->respond(['status' => 'success', 'data' => [
            'has_ht' => !empty($htCodes), 'ht_codes' => $htCodes,
            'has_dm' => !empty($dmCodes), 'dm_codes' => $dmCodes
        ]]);
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES.'/pemeriksaan_ralan_dev/js/admin/pemeriksaan_ralan_dev.js', [
            'mlite' => [
                'websocket' => $this->settings->get('settings.websocket'),
                'websocket_proxy' => $this->settings->get('settings.websocket_proxy'),
                'fullname' => $this->core->getUserInfo('fullname', null, true)
            ]
        ]);
        exit();
    }

    public function getCss()
    {
        header('Content-type: text/css');
        echo $this->draw(MODULES.'/pemeriksaan_ralan_dev/css/admin/pemeriksaan_ralan_dev.css');
        exit();
    }

    private function viewData()
    {
        $this->ensureWorkflowTables();
        $list = $this->getVisits();
        $waiting = 0;
        $completed = 0;
        foreach ($list as $visit) {
            if ($visit['stts'] === 'Belum') {
                $waiting++;
            }
            if (!empty($visit['sudah_diperiksa'])) {
                $completed++;
            }
        }
        return [
            'list' => $list,
            'summary' => [
                'total' => count($list),
                'waiting' => $waiting,
                'completed' => $completed
            ],
            'websocket_enabled' => $this->settings->get('settings.websocket') === 'ya'
        ];
    }

    private function getVisits()
    {
        $start = $this->requestDate('periode_rawat_jalan', date('Y-m-d'));
        $end = $this->requestDate('periode_rawat_jalan_akhir', $start);
        if ($end < $start) {
            $end = $start;
        }
        $status = isset($_POST['status_periksa']) ? trim((string) $_POST['status_periksa']) : '';
        $igd = $this->settings('settings', 'igd');
        $sql = "SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, r.status_lanjut, r.status_bayar,
                    r.kd_dokter, r.kd_pj, p.no_rkm_medis, p.nm_pasien, p.no_peserta, p.no_ktp, p.no_tlp,
                    poli.kd_poli, poli.nm_poli, d.nm_dokter, pj.png_jawab,
                    EXISTS(SELECT 1 FROM pemeriksaan_ralan pr WHERE pr.no_rawat=r.no_rawat) AS sudah_diperiksa,
                    EXISTS(SELECT 1 FROM mutasi_berkas mb WHERE mb.no_rawat=r.no_rawat
                        AND mb.dikirim > '1000-01-01 00:00:00') AS berkas_tercatat,
                    (r.stts = 'Batal') AS is_batal
                FROM reg_periksa r
                INNER JOIN pasien p ON p.no_rkm_medis=r.no_rkm_medis
                INNER JOIN poliklinik poli ON poli.kd_poli=r.kd_poli
                INNER JOIN dokter d ON d.kd_dokter=r.kd_dokter
                INNER JOIN penjab pj ON pj.kd_pj=r.kd_pj
                WHERE r.status_lanjut='Ralan' AND r.tgl_registrasi BETWEEN :start AND :end";
        $params = [':start' => $start, ':end' => $end];
        if ($igd !== '') {
            $sql .= ' AND r.kd_poli <> :igd';
            $params[':igd'] = $igd;
        }
        $allowedStatuses = $this->visitStatuses();
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $sql .= ' AND r.stts = :visit_status';
            $params[':visit_status'] = $status;
        }
        if (!$this->isAdmin()) {
            $caps = $this->caps();
            if (!$caps) {
                return [];
            }
            $placeholders = [];
            foreach ($caps as $index => $cap) {
                $key = ':cap'.$index;
                $placeholders[] = $key;
                $params[$key] = $cap;
            }
            $sql .= ' AND r.kd_poli IN ('.implode(',', $placeholders).')';
        }
        $sql .= ' ORDER BY (r.no_reg + 0) ASC, r.tgl_registrasi ASC, r.jam_reg ASC';
        $statement = $this->db()->pdo()->prepare($sql);
        $statement->execute($params);
        $visits = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($visits as &$visit) {
            $visit['status_class'] = $this->statusClass($visit['stts']);
            $visit['status_label'] = $this->statusLabel($visit['stts']);
            $visit['is_closed'] = $visit['stts'] !== 'Belum';
            $visit['needs_reconciliation'] = $visit['stts'] === 'Berkas Dikirim'
                && (empty($visit['sudah_diperiksa']) || empty($visit['berkas_tercatat']));
            $visit['can_call'] = $visit['stts'] === 'Belum';
            $visit['action_label'] = $visit['stts'] === 'Belum'
                ? 'Periksa'
                : ($visit['stts'] === 'Berkas Dikirim' && !empty($visit['sudah_diperiksa']) ? 'Koreksi TTV' : 'Lihat SOAP');
            $visit['fktp'] = $this->fktpEligibility($visit, 'add');
            $states = $this->queueOperationStates($visit['no_rawat']);
            $visit['fktp_add_status'] = isset($states['add']['status']) ? $states['add']['status'] : 'not_sent';
            $visit['fktp_cancel_status'] = isset($states['cancel']['status']) ? $states['cancel']['status'] : 'not_sent';
            $queueLabels = ['not_sent' => 'belum dikirim', 'pending' => 'diproses', 'success' => 'terdaftar',
                'failed' => 'gagal', 'needs_review' => 'perlu ditinjau'];
            $visit['fktp_add_label'] = isset($queueLabels[$visit['fktp_add_status']])
                ? $queueLabels[$visit['fktp_add_status']] : $visit['fktp_add_status'];
            $visit['fktp_can_add'] = $visit['stts'] === 'Belum' && $visit['fktp']['eligible'] && $visit['fktp_add_status'] !== 'success';
            $visit['fktp_can_retry_cancel'] = $visit['stts'] === 'Batal' && in_array($visit['fktp_cancel_status'], ['failed', 'needs_review'], true);
        }
        unset($visit);
        return $visits;
    }

    private function visitStatuses()
    {
        return ['Belum', 'Berkas Dikirim', 'Berkas Diterima', 'Sudah', 'Batal', 'Dirujuk', 'Meninggal', 'Dirawat', 'Pulang Paksa'];
    }

    private function statusClass($status)
    {
        $classes = [
            'Belum' => 'waiting', 'Berkas Dikirim' => 'sent', 'Berkas Diterima' => 'received',
            'Sudah' => 'done', 'Batal' => 'cancelled', 'Dirujuk' => 'referred',
            'Meninggal' => 'critical', 'Dirawat' => 'admitted', 'Pulang Paksa' => 'warning'
        ];
        return isset($classes[$status]) ? $classes[$status] : 'neutral';
    }

    private function statusLabel($status)
    {
        $labels = ['Belum' => 'Belum Periksa', 'Sudah' => 'Sudah Periksa', 'Batal' => 'Batal Periksa',
            'Dirujuk' => 'Pasien Dirujuk'];
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    private function ensureWorkflowTables()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $pdo = $this->db()->pdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS mlite_pemeriksaan_ralan_dev_status_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT, no_rawat VARCHAR(17) NOT NULL,
                from_status VARCHAR(40) NOT NULL, to_status VARCHAR(40) NOT NULL,
                reason VARCHAR(255) NOT NULL DEFAULT '', actor_nip VARCHAR(20) NOT NULL,
                event_key VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS mlite_pemeriksaan_ralan_dev_antrean (
                id INTEGER PRIMARY KEY AUTOINCREMENT, no_rawat VARCHAR(17) NOT NULL,
                operation VARCHAR(20) NOT NULL, event_key VARCHAR(100) NOT NULL UNIQUE,
                event_at_ms INTEGER, reason VARCHAR(255) NOT NULL DEFAULT '',
                requested_at DATETIME NOT NULL, completed_at DATETIME,
                status VARCHAR(20) NOT NULL, attempt_count INTEGER NOT NULL DEFAULT 1,
                response_code VARCHAR(20) NOT NULL DEFAULT '', response_message VARCHAR(255) NOT NULL DEFAULT '',
                actor_nip VARCHAR(20) NOT NULL, payload_hash VARCHAR(64) NOT NULL DEFAULT ''
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_prd_antrean_visit ON mlite_pemeriksaan_ralan_dev_antrean (no_rawat, operation)');
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `mlite_pemeriksaan_ralan_dev_status_log` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT, `no_rawat` varchar(17) NOT NULL,
                `from_status` varchar(40) NOT NULL, `to_status` varchar(40) NOT NULL,
                `reason` varchar(255) NOT NULL DEFAULT '', `actor_nip` varchar(20) NOT NULL,
                `event_key` varchar(100) NOT NULL, `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`), UNIQUE KEY `uniq_prd_status_event` (`event_key`),
                KEY `idx_prd_status_visit` (`no_rawat`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `mlite_pemeriksaan_ralan_dev_antrean` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT, `no_rawat` varchar(17) NOT NULL,
                `operation` varchar(20) NOT NULL, `event_key` varchar(100) NOT NULL,
                `event_at_ms` bigint DEFAULT NULL, `reason` varchar(255) NOT NULL DEFAULT '',
                `requested_at` datetime NOT NULL, `completed_at` datetime DEFAULT NULL,
                `status` varchar(20) NOT NULL, `attempt_count` int unsigned NOT NULL DEFAULT 1,
                `response_code` varchar(20) NOT NULL DEFAULT '', `response_message` varchar(255) NOT NULL DEFAULT '',
                `actor_nip` varchar(20) NOT NULL, `payload_hash` char(64) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`), UNIQUE KEY `uniq_prd_antrean_event` (`event_key`),
                KEY `idx_prd_antrean_visit` (`no_rawat`, `operation`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        }
        $ready = true;
    }

    private function lockVisit($noRawat)
    {
        $pdo = $this->db()->pdo();
        $sql = 'SELECT * FROM reg_periksa WHERE no_rawat=:no_rawat';
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $query = $pdo->prepare($sql);
        $query->execute([':no_rawat' => $noRawat]);
        return $query->fetch(\PDO::FETCH_ASSOC);
    }

    private function markFileSent($noRawat)
    {
        $existing = $this->db('mutasi_berkas')->where('no_rawat', $noRawat)->oneArray();
        if ($existing) {
            if (empty($existing['dikirim']) || $existing['dikirim'] === '0000-00-00 00:00:00') {
                $this->db('mutasi_berkas')->where('no_rawat', $noRawat)->save([
                    'status' => 'Sudah Dikirim', 'dikirim' => date('Y-m-d H:i:s')
                ]);
            }
            return;
        }
        $this->db('mutasi_berkas')->save([
            'no_rawat' => $noRawat, 'status' => 'Sudah Dikirim', 'dikirim' => date('Y-m-d H:i:s'),
            'diterima' => '0000-00-00 00:00:00', 'kembali' => '0000-00-00 00:00:00',
            'tidakada' => '0000-00-00 00:00:00', 'ranap' => '0000-00-00 00:00:00'
        ]);
    }

    private function logStatusTransition($noRawat, $from, $to, $reason, $eventKey)
    {
        $statement = $this->db()->pdo()->prepare('INSERT INTO mlite_pemeriksaan_ralan_dev_status_log
            (no_rawat, from_status, to_status, reason, actor_nip, event_key, created_at)
            VALUES (:no_rawat, :from_status, :to_status, :reason, :actor_nip, :event_key, :created_at)');
        $statement->execute([
            ':no_rawat' => $noRawat, ':from_status' => $from, ':to_status' => $to,
            ':reason' => $reason, ':actor_nip' => $this->username(), ':event_key' => $eventKey,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function fktpModuleActive()
    {
        try {
            return (bool) $this->core->ActiveModule('jkn_mobile_fktp');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fktpEligibility($visit, $operation)
    {
        try {
            $context = $this->fktpContext($visit, $operation);
        } catch (\Throwable $e) {
            return ['active' => $this->fktpModuleActive(), 'applicable' => false, 'eligible' => false,
                'message' => 'Konfigurasi atau tabel mapping JKN Mobile FKTP belum siap.'];
        }
        foreach (['credentials', 'patient', 'poli_mapping', 'doctor_mapping', 'api_url', 'schedule'] as $privateKey) {
            unset($context[$privateKey]);
        }
        return $context;
    }

    private function fktpContext($visit, $operation)
    {
        $result = ['active' => false, 'applicable' => false, 'eligible' => false, 'message' => 'Modul JKN Mobile FKTP tidak aktif.'];
        if (!$this->fktpModuleActive()) {
            return $result;
        }
        $result['active'] = true;
        $payerCode = trim((string) $this->settings->get('jkn_mobile_fktp.kd_pj'));
        if ($payerCode === '') {
            $result['message'] = 'Kode penjamin JKN Mobile FKTP belum dikonfigurasi.';
            return $result;
        }
        if (!isset($visit['kd_pj']) || $visit['kd_pj'] !== $payerCode) {
            $result['message'] = 'Penjamin kunjungan tidak menggunakan JKN Mobile FKTP.';
            return $result;
        }
        $result['applicable'] = true;
        $credentials = [
            'username' => trim((string) $this->settings->get('pcare.usernamePcare')),
            'password' => trim((string) $this->settings->get('pcare.passwordPcare')),
            'consumer_id' => trim((string) $this->settings->get('pcare.consumerID')),
            'consumer_secret' => trim((string) $this->settings->get('pcare.consumerSecret')),
            'user_key' => trim((string) $this->settings->get('pcare.consumerUserKeyAntrol'))
        ];
        foreach ($credentials as $value) {
            if ($value === '') {
                $result['message'] = 'Kredensial Antrean FKTP belum lengkap.';
                return $result;
            }
        }
        $patient = array_key_exists('no_peserta', $visit) && array_key_exists('no_ktp', $visit) && array_key_exists('no_tlp', $visit)
            ? ['no_rkm_medis' => $visit['no_rkm_medis'], 'no_peserta' => $visit['no_peserta'],
                'no_ktp' => $visit['no_ktp'], 'no_tlp' => $visit['no_tlp']]
            : $this->db('pasien')->where('no_rkm_medis', $visit['no_rkm_medis'])->oneArray();
        if (!$patient) {
            $result['message'] = 'Data pasien tidak ditemukan.';
            return $result;
        }
        $card = trim((string) ($patient['no_peserta'] ?? ''));
        $nik = trim((string) ($patient['no_ktp'] ?? ''));
        $phone = trim((string) ($patient['no_tlp'] ?? ''));
        if (!preg_match('/^\d{13}$/', $card)) {
            $result['message'] = 'Nomor kartu BPJS harus terdiri dari 13 digit.';
            return $result;
        }
        if ($operation === 'add' && !preg_match('/^\d{16}$/', $nik)) {
            $result['message'] = 'NIK pasien harus terdiri dari 16 digit.';
            return $result;
        }
        if ($operation === 'add' && $phone === '') {
            $result['message'] = 'Nomor telepon pasien wajib tersedia untuk menambah antrean FKTP.';
            return $result;
        }
        $poli = $this->db('maping_poliklinik_pcare')->where('kd_poli_rs', $visit['kd_poli'])->oneArray();
        if (!$poli || trim((string) ($poli['kd_poli_pcare'] ?? '')) === '') {
            $result['message'] = 'Mapping poli PCare belum tersedia.';
            return $result;
        }
        $doctor = [];
        if ($operation === 'add') {
            $doctor = $this->db('maping_dokter_pcare')->where('kd_dokter', $visit['kd_dokter'])->oneArray();
            if (!$doctor || trim((string) ($doctor['kd_dokter_pcare'] ?? '')) === '') {
                $result['message'] = 'Mapping dokter PCare belum tersedia.';
                return $result;
            }
        }
        $apiSetting = trim((string) $this->settings->get('pcare.PCareApiUrl'));
        $apiUrl = strpos($apiSetting, 'dev') !== false
            ? 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/'
            : 'https://apijkn.bpjs-kesehatan.go.id/antreanfktp/';
        $schedule = $operation === 'add' ? $this->visitSchedule($visit) : '';
        $result['eligible'] = true;
        $result['message'] = 'Siap disinkronkan dengan Antrean FKTP.';
        $result['credentials'] = $credentials;
        $result['patient'] = $patient;
        $result['poli_mapping'] = $poli;
        $result['doctor_mapping'] = $doctor;
        $result['api_url'] = $apiUrl;
        $result['schedule'] = $schedule;
        return $result;
    }

    private function visitSchedule($visit)
    {
        $days = ['Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU'];
        $day = $days[date('D', strtotime($visit['tgl_registrasi']))];
        $schedule = $this->db('jadwal')->where('kd_dokter', $visit['kd_dokter'])
            ->where('kd_poli', $visit['kd_poli'])->where('hari_kerja', $day)->oneArray();
        if (!$schedule || empty($schedule['jam_mulai']) || empty($schedule['jam_selesai'])) {
            return '07:00-23:00';
        }
        return date('H:i', strtotime($schedule['jam_mulai'])).'-'.date('H:i', strtotime($schedule['jam_selesai']));
    }

    private function sendFktpQueueOperation($operation, $visit, $eventKey, $reason = '')
    {
        $this->ensureWorkflowTables();
        try {
            $context = $this->fktpContext($visit, $operation);
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'code' => 'LOCAL_CONFIGURATION',
                'message' => 'Konfigurasi atau tabel mapping JKN Mobile FKTP belum siap.'];
        }
        if (!$context['eligible']) {
            return ['status' => 'failed', 'code' => 'LOCAL_VALIDATION', 'message' => $context['message']];
        }
        $existing = $this->queueOperationByEvent($eventKey);
        if ($existing && $existing['status'] === 'success') {
            return ['status' => 'success', 'code' => $existing['response_code'], 'message' => $existing['response_message'], 'idempotent' => true];
        }
        if ($existing && $existing['status'] === 'pending') {
            return ['status' => 'needs_review', 'code' => 'PENDING', 'message' => 'Permintaan yang sama sedang atau sudah pernah diproses. Periksa status sebelum mengulang.'];
        }
        $eventAtMs = $existing && !empty($existing['event_at_ms']) ? (int) $existing['event_at_ms'] : (int) round(microtime(true) * 1000);
        $patient = $context['patient'];
        $poli = $context['poli_mapping'];
        if ($operation === 'add') {
            $number = max(1, (int) $visit['no_reg']);
            $payload = [
                'nomorkartu' => trim((string) $patient['no_peserta']), 'nik' => trim((string) $patient['no_ktp']),
                'nohp' => trim((string) $patient['no_tlp']), 'kodepoli' => $poli['kd_poli_pcare'],
                'namapoli' => $poli['nm_poli_pcare'], 'norm' => $visit['no_rkm_medis'],
                'tanggalperiksa' => $visit['tgl_registrasi'], 'kodedokter' => $context['doctor_mapping']['kd_dokter_pcare'],
                'namadokter' => $context['doctor_mapping']['nm_dokter_pcare'], 'jampraktek' => $context['schedule'],
                'nomorantrean' => strtoupper($poli['kd_poli_pcare']).'-'.$number, 'angkaantrean' => $number,
                'keterangan' => ''
            ];
            $path = 'antrean/add';
        } elseif ($operation === 'call') {
            $payload = ['tanggalperiksa' => $visit['tgl_registrasi'], 'kodepoli' => $poli['kd_poli_pcare'],
                'nomorkartu' => trim((string) $patient['no_peserta']), 'status' => 1, 'waktu' => $eventAtMs];
            $path = 'antrean/panggil';
        } else {
            $payload = ['tanggalperiksa' => $visit['tgl_registrasi'], 'kodepoli' => $poli['kd_poli_pcare'],
                'nomorkartu' => trim((string) $patient['no_peserta']), 'alasan' => $reason];
            $path = 'antrean/batal';
        }
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            $this->recordQueuePending($visit['no_rawat'], $operation, $eventKey, $eventAtMs, $reason, hash('sha256', $jsonPayload), $existing);
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'code' => 'LOCAL_AUDIT', 'message' => 'Jejak sinkronisasi FKTP tidak dapat dibuat; request eksternal tidak dikirim.'];
        }
        $timezone = date_default_timezone_get();
        try {
            $credentials = $context['credentials'];
            $raw = PcareService::post($context['api_url'].$path, $jsonPayload,
                $credentials['consumer_id'], $credentials['consumer_secret'], $credentials['user_key'],
                $credentials['username'], $credentials['password'], '095');
            $curlError = (string) PcareService::getStatus();
            $httpCode = (int) PcareService::getLastHttpCode();
        } catch (\Throwable $e) {
            $raw = false;
            $curlError = $e->getMessage();
            $httpCode = 0;
        } finally {
            date_default_timezone_set($timezone);
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $metadata = is_array($decoded) ? ($decoded['metadata'] ?? ($decoded['metaData'] ?? [])) : [];
        $code = isset($metadata['code']) ? (string) $metadata['code'] : ($httpCode ? (string) $httpCode : 'NO_RESPONSE');
        $message = trim((string) ($metadata['message'] ?? $curlError));
        if ($message === '') {
            $message = is_array($decoded) ? 'Respons Antrean FKTP tidak memiliki metadata.' : 'Tidak ada respons valid dari Antrean FKTP.';
        }
        $duplicate = $operation === 'add'
            && preg_match('/\b(?:tidak|belum|gagal)\b/i', $message) !== 1
            && preg_match('/\b(?:sudah|telah)\b.{0,80}\b(?:terdaftar|tersimpan|ada)\b|\bduplicate\b/i', $message) === 1;
        if ($code === '200' || $duplicate) {
            $status = 'success';
        } elseif ($raw === false || !is_array($decoded) || $curlError !== '' || $httpCode === 0) {
            $status = 'needs_review';
        } else {
            $status = 'failed';
        }
        try {
            $this->completeQueueOperation($eventKey, $status, $code, $message);
        } catch (\Throwable $e) {
            return ['status' => 'needs_review', 'code' => $code,
                'message' => 'Respons FKTP diterima tetapi status lokal gagal diperbarui; lakukan rekonsiliasi sebelum mengulang.'];
        }
        return ['status' => $status, 'code' => $code, 'message' => $message, 'event_at_ms' => $eventAtMs];
    }

    private function recordQueuePending($noRawat, $operation, $eventKey, $eventAtMs, $reason, $payloadHash, $existing)
    {
        $pdo = $this->db()->pdo();
        if ($existing) {
            $statement = $pdo->prepare("UPDATE mlite_pemeriksaan_ralan_dev_antrean
                SET status='pending', requested_at=:requested_at, completed_at=NULL,
                    attempt_count=attempt_count+1, response_code='', response_message='', payload_hash=:payload_hash
                WHERE event_key=:event_key");
            $statement->execute([':requested_at' => date('Y-m-d H:i:s'), ':payload_hash' => $payloadHash, ':event_key' => $eventKey]);
            return;
        }
        $statement = $pdo->prepare('INSERT INTO mlite_pemeriksaan_ralan_dev_antrean
            (no_rawat, operation, event_key, event_at_ms, reason, requested_at, status, attempt_count, actor_nip, payload_hash)
            VALUES (:no_rawat, :operation, :event_key, :event_at_ms, :reason, :requested_at, :status, 1, :actor_nip, :payload_hash)');
        $statement->execute([
            ':no_rawat' => $noRawat, ':operation' => $operation, ':event_key' => $eventKey,
            ':event_at_ms' => $eventAtMs, ':reason' => $reason, ':requested_at' => date('Y-m-d H:i:s'),
            ':status' => 'pending', ':actor_nip' => $this->username(), ':payload_hash' => $payloadHash
        ]);
    }

    private function completeQueueOperation($eventKey, $status, $code, $message)
    {
        $statement = $this->db()->pdo()->prepare('UPDATE mlite_pemeriksaan_ralan_dev_antrean
            SET status=:status, completed_at=:completed_at, response_code=:response_code, response_message=:response_message
            WHERE event_key=:event_key');
        $statement->execute([
            ':status' => $status, ':completed_at' => date('Y-m-d H:i:s'), ':response_code' => mb_substr($code, 0, 20),
            ':response_message' => mb_substr($message, 0, 255), ':event_key' => $eventKey
        ]);
    }

    private function queueOperationByEvent($eventKey)
    {
        $statement = $this->db()->pdo()->prepare('SELECT * FROM mlite_pemeriksaan_ralan_dev_antrean WHERE event_key=:event_key LIMIT 1');
        $statement->execute([':event_key' => $eventKey]);
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    private function latestQueueOperation($noRawat, $operation)
    {
        $this->ensureWorkflowTables();
        $statement = $this->db()->pdo()->prepare('SELECT * FROM mlite_pemeriksaan_ralan_dev_antrean
            WHERE no_rawat=:no_rawat AND operation=:operation ORDER BY id DESC LIMIT 1');
        $statement->execute([':no_rawat' => $noRawat, ':operation' => $operation]);
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    private function queueOperationStates($noRawat)
    {
        $states = [];
        foreach (['add', 'call', 'cancel'] as $operation) {
            $row = $this->latestQueueOperation($noRawat, $operation);
            if ($row) {
                $states[$operation] = [
                    'status' => $row['status'], 'code' => $row['response_code'],
                    'message' => $row['response_message'], 'attempt_count' => (int) $row['attempt_count']
                ];
            }
        }
        return $states;
    }

    private function hasSuccessfulQueueOperation($noRawat, $operation)
    {
        $row = $this->latestQueueOperation($noRawat, $operation);
        return $row && $row['status'] === 'success';
    }

    private function respondQueueResult($result, $successMessage)
    {
        if ($result['status'] === 'success') {
            $this->respond(['status' => 'success', 'message' => $successMessage, 'data' => $result]);
        }
        $this->respondError($result['message'], $result['status'] === 'failed' ? 422 : 502, ['queue' => $result]);
    }

    private function requireVisitAccess($noRawat)
    {
        if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[0-9]{6}$/', $noRawat)) {
            $this->respondError('Nomor rawat tidak valid.');
        }
        $visit = $this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray();
        if (!$visit || $visit['status_lanjut'] !== 'Ralan') {
            $this->respondError('Kunjungan rawat jalan tidak ditemukan.', 404);
        }
        if (!$this->isAdmin() && !in_array($visit['kd_poli'], $this->caps(), true)) {
            $this->respondError('Anda tidak memiliki akses ke poli pasien ini.', 403);
        }
        return $visit;
    }

    private function requireStaff()
    {
        $username = $this->username();
        if ($username === '') {
            $this->respondError('Sesi pengguna tidak valid.', 401);
        }
        if (!$this->db('pegawai')->where('nik', $username)->oneArray()) {
            $this->respondError('Akun harus terhubung dengan data pegawai untuk menyimpan pemeriksaan.', 403);
        }
    }

    private function validatedPemeriksaan($noRawat, $date, $time, $nip)
    {
        $tensi = trim($this->post('tensi'));
        $suhu = $this->number($this->post('suhu_tubuh'), 'Suhu', 30, 45, true, 'suhu_tubuh');
        $nadi = $this->number($this->post('nadi'), 'Nadi', 20, 300, false, 'nadi');
        $respirasi = $this->number($this->post('respirasi'), 'Frekuensi napas', 5, 100, false, 'respirasi');
        $tinggi = $this->number($this->post('tinggi'), 'Tinggi badan', 20, 300, false, 'tinggi');
        $berat = $this->number($this->post('berat'), 'Berat badan', 0.1, 500, true, 'berat');
        $spo2 = $this->number($this->post('spo2'), 'SpO2', 0, 100, false, 'spo2');
        $lingkar = $this->number($this->post('lingkar_perut'), 'Lingkar perut', 1, 300, true, 'lingkar_perut');
        $gcs = strtoupper(trim($this->post('gcs')));
        $kesadaran = trim($this->post('kesadaran'));
        $keluhan = trim($this->post('keluhan'));
        $pemeriksaan = trim($this->post('pemeriksaan'));
        $alergi = trim($this->post('alergi'));
        if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $tensi)) {
            $this->respondError('Tensi harus berformat sistolik/diastolik, misalnya 120/80.', 422, ['field' => 'tensi']);
        }
        if (!preg_match('/^(?:[3-9]|1[0-5]|E[1-4]V[1-5]M[1-6])$/', $gcs)) {
            $this->respondError('GCS harus berupa nilai 3-15 atau format E4V5M6.', 422, ['field' => 'gcs']);
        }
        if (!in_array($kesadaran, ['Compos Mentis', 'Somnolence', 'Sopor', 'Coma'], true)) {
            $this->respondError('Nilai kesadaran tidak valid.', 422, ['field' => 'kesadaran']);
        }
        if ($keluhan === '' || mb_strlen($keluhan) > 2000) {
            $this->respondError('Keluhan/anamnesa awal wajib diisi dan maksimal 2000 karakter.', 422, ['field' => 'keluhan']);
        }
        if (mb_strlen($pemeriksaan) > 2000 || $alergi === '' || mb_strlen($alergi) > 50) {
            $this->respondError('Temuan awal atau ringkasan alergi tidak valid.', 422, ['field' => $alergi === '' ? 'alergi' : 'pemeriksaan']);
        }
        return [
            'no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam_rawat' => $time, 'nip' => $nip,
            'tensi' => $tensi, 'suhu_tubuh' => $suhu, 'nadi' => $nadi, 'respirasi' => $respirasi,
            'tinggi' => $tinggi, 'berat' => $berat, 'spo2' => $spo2, 'gcs' => $gcs,
            'kesadaran' => $kesadaran, 'lingkar_perut' => $lingkar, 'keluhan' => $keluhan,
            'pemeriksaan' => $pemeriksaan, 'alergi' => $alergi,
            'rtl' => '-', 'penilaian' => '-', 'instruksi' => '-', 'evaluasi' => '-'
        ];
    }

    private function validatedAlergi()
    {
        $makanan = $this->post('alergi_makanan', '00');
        $udara = $this->post('alergi_udara', '00');
        $obat = $this->post('alergi_obat', '00');
        if (!in_array($makanan, ['00', '01', '02', '03', '04', '05'], true) || !in_array($udara, ['00', '01', '02', '03'], true) || !in_array($obat, ['00', '01', '02', '03', '04', '05', '06', '07'], true)) {
            $this->respondError('Kode alergi tidak valid.');
        }
        $makananLain = trim($this->post('alergi_makanan_lainnya'));
        $udaraLain = trim($this->post('alergi_udara_lainnya'));
        $obatLain = trim($this->post('alergi_obat_lainnya'));
        if (($makanan === '05' && $makananLain === '') || ($obat === '07' && $obatLain === '')) {
            $this->respondError('Keterangan alergi lainnya wajib diisi.');
        }
        if (mb_strlen($makananLain) > 255 || mb_strlen($udaraLain) > 255 || mb_strlen($obatLain) > 255) {
            $this->respondError('Keterangan alergi maksimal 255 karakter.');
        }
        return [
            'alergi_makanan' => $makanan, 'alergi_makanan_lainnya' => $makanan === '05' ? $makananLain : '',
            'alergi_udara' => $udara, 'alergi_udara_lainnya' => $udaraLain,
            'alergi_obat' => $obat, 'alergi_obat_lainnya' => $obat === '07' ? $obatLain : ''
        ];
    }

    private function alergiSummary($data)
    {
        $makanan = ['00' => '', '01' => 'Seafood', '02' => 'Gandum', '03' => 'Susu Sapi', '04' => 'Kacang-Kacangan', '05' => isset($data['alergi_makanan_lainnya']) ? $data['alergi_makanan_lainnya'] : 'Makanan lain'];
        $udara = ['00' => '', '01' => 'Udara Panas', '02' => 'Udara Dingin', '03' => 'Udara Kotor'];
        $obat = ['00' => '', '01' => 'Antibiotik', '02' => 'Antiinflamasi', '03' => 'Non Steroid', '04' => 'Aspirin', '05' => 'Kortikosteroid', '06' => 'Insulin', '07' => isset($data['alergi_obat_lainnya']) ? $data['alergi_obat_lainnya'] : 'Obat lain'];
        $parts = [$makanan[$data['alergi_makanan']], $udara[$data['alergi_udara']], $obat[$data['alergi_obat']]];
        $parts = array_filter($parts);
        $summary = $parts ? implode(', ', $parts) : 'Tidak Ada';
        return mb_strlen($summary) <= 50 ? $summary : 'Lihat profil alergi';
    }

    private function emptyAlergi()
    {
        return ['alergi_makanan' => '00', 'alergi_makanan_lainnya' => '', 'alergi_udara' => '00', 'alergi_udara_lainnya' => '', 'alergi_obat' => '00', 'alergi_obat_lainnya' => ''];
    }

    private function number($value, $label, $min, $max, $decimal, $field)
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            $this->respondError($label.' harus diisi dengan nilai antara '.$min.' dan '.$max.'.', 422, ['field' => $field]);
        }
        return $decimal ? rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') : (string) (int) $value;
    }

    private function requestDate($key, $default)
    {
        $date = isset($_POST[$key]) ? trim($_POST[$key]) : $default;
        return $this->isValidDate($date) ? $date : $default;
    }

    private function isValidDate($date)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    }

    private function isValidTime($time)
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time);
    }

    private function username()
    {
        return (string) $this->core->getUserInfo('username', null, true);
    }

    private function isAdmin()
    {
        return $this->core->getUserInfo('role') === 'admin';
    }

    private function caps()
    {
        $caps = explode(',', (string) $this->core->getUserInfo('cap', null, true));
        return array_values(array_filter(array_map('trim', $caps)));
    }

    private function post($key, $default = '')
    {
        return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
    }

    private function respond($payload)
    {
        header('Content-type: application/json');
        echo json_encode($payload);
        exit();
    }

    private function respondError($message, $status = 422, $data = [])
    {
        http_response_code($status);
        $payload = ['status' => 'error', 'message' => $message];
        if ($data) {
            $payload['data'] = $data;
        }
        $this->respond($payload);
    }

    private function addHeaderFiles()
    {
        $this->addStyleFile();
        $this->core->addJS(url([ADMIN, 'pemeriksaan_ralan_dev', 'javascript']), 'footer');
    }

    private function addStyleFile()
    {
        $this->core->addCSS(url([ADMIN, 'pemeriksaan_ralan_dev', 'css']));
    }
}
