<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

use Systems\AdminModule;

require_once __DIR__.'/SchedulePolicy.php';
require_once __DIR__.'/BpjsScheduleReference.php';
require_once __DIR__.'/ReferenceActivation.php';
require_once __DIR__.'/ScheduleRepository.php';
require_once __DIR__.'/BookingIntegrity.php';

class Admin extends AdminModule
{
    private const MODULE = 'jkn_mobile_fktp_dev';

    public function init()
    {
        // Idempotent one-table migration for installations that already had the Dev module.
        $pdo = $this->db()->pdo();
        if (!ScheduleRepository::tableExists($pdo)) { ScheduleRepository::install($pdo); }
        try { BookingIntegrity::cleanupOrphans($pdo); }
        catch (\Throwable $e) { error_log('[jkn_mobile_fktp_dev][integrity] '.$e->getMessage()); }

        // Rawat Jalan exposes generic extension points. The production Antrol button remains unchanged.
        \Systems\Lib\Event::add('rawat_jalan.antrol_button', function ($row) {
            $this->renderRawatJalanAntrolButton($row);
        });
        \Systems\Lib\Event::add('rawat_jalan.antrol_scripts', function () {
            $this->renderRawatJalanAntrolScript();
        });
    }

    public function postAntrolDevAction()
    {
        header('Content-Type: application/json; charset=utf-8');
        $action = isset($_POST['action']) && is_scalar($_POST['action']) ? trim((string) $_POST['action']) : '';
        $noRawat = isset($_POST['no_rawat']) && is_scalar($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
        if (!in_array($action, ['add', 'panggil', 'batal'], true) || $noRawat === '') {
            return $this->antrolAdminResponse(422, 'Aksi atau ID kunjungan tidak valid.');
        }
        if ((string) $this->settings->get(self::MODULE.'.antrol_enabled') !== '1') {
            return $this->antrolAdminResponse(409, 'Integrasi outbound Antrol Dev belum diaktifkan pada Pengaturan.');
        }

        try {
            BookingIntegrity::cleanupOrphans($this->db()->pdo());
            $visit = $this->devVisit($noRawat);
            if (!$visit) { throw new \DomainException('Kunjungan bukan booking JKN Mobile FKTP Dev atau sudah dihapus.'); }
            $payload = $this->antrolPayload($action, $visit);
            $result = $this->referenceClient()->postJson('antrean/'.$action, json_encode($payload, JSON_UNESCAPED_UNICODE));
            $this->adminAudit('antrol_'.$action, $noRawat, 200, 200, 'success',
                'BPJS menerima antrean/'.$action.': '.($result['message'] ?: 'OK'));
            return $this->antrolAdminResponse(200, 'BPJS menerima antrean/'.$action.': '.($result['message'] ?: 'OK'), 200);
        } catch (\DomainException $e) {
            $message = $this->safeAdminMessage($e->getMessage());
            $this->adminAudit('antrol_'.$action, $noRawat, 201, 422, 'rejected', $message);
            return $this->antrolAdminResponse(422, $message);
        } catch (\Throwable $e) {
            $message = $this->safeAdminMessage($e->getMessage());
            $this->adminAudit('antrol_'.$action, $noRawat, 201, 502, 'error', $message);
            return $this->antrolAdminResponse(502, $message);
        }
    }

    private function renderRawatJalanAntrolButton($row)
    {
        if (!is_array($row) || empty($row['no_rawat'])) { return; }
        static $devVisits = null;
        if ($devVisits === null) {
            $devVisits = [];
            try {
                $rows = $this->db()->pdo()->query(
                    'SELECT b.no_rawat FROM mlite_jkn_mobile_fktp_dev_booking b ' .
                    'INNER JOIN reg_periksa r ON r.no_rawat = b.no_rawat'
                )->fetchAll(\PDO::FETCH_COLUMN);
                $devVisits = array_fill_keys(array_map('strval', $rows), true);
            } catch (\Throwable $e) { return; }
        }
        $noRawat = (string) $row['no_rawat'];
        if (!isset($devVisits[$noRawat])) { return; }
        $encoded = htmlspecialchars($noRawat, ENT_QUOTES, 'UTF-8');
        echo '<span class="btn-group jkn-fktp-dev-antrol" style="margin-left:3px">';
        echo '<button type="button" class="btn btn-xs btn-success" data-jkn-dev-antrol="panggil" data-no-rawat="'.$encoded.'" title="Kirim panggilan ke Antrol JKN Mobile FKTP Dev">';
        echo '<span class="fa fa-bullhorn"></span> Panggil JKN Dev</button>';
        echo '<button type="button" class="btn btn-xs btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Aksi Antrol Dev lainnya"><span class="caret"></span></button>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a href="#" data-jkn-dev-antrol="add" data-no-rawat="'.$encoded.'">Kirim/Ulang Add Dev</a></li>';
        echo '<li><a href="#" data-jkn-dev-antrol="batal" data-no-rawat="'.$encoded.'">Batal Antrol Dev</a></li>';
        echo '</ul></span>';
    }

    private function renderRawatJalanAntrolScript()
    {
        $endpoint = json_encode(url([ADMIN, self::MODULE, 'antrolDevAction']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>(function(){\n';
        echo 'var endpoint='.$endpoint.';\n';
        echo '$(document).off("click.jknFktpDev", "[data-jkn-dev-antrol]").on("click.jknFktpDev", "[data-jkn-dev-antrol]", function(event){\n';
        echo 'event.preventDefault(); var item=$(this), action=item.data("jkn-dev-antrol"), noRawat=item.data("no-rawat");\n';
        echo 'if(!window.confirm("Kirim aksi Antrol Dev "+action+" untuk "+noRawat+"?")){return;}\n';
        echo 'item.css("pointer-events","none");\n';
        echo '$.ajax({url:endpoint+"?t="+encodeURIComponent(mlite.token),method:"POST",dataType:"json",data:{action:action,no_rawat:noRawat}})\n';
        echo '.done(function(data){var meta=data.metaData||data.metadata||{}; if(String(meta.code)!=="200"){window.alert("Antrol Dev ditolak: "+(meta.message||"Tidak ada pesan"));return;} window.alert("Antrol Dev berhasil: "+(meta.message||"OK"));})\n';
        echo '.fail(function(xhr){var data=xhr.responseJSON||{}, meta=data.metaData||data.metadata||{}; window.alert("Antrol Dev gagal: "+(meta.message||"HTTP "+xhr.status));})\n';
        echo '.always(function(){item.css("pointer-events","");});\n';
        echo '});})();</script>';
    }

    private function devVisit($noRawat)
    {
        $stmt = $this->db()->pdo()->prepare(
            'SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.kd_dokter, r.kd_poli, r.no_rkm_medis, r.stts, ' .
            'b.kodepoli, b.kodedokter, b.jampraktek, p.no_peserta, p.no_ktp, p.no_tlp, ' .
            'COALESCE(NULLIF(s.namapoli, \'\'), po.nm_poli) AS nm_poli, ' .
            'COALESCE(NULLIF(s.namadokter, \'\'), d.nm_dokter) AS nm_dokter ' .
            'FROM mlite_jkn_mobile_fktp_dev_booking b ' .
            'INNER JOIN reg_periksa r ON r.no_rawat = b.no_rawat ' .
            'INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis ' .
            'INNER JOIN poliklinik po ON po.kd_poli = r.kd_poli ' .
            'INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter ' .
            'LEFT JOIN mlite_jkn_mobile_fktp_dev_schedule s ON s.service_date = r.tgl_registrasi ' .
            'AND s.kodepoli = b.kodepoli AND s.kodedokter = b.kodedokter ' .
            'AND CONCAT(DATE_FORMAT(s.jam_mulai, \'%H:%i\'), \'-\', DATE_FORMAT(s.jam_selesai, \'%H:%i\')) = b.jampraktek ' .
            'WHERE b.no_rawat = ? ORDER BY s.is_active DESC, s.id DESC LIMIT 1'
        );
        $stmt->execute([$noRawat]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function antrolPayload($action, array $visit)
    {
        if (!preg_match('/^\d{13}$/', (string) $visit['no_peserta'])) {
            throw new \DomainException('Nomor kartu pasien tidak valid untuk Antrol Dev.');
        }
        if ($action === 'add') {
            if ((string) $visit['stts'] === 'Batal') {
                throw new \DomainException('Kunjungan lokal sudah Batal; antrean/add Dev tidak boleh dikirim.');
            }
            if (!preg_match('/^\d{16}$/', (string) $visit['no_ktp'])) {
                throw new \DomainException('NIK pasien tidak valid untuk Antrol Dev.');
            }
            return [
                'nomorkartu'=>(string) $visit['no_peserta'], 'nik'=>(string) $visit['no_ktp'],
                'nohp'=>(string) $visit['no_tlp'], 'kodepoli'=>(string) $visit['kodepoli'],
                'namapoli'=>(string) $visit['nm_poli'], 'norm'=>(string) $visit['no_rkm_medis'],
                'tanggalperiksa'=>(string) $visit['tgl_registrasi'],
                'kodedokter'=>ctype_digit((string) $visit['kodedokter']) ? (int) $visit['kodedokter'] : (string) $visit['kodedokter'],
                'namadokter'=>(string) $visit['nm_dokter'], 'jampraktek'=>(string) $visit['jampraktek'],
                'nomorantrean'=>$this->adminQueueNumber($visit['kodepoli'], $visit['no_reg']),
                'angkaantrean'=>(int) $visit['no_reg'], 'keterangan'=>'Pengiriman manual dari Rawat Jalan (Dev)'
            ];
        }
        if ($action === 'panggil') {
            if ((string) $visit['stts'] === 'Batal') {
                throw new \DomainException('Kunjungan lokal sudah Batal; antrean/panggil Dev tidak boleh dikirim.');
            }
            return ['tanggalperiksa'=>(string) $visit['tgl_registrasi'], 'kodepoli'=>(string) $visit['kodepoli'],
                'nomorkartu'=>(string) $visit['no_peserta'], 'status'=>1, 'waktu'=>(int) round(microtime(true) * 1000)];
        }
        if ((string) $visit['stts'] !== 'Batal') {
            throw new \DomainException('Kunjungan lokal belum Batal; antrean/batal Dev tidak boleh dikirim agar status lokal dan BPJS tetap konsisten.');
        }
        return ['tanggalperiksa'=>(string) $visit['tgl_registrasi'], 'kodepoli'=>(string) $visit['kodepoli'],
            'nomorkartu'=>(string) $visit['no_peserta'], 'alasan'=>'Pembatalan manual dari Rawat Jalan (Dev)'];
    }

    private function adminQueueNumber($kodePoli, $noReg)
    {
        $format = (string) $this->settings->get(self::MODULE.'.queue_format');
        if ($format === '' || strpos($format, '{nomor}') === false) { $format = '{kodepoli}-{nomor}'; }
        return str_replace(['{kodepoli}', '{nomor}'], [(string) $kodePoli, (string) ((int) $noReg)], $format);
    }

    private function adminAudit($action, $noRawat, $metadataCode, $httpCode, $outcome, $message)
    {
        try {
            $stmt = $this->db()->pdo()->prepare(
                'INSERT INTO mlite_jkn_mobile_fktp_dev_log ' .
                '(request_id, action, endpoint, method, no_rawat, metadata_code, http_code, outcome, message, created_at) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([substr(bin2hex(random_bytes(12)), 0, 24), $action, '/bpjs/antrean/'.substr($action, 7),
                'POST', substr((string) $noRawat, 0, 25), (int) $metadataCode, (int) $httpCode,
                $outcome, mb_substr((string) $message, 0, 255), date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) { /* Logging must not replace the outbound result. */ }
    }

    private function antrolAdminResponse($httpCode, $message, $metadataCode = 201)
    {
        http_response_code((int) $httpCode);
        echo json_encode(['metaData'=>['code'=>(string) $metadataCode, 'message'=>$message]], JSON_UNESCAPED_UNICODE);
        return null;
    }

    private function safeAdminMessage($message)
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', trim((string) $message));
        return mb_substr($message !== '' ? $message : 'BPJS tidak memberikan alasan penolakan.', 0, 220);
    }

    public function navigation()
    {
        return [
            'Pengaturan' => 'manage',
            'Poli & Jadwal Online' => 'onlineSchedule',
            'Generate 1 Minggu' => 'weekSchedule',
            'Mapping Poli' => 'mappingPoli',
            'Mapping Dokter' => 'mappingDokter',
            'Log transaksi' => 'logs'
        ];
    }

    public function getManage()
    {
        $settings = array_merge($this->defaults(), (array) $this->settings(self::MODULE));
        $settings['base_url'] = url(['jknmobilefktpdev']);
        $settings['penjab'] = $this->db('penjab')->where('status', '1')->toArray();
        $antrol_config = $this->antrolConfig();
        $links = [
            'save' => url([ADMIN, self::MODULE, 'saveSettings']),
            'pcare' => url([ADMIN, 'pcare', 'manage']),
            'poli' => url([ADMIN, self::MODULE, 'mappingPoli']),
            'doctor' => url([ADMIN, self::MODULE, 'mappingDokter']),
            'schedule' => url([ADMIN, self::MODULE, 'onlineSchedule']),
            'week' => url([ADMIN, self::MODULE, 'weekSchedule']),
            'logs' => url([ADMIN, self::MODULE, 'logs'])
        ];
        return $this->draw('manage.html', [
            'settings' => htmlspecialchars_array($settings),
            'antrol_config' => htmlspecialchars_array($antrol_config),
            'pcare_configured' => trim((string) $this->settings->get('pcare.consumerID')) !== ''
                && trim((string) $this->settings->get('pcare.consumerSecret')) !== ''
                && trim((string) $this->settings->get('pcare.consumerUserKeyAntrol')) !== '',
            'links' => htmlspecialchars_array($links),
            'nav' => $this->adminNav('settings'),
            'stats' => $this->moduleStats()
        ]);
    }

    public function getOnlineSchedule()
    {
        $requested = is_scalar($_GET['start'] ?? null) ? (string) $_GET['start'] : date('Y-m-d');
        $startDate = SchedulePolicy::validDate($requested) ? $requested : date('Y-m-d');
        return $this->localWeekPage($startDate);
    }

    /** Local weekly editor. This path deliberately never constructs the BPJS client. */
    private function localWeekPage($startDate)
    {
        $pdo = $this->db()->pdo();
        $dayLabels = ['MIN', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB'];
        $days = [];
        $rowsByDate = [];
        $groups = [];
        $latestSaved = '';
        $latestFetched = '';
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($startDate.' +'.$i.' days'));
            $dow = (int) date('w', strtotime($date));
            $days[] = ['date'=>$date, 'label'=>$dayLabels[$dow], 'short'=>date('j M', strtotime($date))];
            $rowsByDate[$date] = ScheduleRepository::rowsForDate($pdo, $date);
            foreach ($rowsByDate[$date] as $row) {
                if ($row['saved_at'] > $latestSaved) { $latestSaved = $row['saved_at']; }
                if ($row['source_fetched_at'] > $latestFetched) { $latestFetched = $row['source_fetched_at']; }
                if (!(int) $row['is_active'] || !in_array($row['shift_name'], ['pagi', 'sore'], true)) { continue; }
                $key = $row['kodepoli'].'|'.$row['kd_poli'].'|'.$row['shift_name'];
                if (!isset($groups[$key])) {
                    $groups[$key] = ['kodepoli'=>$row['kodepoli'], 'namapoli'=>$row['namapoli'],
                        'kd_poli'=>$row['kd_poli'], 'shift'=>$row['shift_name']];
                }
            }
        }
        uasort($groups, function ($left, $right) {
            $poli = strcmp($left['kodepoli'], $right['kodepoli']);
            if ($poli !== 0) { return $poli; }
            return ($left['shift'] === 'pagi' ? 0 : 1) <=> ($right['shift'] === 'pagi' ? 0 : 1);
        });

        $gridRows = [];
        foreach ($groups as $group) {
            $cells = [];
            foreach ($days as $day) {
                $records = $rowsByDate[$day['date']];
                $active = null;
                $closedReason = '';
                foreach ($records as $record) {
                    if ($record['shift_name'] === 'tutup'
                        && ($record['kodepoli'] === '*' || $record['kodepoli'] === $group['kodepoli'])) {
                        $closedReason = trim((string) $record['namadokter']);
                    }
                    if ((int) $record['is_active'] && $record['kodepoli'] === $group['kodepoli']
                        && $record['kd_poli'] === $group['kd_poli'] && $record['shift_name'] === $group['shift']) {
                        $active = $record;
                    }
                }
                $doctorOptions = [];
                if ($active !== null) {
                    foreach ($records as $record) {
                        if ($record['kodepoli'] !== $group['kodepoli'] || $record['kd_poli'] !== $group['kd_poli']
                            || $record['shift_name'] !== $group['shift'] || $record['kodedokter'] === ''
                            || $record['kd_dokter'] === '' || (int) $record['quota'] < 1) {
                            continue;
                        }
                        $doctorOptions[] = [
                            'id'=>(int) $record['id'], 'doctor'=>$record['namadokter'],
                            'doctor_code'=>$record['kodedokter'],
                            'practice'=>substr($record['jam_mulai'], 0, 5).'-'.substr($record['jam_selesai'], 0, 5),
                            'quota'=>(int) $record['quota'], 'selected'=>(int) $record['id'] === (int) $active['id']
                        ];
                    }
                }
                $cells[] = [
                    'date'=>$day['date'], 'has_data'=>!empty($records), 'closed_reason'=>$closedReason,
                    'has_schedule'=>$active !== null,
                    'doctor'=>$active ? $active['namadokter'] : '',
                    'doctor_code'=>$active ? $active['kodedokter'] : '',
                    'practice'=>$active ? substr($active['jam_mulai'], 0, 5).'-'.substr($active['jam_selesai'], 0, 5) : '',
                    'quota'=>$active ? (int) $active['quota'] : '',
                    'quota_name'=>$active ? 'quota['.(int) $active['id'].']' : '',
                    'assignment_name'=>$active ? 'assignment['.(int) $active['id'].']' : '',
                    'doctor_options'=>$doctorOptions
                ];
            }
            $gridRows[] = ['kodepoli'=>$group['kodepoli'], 'namapoli'=>$group['namapoli'],
                'kd_poli'=>$group['kd_poli'], 'shift'=>$group['shift'],
                'shift_label'=>$group['shift'] === 'pagi' ? 'Pagi' : 'Sore', 'cells'=>$cells];
        }

        return $this->draw('local.week.html', [
            'nav'=>$this->adminNav('schedule'),
            'local_week'=>htmlspecialchars_array([
                'csrf'=>$_SESSION['token'] ?? '', 'start_date'=>$startDate,
                'end_date'=>$days[6]['date'], 'days'=>$days, 'rows'=>$gridRows,
                'latest_saved'=>$latestSaved ?: '-', 'latest_fetched'=>$latestFetched ?: '-',
                'previous_url'=>$this->onlineWeekUrl(date('Y-m-d', strtotime($startDate.' -7 days'))),
                'next_url'=>$this->onlineWeekUrl(date('Y-m-d', strtotime($startDate.' +7 days'))),
                'today_url'=>$this->onlineWeekUrl(date('Y-m-d')),
                'view_url'=>url([ADMIN,self::MODULE,'onlineSchedule']),
                'save_url'=>url([ADMIN,self::MODULE,'saveLocalWeekCapacity']),
                'generate_url'=>url([ADMIN,self::MODULE,'weekSchedule'])
            ])
        ]);
    }

    public function postSaveLocalWeekCapacity()
    {
        $startDate = $this->postText('start_date');
        try {
            if (empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $this->postText('schedule_csrf'))) {
                throw new \DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if ($this->postText('form_complete') !== '1' || !SchedulePolicy::validDate($startDate)) {
                throw new \DomainException('Form jadwal mingguan tidak lengkap.');
            }
            $quota = $_POST['quota'] ?? [];
            $assignment = $_POST['assignment'] ?? [];
            if (!is_array($quota) || !$quota || !is_array($assignment) || !$assignment) {
                throw new \DomainException('Pilihan dokter atau kapasitas jadwal tidak lengkap.');
            }
            $pdo = $this->db()->pdo();
            $updated = ScheduleRepository::updateAssignments($pdo, $startDate,
                date('Y-m-d', strtotime($startDate.' +6 days')), $assignment, $quota,
                SchedulePolicy::catalogs($pdo));
            $this->notify('success', $updated.' jadwal dokter/kapasitas berhasil diperbarui tanpa memanggil API BPJS. Booking lama tidak dipindahkan.');
            redirect($this->onlineWeekUrl($startDate));
        } catch (\DomainException $e) {
            $this->notify('failure', $e->getMessage());
            redirect($this->onlineWeekUrl(SchedulePolicy::validDate($startDate) ? $startDate : date('Y-m-d')));
        } catch (\RuntimeException $e) {
            $this->notify('failure', $e->getMessage());
            redirect($this->onlineWeekUrl(SchedulePolicy::validDate($startDate) ? $startDate : date('Y-m-d')));
        }
    }

    private function referenceClient()
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
        $url            = trim((string) $this->settings->get(self::MODULE.'.antrol_url'));
        
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

    private function referenceForm()
    {
        if (empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $this->postText('schedule_csrf'))) {
            throw new \DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        if ($this->postText('form_complete') !== '1') { throw new \DomainException('Form terpotong. Jadwal tersimpan tidak diubah.'); }
        $date = $this->postText('date');
        // Horizon administrasi dipisahkan dari batas booking pasien.
        if (!SchedulePolicy::validDate($date) || $date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+90 days'))) {
            throw new \DomainException('Tanggal referensi harus hari ini sampai 90 hari ke depan.');
        }
        return $date;
    }

    public function postFetchBpjsSchedule()
    {
        $date = date('Y-m-d');
        try {
            $date = $this->referenceForm();
            $snapshot = $this->referenceClient()->fetch($date);
            $snapshot['nonce'] = bin2hex(random_bytes(16));
            $_SESSION['jkn_dev_references'][$snapshot['nonce']] = $snapshot;
            while (count($_SESSION['jkn_dev_references']) > 5) { array_shift($_SESSION['jkn_dev_references']); }
            return $this->referencePage($date, '', $snapshot);
        } catch (\DomainException $e) {
            return $this->referencePage($date, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->referencePage($date, $e->getMessage());
        }
    }

    public function postSaveOnlineSchedule()
    {
        $date = date('Y-m-d');
        $snapshot = null;
        try {
            $date = $this->referenceForm();
            $nonce = $this->postText('snapshot');
            $snapshot = $_SESSION['jkn_dev_references'][$nonce] ?? null;
            if (!is_array($snapshot) || $snapshot['date'] !== $date) {
                throw new \DomainException('Ambil referensi BPJS terlebih dahulu; snapshot sesi tidak tersedia.');
            }
            $selected = $_POST['selected'] ?? [];
            $allowed = $_POST['allowed'] ?? [];
            if (!is_array($selected) || !is_array($allowed)) { throw new \DomainException('Format pilihan tidak valid.'); }
            $client = $this->referenceClient();
            ScheduleRepository::replaceBatch($this->db()->pdo(), [$date=>$snapshot], [$date=>$selected],
                array_values(array_map('strval', $allowed)), SchedulePolicy::catalogs($this->db()->pdo()), $client->fingerprint());
            unset($_SESSION['jkn_dev_references'][$nonce]);
            $this->notify('success', 'Checklist jadwal '.$date.' tersimpan ke tabel khusus Dev. Jadwal BPJS/master tidak diubah.');
            redirect($this->onlineWeekUrl($date));
        } catch (\DomainException $e) {
            return $this->referencePage($date, $e->getMessage(), $snapshot, true);
        } catch (\RuntimeException $e) {
            return $this->referencePage($date, $e->getMessage(), $snapshot, true);
        }
    }

    private function referencePage($date, $error = '', $snapshot = null, $retry = false)
    {
        $pdo = $this->db()->pdo();
        $fingerprint = '';
        $source = '';
        try { $client = $this->referenceClient(); $fingerprint = $client->fingerprint(); $source = $client->source(); }
        catch (\RuntimeException $e) { if ($error === '') { $error = $e->getMessage(); } }
        $catalog = SchedulePolicy::catalogs($pdo);
        if ($snapshot === null) {
            foreach ($_SESSION['jkn_dev_references'] ?? [] as $candidate) {
                if (($candidate['date'] ?? '') === $date && ReferenceActivation::fresh($candidate, $fingerprint, ScheduleRepository::APPROVAL_TTL)) {
                    $snapshot = $candidate;
                }
            }
        }
        $stored = ScheduleRepository::rowsForDate($pdo, $date);
        $storedSelected = [];
        $storedAllowed = [];
        foreach ($stored as $row) {
            if ((int) $row['is_active']) { $storedSelected[] = $row['candidate_id']; }
            if ($row['kd_poli'] !== '') { $storedAllowed[$row['kd_poli']] = true; }
        }
        $selected = $retry && is_array($_POST['selected'] ?? null) ? array_map('strval', $_POST['selected']) : $storedSelected;
        $allowed = $retry && is_array($_POST['allowed'] ?? null) ? array_map('strval', $_POST['allowed']) : array_keys($storedAllowed);
        $canSave = is_array($snapshot) && !empty($snapshot['nonce'])
            && ReferenceActivation::fresh($snapshot, $fingerprint, ScheduleRepository::APPROVAL_TTL);
        $rows = [];
        $closures = [];
        if (is_array($snapshot)) {
            if (!empty($snapshot['day_reason'])) { $closures[] = ['kodepoli'=>'Semua poli', 'reason'=>$snapshot['day_reason']]; }
            foreach ($snapshot['closures'] ?? [] as $closure) {
                $closures[] = ['kodepoli'=>(string) ($closure['kodepoli'] ?? ''),
                    'reason'=>(string) ($closure['reason'] ?? 'BPJS tidak menyediakan jadwal dokter pada tanggal ini.')];
            }
            foreach ($snapshot['rows'] ?? [] as $r) {
                $doctor = ReferenceActivation::doctor($r, $catalog);
                $r['local_doctor'] = $doctor ? $doctor['kd_dokter'].' · '.$doctor['nm_dokter'] : 'Mapping dokter tidak tersedia / ambigu';
                $r['blocked'] = !$doctor || $r['quota'] < 1;
                $r['selected'] = in_array($r['id'], $selected, true);
                $r['reason'] = !$doctor ? 'Periksa mapping dokter aktif' : ($r['quota'] < 1 ? 'Kapasitas nol' : '');
                $rows[] = $r;
            }
        } else {
            foreach ($stored as $r) {
                if ($r['shift_name'] === 'tutup') {
                    $closures[] = ['kodepoli'=>$r['kodepoli'] === '*' ? 'Semua poli' : $r['kodepoli'],
                        'reason'=>$r['namadokter']];
                    continue;
                }
                $rows[] = ['id'=>$r['candidate_id'], 'kodepoli'=>$r['kodepoli'], 'namapoli'=>$r['namapoli'],
                    'kodedokter'=>$r['kodedokter'], 'namadokter'=>$r['namadokter'],
                    'jampraktek'=>substr($r['jam_mulai'],0,5).'-'.substr($r['jam_selesai'],0,5),
                    'quota'=>(int) $r['quota'], 'shift'=>$r['shift_name'],
                    'local_doctor'=>$r['kd_dokter'] ?: 'Mapping tidak tersedia saat disimpan',
                    'blocked'=>true, 'selected'=>(bool) $r['is_active'], 'reason'=>'Muat ulang referensi untuk mengubah checklist'];
            }
        }
        foreach ($catalog['polis'] as &$p) {
            $p['blocked'] = SchedulePolicy::referral($p['kd_poli'], $p['nm_poli']) || (string) $p['status'] !== '1';
            $p['selected'] = in_array((string) $p['kd_poli'], $allowed, true);
        }
        unset($p);
        $dates = [];
        foreach (ScheduleRepository::activeDates($pdo, date('Y-m-d')) as $item) {
            $dates[] = ['date'=>$item['service_date'], 'count'=>(int) $item['active_count'],
                'saved_at'=>$item['saved_at'], 'url'=>$this->onlineWeekUrl($item['service_date'])];
        }
        return $this->draw('bpjs.schedule.html', [
            'nav'=>$this->adminNav('schedule'),
            'reference'=>htmlspecialchars_array([
                'date'=>$date, 'error'=>$error, 'source'=>$source, 'csrf'=>$_SESSION['token'] ?? '',
                'min_date'=>date('Y-m-d'), 'max_date'=>date('Y-m-d', strtotime('+90 days')),
                'nonce'=>$snapshot['nonce'] ?? '', 'can_save'=>$canSave,
                'fetched_at'=>isset($snapshot['fetched_at']) ? date('Y-m-d H:i:s', $snapshot['fetched_at'])
                    : ($stored[0]['source_fetched_at'] ?? '-'),
                'rows'=>$rows, 'closures'=>$closures, 'polis'=>$catalog['polis'], 'preview'=>ScheduleRepository::schedules($pdo, $catalog, $date),
                'dates'=>$dates, 'has_snapshot'=>is_array($snapshot), 'fetch_url'=>url([ADMIN,self::MODULE,'fetchBpjsSchedule']),
                'save_url'=>url([ADMIN,self::MODULE,'saveOnlineSchedule']), 'week_url'=>url([ADMIN,self::MODULE,'weekSchedule']),
                'settings_url'=>url([ADMIN,self::MODULE,'manage'])
            ])
        ]);
    }

    public function getMappingPoli()
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT m.kd_poli_rs, p.nm_poli, m.kd_poli_pcare, m.nm_poli_pcare
            FROM maping_poliklinik_pcare m
            LEFT JOIN poliklinik p ON p.kd_poli = m.kd_poli_rs';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE m.kd_poli_rs LIKE ? OR m.kd_poli_pcare LIKE ? OR p.nm_poli LIKE ? OR m.nm_poli_pcare LIKE ?';
            $params = array_fill(0, 4, '%'.$query.'%');
        }
        $sql .= ' ORDER BY p.nm_poli, m.kd_poli_rs LIMIT 200';
        $stmt = $this->db()->pdo()->prepare($sql);
        $stmt->execute($params);
        return $this->draw('mapping.poli.html', [
            'nav' => $this->adminNav('poli'),
            'mappings' => htmlspecialchars_array($stmt->fetchAll(\PDO::FETCH_ASSOC)),
            'poliklinik' => htmlspecialchars_array($this->db('poliklinik')->asc('nm_poli')->toArray()),
            'query' => htmlspecialchars($query, ENT_QUOTES, 'UTF-8'),
            'save_url' => htmlspecialchars(url([ADMIN,self::MODULE,'saveMappingPoli']), ENT_QUOTES, 'UTF-8'),
            'search_url' => htmlspecialchars(url([ADMIN,self::MODULE,'mappingPoli']), ENT_QUOTES, 'UTF-8')
        ]);
    }

    public function postSaveMappingPoli()
    {
        $local = $this->postText('kd_poli_rs');
        $code = $this->postText('kd_poli_pcare');
        $name = $this->postText('nm_poli_pcare');
        $this->validateMapping($local, $code, $name, 'poli');
        $pdo = $this->db()->pdo();
        $exists = $pdo->prepare('SELECT kd_poli_rs FROM maping_poliklinik_pcare WHERE kd_poli_rs = ?');
        $exists->execute([$local]);
        if ($exists->fetchColumn()) {
            $stmt = $pdo->prepare('UPDATE maping_poliklinik_pcare SET kd_poli_pcare = ?, nm_poli_pcare = ? WHERE kd_poli_rs = ?');
            $stmt->execute([$code, $name, $local]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO maping_poliklinik_pcare (kd_poli_rs, kd_poli_pcare, nm_poli_pcare) VALUES (?, ?, ?)');
            $stmt->execute([$local, $code, $name]);
        }
        $this->notify('success', 'Mapping poli tersimpan. Perubahan langsung dipakai endpoint dev.');
        redirect(url([ADMIN, self::MODULE, 'mappingPoli']));
    }

    public function getMappingDokter()
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT m.kd_dokter, d.nm_dokter, m.kd_dokter_pcare, m.nm_dokter_pcare
            FROM maping_dokter_pcare m
            LEFT JOIN dokter d ON d.kd_dokter = m.kd_dokter';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE m.kd_dokter LIKE ? OR m.kd_dokter_pcare LIKE ? OR d.nm_dokter LIKE ? OR m.nm_dokter_pcare LIKE ?';
            $params = array_fill(0, 4, '%'.$query.'%');
        }
        $sql .= ' ORDER BY d.nm_dokter, m.kd_dokter LIMIT 200';
        $stmt = $this->db()->pdo()->prepare($sql);
        $stmt->execute($params);
        return $this->draw('mapping.dokter.html', [
            'nav' => $this->adminNav('doctor'),
            'mappings' => htmlspecialchars_array($stmt->fetchAll(\PDO::FETCH_ASSOC)),
            'dokter' => htmlspecialchars_array($this->db('dokter')->asc('nm_dokter')->toArray()),
            'query' => htmlspecialchars($query, ENT_QUOTES, 'UTF-8'),
            'save_url' => htmlspecialchars(url([ADMIN,self::MODULE,'saveMappingDokter']), ENT_QUOTES, 'UTF-8'),
            'search_url' => htmlspecialchars(url([ADMIN,self::MODULE,'mappingDokter']), ENT_QUOTES, 'UTF-8')
        ]);
    }

    public function postSaveMappingDokter()
    {
        $local = $this->postText('kd_dokter');
        $code = $this->postText('kd_dokter_pcare');
        $name = $this->postText('nm_dokter_pcare');
        $this->validateMapping($local, $code, $name, 'dokter');
        $pdo = $this->db()->pdo();
        $exists = $pdo->prepare('SELECT kd_dokter FROM maping_dokter_pcare WHERE kd_dokter = ?');
        $exists->execute([$local]);
        if ($exists->fetchColumn()) {
            $stmt = $pdo->prepare('UPDATE maping_dokter_pcare SET kd_dokter_pcare = ?, nm_dokter_pcare = ? WHERE kd_dokter = ?');
            $stmt->execute([$code, $name, $local]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO maping_dokter_pcare (kd_dokter, kd_dokter_pcare, nm_dokter_pcare) VALUES (?, ?, ?)');
            $stmt->execute([$local, $code, $name]);
        }
        $this->notify('success', 'Mapping dokter tersimpan. Perubahan langsung dipakai endpoint dev.');
        redirect(url([ADMIN, self::MODULE, 'mappingDokter']));
    }

    public function getWeekSchedule()
    {
        $pdo = $this->db()->pdo();
        $catalog = SchedulePolicy::catalogs($pdo);
        $accepted = ScheduleRepository::acceptedPolis($pdo, date('Y-m-d'));
        foreach ($catalog['polis'] as &$p) {
            $p['blocked'] = SchedulePolicy::referral($p['kd_poli'], $p['nm_poli']) || (string) $p['status'] !== '1';
            $p['selected'] = in_array((string) $p['kd_poli'], $accepted, true);
        }
        unset($p);
        return $this->draw('week.schedule.html', [
            'nav' => $this->adminNav('week'),
            'week' => htmlspecialchars_array([
                'csrf'       => $_SESSION['token'] ?? '',
                'start_date' => date('Y-m-d'),
                'max_start'  => date('Y-m-d', strtotime('+84 days')),
                'source'     => $this->antrolSource(),
                'polis'      => $catalog['polis'],
                'results'    => false,
                'grid'       => false,
                'can_save'   => false,
                'rows'       => [],
                'days'       => [],
                'allowed'    => [],
                'load_url'   => url([ADMIN,self::MODULE,'activateWeek']),
                'save_url'   => url([ADMIN,self::MODULE,'saveWeekSchedule']),
                'cancel_url' => url([ADMIN,self::MODULE,'onlineSchedule']),
                'week_url'   => url([ADMIN,self::MODULE,'weekSchedule']),
            ])
        ]);
    }

    public function postActivateWeek()
    {
        try {
            if (empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $this->postText('schedule_csrf'))) {
                throw new \DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if ($this->postText('form_complete') !== '1') { throw new \DomainException('Form tidak lengkap.'); }
            $startDate = $this->postText('start_date');
            $maxStart = date('Y-m-d', strtotime('+84 days'));
            if (!SchedulePolicy::validDate($startDate) || $startDate < date('Y-m-d') || $startDate > $maxStart) {
                throw new \DomainException('Tanggal mulai harus hari ini sampai 84 hari ke depan agar seluruh 7 hari berada dalam horizon administrasi 90 hari.');
            }
            $allowed = $_POST['allowed'] ?? [];
            if (!is_array($allowed) || empty($allowed)) { throw new \DomainException('Pilih minimal satu poli tujuan.'); }
            $allowed = array_values(array_map('strval', $allowed));

            $client = $this->referenceClient();
            $fingerprint = $client->fingerprint();
            $catalog = SchedulePolicy::catalogs($this->db()->pdo());

            // Day labels in Bahasa Indonesia (Sunday=0 … Saturday=6)
            $dayLabels = ['MIN', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB'];

            // Fetch references for all 7 days, store in session for later save
            $_SESSION['jkn_dev_week_snapshots'] = [];
            $dayDates = [];
            $snapshots = [];
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($startDate.' +'.$i.' days'));
                $dow  = (int) date('w', strtotime($date));
                $skip = false;
                $error = '';
                $snapshot = null;
                if (!$skip) {
                    try {
                        $snapshot = $client->fetch($date);
                        $_SESSION['jkn_dev_week_snapshots'][$date] = $snapshot;
                    } catch (\Throwable $e) {
                        $error = $e->getMessage();
                        unset($_SESSION['jkn_dev_week_snapshots'][$date]);
                    }
                }
                $dayDates[] = ['date' => $date, 'label' => $dayLabels[$dow],
                    'short' => date('j M', strtotime($date)), 'skip' => $skip, 'error' => $error];
                $snapshots[$date] = $snapshot;
            }
            $_SESSION['jkn_dev_week_fingerprint'] = $fingerprint;
            $_SESSION['jkn_dev_week_dates'] = array_column($dayDates, 'date');

            // Aggregate unique polis across all fetched days
            $allPolis = [];
            foreach ($snapshots as $snapshot) {
                if (!$snapshot) { continue; }
                foreach ($snapshot['rows'] as $r) {
                    $kp = $r['kodepoli'];
                    if (!isset($allPolis[$kp])) { $allPolis[$kp] = ['kd_poli' => $kp, 'nm_poli' => $r['namapoli']]; }
                }
                foreach ($snapshot['closures'] ?? [] as $closure) {
                    $kp = (string) ($closure['kodepoli'] ?? '');
                    if ($kp !== '' && !isset($allPolis[$kp])) {
                        $allPolis[$kp] = ['kd_poli'=>$kp, 'nm_poli'=>(string) ($closure['namapoli'] ?? '')];
                    }
                }
            }
            ksort($allPolis);

            // Build grid rows: one row per poli, one cell per day
            $savedIds = [];
            foreach ($dayDates as $day) {
                foreach (ScheduleRepository::rowsForDate($this->db()->pdo(), $day['date']) as $savedRow) {
                    if ((int) $savedRow['is_active']) { $savedIds[$day['date']][$savedRow['candidate_id']] = true; }
                }
            }
            $allowedRemote = array_values($this->remoteCodesForAllowed($allowed, $catalog));
            $gridRows = [];
            foreach ($allPolis as $kp => $poliInfo) {
                $cells = [];
                foreach ($dayDates as $day) {
                    $date     = $day['date'];
                    $snapshot = $snapshots[$date] ?? null;
                    $slots    = [];
                    $closedReason = '';
                    if ($snapshot) {
                        $closedReason = (string) ($snapshot['day_reason'] ?? '');
                        foreach ($snapshot['closures'] ?? [] as $closure) {
                            if ((string) ($closure['kodepoli'] ?? '') === (string) $kp) {
                                $closedReason = (string) ($closure['reason'] ?? 'BPJS tidak menyediakan jadwal dokter pada tanggal ini.');
                                break;
                            }
                        }
                        foreach ($snapshot['rows'] as $r) {
                            if ($r['kodepoli'] !== $kp) { continue; }
                            $doctor   = ReferenceActivation::doctor($r, $catalog);
                            $eligible = $doctor && (int) $r['quota'] >= 1 && in_array($kp, $allowedRemote, true);
                            $selected = isset($savedIds[$date][$r['id']]);
                            $slots[] = [
                                'id'         => $r['id'],
                                'input_name' => 'slots['.$date.'][]', // pre-computed; avoids nested $value conflict
                                'choice_group' => hash('sha256', $date.'|'.$kp.'|'.$r['shift']),
                                'namadokter' => $r['namadokter'],
                                'jampraktek' => $r['jampraktek'],
                                'quota'      => (int) $r['quota'],
                                'shift'      => $r['shift'],
                                'eligible'   => $eligible,
                                'selected'   => $selected,
                                'blocked'    => !$eligible,
                                'local_doctor' => $doctor ? $doctor['nm_dokter'] : '',
                            ];
                        }
                    }
                    usort($slots, function ($left, $right) {
                        $shiftOrder = ['pagi'=>0, 'sore'=>1];
                        $byShift = ($shiftOrder[$left['shift']] ?? 2) <=> ($shiftOrder[$right['shift']] ?? 2);
                        if ($byShift !== 0) { return $byShift; }
                        $byTime = strcmp($left['jampraktek'], $right['jampraktek']);
                        return $byTime !== 0 ? $byTime : strcmp($left['namadokter'], $right['namadokter']);
                    });
                    $previousShift = '';
                    foreach ($slots as &$slot) {
                        $slot['show_shift_header'] = $slot['shift'] !== $previousShift;
                        $previousShift = $slot['shift'];
                    }
                    unset($slot);
                    $cells[] = ['date' => $date, 'skip' => $day['skip'],
                        'error' => $day['error'], 'closed_reason'=>$closedReason, 'slots' => $slots];
                }
                $gridRows[] = ['kd_poli' => $kp, 'nm_poli' => $poliInfo['nm_poli'],
                    'in_allowed' => in_array($kp, $allowedRemote, true), 'cells' => $cells];
            }

            return $this->draw('week.schedule.html', [
                'nav'  => $this->adminNav('week'),
                'week' => htmlspecialchars_array([
                    'csrf'       => $_SESSION['token'] ?? '',
                    'start_date' => $startDate,
                    'max_start'  => $maxStart,
                    'source'     => $client->source(),
                    'allowed'    => $allowed,
                    'days'       => $dayDates,
                    'rows'       => $gridRows,
                    'polis'      => [],
                    'results'    => false,
                    'grid'       => true,
                    'can_save'   => count(array_filter($dayDates, function ($day) { return $day['error'] !== ''; })) === 0,
                    'load_url'   => url([ADMIN,self::MODULE,'activateWeek']),
                    'save_url'   => url([ADMIN,self::MODULE,'saveWeekSchedule']),
                    'cancel_url' => url([ADMIN,self::MODULE,'onlineSchedule']),
                    'week_url'   => url([ADMIN,self::MODULE,'weekSchedule']),
                ])
            ]);
        } catch (\DomainException $e) {
            $this->notify('failure', $e->getMessage());
            redirect(url([ADMIN, self::MODULE, 'weekSchedule']));
        } catch (\RuntimeException $e) {
            $this->notify('failure', $e->getMessage());
            redirect(url([ADMIN, self::MODULE, 'weekSchedule']));
        }
    }

    public function postSaveWeekSchedule()
    {
        try {
            if (empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $this->postText('schedule_csrf'))) {
                throw new \DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if ($this->postText('form_complete') !== '1') { throw new \DomainException('Form terpotong. Tidak ada jadwal yang disimpan.'); }
            $slots   = isset($_POST['slots']) && is_array($_POST['slots']) ? $_POST['slots'] : [];
            $allowed = isset($_POST['allowed']) && is_array($_POST['allowed'])
                ? array_values(array_map('strval', $_POST['allowed'])) : [];
            if (empty($allowed)) { throw new \DomainException('Tidak ada poli tujuan dalam form.'); }

            $weekSnapshots = $_SESSION['jkn_dev_week_snapshots'] ?? [];
            $weekDates     = $_SESSION['jkn_dev_week_dates'] ?? [];
            $storedFp      = $_SESSION['jkn_dev_week_fingerprint'] ?? '';
            if (count($weekDates) !== 7 || count($weekSnapshots) !== 7 || array_keys($weekSnapshots) !== $weekDates) {
                throw new \DomainException('Referensi 7 hari tidak lengkap. Muat ulang; tidak ada jadwal yang diubah.');
            }

            $client      = $this->referenceClient();
            $fingerprint = $client->fingerprint();
            if ($storedFp && $storedFp !== $fingerprint) {
                throw new \DomainException('Konfigurasi Antrol berubah sejak referensi diambil. Muat ulang referensi.');
            }

            $selectedByDate = [];
            foreach ($weekDates as $date) {
                $selectedByDate[$date] = isset($slots[$date]) && is_array($slots[$date])
                    ? array_values(array_map('strval', $slots[$date])) : [];
            }
            $summary = ScheduleRepository::replaceBatch($this->db()->pdo(), $weekSnapshots, $selectedByDate, $allowed,
                SchedulePolicy::catalogs($this->db()->pdo()), $fingerprint);
            $results = [];
            foreach ($weekDates as $date) {
                $results[] = ['date'=>$date, 'status'=>'ok', 'message'=>'Checklist tersimpan atomik ke tabel Dev.',
                    'count'=>count($selectedByDate[$date])];
            }
            unset($_SESSION['jkn_dev_week_snapshots'], $_SESSION['jkn_dev_week_fingerprint'], $_SESSION['jkn_dev_week_dates']);

            return $this->draw('week.schedule.html', [
                'nav'  => $this->adminNav('week'),
                'week' => htmlspecialchars_array([
                    'csrf'       => $_SESSION['token'] ?? '',
                    'start_date' => '',
                    'max_start'  => date('Y-m-d', strtotime('+84 days')),
                    'source'     => $this->antrolSource(),
                    'results'    => $results,
                    'grid'       => false,
                    'polis'      => [],
                    'rows'       => [],
                    'days'       => [],
                    'allowed'    => [],
                    'can_save'   => false,
                    'load_url'   => url([ADMIN,self::MODULE,'activateWeek']),
                    'save_url'   => url([ADMIN,self::MODULE,'saveWeekSchedule']),
                    'cancel_url' => url([ADMIN,self::MODULE,'onlineSchedule']),
                    'week_url'   => url([ADMIN,self::MODULE,'weekSchedule']),
                ])
            ]);
        } catch (\DomainException $e) {
            $this->notify('failure', $e->getMessage());
            redirect(url([ADMIN, self::MODULE, 'weekSchedule']));
        } catch (\RuntimeException $e) {
            $this->notify('failure', $e->getMessage());
            redirect(url([ADMIN, self::MODULE, 'weekSchedule']));
        }
    }

    private function antrolSource()
    {
        try { return $this->referenceClient()->source(); }
        catch (\RuntimeException $e) { return ''; }
    }

    private function remoteCodesForAllowed(array $allowed, array $catalog)
    {
        $result = [];
        foreach ($catalog['polis'] as $poli) {
            if (in_array((string) $poli['kd_poli'], $allowed, true) && !SchedulePolicy::referral($poli['kd_poli'], $poli['nm_poli'])
                && (string) $poli['status'] === '1') {
                $result[$poli['kd_poli']] = (string) $poli['kd_poli_pcare'];
            }
        }
        return $result;
    }

    private function datedUrl($method, $date)
    {
        $target = url([ADMIN, self::MODULE, $method]);
        return $target.(strpos($target, '?') === false ? '?' : '&').'date='.rawurlencode($date);
    }

    private function onlineWeekUrl($startDate)
    {
        $target = url([ADMIN, self::MODULE, 'onlineSchedule']);
        return $target.(strpos($target, '?') === false ? '?' : '&').'start='.rawurlencode($startDate);
    }

    public function getLogs()
    {
        $date = $this->dateFilter($_GET['date'] ?? date('Y-m-d'));
        $action = $this->filterValue($_GET['action'] ?? '', [
            'auth', 'patient', 'booking', 'status', 'participant_queue', 'cancel',
            'antrol_add', 'antrol_panggil', 'antrol_batal', 'integrity_cleanup'
        ]);
        $outcome = $this->filterValue($_GET['outcome'] ?? '', ['success', 'rejected', 'error']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $where = ['DATE(created_at) = ?'];
        $params = [$date];
        if ($action !== '') {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($outcome !== '') {
            $where[] = 'outcome = ?';
            $params[] = $outcome;
        }
        $sqlWhere = implode(' AND ', $where);
        $pdo = $this->db()->pdo();
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_log WHERE '.$sqlWhere);
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();
        $list = $pdo->prepare('SELECT id, request_id, action, endpoint, method, no_rawat, metadata_code, http_code, outcome, message, created_at FROM mlite_jkn_mobile_fktp_dev_log WHERE '.$sqlWhere.' ORDER BY id DESC LIMIT ? OFFSET ?');
        foreach ($params as $index => $value) {
            $list->bindValue($index + 1, $value);
        }
        $list->bindValue(count($params) + 1, $perPage, \PDO::PARAM_INT);
        $list->bindValue(count($params) + 2, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $list->execute();
        $pages = max(1, (int) ceil($total / $perPage));
        $rows = $list->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['detail_url'] = url([ADMIN, self::MODULE, 'logDetail', $row['id']]);
        }
        unset($row);
        $data = [
            'list' => $rows,
            'date' => $date, 'action' => $action, 'outcome' => $outcome,
            'page' => $page, 'pages' => $pages, 'total' => $total,
            'previous_url' => $this->logUrl(max(1, $page - 1), $date, $action, $outcome),
            'next_url' => $this->logUrl(min($pages, $page + 1), $date, $action, $outcome),
            'manage_url' => url([ADMIN,self::MODULE,'manage']),
            'filter_url' => url([ADMIN,self::MODULE,'logs'])
        ];
        return $this->draw('logs.html', ['logs' => htmlspecialchars_array($data), 'nav' => $this->adminNav('logs')]);
    }

    public function getLogDetail($id)
    {
        $stmt = $this->db()->pdo()->prepare('SELECT id, request_id, action, endpoint, method, no_rawat, metadata_code, http_code, outcome, message, created_at FROM mlite_jkn_mobile_fktp_dev_log WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$log) {
            $this->notify('failure', 'Log transaksi tidak ditemukan.');
            redirect(url([ADMIN, self::MODULE, 'logs']));
        }
        return $this->draw('log.detail.html', ['log' => htmlspecialchars_array($log), 'nav' => $this->adminNav('logs'),
            'back_url' => htmlspecialchars(url([ADMIN,self::MODULE,'logs']), ENT_QUOTES, 'UTF-8')]);
    }

    public function postSaveSettings()
    {
        $input = isset($_POST['jkn_mobile_fktp_dev']) && is_array($_POST['jkn_mobile_fktp_dev'])
            ? $_POST['jkn_mobile_fktp_dev'] : [];
        if (!$this->settings->get('pcare.consumerID')) {
            $this->notify('warning', 'Modul PCare belum dikonfigurasi. Pastikan pengaturan PCare sudah lengkap untuk Antrol credentials.');
        }
        $defaults = $this->defaults();
        $current = (array) $this->settings(self::MODULE);
        // Checkbox absent from POST means unchecked
        if (!isset($input['antrol_enabled'])) { $input['antrol_enabled'] = '0'; }
        foreach ($defaults as $key => $default) {
            $value = isset($input[$key]) ? trim((string) $input[$key]) : $default;
            if ($key === 'token_secret' && $value === '') {
                $value = !empty($current['token_secret']) ? $current['token_secret'] : bin2hex(random_bytes(32));
            }
            if ($key === 'token_ttl_seconds') {
                $value = (string) min(3600, max(60, (int) $value));
            }
            if ($key === 'booking_open_days') {
                $value = (string) min(90, max(0, (int) $value));
            }
            if ($key === 'queue_format' && strpos($value, '{nomor}') === false) {
                $value = '{kodepoli}-{nomor}';
            }
            $this->settings(self::MODULE, $key, $value);
        }
        $this->notify('success', 'Pengaturan JKN Mobile FKTP Dev tersimpan.');
        redirect(url([ADMIN, self::MODULE, 'manage']));
    }

    private function logUrl($page, $date, $action, $outcome)
    {
        return url([ADMIN, self::MODULE, 'logs']).'?'.http_build_query([
            'date' => $date, 'action' => $action, 'outcome' => $outcome, 'page' => $page
        ]);
    }

    private function adminNav($active)
    {
        return [
            ['key' => 'settings', 'label' => 'Pengaturan', 'icon' => 'cog', 'url' => url([ADMIN, self::MODULE, 'manage']), 'active' => $active === 'settings'],
            ['key' => 'schedule', 'label' => 'Poli & Jadwal Online', 'icon' => 'calendar', 'url' => url([ADMIN, self::MODULE, 'onlineSchedule']), 'active' => $active === 'schedule'],
            ['key' => 'week', 'label' => 'Generate 1 Minggu', 'icon' => 'calendar-check-o', 'url' => url([ADMIN, self::MODULE, 'weekSchedule']), 'active' => $active === 'week'],
            ['key' => 'poli', 'label' => 'Mapping Poli', 'icon' => 'building-o', 'url' => url([ADMIN, self::MODULE, 'mappingPoli']), 'active' => $active === 'poli'],
            ['key' => 'doctor', 'label' => 'Mapping Dokter', 'icon' => 'user-md', 'url' => url([ADMIN, self::MODULE, 'mappingDokter']), 'active' => $active === 'doctor'],
            ['key' => 'logs', 'label' => 'Log Transaksi', 'icon' => 'list-alt', 'url' => url([ADMIN, self::MODULE, 'logs']), 'active' => $active === 'logs']
        ];
    }

    private function moduleStats()
    {
        $pdo = $this->db()->pdo();
        return [
            'poli' => (int) $pdo->query('SELECT COUNT(*) FROM maping_poliklinik_pcare')->fetchColumn(),
            'dokter' => (int) $pdo->query('SELECT COUNT(*) FROM maping_dokter_pcare')->fetchColumn(),
            'logs_today' => (int) $pdo->query('SELECT COUNT(*) FROM mlite_jkn_mobile_fktp_dev_log WHERE DATE(created_at) = CURDATE()')->fetchColumn()
        ];
    }

    private function postText($key)
    {
        return isset($_POST[$key]) && !is_array($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    }

    private function validateMapping($local, $code, $name, $type)
    {
        if ($local === '' || $code === '' || $name === '') {
            $this->notify('failure', 'Kode lokal, kode PCare, dan nama PCare wajib diisi.');
            redirect(url([ADMIN, self::MODULE, $type === 'poli' ? 'mappingPoli' : 'mappingDokter']));
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,30}$/', $code) || strlen($name) > 100) {
            $this->notify('failure', 'Kode PCare hanya boleh huruf, angka, garis bawah, atau strip (maks. 30); nama maks. 100 karakter.');
            redirect(url([ADMIN, self::MODULE, $type === 'poli' ? 'mappingPoli' : 'mappingDokter']));
        }
        $table = $type === 'poli' ? 'poliklinik' : 'dokter';
        $column = $type === 'poli' ? 'kd_poli' : 'kd_dokter';
        $localExists = $this->db()->pdo()->prepare('SELECT 1 FROM '.$table.' WHERE '.$column.' = ? LIMIT 1');
        $localExists->execute([$local]);
        if (!$localExists->fetchColumn()) {
            $this->notify('failure', 'Kode lokal tidak ditemukan. Pilih poli atau dokter dari daftar.');
            redirect(url([ADMIN, self::MODULE, $type === 'poli' ? 'mappingPoli' : 'mappingDokter']));
        }
    }

    private function dateFilter($value)
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $value : date('Y-m-d');
    }

    private function filterValue($value, array $allowed)
    {
        return in_array($value, $allowed, true) ? $value : '';
    }


    private function defaults()
    {
        return [
            'username' => '', 'password' => '', 'token_secret' => '',
            'token_ttl_seconds' => '300', 'payer_code' => 'BPJ',
            'booking_open_days' => '30', 'queue_format' => '{kodepoli}-{nomor}',
            'allowed_origin' => '', 'perusahaan_pasien' => '-',
            'suku_bangsa' => '1', 'bahasa_pasien' => '1', 'cacat_fisik' => '1',
            'antrol_url' => '', 'antrol_enabled' => '0'
        ];
    }
}
