<?php
namespace Plugins\Pemeriksaan_Ralan_Dev;

use Systems\AdminModule;

class Admin extends AdminModule
{
    private $assign = [];

    public function navigation()
    {
        return [
            'Kelola' => 'index',
            'Pemeriksaan Awal' => 'manage',
            'Pengaturan' => 'settings'
        ];
    }

    public function getIndex()
    {
        return $this->draw('index.html', [
            'sub_modules' => [
                ['name' => 'Pemeriksaan Awal', 'url' => url([ADMIN, 'pemeriksaan_ralan_dev', 'manage']), 'icon' => 'stethoscope', 'desc' => 'TTV, anamnesa awal, alergi, dan panggilan antrean'],
                ['name' => 'Pengaturan', 'url' => url([ADMIN, 'pemeriksaan_ralan_dev', 'settings']), 'icon' => 'wrench', 'desc' => 'Status kunjungan setelah pemeriksaan']
            ]
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
        $bpjsCode = trim((string) $this->settings->get('jkn_mobile.kd_pj_bpjs'));
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
            $record['can_edit'] = $isAdmin || $record['nip'] === $username;
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
                $row['can_copy'] = $table === 'pemeriksaan_ralan';
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
        if ($isEdit) {
            $this->db('pemeriksaan_ralan')
                ->where('no_rawat', $visit['no_rawat'])
                ->where('tgl_perawatan', $date)
                ->where('jam_rawat', $time)
                ->save($data);
        } else {
            $this->db('pemeriksaan_ralan')->save($data);
        }

        if ($this->settings->get('pemeriksaan_ralan_dev.set_sudah') === 'ya') {
            $this->db('reg_periksa')->where('no_rawat', $visit['no_rawat'])->save(['stts' => 'Sudah']);
        }
        $this->respond(['status' => 'success', 'message' => 'Pemeriksaan awal berhasil disimpan.', 'data' => ['tgl_perawatan' => $date, 'jam_rawat' => $time]]);
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
        if (!$this->isAdmin() && $existing['nip'] !== $this->username()) {
            $this->respondError('Anda hanya dapat menghapus catatan milik sendiri.', 403);
        }
        $this->db('pemeriksaan_ralan')
            ->where('no_rawat', $visit['no_rawat'])
            ->where('tgl_perawatan', $date)
            ->where('jam_rawat', $time)
            ->delete();
        $this->respond(['status' => 'success', 'message' => 'Catatan pemeriksaan dihapus.']);
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

    public function getSettings()
    {
        return $this->draw('settings.html', ['settings' => ['pemeriksaan_ralan_dev' => $this->settings('pemeriksaan_ralan_dev')]]);
    }

    public function postSaveSettings()
    {
        $value = isset($_POST['pemeriksaan_ralan_dev']['set_sudah']) ? $_POST['pemeriksaan_ralan_dev']['set_sudah'] : 'tidak';
        $this->settings('pemeriksaan_ralan_dev', 'set_sudah', $value === 'ya' ? 'ya' : 'tidak');
        $this->notify('success', 'Pengaturan Pemeriksaan Awal Paramedis telah disimpan.');
        redirect(url([ADMIN, 'pemeriksaan_ralan_dev', 'settings']));
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
        $list = $this->getVisits();
        $completed = 0;
        foreach ($list as $visit) {
            if (!empty($visit['sudah_diperiksa'])) {
                $completed++;
            }
        }
        return [
            'list' => $list,
            'summary' => [
                'total' => count($list),
                'waiting' => count($list) - $completed,
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
        $status = isset($_POST['status_periksa']) ? $_POST['status_periksa'] : '';
        $igd = $this->settings('settings', 'igd');
        $sql = "SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, r.status_lanjut, r.status_bayar,
                    p.no_rkm_medis, p.nm_pasien, p.no_peserta,
                    poli.kd_poli, poli.nm_poli, d.nm_dokter, pj.png_jawab,
                    EXISTS(SELECT 1 FROM pemeriksaan_ralan pr WHERE pr.no_rawat=r.no_rawat) AS sudah_diperiksa,
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
        if ($status === 'belum') {
            $sql .= ' AND NOT EXISTS(SELECT 1 FROM pemeriksaan_ralan prs WHERE prs.no_rawat=r.no_rawat)';
        } elseif ($status === 'selesai') {
            $sql .= ' AND EXISTS(SELECT 1 FROM pemeriksaan_ralan prs WHERE prs.no_rawat=r.no_rawat)';
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
        return $statement->fetchAll();
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
        $suhu = $this->number($this->post('suhu_tubuh'), 'Suhu', 30, 45, true);
        $nadi = $this->number($this->post('nadi'), 'Nadi', 20, 300, false);
        $respirasi = $this->number($this->post('respirasi'), 'Frekuensi napas', 5, 100, false);
        $tinggi = $this->number($this->post('tinggi'), 'Tinggi badan', 20, 300, false);
        $berat = $this->number($this->post('berat'), 'Berat badan', 0.1, 500, true);
        $spo2 = $this->number($this->post('spo2'), 'SpO2', 0, 100, false);
        $lingkar = $this->number($this->post('lingkar_perut'), 'Lingkar perut', 1, 300, true);
        $gcs = strtoupper(trim($this->post('gcs')));
        $kesadaran = trim($this->post('kesadaran'));
        $keluhan = trim($this->post('keluhan'));
        $pemeriksaan = trim($this->post('pemeriksaan'));
        $alergi = trim($this->post('alergi'));
        if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $tensi)) {
            $this->respondError('Tensi harus berformat sistolik/diastolik, misalnya 120/80.');
        }
        if (!preg_match('/^(?:[3-9]|1[0-5]|E[1-4]V[1-5]M[1-6])$/', $gcs)) {
            $this->respondError('GCS harus berupa nilai 3-15 atau format E4V5M6.');
        }
        if (!in_array($kesadaran, ['Compos Mentis', 'Somnolence', 'Sopor', 'Coma'], true)) {
            $this->respondError('Nilai kesadaran tidak valid.');
        }
        if ($keluhan === '' || mb_strlen($keluhan) > 2000) {
            $this->respondError('Keluhan/anamnesa awal wajib diisi dan maksimal 2000 karakter.');
        }
        if (mb_strlen($pemeriksaan) > 2000 || $alergi === '' || mb_strlen($alergi) > 50) {
            $this->respondError('Temuan awal atau ringkasan alergi tidak valid.');
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

    private function number($value, $label, $min, $max, $decimal)
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            $this->respondError($label.' harus diisi dengan nilai antara '.$min.' dan '.$max.'.');
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

    private function respondError($message, $status = 422)
    {
        http_response_code($status);
        $this->respond(['status' => 'error', 'message' => $message]);
    }

    private function addHeaderFiles()
    {
        $this->core->addCSS(url([ADMIN, 'pemeriksaan_ralan_dev', 'css']));
        $this->core->addJS(url([ADMIN, 'pemeriksaan_ralan_dev', 'javascript']), 'footer');
    }
}
