<?php
namespace Plugins\Pcare;

use Systems\AdminModule;
use Systems\Lib\PcareService;
use LZCompressor\LZString;

class Admin extends AdminModule
{
  private $usernamePcare;
  private $passwordPcare;
  private $kdAplikasi;
  private $consumerID;
  private $consumerSecret;
  private $consumerUserKey;
  private $api_url;
  private $api_url_antrol;
  private $api_url_icare;
  private $assign;

  public function init()
  {
    $this->usernamePcare = $this->settings->get('pcare.usernamePcare');
    $this->passwordPcare = $this->settings->get('pcare.passwordPcare');
    $this->kdAplikasi = '095';
    $this->consumerID = $this->settings->get('pcare.consumerID');
    $this->consumerSecret = $this->settings->get('pcare.consumerSecret');
    $this->consumerUserKey = $this->settings->get('pcare.consumerUserKey');
    $this->api_url = $this->settings->get('pcare.PCareApiUrl');
    if (!empty($this->api_url)) {
        $this->api_url = rtrim($this->api_url, '/') . '/';
    }
    $this->api_url_antrol = 'https://apijkn.bpjs-kesehatan.go.id/antreanfktp/';
    $this->api_url_icare = 'https://apijkn.bpjs-kesehatan.go.id/wsIHS/api/pcare/validate';
    if ($this->api_url !== null && strpos($this->api_url, 'dev') !== false) { 
      $this->api_url_antrol = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev/';
      $this->api_url_icare = 'https://apijkn-dev.bpjs-kesehatan.go.id/ihs_dev/api/pcare/validate';
    }  
  }

  public function navigation()
  {
      return [
          'Kelola'   => 'manage',
          'Monitor PCare' => 'monitorpcare',
          'Dashboard' => 'dashboard',
          'Data Kunjungan BPJS' => 'datakunjunganbpjs',
          'Cek Sinkronisasi' => 'ceksinkronisasi',
          'Cek Pendaftaran Provider' => 'cekpendaftaranprovider',
          'Diagnosa' => 'refdiagnosa',
          'Dokter' => 'refdokter',
          'Kesadaran' => 'refkesadaran',
          'Kunjungan' => 'refkunjungan',
          'MCU' => 'refmcu',
          'Obat' => 'refobat',
          'Pendaftaran' => 'refpendaftaran',
          'Peserta' => 'refpeserta',
          'Poli' => 'refpoli',
          'Alergi' => 'refalergi',
          'Prognosa' => 'refprognosa',
          'Provider' => 'refprovider',
          'Tindakan' => 'reftindakan',
          'Status Pulang' => 'refstatuspulang',
          'Kelompok' => 'refkelompok',
          'Spesialis' => 'refspesialis',
          'Settings' => 'settings'
      ];
  }

  public function getManage()
  {
      $parsedown = new \Systems\Lib\Parsedown();
      $readme_file = MODULES.'/pcare/Help.md';
      $readme =  $parsedown->text($this->tpl->noParse(file_get_contents($readme_file)));
      return $this->draw('manage.html', ['readme' => $readme]);
  }

  public function getMonitorpcare()
  {
      $this->_addHeaderFiles();
      return $this->draw('monitorpcare.html');
  }

  public function getMonitorpcareDisplay()
  {
      $date_input = isset($_GET['date']) ? $_GET['date'] : date('d-m-Y');
      $date_parts = explode('-', $date_input);
      if (count($date_parts) == 3) {
          $date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
      } else {
          $date = date('Y-m-d');
      }

      // === DATA DASHBOARD ===
      $kd_pj_bpjs = $this->settings->get('jkn_mobile.kd_pj_bpjs');

      $query_dashboard = "SELECT
          reg_periksa.no_rawat,
          reg_periksa.no_rkm_medis,
          reg_periksa.tgl_registrasi,
          reg_periksa.jam_reg,
          reg_periksa.kd_dokter,
          reg_periksa.kd_poli,
          reg_periksa.stts,
          reg_periksa.no_reg,
          pasien.nm_pasien,
          pasien.no_peserta,
          poliklinik.nm_poli,
          dokter.nm_dokter,
          penjab.png_jawab,
          bp.id AS bridging_id,
          bp.nomor_urut,
          bp.nomor_kunjungan,
          bp.nomor_jaminan,
          bp.status_kirim
      FROM reg_periksa
      INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
      INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
      INNER JOIN penjab ON penjab.kd_pj = reg_periksa.kd_pj
      LEFT JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
      LEFT JOIN mlite_bridging_pcare bp ON bp.no_rawat = reg_periksa.no_rawat
      WHERE reg_periksa.tgl_registrasi = '" . addslashes($date) . "'";

      if (!empty($kd_pj_bpjs)) {
          $query_dashboard .= " AND reg_periksa.kd_pj = '" . addslashes($kd_pj_bpjs) . "'";
      } else {
          $query_dashboard .= " AND (penjab.png_jawab LIKE '%BPJS%' OR penjab.png_jawab LIKE '%JKN%')";
      }
      $query_dashboard .= " ORDER BY reg_periksa.no_reg ASC";
      $rows = $this->db()->pdo()->query($query_dashboard)->fetchAll(\PDO::FETCH_ASSOC);

      $total = count($rows);
      $sudah_daftar = 0;
      $sudah_kunjungan = 0;
      $belum_kirim = 0;
      $siap_kirim = 0;
      $sudah_periksa = 0;
      $batal = 0;
      foreach ($rows as $row) {
          if ($row['stts'] == 'Batal') $batal++;
          if ($row['stts'] == 'Sudah') $sudah_periksa++;
          if (!empty($row['nomor_urut'])) $sudah_daftar++;
          if (!empty($row['nomor_kunjungan'])) $sudah_kunjungan++;
          if (empty($row['nomor_kunjungan']) && $row['stts'] != 'Batal') $belum_kirim++;
          if (!empty($row['nomor_urut']) && empty($row['nomor_kunjungan']) && $row['stts'] == 'Sudah') $siap_kirim++;
      }

      // === DATA SINKRONISASI ===
      $query_sync = "SELECT
          bp.nomor_urut,
          bp.no_rawat,
          bp.no_rkm_medis,
          bp.nomor_kunjungan,
          bp.kode_poli AS kode_poli_pcare,
          bp.status_kirim,
          reg_periksa.stts,
          reg_periksa.kd_poli,
          pasien.nm_pasien,
          pasien.no_peserta,
          poliklinik.nm_poli
      FROM mlite_bridging_pcare bp
      INNER JOIN reg_periksa ON reg_periksa.no_rawat = bp.no_rawat
      INNER JOIN pasien ON pasien.no_rkm_medis = bp.no_rkm_medis
      INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
      WHERE reg_periksa.tgl_registrasi = '" . addslashes($date) . "'";

      if (!empty($kd_pj_bpjs)) {
          $query_sync .= " AND reg_periksa.kd_pj = '" . addslashes($kd_pj_bpjs) . "'";
      }
      $query_sync .= " ORDER BY bp.nomor_urut ASC";
      $mlite_rows = $this->db()->pdo()->query($query_sync)->fetchAll(\PDO::FETCH_ASSOC);

      $prefix_max = [];
      $mlite_urut_list = [];
      foreach ($mlite_rows as $row) {
          $noUrut = $row['nomor_urut'];
          $mlite_urut_list[] = $noUrut;
          if (preg_match('/^([A-Z]+)(\d+)$/i', $noUrut, $m)) {
              $prefix = strtoupper($m[1]);
              $num = intval($m[2]);
              if (!isset($prefix_max[$prefix]) || $num > $prefix_max[$prefix]) {
                  $prefix_max[$prefix] = $num;
              }
          }
      }

      echo $this->draw('monitorpcare.display.html', [
          'rows' => $rows,
          'date' => $date_input,
          'total' => $total,
          'sudah_daftar' => $sudah_daftar,
          'sudah_kunjungan' => $sudah_kunjungan,
          'belum_kirim' => $belum_kirim,
          'siap_kirim' => $siap_kirim,
          'sudah_periksa' => $sudah_periksa,
          'batal' => $batal,
          'tglDaftar' => $date_input,
          'mlite_rows' => $mlite_rows,
          'mlite_urut_list' => $mlite_urut_list,
          'prefix_max' => $prefix_max,
          'total_mlite' => count($mlite_rows)
      ]);
      exit();
  }

  public function getDashboard()
  {
      $this->_addHeaderFiles();
      return $this->draw('dashboard.html');
  }

  public function getDashboardDisplay()
  {
      $date_input = isset($_GET['date']) ? $_GET['date'] : date('d-m-Y');
      $date_parts = explode('-', $date_input);
      if (count($date_parts) == 3) {
          $date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
      } else {
          $date = date('Y-m-d');
      }

      // Ambil kd_pj BPJS dari setting
      $kd_pj_bpjs = $this->settings->get('jkn_mobile.kd_pj_bpjs');

      // Query semua pasien BPJS terdaftar hari itu, LEFT JOIN ke bridging
      $query = "SELECT
          reg_periksa.no_rawat,
          reg_periksa.no_rkm_medis,
          reg_periksa.tgl_registrasi,
          reg_periksa.jam_reg,
          reg_periksa.kd_dokter,
          reg_periksa.kd_poli,
          reg_periksa.stts,
          reg_periksa.no_reg,
          pasien.nm_pasien,
          pasien.no_peserta,
          poliklinik.nm_poli,
          dokter.nm_dokter,
          penjab.png_jawab,
          bp.id AS bridging_id,
          bp.nomor_urut,
          bp.nomor_kunjungan,
          bp.nomor_jaminan,
          bp.status_kirim
      FROM reg_periksa
      INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
      INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
      INNER JOIN penjab ON penjab.kd_pj = reg_periksa.kd_pj
      LEFT JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
      LEFT JOIN mlite_bridging_pcare bp ON bp.no_rawat = reg_periksa.no_rawat
      WHERE reg_periksa.tgl_registrasi = '" . addslashes($date) . "'";

      // Filter BPJS: gunakan kd_pj jika ada, fallback ke nama penjab
      if (!empty($kd_pj_bpjs)) {
          $query .= " AND reg_periksa.kd_pj = '" . addslashes($kd_pj_bpjs) . "'";
      } else {
          $query .= " AND (penjab.png_jawab LIKE '%BPJS%' OR penjab.png_jawab LIKE '%JKN%')";
      }

      $query .= " ORDER BY reg_periksa.no_reg ASC";

      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      // Hitung summary
      $total = count($rows);
      $sudah_daftar = 0;
      $sudah_kunjungan = 0;
      $belum_kirim = 0;
      $siap_kirim = 0;
      $sudah_periksa = 0;
      $batal = 0;
      foreach ($rows as $row) {
          if ($row['stts'] == 'Batal') $batal++;
          if ($row['stts'] == 'Sudah') $sudah_periksa++;
          if (!empty($row['nomor_urut'])) $sudah_daftar++;
          if (!empty($row['nomor_kunjungan'])) $sudah_kunjungan++;
          if (empty($row['nomor_kunjungan']) && $row['stts'] != 'Batal') $belum_kirim++;
          // Siap kirim = sudah daftar + belum kirim kunjungan + sudah selesai diperiksa dokter
          if (!empty($row['nomor_urut']) && empty($row['nomor_kunjungan']) && $row['stts'] == 'Sudah') $siap_kirim++;
      }

      echo $this->draw('dashboard.display.html', [
          'rows' => $rows,
          'date' => $date_input,
          'total' => $total,
          'sudah_daftar' => $sudah_daftar,
          'sudah_kunjungan' => $sudah_kunjungan,
          'belum_kirim' => $belum_kirim,
          'siap_kirim' => $siap_kirim,
          'sudah_periksa' => $sudah_periksa,
          'batal' => $batal
      ]);
      exit();
  }

  public function getDataKunjunganBpjs()
  {
      $this->_addHeaderFiles();
      return $this->draw('datakunjunganbpjs.html');
  }

  public function getDataKunjunganBpjsDisplay()
  {
      $date_input = isset($_GET['date']) ? $_GET['date'] : date('d-m-Y');
      // Konversi dari format DD-MM-YYYY ke YYYY-MM-DD untuk query database
      $date_parts = explode('-', $date_input);
      if (count($date_parts) == 3) {
          $date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0]; // YYYY-MM-DD
      } else {
          $date = date('Y-m-d');
      }
      
      // Query data kunjungan BPJS dari mlite_bridging_pcare
      $rows = $this->db('mlite_bridging_pcare')
          ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
          ->join('reg_periksa', 'reg_periksa.no_rawat=mlite_bridging_pcare.no_rawat')
          ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
          ->leftJoin('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
          ->where('reg_periksa.tgl_registrasi', $date)
          ->select([
              'mlite_bridging_pcare.id',
              'mlite_bridging_pcare.no_rawat',
              'mlite_bridging_pcare.no_rkm_medis',
              'mlite_bridging_pcare.nomor_urut',
              'mlite_bridging_pcare.nomor_kunjungan',
              'mlite_bridging_pcare.nomor_jaminan',
              'mlite_bridging_pcare.tgl_daftar',
              'mlite_bridging_pcare.tgl_kunjungan',
              'mlite_bridging_pcare.kode_poli',
              'mlite_bridging_pcare.nama_poli',
              'mlite_bridging_pcare.kode_dokter',
              'mlite_bridging_pcare.nama_dokter',
              'mlite_bridging_pcare.kunjungan_sakit',
              'mlite_bridging_pcare.sistole',
              'mlite_bridging_pcare.diastole',
              'mlite_bridging_pcare.berat',
              'mlite_bridging_pcare.tinggi',
              'mlite_bridging_pcare.nadi',
              'mlite_bridging_pcare.respirasi',
              'mlite_bridging_pcare.lingkar_perut',
              'mlite_bridging_pcare.subyektif',
              'mlite_bridging_pcare.kode_kesadaran',
              'mlite_bridging_pcare.nama_kesadaran',
              'mlite_bridging_pcare.terapi',
              'mlite_bridging_pcare.kode_status_pulang',
              'mlite_bridging_pcare.nama_status_pulang',
              'mlite_bridging_pcare.tgl_pulang',
              'mlite_bridging_pcare.kode_diagnosa1',
              'mlite_bridging_pcare.nama_diagnosa1',
              'mlite_bridging_pcare.kode_diagnosa2',
              'mlite_bridging_pcare.nama_diagnosa2',
              'mlite_bridging_pcare.kode_diagnosa3',
              'mlite_bridging_pcare.nama_diagnosa3',
              'mlite_bridging_pcare.kode_provider_peserta',
              'mlite_bridging_pcare.rujuk_balik',
              'mlite_bridging_pcare.kode_tkp',
              'mlite_bridging_pcare.tgl_estimasi_rujuk',
              'mlite_bridging_pcare.kode_ppk',
              'mlite_bridging_pcare.nama_ppk',
              'mlite_bridging_pcare.kode_spesialis',
              'mlite_bridging_pcare.nama_spesialis',
              'mlite_bridging_pcare.kode_subspesialis',
              'mlite_bridging_pcare.nama_subspesialis',
              'mlite_bridging_pcare.kode_sarana',
              'mlite_bridging_pcare.nama_sarana',
              'mlite_bridging_pcare.kode_referensikhusus',
              'mlite_bridging_pcare.nama_referensikhusus',
              'mlite_bridging_pcare.kode_faskeskhusus',
              'mlite_bridging_pcare.nama_faskeskhusus',
              'mlite_bridging_pcare.catatan',
              'mlite_bridging_pcare.kode_tacc',
              'mlite_bridging_pcare.nama_tacc',
              'mlite_bridging_pcare.alasan_tacc',
              'mlite_bridging_pcare.status_kirim',
              'mlite_bridging_pcare.tgl_input',
              'pasien.nm_pasien',
              'pasien.no_ktp',
              'pasien.jk',
              'pasien.tgl_lahir',
              'pasien.alamat',
              'reg_periksa.no_reg',
              'reg_periksa.tgl_registrasi',
              'reg_periksa.jam_reg',
              'reg_periksa.stts',
              'reg_periksa.kd_pj',
              'poliklinik.nm_poli',
              'dokter.nm_dokter'
          ])
          ->asc('mlite_bridging_pcare.nomor_urut')
          ->toArray();
      
      echo $this->draw('datakunjunganbpjs.display.html', [
          'kunjungan_bpjs' => $rows,
          'date' => $date_input
      ]);
      exit();
  }

  public function postKirimKunjunganBpjs()
  {
    try {
      $id = isset($_POST['id']) ? $_POST['id'] : '';
      $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
      $no_rkm_medis = isset($_POST['no_rkm_medis']) ? $_POST['no_rkm_medis'] : '';
      $nomor_jaminan = isset($_POST['nomor_jaminan']) ? $_POST['nomor_jaminan'] : '';
      
      // Parameter dari form modal
      $tgl_kunjungan = isset($_POST['tgl_kunjungan']) ? $_POST['tgl_kunjungan'] : '';
      $tgl_pulang = isset($_POST['tgl_pulang']) ? $_POST['tgl_pulang'] : '';
      $kunjungan_sakit = isset($_POST['kunjungan_sakit']) ? $_POST['kunjungan_sakit'] : 'true';
      $subyektif = isset($_POST['subyektif']) ? $_POST['subyektif'] : '';
      $sistole = isset($_POST['sistole']) ? $_POST['sistole'] : '0';
      $diastole = isset($_POST['diastole']) ? $_POST['diastole'] : '0';
      $berat = isset($_POST['berat']) ? $_POST['berat'] : '0';
      $tinggi = isset($_POST['tinggi']) ? $_POST['tinggi'] : '0';
      $nadi = isset($_POST['nadi']) ? $_POST['nadi'] : '0';
      $respirasi = isset($_POST['respirasi']) ? $_POST['respirasi'] : '0';
      $lingkar_perut = isset($_POST['lingkar_perut']) ? $_POST['lingkar_perut'] : '0';
      $kdDokter = isset($_POST['kdDokter']) ? $_POST['kdDokter'] : '';
      $kdKesadaran = isset($_POST['kdKesadaran']) ? $_POST['kdKesadaran'] : '';
      $kdStatusPulang = isset($_POST['kdStatusPulang']) ? $_POST['kdStatusPulang'] : '';
      $kdDiagnosa1 = isset($_POST['kdDiagnosa1']) ? $_POST['kdDiagnosa1'] : '';
      $kdDiagnosa2 = isset($_POST['kdDiagnosa2']) ? $_POST['kdDiagnosa2'] : '';
      $kdDiagnosa3 = isset($_POST['kdDiagnosa3']) ? $_POST['kdDiagnosa3'] : '';
      $kdPoli = isset($_POST['kdPoli']) ? $_POST['kdPoli'] : '';
      $terapi = isset($_POST['terapi']) ? $_POST['terapi'] : '';
      $terapiObat = isset($_POST['terapiObat']) ? $_POST['terapiObat'] : 'tidak ada';
      $terapiNonObat = isset($_POST['terapiNonObat']) ? $_POST['terapiNonObat'] : 'tidak ada';
      
      // Parameter tambahan
      $suhu = isset($_POST['suhu']) ? $_POST['suhu'] : '36';
      $anamnesa = isset($_POST['anamnesa']) ? $_POST['anamnesa'] : '';
      $kdPrognosa = isset($_POST['kdPrognosa']) ? $_POST['kdPrognosa'] : '01';
      $alergiMakan = isset($_POST['kdAlergiMakan']) ? $_POST['kdAlergiMakan'] : '';
      $alergiUdara = isset($_POST['kdAlergiUdara']) ? $_POST['kdAlergiUdara'] : '';
      $alergiObat = isset($_POST['kdAlergiObat']) ? $_POST['kdAlergiObat'] : '';
      $kdProvider = isset($_POST['kode_provider']) ? $_POST['kode_provider'] : '';
      $jnsLayanan = isset($_POST['jenis_layanan']) ? $_POST['jenis_layanan'] : '1';
      
      if (empty($id) || empty($no_rawat)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
          exit();
      }
      
      // Ambil data dari mlite_bridging_pcare
      $bridging = $this->db('mlite_bridging_pcare')->where('id', $id)->oneArray();
      if (empty($bridging)) {
          echo json_encode(['status' => 'error', 'message' => 'Data bridging tidak ditemukan']);
          exit();
      }
      
      // Ambil data reg_periksa
      $reg_periksa = $this->db('reg_periksa')
          ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
          ->where('no_rawat', $no_rawat)
          ->oneArray();

      // Validasi status periksa - hanya pasien yang sudah selesai diperiksa yang bisa dikirim
      if (!empty($reg_periksa) && $reg_periksa['stts'] != 'Sudah') {
          echo json_encode(['status' => 'error', 'message' => 'Pasien belum selesai diperiksa (status: ' . $reg_periksa['stts'] . '). Kunjungan hanya bisa dikirim untuk pasien dengan status Sudah.']);
          exit();
      }
      
      // Ambil data pemeriksaan untuk keluhan
      $pemeriksaan = $this->db('pemeriksaan_ralan')
          ->where('no_rawat', $no_rawat)
          ->oneArray();
      
      // Tentukan nilai-nilai dengan prioritas: POST > bridging > pemeriksaan > default
      // Kode Poli
      if (empty($kdPoli)) {
          $map_poli = $this->db('maping_poliklinik_pcare')
              ->where('kd_poli_rs', $reg_periksa['kd_poli'])
              ->oneArray();
          $kdPoli = !empty($map_poli) ? $map_poli['kd_poli_pcare'] : '001';
      }
      
      // Kode Dokter
      if (empty($kdDokter)) {
          $map_dokter = $this->db('maping_dokter_pcare')
              ->where('kd_dokter', $reg_periksa['kd_dokter'])
              ->oneArray();
          $kdDokter = !empty($map_dokter) ? $map_dokter['kd_dokter_pcare'] : '';
      }
      
      // Diagnosa
      if (empty($kdDiagnosa1)) {
          $diagnosa = $this->db('diagnosa_pasien')
              ->where('no_rawat', $no_rawat)
              ->where('status', 'Ralan')
              ->where('prioritas', 1)
              ->oneArray();
          $kdDiagnosa1 = !empty($diagnosa) ? $diagnosa['kd_penyakit'] : 'Z00.0';
      }
      
      // Format tanggal  
      $tglDaftar = !empty($tgl_kunjungan) ? date('d-m-Y', strtotime($tgl_kunjungan)) : date('d-m-Y', strtotime($reg_periksa['tgl_registrasi']));
      $tglPulang = !empty($tgl_pulang) ? date('d-m-Y', strtotime($tgl_pulang)) : $tglDaftar;
      
      // Keluhan - prioritas subyektif dari form
      $keluhan = !empty($subyektif) ? $subyektif : (!empty($pemeriksaan['keluhan']) ? $pemeriksaan['keluhan'] : (!empty($bridging['subyektif']) ? $bridging['subyektif'] : 'Keluhan umum'));
      
      // Kesadaran default
      if (empty($kdKesadaran)) $kdKesadaran = !empty($bridging['kode_kesadaran']) ? $bridging['kode_kesadaran'] : '01';
      
      // Status Pulang default
      if (empty($kdStatusPulang)) $kdStatusPulang = !empty($bridging['kode_status_pulang']) ? $bridging['kode_status_pulang'] : '3';
      
      // Siapkan data untuk kirim ke PCare
      $data = [
          'noKunjungan' => null,
          'noKartu' => $nomor_jaminan,
          'tglDaftar' => $tglDaftar,
          'kdPoli' => $kdPoli,
          'keluhan' => $keluhan,
          'kdSadar' => $kdKesadaran,
          'sistole' => intval($sistole) > 0 ? intval($sistole) : (intval($bridging['sistole']) > 0 ? intval($bridging['sistole']) : 120),
          'diastole' => intval($diastole) > 0 ? intval($diastole) : (intval($bridging['diastole']) > 0 ? intval($bridging['diastole']) : 80),
          'beratBadan' => intval($berat) > 0 ? intval($berat) : (intval($bridging['berat']) > 0 ? intval($bridging['berat']) : 50),
          'tinggiBadan' => intval($tinggi) > 0 ? intval($tinggi) : (intval($bridging['tinggi']) > 0 ? intval($bridging['tinggi']) : 160),
          'respRate' => intval($respirasi) > 0 ? intval($respirasi) : (intval($bridging['respirasi']) > 0 ? intval($bridging['respirasi']) : 20),
          'heartRate' => intval($nadi) > 0 ? intval($nadi) : (intval($bridging['nadi']) > 0 ? intval($bridging['nadi']) : 80),
          'lingkarPerut' => intval($lingkar_perut) > 0 ? intval($lingkar_perut) : (intval($bridging['lingkar_perut']) > 0 ? intval($bridging['lingkar_perut']) : 70),
          'kdStatusPulang' => $kdStatusPulang,
          'tglPulang' => $tglPulang,
          'kdDokter' => $kdDokter,
          'kdDiag1' => $kdDiagnosa1,
          'kdDiag2' => !empty($kdDiagnosa2) ? $kdDiagnosa2 : null,
          'kdDiag3' => !empty($kdDiagnosa3) ? $kdDiagnosa3 : null,
          'kdPoliRujukInternal' => null,
          'rujukLanjut' => null,
          'kdTacc' => -1,
          'alasanTacc' => null,
          'alergiMakan' => !empty($alergiMakan) ? $alergiMakan : null,
          'alergiUdara' => !empty($alergiUdara) ? $alergiUdara : null,
          'alergiObat' => !empty($alergiObat) ? $alergiObat : null,
          'kdPrognosa' => !empty($kdPrognosa) ? $kdPrognosa : '01',
          'anamnesa' => !empty($anamnesa) ? $anamnesa : (!empty($pemeriksaan['pemeriksaan']) ? $pemeriksaan['pemeriksaan'] : 'Anamnesa'),
          'terapiObat' => $terapiObat,
          'terapiNonObat' => $terapiNonObat,
          'bmhp' => 'bmhp',
          'suhu' => intval($suhu) > 0 ? intval($suhu) : 36
      ];
      
      // Tambahkan kdProvider jika ada
      if (!empty($kdProvider)) {
          $data['kdProvider'] = $kdProvider;
      }
      
      // Jenis layanan (jika diperlukan)
      // $data['jnsLayanan'] = $jnsLayanan;
      
      $data = json_encode($data);
      
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;
      
      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      
      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      
      if ($json != null && $code == '201') {
          $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
          $decompress = '';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
          
          $responseData = json_decode($decompress, true);
          $noKunjungan = '';
          if (is_array($responseData) && isset($responseData[0]['message'])) {
              $noKunjungan = $responseData[0]['message'];
          }
          
          // Update database
          $this->db('mlite_bridging_pcare')
              ->where('id', $id)
              ->save([
                  'nomor_kunjungan' => $noKunjungan,
                  'status_kirim' => 'Sudah',
                  'tgl_input' => date('Y-m-d H:i:s')
              ]);
          
          echo json_encode([
              'status' => 'success',
              'message' => 'Kunjungan berhasil dikirim',
              'nomor_kunjungan' => $noKunjungan
          ]);
      } else {
          // Decrypt error response
          $errorDetail = $message;
          if (isset($json['response']) && !empty($json['response'])) {
              $stringDecrypt = stringDecrypt($key, $json['response']);
              if (!empty($stringDecrypt)) {
                  $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
                  if (!empty($decompress)) {
                      $errorDetail = $decompress;
                  }
              }
          }
          
          echo json_encode([
              'status' => 'error',
              'message' => 'Gagal kirim: ' . $errorDetail
          ]);
      }
      
    } catch (\Throwable $e) {
      echo json_encode([
          'status' => 'error',
          'message' => 'Exception: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
      ]);
    }
    exit();
  }

  public function getSettings()
  {
      $this->_addHeaderFiles();
      $this->assign['title'] = 'Pengaturan PCare';
      
      // Default settings untuk pcare
      $defaultSettings = [
          'usernamePcare' => '',
          'passwordPcare' => '',
          'consumerID' => '',
          'consumerSecret' => '',
          'consumerUserKey' => '',
          'consumerUserKeyAntrol' => '',
          'PCareApiUrl' => '',
          'kode_fktp' => '',
          'nama_fktp' => '',
          'kode_kabupatenkota' => '',
          'kabupatenkota' => '',
          'wilayah' => '',
          'cabang' => ''
      ];
      
      // Ambil settings dari database
      $dbSettings = $this->settings('pcare');
      if (!is_array($dbSettings)) {
          $dbSettings = [];
      }
      
      // Gabungkan default settings dengan database settings
      $pcareSettings = array_merge($defaultSettings, $dbSettings);
      
      $this->assign['pcare'] = htmlspecialchars_array($pcareSettings);
      return $this->draw('settings.html', ['settings' => $this->assign]);
  }

  public function postSaveSettings()
  {
      foreach ($_POST['pcare'] as $key => $val) {
          $this->settings('pcare', $key, $val);
      }
      $this->notify('success', 'Pengaturan telah disimpan');
      redirect(url([ADMIN, 'pcare', 'settings']));
  }

  public function getRefDiagnosa()
  {
      return $this->draw('diagnosa.html');
  }

  public function getDiagnosa($keyword)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'diagnosa/'.$keyword.'/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefDokter()
  {
      return $this->draw('dokter.html');
  }

  public function getDokter()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'dokter/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          $curlError = PcareService::getStatus();
          $httpCode  = PcareService::getLastHttpCode();
          $detail = 'ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS.';
          if ($curlError) $detail .= ' [CURL: ' . addslashes($curlError) . ']';
          if ($httpCode)  $detail .= ' [HTTP: ' . $httpCode . ']';
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "' . $detail . '"}';
      }

      exit();
  }

  public function getRefKesadaran()
  {
      return $this->draw('kesadaran.html');
  }

  public function getKesadaran()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'kesadaran/';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefAlergi()
  {
      return $this->draw('alergi.html');
  }

  public function getAlergi($jenis)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'alergi/jenis/'.$jenis;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefPrognosa()
  {
      return $this->draw('prognosa.html');
  }

  public function getPrognosa()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'prognosa';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefKunjungan()
  {
      $this->_addHeaderFiles();
      return $this->draw('kunjungan.html');
  }

  public function getKunjungan($keyword, $param)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'kunjungan/'.$keyword.'/'.$param;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getAddKunjungan($kdDokter, $tglDaftar, $noKartu, $kdPoli)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $data = [
      'noKunjungan' => null,
      'noKartu' => $noKartu,
      'tglDaftar' => $tglDaftar,
      'kdPoli' => $kdPoli,
      'keluhan' => 'keluhan',
      'kdSadar' => '01',
      'sistole' => 120,
      'diastole' => 80,
      'beratBadan' => 50,
      'tinggiBadan' => 170,
      'respRate' => 70,
      'heartRate' => 80,
      'lingkarPerut' => 36,
      'terapi' => 'catatan',
      'kdStatusPulang' => '3',
      'tglPulang' => $tglDaftar,
      'kdDokter' => $kdDokter,
      'kdDiag1' => 'K04.1',
      'kdDiag2' => null,
      'kdDiag3' => null,
      'kdPoliRujukInternal' => null,
      'rujukLanjut' => [
          'kdppk' => null,
          'tglEstRujuk' => null,
          'subSpesialis' => [
              'kdSubSpesialis1' => null,
              'kdSarana' => null
          ],
          'khusus' => null
      ],
      'kdTacc' => -1,
      'alasanTacc' => null
    ];

    $data = json_encode($data);
    //echo $data;

    $url = $this->api_url . 'kunjungan';
    $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $decompress = '""';
    
    // Decrypt response baik success maupun error
    if (isset($json['response']) && !empty($json['response'])) {
        $stringDecrypt = stringDecrypt($key, $json['response']);
        if (!empty($stringDecrypt)) {
            $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
        }
    }
    
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'add_kunjungan', 'noKartu' => $noKartu]);
    }

    exit();

  }

  public function getEditKunjungan($noKunjungan, $noKartu, $kdSadar, $tglPulang, $kdDokter)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $data = [
      'noKunjungan' => $noKunjungan,
      'noKartu' => $noKartu,
      'keluhan' => 'keluhan',
      'kdSadar' => $kdSadar,
      'sistole' => 80,
      'diastole' => 80,
      'beratBadan' => 50,
      'tinggiBadan' => 170,
      'respRate' => 70,
      'heartRate' => 80,
      'lingkarPerut' => 36,
      'terapi' => 'catatan',
      'kdStatusPulang' => '3',
      'tglPulang' => $tglPulang,
      'kdDokter' => $kdDokter,
      'kdDiag1' => 'K04.1',
      'kdDiag2' => null,
      'kdDiag3' => null,
      'kdPoliRujukInternal' => null,
      'rujukLanjut' => [
          'kdppk' => null,
          'tglEstRujuk' => null,
          'subSpesialis' => [
              'kdSubSpesialis1' => null,
              'kdSarana' => null
          ],
          'khusus' => null
      ],
      'kdTacc' => -1,
      'alasanTacc' => null
    ];

    $data = json_encode($data);
    //echo $data;

    $url = $this->api_url . 'kunjungan';
    $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
    //echo $stringDecrypt;
    $decompress = '""';
    if (!empty($stringDecrypt)) {
        $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
    }
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        echo '{
            "metaData": {
              "code": "5000",
              "message": "ERROR"
            },
            "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';

    }

    exit();

  }

  public function getDelKunjungan($noKunjungan)
  {

    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url.'kunjungan/'.$noKunjungan;
    $output = PcareService::delete($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);   
    $json = json_decode($output, true);
    
    if ($json != null) {
        $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
        $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
        
        // Decrypt response untuk detail
        $decompress = '""';
        if (isset($json['response']) && !empty($json['response'])) {
            $stringDecrypt = stringDecrypt($key, $json['response']);
            if (!empty($stringDecrypt)) {
                $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
            }
        }
        
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'delete_kunjungan', 'noKunjungan' => $noKunjungan]);
    }

    exit();
  }

  public function getRefPeserta()
  {
      $this->_addHeaderFiles();
      return $this->draw('peserta.html');
  }

  public function getPeserta($noKartu)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'peserta/'.$noKartu;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getByJenisKartu($jeniskartu, $nomor)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'peserta/'.$jeniskartu.'/'.$nomor;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefPoli()
  {
      return $this->draw('poli.html');
  }

  public function getPoli()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'poli/fktp/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefProvider()
  {
      return $this->draw('provider.html');
  }

  public function getProvider()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'provider/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefStatusPulang()
  {
      return $this->draw('status.pulang.html');
  }

  public function getStatusPulang($status='false')
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'statuspulang/rawatInap/'.$status;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefPendaftaran()
  {
      $this->_addHeaderFiles();
      return $this->draw('pendaftaran.html');
  }

  public function getAddPendaftaran($kdProviderPeserta, $tglDaftar, $noKartu, $kdPoli)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $data = [
      'kdProviderPeserta' => $kdProviderPeserta,
      'tglDaftar' => $tglDaftar,
      'noKartu' => $noKartu,
      'kdPoli' => $kdPoli,
      'keluhan' => null,
      'kunjSakit' => true,
      'sistole' => 0,
      'diastole' => 0,
      'beratBadan' => 0,
      'tinggiBadan' => 0,
      'respRate' => 0,
      'lingkarPerut' => 0,
      'heartRate' => 0,
      'rujukBalik' => 0,
      'kdTkp' => '10'
    ];

    $data = json_encode($data);
    //echo $data;

    $url = $this->api_url . 'pendaftaran';
    $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $decompress = '""';
    
    // Decrypt response baik success maupun error
    if (isset($json['response']) && !empty($json['response'])) {
        $stringDecrypt = stringDecrypt($key, $json['response']);
        if (!empty($stringDecrypt)) {
            $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
        }
    }
    
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'add_pendaftaran', 'noKartu' => $noKartu]);
    }

    exit();

  }

  public function getGetPendaftaranNoUrut($noUrut, $tglDaftar)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url . 'pendaftaran/noUrut/'.$noUrut.'/tglDaftar/'.$tglDaftar;
    $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
    $decompress = '""';
    if (!empty($stringDecrypt)) {
        $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
    }
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        echo '{
            "metaData": {
              "code": "5000",
              "message": "ERROR"
            },
            "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';

    }

    exit();

  }

  public function getGetPendaftaranProvider($tglDaftar)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url . 'pendaftaran/tglDaftar/'.$tglDaftar.'/0/500';
    $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
    $decompress = '""';
    if (!empty($stringDecrypt)) {
        $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
    }
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        echo '{
            "metaData": {
              "code": "5000",
              "message": "ERROR"
            },
            "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';

    }

    exit();

  }

  public function getDelPendaftaran($noKartu, $tglDaftar, $noUrut, $kdPoli)
  {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url . 'pendaftaran/peserta/'.$noKartu.'/tglDaftar/'.$tglDaftar.'/noUrut/'.$noUrut.'/kdPoli/'.$kdPoli;
    $output = PcareService::delete($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $decompress = '""';
    
    // Decrypt response baik success maupun error
    if (isset($json['response']) && !empty($json['response'])) {
        $stringDecrypt = stringDecrypt($key, $json['response']);
        if (!empty($stringDecrypt)) {
            $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
        }
    }
    
    if ($json != null) {
        echo '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
    } else {
        $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'delete_pendaftaran', 'noKartu' => $noKartu]);
    }

    exit();

  }

  public function getRefSpesialis()
  {
      $this->_addHeaderFiles();
      return $this->draw('spesialis.html');
  }

  public function getSpesialis()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getSubSpesialis($subspesialis)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/'.$subspesialis.'/subspesialis';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getSarana()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/sarana';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getFaskesSpesialis($subspesialis, $sarana, $date)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/rujuk/subspesialis/'.$subspesialis.'/sarana/'.$sarana.'/tglEstRujuk/'.$date;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getKhusus()
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/khusus';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getFaskesKhusus($kodeKhusus, $subspesialis, $noKartu, $date)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'spesialis/rujuk/khusus/'.$kodeKhusus.'/noKartu/'.$noKartu.'/tglEstRujuk/'.$date;
      if($kodeKhusus == 'THA') {
        $url = $this->api_url.'spesialis/rujuk/khusus/'.$kodeKhusus.'/subspesialis/'.$subspesialis.'/noKartu/'.$noKartu.'/tglEstRujuk/'.$date;
      }
      if($kodeKhusus == 'HEM') {
        $url = $this->api_url.'spesialis/rujuk/khusus/'.$kodeKhusus.'/subspesialis/'.$subspesialis.'/noKartu/'.$noKartu.'/tglEstRujuk/'.$date;
      }
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getRefTindakan()
  {
      $this->_addHeaderFiles();
      return $this->draw('tindakan.html');
  }

  public function getTindakanKunjungan($noKunjungan)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'tindakan/kunjungan/'.$noKunjungan;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getTindakanReferensi($kdTkp)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'tindakan/kdTkp/'.$kdTkp.'/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getAddTindakan($noKunjungan, $kdTindakan)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $data = [
        'kdTindakanSK' => 0,
        'noKunjungan' => $noKunjungan,
        'kdTindakan' => $kdTindakan,
        'biaya' => 1000,
        'keterangan' => null,
        'hasil' => 1
      ];

      $data = json_encode($data);
      //echo $data;

      $url = $this->api_url . 'tindakan';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $decompress = '""';
      
      // Decrypt response baik success maupun error
      if (isset($json['response']) && !empty($json['response'])) {
          $stringDecrypt = stringDecrypt($key, $json['response']);
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
      }
      
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'add_tindakan', 'noKunjungan' => $noKunjungan]);
      }

      exit();
  }

  public function getEditTindakan($kdTindakanSK, $noKunjungan, $kdTindakan)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $data = [
        'kdTindakanSK' => $kdTindakanSK,
        'noKunjungan' => $noKunjungan,
        'kdTindakan' => $kdTindakan,
        'biaya' => 0,
        'keterangan' => null,
        'hasil' => 0
      ];

      $data = json_encode($data);
      //echo $data;

      $url = $this->api_url . 'tindakan';
      $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getDelTindakan($kdTindakanSK, $noKunjungan)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'tindakan/'.$kdTindakanSK.'/kunjungan/'.$noKunjungan;
      $output = PcareService::delete($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $decompress = '""';
      
      // Decrypt response baik success maupun error
      if (isset($json['response']) && !empty($json['response'])) {
          $stringDecrypt = stringDecrypt($key, $json['response']);
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
      }
      
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'delete_tindakan', 'kdTindakanSK' => $kdTindakanSK]);
      }

      exit();
  }

  public function getRefObat()
  {
      $this->_addHeaderFiles();
      return $this->draw('obat.html');
  }

  public function getObatReferensi($dpho)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'obat/dpho/'.$dpho.'/0/500';
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function getAddObat($noKunjungan, $kdObat, $signa1, $signa2, $jumlah)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $data = [
        'kdObatSK' => 0,
        'noKunjungan' => $noKunjungan,
        'racikan' => false,
        'kdRacikan' => null,
        'obatDPHO' => true,
        'kdObat' => $kdObat,
        'signa1' => intval($signa1),
        'signa2' => intval($signa2),
        'jmlObat' => intval($jumlah),
        'jmlPermintaan' => 1,
        'nmObatNonDPHO' => '-'
      ];

      $data = json_encode($data);
      //echo $data;

      $url = $this->api_url . 'obat/kunjungan';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $decompress = '""';
      
      // Decrypt response baik success maupun error
      if (isset($json['response']) && !empty($json['response'])) {
          $stringDecrypt = stringDecrypt($key, $json['response']);
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
      }
      
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'add_obat', 'noKunjungan' => $noKunjungan]);
      }

      exit();
  }

  public function getDelObat($kdObatSK, $noKunjungan)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'obat/'.$kdObatSK.'/kunjungan/'.$noKunjungan;
      $output = PcareService::delete($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $decompress = '""';
      
      // Decrypt response baik success maupun error
      if (isset($json['response']) && !empty($json['response'])) {
          $stringDecrypt = stringDecrypt($key, $json['response']);
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
      }
      
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'delete_obat', 'kdObatSK' => $kdObatSK]);
      }

      exit();
  }

  public function getPendaftaranPCare($no_rkm_medis, $date)
  {
    $reg_periksa = $this->db('reg_periksa')->where('no_rkm_medis', $no_rkm_medis)->like('tgl_registrasi', $date)->oneArray();
    $rows_bridging_pcare = $this->db('mlite_bridging_pcare')
      ->where('no_rkm_medis', $no_rkm_medis)
      ->toArray();
    $bridging_pcare = [];
    foreach($rows_bridging_pcare as $row) {
      $row['pasien'] = $this->db('pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray();
      $bridging_pcare[] = $row;
    }
    echo $this->draw('pendaftaranpcare.html', ['pasien' => $this->db('pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray(), 'bridging_pcare' => $bridging_pcare, 'pendaftaran' => $reg_periksa, 'kode_fktp' => $this->settings->get('pcare.kode_fktp')]);
    exit();
  }

  public function getBridgingPCare($no_rkm_medis, $date)
  {
    $date = date('Y-m-d', strtotime($date));
    $pendaftaran = $this->db('reg_periksa')->where('no_rkm_medis', $no_rkm_medis)->like('tgl_registrasi', $date)->oneArray();
    if (empty($pendaftaran)) $pendaftaran = ['no_rawat' => '', 'kd_poli' => '', 'kd_dokter' => ''];
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('mlite_bridging_pcare.no_rkm_medis', $no_rkm_medis)
      ->toArray();
    
    // Gabungkan data pemeriksaan dari multiple petugas
    $pemeriksaanList = !empty($pendaftaran['no_rawat']) ? $this->db('pemeriksaan_ralan')->where('no_rawat', $pendaftaran['no_rawat'])->toArray() : [];
    $pemeriksaanGabung = $this->gabungPemeriksaan($pemeriksaanList);

    // Prefill Diagnosa 1 dari diagnosa_pasien prioritas 1 (status Ralan)
    $diagnosa_prefill = '';
    $diagnosa = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
      ->where('diagnosa_pasien.no_rawat', $pendaftaran['no_rawat'])
      ->where('diagnosa_pasien.status', 'Ralan')
      ->where('diagnosa_pasien.prioritas', 1)
      ->oneArray();
    if (!empty($diagnosa)) {
      $diagnosa_prefill = $diagnosa['kd_penyakit'] . ': ' . $diagnosa['nm_penyakit'];
    }

    // Prefill Poliklinik dari mapping
    $poli_prefill = '';
    if (!empty($pendaftaran['kd_poli'])) {
      $map_poli = $this->db('maping_poliklinik_pcare')->where('kd_poli_rs', $pendaftaran['kd_poli'])->oneArray();
      if (!empty($map_poli)) {
        $poli_prefill = $map_poli['kd_poli_pcare'] . ': ' . $map_poli['nm_poli_pcare'];
      }
    }

    // Prefill Dokter dari mapping
    $dokter_prefill = '';
    if (!empty($pendaftaran['kd_dokter'])) {
      $map_dokter = $this->db('maping_dokter_pcare')->where('kd_dokter', $pendaftaran['kd_dokter'])->oneArray();
      if (!empty($map_dokter)) {
        $dokter_prefill = $map_dokter['kd_dokter_pcare'] . ': ' . ltrim($map_dokter['nm_dokter_pcare']);
      }
    }

    // Prefill referensi lain (default contoh seperti lampiran)
    $status_pulang_prefill = '3: Berobat Jalan';
    $kesadaran_prefill = '01: Compos mentis';
    $prognosa_prefill = '01: Sanam (Sembuh)';
    $alergi_makan_prefill = '00: Tidak Ada';
    $alergi_udara_prefill = '00: Tidak Ada';
    $alergi_obat_prefill = '00: Tidak Ada';

    // Ambil data alergi dari tabel alergi_pasien untuk prefill
    $alergi_pasien_data = $this->db('alergi_pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray();
    if (!empty($alergi_pasien_data)) {
      $mapMakanan = ['00'=>'Tidak Ada','01'=>'Seafood','02'=>'Gandum','03'=>'Susu Sapi','04'=>'Kacang-Kacangan','05'=>'Makanan Lain'];
      $mapUdara = ['00'=>'Tidak Ada','01'=>'Udara Panas','02'=>'Udara Dingin','03'=>'Udara Kotor'];
      $mapObat = ['00'=>'Tidak Ada','01'=>'Antibiotik','02'=>'Antiinflamasi','03'=>'Non Steroid','04'=>'Aspirin','05'=>'Kortikosteroid','06'=>'Insulin','07'=>'Obat-Obatan Lain'];

      $kdMakanan = $alergi_pasien_data['alergi_makanan'] ?? '00';
      $kdUdara = $alergi_pasien_data['alergi_udara'] ?? '00';
      $kdObat = $alergi_pasien_data['alergi_obat'] ?? '00';

      $nmMakanan = $mapMakanan[$kdMakanan] ?? 'Tidak Ada';
      $nmUdara = $mapUdara[$kdUdara] ?? 'Tidak Ada';
      $nmObat = $mapObat[$kdObat] ?? 'Tidak Ada';

      // Jika ada keterangan "lainnya", tambahkan
      if ($kdMakanan == '05' && !empty($alergi_pasien_data['alergi_makanan_lainnya'])) {
        $nmMakanan .= ' (' . $alergi_pasien_data['alergi_makanan_lainnya'] . ')';
      }
      if (!empty($alergi_pasien_data['alergi_udara_lainnya'])) {
        $nmUdara .= ' (' . $alergi_pasien_data['alergi_udara_lainnya'] . ')';
      }
      if ($kdObat == '07' && !empty($alergi_pasien_data['alergi_obat_lainnya'])) {
        $nmObat .= ' (' . $alergi_pasien_data['alergi_obat_lainnya'] . ')';
      }

      $alergi_makan_prefill = $kdMakanan . ': ' . $nmMakanan;
      $alergi_udara_prefill = $kdUdara . ': ' . $nmUdara;
      $alergi_obat_prefill = $kdObat . ': ' . $nmObat;
    }
    
    // Prefill Terapi Obat dari tabel detail_pemberian_obat (data apotek)
    $terapi_obat_prefill = 'tidak ada';
    $pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
      ->where('detail_pemberian_obat.no_rawat', $pendaftaran['no_rawat'])
      ->desc('detail_pemberian_obat.tgl_perawatan')
      ->desc('detail_pemberian_obat.jam')
      ->toArray();
    if (!empty($pemberian_obat)) {
      $obat_list = [];
      foreach ($pemberian_obat as $obat) {
        // Ambil aturan pakai dari tabel aturan_pakai
        $aturan = $this->db('aturan_pakai')
          ->where('no_rawat', $obat['no_rawat'])
          ->where('kode_brng', $obat['kode_brng'])
          ->where('tgl_perawatan', $obat['tgl_perawatan'])
          ->where('jam', $obat['jam'])
          ->oneArray();
        $aturan_text = !empty($aturan['aturan']) ? ' ' . $aturan['aturan'] : '';
        $obat_list[] = $obat['nama_brng'] . ' (' . $obat['jml'] . ')' . $aturan_text;
      }
      $terapi_obat_prefill = implode(', ', $obat_list);
    }

    // Ambil tanggal dari no_rawat (format: YYYY/MM/DD/XXXXXX)
    $tgl_dari_norawat = date('d-m-Y');
    if (!empty($pendaftaran['no_rawat'])) {
        $parts = explode('/', $pendaftaran['no_rawat']);
        if (count($parts) >= 3 && strlen($parts[0]) == 4 && strlen($parts[1]) == 2 && strlen($parts[2]) == 2) {
            $tgl_dari_norawat = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }

    echo $this->draw('bridgingpcare.html', [
      'pasien' => $this->db('pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray(),
      'pemeriksaan' => $pemeriksaanGabung,
      'bridging_pcare' => $bridging_pcare,
      'diagnosa1_prefill' => $diagnosa_prefill,
      'poli_prefill' => $poli_prefill,
      'dokter_prefill' => $dokter_prefill,
      'status_pulang_prefill' => $status_pulang_prefill,
      'kesadaran_prefill' => $kesadaran_prefill,
      'prognosa_prefill' => $prognosa_prefill,
      'alergi_makan_prefill' => $alergi_makan_prefill,
      'alergi_udara_prefill' => $alergi_udara_prefill,
      'alergi_obat_prefill' => $alergi_obat_prefill,
      'terapi_obat_prefill' => $terapi_obat_prefill,
      'tgl_dari_norawat' => $tgl_dari_norawat
    ]);
    exit();
  }

  // Fungsi untuk menggabungkan data pemeriksaan dari multiple petugas
  private function gabungPemeriksaan($pemeriksaanList)
  {
    if(empty($pemeriksaanList)) {
      return [];
    }

    // Jika hanya ada 1 record, return langsung
    if(count($pemeriksaanList) == 1) {
      return $pemeriksaanList[0];
    }

    // Gabungkan data dari multiple records
    $gabung = $pemeriksaanList[0];
    
    $pemeriksaanArray = [];
    $rtlArray = [];
    $keluhanArray = [];
    
    foreach($pemeriksaanList as $p) {
      if(!empty($p['pemeriksaan'])) {
        $pemeriksaanArray[] = $p['pemeriksaan'];
      }
      if(!empty($p['rtl'])) {
        $rtlArray[] = $p['rtl'];
      }
      if(!empty($p['keluhan'])) {
        $keluhanArray[] = $p['keluhan'];
      }
    }
    
    // Gabungkan dengan separator "--"
    $gabung['pemeriksaan'] = implode(' -- ', $pemeriksaanArray);
    $gabung['rtl'] = implode(' -- ', $rtlArray);
    $gabung['keluhan'] = implode(' -- ', $keluhanArray);
    
    return $gabung;
  }

  public function getBridgingPCarePendaftaranTampil($no_rkm_medis)
  {
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('mlite_bridging_pcare.no_rkm_medis', $no_rkm_medis)
      ->desc("STR_TO_DATE(mlite_bridging_pcare.tgl_kunjungan, '%d-%m-%Y')")
      ->toArray();
    echo $this->draw('bridgingpcare.pendaftaran.tampil.html', ['bridging_pcare' => $bridging_pcare]);
    exit();
  }

  public function getBridgingPCareKunjunganTampil($no_rkm_medis)
  {
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('mlite_bridging_pcare.no_rkm_medis', $no_rkm_medis)
      ->desc("STR_TO_DATE(mlite_bridging_pcare.tgl_kunjungan, '%d-%m-%Y')")
      ->toArray();
    echo $this->draw('bridgingpcare.kunjungan.tampil.html', ['bridging_pcare' => $bridging_pcare]);
    exit();
  }

  public function getBridgingPCareRujukanTampil($no_rkm_medis)
  {
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('mlite_bridging_pcare.no_rkm_medis', $no_rkm_medis)
      ->where(function($q) {
          $q->where('kode_ppk', '<>', '')
            ->orWhere('kode_faskeskhusus', '<>', '');
      })
      ->desc("STR_TO_DATE(mlite_bridging_pcare.tgl_kunjungan, '%d-%m-%Y')")
      ->toArray();
    echo $this->draw('bridgingpcare.rujukan.tampil.html', ['bridging_pcare' => $bridging_pcare]);
    exit();
  }

  public function getBridgingPCareRujukanCetak($nomor_kunjungan)
  {
    $settings = $this->settings('pcare');
    $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));

    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('nomor_kunjungan', $nomor_kunjungan)
      ->oneArray();
    $umur = !empty($bridging_pcare['tgl_lahir']) ? $this->hitungUmur($bridging_pcare['tgl_lahir']) : '0 Th 0 Bl 0 Hr';
    echo $this->draw('bridgingpcare.rujukan.cetak.html', ['bridging_pcare' => $bridging_pcare, 'umur' => $umur]);
    exit();
  }

  public function getBridgingPCareTindakan($nomor_kunjungan)
  {
    $this->_addHeaderFiles();
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('nomor_kunjungan', $nomor_kunjungan)
      ->oneArray();

    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url.'tindakan/kunjungan/'.$nomor_kunjungan;
    $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    // echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
    $decompress = '""';
    $data_tindakan = [];
    if($code == '200') {
      $stringDecrypt = stringDecrypt($key, $json['response']);
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          $data_tindakan = json_decode($decompress,true);
      }  
    }

    $ref_tindakan = '[
      {
				"kdTindakan": "01006",
				"nmTindakan": "Pelayanan KB : Suntik",
				"maxTarif": 20000,
				"withValue": false
			},
			{
				"kdTindakan": "01023",
				"nmTindakan": "Pelayanan ANC 1 (Satu) oleh Dokter",
				"maxTarif": 90000,
				"withValue": false
			},
			{
				"kdTindakan": "01024",
				"nmTindakan": "Pelayanan ANC 2 (Dua) oleh Dokter",
				"maxTarif": 90000,
				"withValue": false
			},
			{
				"kdTindakan": "01025",
				"nmTindakan": "Pelayanan ANC 3 (Tiga) oleh Dokter",
				"maxTarif": 90000,
				"withValue": false
			},
			{
				"kdTindakan": "01026",
				"nmTindakan": "Pelayanan ANC 4 (Empat) oleh Dokter",
				"maxTarif": 90000,
				"withValue": false
			},
			{
				"kdTindakan": "01027",
				"nmTindakan": "Pelayanan PNC 1 (Satu)",
				"maxTarif": 50000,
				"withValue": false
			},
			{
				"kdTindakan": "01028",
				"nmTindakan": "Pelayanan PNC 2 (Dua)",
				"maxTarif": 50000,
				"withValue": false
			},
			{
				"kdTindakan": "01029",
				"nmTindakan": "Pelayanan PNC 3 (Tiga)",
				"maxTarif": 50000,
				"withValue": false
			},
			{
				"kdTindakan": "01030",
				"nmTindakan": "Pelayanan PNC 4 (Empat)",
				"maxTarif": 50000,
				"withValue": false
			},
			{
				"kdTindakan": "02004",
				"nmTindakan": "Pelayanan pra-rujukan pada komplikasi kebidanan dan neonatal",
				"maxTarif": 200000,
				"withValue": false
			},
			{
				"kdTindakan": "03122",
				"nmTindakan": "MOP / Vasektomi",
				"maxTarif": 370000,
				"withValue": false
			},
			{
				"kdTindakan": "09001",
				"nmTindakan": "Evakuasi medis / Ambulans Darat",
				"maxTarif": 0,
				"withValue": false
			},
			{
				"kdTindakan": "09002",
				"nmTindakan": "Evakuasi medis / Ambulans Air",
				"maxTarif": 0,
				"withValue": false
			}
    ]';

    // $ref_tindakan = '[
    //   {
    //     "kdTindakan": "01001",
    //     "nmTindakan": "Rawat jalan di Poliklinik Umum / KIA - KB/Gigi setiap kali kunjungan ",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01005",
    //     "nmTindakan": "Pelayanan KB : Pemasangan IUD / Implant\r\n",
    //     "maxTarif": 100000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01006",
    //     "nmTindakan": "Pelayanan KB : Suntik\r\n",
    //     "maxTarif": 15000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01023",
    //     "nmTindakan": "Pelayanan ANC 1 (Satu)",
    //     "maxTarif": 50000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01024",
    //     "nmTindakan": "Pelayanan ANC 2 (Dua)",
    //     "maxTarif": 50000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01025",
    //     "nmTindakan": "Pelayanan ANC 3 (Tiga)",
    //     "maxTarif": 50000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01026",
    //     "nmTindakan": "Pelayanan ANC 4 (Empat)",
    //     "maxTarif": 50000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01027",
    //     "nmTindakan": "Pelayanan PNC 1 (Satu)",
    //     "maxTarif": 25000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01028",
    //     "nmTindakan": "Pelayanan PNC 2 (Dua)",
    //     "maxTarif": 25000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01029",
    //     "nmTindakan": "Pelayanan PNC 3 (Tiga)",
    //     "maxTarif": 25000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "01030",
    //     "nmTindakan": "Pelayanan PNC 4 (Empat)",
    //     "maxTarif": 25000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "02004",
    //     "nmTindakan": "Pelayanan pra-rujukan pada komplikasi kebidanan dan neonatal",
    //     "maxTarif": 125000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03005",
    //     "nmTindakan": "Perawatan Luka tanpa jahitan / ganti verban",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03052",
    //     "nmTindakan": "Tampon Hidung",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03092",
    //     "nmTindakan": "Pemasangan/ pengangkatan IUD oleh Bidan",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03095",
    //     "nmTindakan": "Injeksi KB",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03096",
    //     "nmTindakan": "Kontrol IUD",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03113",
    //     "nmTindakan": "Skintest",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "03122",
    //     "nmTindakan": "MOP / Vasektomi",
    //     "maxTarif": 350000,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04002",
    //     "nmTindakan": "Tambalan Composite",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04003",
    //     "nmTindakan": "Tambalan GIC",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04010",
    //     "nmTindakan": "Kontrol Pasca Tindakan",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04011",
    //     "nmTindakan": "Pencabutan gigi tetap dengan anestesi topikal",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04014",
    //     "nmTindakan": "Hecting 1-3 jahitan",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04015",
    //     "nmTindakan": "Buka jahitan / post pencabutan gigi dengan tindakan",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04017",
    //     "nmTindakan": "Kontrol post pencabutan gigi",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "04024",
    //     "nmTindakan": "Kontrol Pasca Tindakan",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "09001",
    //     "nmTindakan": "Evakuasi medis / Ambulans Darat\r\n",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "09002",
    //     "nmTindakan": "Evakuasi medis / Ambulans Air\r\n",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05022",
    //     "nmTindakan": "Ureum",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05023",
    //     "nmTindakan": "Kreatinin",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05049",
    //     "nmTindakan": "Gula Darah Puasa (GDP) - PRB/Prolanis",
    //     "maxTarif": 17500,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05051",
    //     "nmTindakan": "HbA1c - PRB/Prolanis",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05052",
    //     "nmTindakan": "Microalbuminaria",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05053",
    //     "nmTindakan": "Kolesterol Total",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05054",
    //     "nmTindakan": "Kolesterol LDL",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05055",
    //     "nmTindakan": "Kolesterol HDL",
    //     "maxTarif": 0,
    //     "withValue": false
    //   },
    //   {
    //     "kdTindakan": "05056",
    //     "nmTindakan": "Trigliserida",
    //     "maxTarif": 0,
    //     "withValue": false
    //   }
    // ]';

    $ref_tindakan = json_decode($ref_tindakan,true);

    echo $this->draw('bridgingpcare.tindakan.html', ['bridging_pcare' => $bridging_pcare, 'data_tindakan' => $data_tindakan, 'ref_tindakan' => $ref_tindakan]);
    exit();
  }

  public function getBridgingPCareObat($nomor_kunjungan)
  {
    $this->_addHeaderFiles();
    $bridging_pcare = $this->db('mlite_bridging_pcare')
      ->join('pasien', 'pasien.no_rkm_medis=mlite_bridging_pcare.no_rkm_medis')
      ->where('nomor_kunjungan', $nomor_kunjungan)
      ->oneArray();

    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consumerID . $this->consumerSecret . $tStamp;

    $url = $this->api_url.'obat/kunjungan/'.$nomor_kunjungan;
    $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
    $json = json_decode($output, true);
    //echo json_encode($json);

    $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
    $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';

    $decompress = '""';
    $data_obat = [];
    if($code == '200') {
      $stringDecrypt = stringDecrypt($key, $json['response']);
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          $data_obat = json_decode($decompress,true);
      }  
    }

    echo $this->draw('bridgingpcare.obat.html', ['bridging_pcare' => $bridging_pcare, 'data_obat' => $data_obat]);
    exit();
  }

  public function postBridgingPCareSave()
  {

    $kunjSakit = true;
    if($_POST['kunjSakit'] == 'false') {
      $kunjSakit = false;
    }

    $alergimakanan = 'null';
    if(isset($_POST['getAlergi1']) && $_POST['getAlergi1'] !=''){
      $alergimakanan = strtok($_POST['getAlergi1'], ':');
    }

    $alergiudara = 'null';
    if(isset($_POST['getAlergi2']) && $_POST['getAlergi2'] !=''){
      $alergiudara = strtok($_POST['getAlergi2'], ':');
    }

    $alergiobat = 'null';
    if(isset($_POST['getAlergi3']) && $_POST['getAlergi3'] !=''){
      $alergiobat = strtok($_POST['getAlergi3'], ':');
    }

    $prognosa = 'null';
    if(isset($_POST['getPrognosa']) && $_POST['getPrognosa'] !=''){
      $prognosa = strtok($_POST['getPrognosa'], ':');
    }

    $diagnosa2 = 'null';
    if(isset($_POST['getDiagnosa2']) && $_POST['getDiagnosa2'] !=''){
      $diagnosa2 = strtok($_POST['getDiagnosa2'], ':');
    }

    $diagnosa3 = 'null';
    if(isset($_POST['getDiagnosa3']) && $_POST['getDiagnosa3'] !=''){
      $diagnosa3 = strtok($_POST['getDiagnosa3'], ':');
    }

    $noUrut = '';
    $noKunjungan = '';
    
    if(isset($_POST['pendaftaran']) && $_POST['pendaftaran'] == 'true') {
      $data = [
        'kdProviderPeserta' => $_POST['kdProviderPeserta'],
        'tglDaftar' => $_POST['tglDaftar'],
        'noKartu' => $_POST['noKartu'],
        'kdPoli' => strtok($_POST['getPoli'], ':'),
        'keluhan' => $_POST['keluhan'],
        'kunjSakit' => $kunjSakit,
        'sistole' => 0,
        'diastole' => 0,
        'beratBadan' => 0,
        'tinggiBadan' => 0,
        'respRate' => 0,
        'lingkarPerut' => 0,
        'heartRate' => 0,
        'rujukBalik' => 0,
        'kdTkp' => $_POST['kode_tkp']
      ];

      $data = json_encode($data);

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'pendaftaran';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '201') {
          $stringDecrypt = stringDecrypt($key, isset_or($json['response']));
          $decompress = '""';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          }
          $data = '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
          // echo $data;
          $data = json_decode($data, true);
          $noUrut = $data['response']['message'];
          echo $noUrut;
        } else {
            // Decrypt response untuk mendapat detail error
            $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
            $errorDetail = $errorResponse['message'] ?? null;
            $this->outputPcareError($code, $message, $errorDetail, ['action' => 'pendaftaran', 'noKartu' => $_POST['noKartu'] ?? '']);
        }        
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'pendaftaran']);

      }
    }    

    $_POST['terapiObat'] = !empty($_POST['terapiObat']) ? $_POST['terapiObat'] : 'tidak ada';
    $_POST['terapiNonObat'] = !empty($_POST['terapiNonObat']) ? $_POST['terapiNonObat'] : 'tidak ada';

    if(isset($_POST['rujukanlanjut']) && $_POST['rujukanlanjut'] == 'false') {
      $data = [
        'noKunjungan' => null,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => strtok($_POST['getPoli'], ':'),
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => intval($_POST['sistole']),
        'diastole' => intval($_POST['diastole']),
        'beratBadan' => intval($_POST['berat']),
        'tinggiBadan' => intval($_POST['tinggi']),
        'respRate' => intval($_POST['respirasi']),
        'heartRate' => intval($_POST['nadi']),
        'lingkarPerut' => intval($_POST['lingkar_perut']),
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => null,
        'kdTacc' => -1,
        'alasanTacc' => null, 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      $data = json_encode($data);

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      // Simpan data array sebelum encode untuk kemungkinan retry
      $dataArray = json_decode($data, true);
      
      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '201') {
          $stringDecrypt = stringDecrypt($key, isset_or($json['response']));
          $decompress = '""';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          }
          $data = '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
          // echo $data;
          $data = json_decode($data, true);
          $noKunjungan = $data['response'][0]['message'];
          echo $noKunjungan;
        } else {
            // Decrypt response untuk mendapat detail error
            $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
            $errorDetail = $errorResponse['message'] ?? null;
            
            // Auto-retry: jika error "sudah di-entri", otomatis update (PUT) instead of insert
            if ($this->isDuplicateKunjunganError($message, $errorDetail)) {
                $existingNoKunjungan = $this->getExistingNoKunjungan($_POST['noKartu'] ?? '', $_POST['id_pendaftaran'] ?? '');
                if ($existingNoKunjungan) {
                    $retryResult = $this->retryKunjunganAsPut($dataArray, $existingNoKunjungan);
                    if ($retryResult['code'] == '200') {
                        $noKunjungan = $existingNoKunjungan;
                        echo 'Update berhasil (noKunjungan: ' . $existingNoKunjungan . ')';
                    } else {
                        $retryErrorResponse = $this->decryptPcareResponse($retryResult['json']['response'] ?? null, $retryResult['key']);
                        $retryErrorDetail = $retryErrorResponse['message'] ?? null;
                        $this->outputPcareError($retryResult['code'], $retryResult['message'], $retryErrorDetail, ['action' => 'kunjungan_auto_update', 'noKartu' => $_POST['noKartu'] ?? '', 'noKunjungan' => $existingNoKunjungan]);
                    }
                } else {
                    echo 'Kunjungan sudah ada di PCare untuk hari ini. Gunakan tombol Edit untuk mengupdate data, atau hapus kunjungan lama terlebih dahulu.';
                }
            } else {
                $this->outputPcareError($code, $message, $errorDetail, ['action' => 'kunjungan', 'noKartu' => $_POST['noKartu'] ?? '']);
            }
        }

      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'kunjungan']);

      }

    }

    if(isset($_POST['rujukanlanjut']) && $_POST['rujukanlanjut'] == 'true' && isset($_POST['rujukankhusus']) && $_POST['rujukankhusus'] == 'false') {
      $data = [
        'noKunjungan' => null,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => strtok($_POST['getPoli'], ':'),
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => intval($_POST['sistole']),
        'diastole' => intval($_POST['diastole']),
        'beratBadan' => intval($_POST['berat']),
        'tinggiBadan' => intval($_POST['tinggi']),
        'respRate' => intval($_POST['respirasi']),
        'heartRate' => intval($_POST['nadi']),
        'lingkarPerut' => intval($_POST['lingkar_perut']),
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => [
            'kdppk' => strtok($_POST['getReferensiFaskesSpesialis'], ':'),
            'tglEstRujuk' => $_POST['tglEstRujuk'],
            'subSpesialis' => [
                'kdSubSpesialis1' => strtok($_POST['getReferensiSubSpesialis'], ':'),
                'kdSarana' => null
            ],
            'khusus' => null
        ],
        'kdTacc' => intval(strtok($_POST['getTACC'], ':')),
        'alasanTacc' => substr($_POST['alasanTacc'], strpos($_POST['alasanTacc'], ': ') + 1), 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      $data_test = json_encode($data);
      // Simpan data array sebelum encode untuk kemungkinan retry
      $dataArray = $data;
      $data = json_encode($data);

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '201') {
          $stringDecrypt = stringDecrypt($key, isset_or($json['response']));
          $decompress = '""';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          }
          $data = '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
          // echo $data;
          $data = json_decode($data, true);
          $noKunjungan = $data['response'][0]['message'];
          echo $noKunjungan;
        } else {
            // Decrypt response untuk mendapat detail error
            $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
            $errorDetail = $errorResponse['message'] ?? null;
            
            // Auto-retry: jika error "sudah di-entri", otomatis update (PUT) instead of insert
            if ($this->isDuplicateKunjunganError($message, $errorDetail)) {
                $existingNoKunjungan = $this->getExistingNoKunjungan($_POST['noKartu'] ?? '', $_POST['id_pendaftaran'] ?? '');
                if ($existingNoKunjungan) {
                    $retryResult = $this->retryKunjunganAsPut($dataArray, $existingNoKunjungan);
                    if ($retryResult['code'] == '200') {
                        $noKunjungan = $existingNoKunjungan;
                        echo 'Update berhasil (noKunjungan: ' . $existingNoKunjungan . ')';
                    } else {
                        $retryErrorResponse = $this->decryptPcareResponse($retryResult['json']['response'] ?? null, $retryResult['key']);
                        $retryErrorDetail = $retryErrorResponse['message'] ?? null;
                        $this->outputPcareError($retryResult['code'], $retryResult['message'], $retryErrorDetail, ['action' => 'kunjungan_rujukan_auto_update', 'noKartu' => $_POST['noKartu'] ?? '', 'noKunjungan' => $existingNoKunjungan]);
                    }
                } else {
                    echo 'Kunjungan sudah ada di PCare untuk hari ini. Gunakan tombol Edit untuk mengupdate data, atau hapus kunjungan lama terlebih dahulu.';
                }
            } else {
                $this->outputPcareError($code, $message, $errorDetail, ['action' => 'kunjungan_rujukan', 'noKartu' => $_POST['noKartu'] ?? '']);
            }
        }

      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'kunjungan_rujukan']);

      }

    }

    if(isset($_POST['rujukankhusus']) && $_POST['rujukankhusus'] == 'true') {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $data = [
        'noKunjungan' => null,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => null,
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => 0,
        'diastole' => 0,
        'beratBadan' => 0,
        'tinggiBadan' => 0,
        'respRate' => 0,
        'heartRate' => 0,
        'lingkarPerut' => 0,
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => [
            'tglEstRujuk' => $_POST['tglEstRujuk'],
            'kdppk' => strtok($_POST['getFaskesKhusus'], ':'),
            'subSpesialis' => null,
            'khusus' => [
              'kdKhusus' => strtok($_POST['getReferensiKhusus'], ':'),
              'kdSubSpesialis' => null,
              'catatan' => $_POST['catatan']
            ]
        ],
        'kdTacc' => 0,
        'alasanTacc' => null, 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      // Simpan data array sebelum encode untuk kemungkinan retry
      $dataArray = $data;
      $data = json_encode($data);

      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::post($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '201') {
          $stringDecrypt = stringDecrypt($key, isset_or($json['response']));
          $decompress = '""';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
          }
          $data = '{
            "metaData": {
              "code": "' . $code . '",
              "message": "' . $message . '"
            },
            "response": ' . $decompress . '}';
          // echo $data;
          $data = json_decode($data, true);
          $noKunjungan = $data['response'][0]['message'];
          echo $noKunjungan;
        } else {
            // Decrypt response untuk mendapat detail error
            $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
            $errorDetail = $errorResponse['message'] ?? null;
            
            // Auto-retry: jika error "sudah di-entri", otomatis update (PUT) instead of insert
            if ($this->isDuplicateKunjunganError($message, $errorDetail)) {
                $existingNoKunjungan = $this->getExistingNoKunjungan($_POST['noKartu'] ?? '', $_POST['id_pendaftaran'] ?? '');
                if ($existingNoKunjungan) {
                    $retryResult = $this->retryKunjunganAsPut($dataArray, $existingNoKunjungan);
                    if ($retryResult['code'] == '200') {
                        $noKunjungan = $existingNoKunjungan;
                        echo 'Update berhasil (noKunjungan: ' . $existingNoKunjungan . ')';
                    } else {
                        $retryErrorResponse = $this->decryptPcareResponse($retryResult['json']['response'] ?? null, $retryResult['key']);
                        $retryErrorDetail = $retryErrorResponse['message'] ?? null;
                        $this->outputPcareError($retryResult['code'], $retryResult['message'], $retryErrorDetail, ['action' => 'kunjungan_khusus_auto_update', 'noKartu' => $_POST['noKartu'] ?? '', 'noKunjungan' => $existingNoKunjungan]);
                    }
                } else {
                    echo 'Kunjungan sudah ada di PCare untuk hari ini. Gunakan tombol Edit untuk mengupdate data, atau hapus kunjungan lama terlebih dahulu.';
                }
            } else {
                $this->outputPcareError($code, $message, $errorDetail, ['action' => 'kunjungan_khusus', 'noKartu' => $_POST['noKartu'] ?? '']);
            }
        }
          
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'kunjungan_khusus']);

      }
    }

    if($noUrut !="" && $_POST['pendaftaran'] !='false') {
      $this->db('mlite_bridging_pcare')->save([
        "no_rawat" => $_POST['id_pendaftaran'],
        "no_rkm_medis" => $_POST['id_pasien'],
        "tgl_daftar" => $_POST['tglDaftar'],
        "nomor_urut" => $noUrut,
        "kode_provider_peserta" => $_POST['kdProviderPeserta'],
        "nomor_jaminan" => $_POST['noKartu'],
        "kode_poli" => strtok($_POST['getPoli'], ':'),
        "nama_poli" => substr($_POST['getPoli'], strpos($_POST['getPoli'], ': ') + 1),
        "kunjungan_sakit" => $_POST['kunjSakit'],
        "subyektif" => $_POST['keluhan'],
        "id_user" => $this->core->getUserInfo('id'),
        "tgl_input" => date('Y-m-d'),
        "status_kirim" => "Terkirim"
      ]);
    }

    if($noKunjungan !="" && $_POST['pendaftaran'] !='true') {
      $this->db('mlite_bridging_pcare')
        ->where('no_rawat', $_POST['id_pendaftaran'])      
        ->save([
        "nomor_kunjungan" => $noKunjungan,
        "sistole" => $_POST['sistole'],
        "diastole" => $_POST['diastole'],
        "nadi" => $_POST['nadi'],
        "respirasi" => $_POST['respirasi'],
        "tinggi" => $_POST['tinggi'],
        "berat" => $_POST['berat'],
        "lingkar_perut" => $_POST['lingkar_perut'],
        "rujuk_balik" => "",
        "kode_tkp" => $_POST['kode_tkp'],
        "kode_kesadaran" => strtok($_POST['getKesadaran'], ':'),
        "nama_kesadaran" => substr($_POST['getKesadaran'], strpos($_POST['getKesadaran'], ': ') + 1),
        "terapi" => $_POST['terapi'],
        "kode_status_pulang" => strtok($_POST['getStatusPulang'], ':'),
        "nama_status_pulang" => substr($_POST['getStatusPulang'], strpos($_POST['getStatusPulang'], ': ') + 1),
        "tgl_pulang" => $_POST['tglPulang'],
        "tgl_kunjungan" => $_POST['tglKunjungan'],
        "kode_dokter" => strtok($_POST['getDokter'], ':'),
        "nama_dokter" => substr($_POST['getDokter'], strpos($_POST['getDokter'], ': ') + 1),
        "kode_diagnosa1" => strtok($_POST['getDiagnosa1'], ':'),
        "nama_diagnosa1" => substr($_POST['getDiagnosa1'], strpos($_POST['getDiagnosa1'], ': ') + 1),
        "kode_diagnosa2" => strtok($_POST['getDiagnosa2'], ':'),
        "nama_diagnosa2" => substr($_POST['getDiagnosa2'], strpos($_POST['getDiagnosa2'], ': ') + 1),
        "kode_diagnosa3" => strtok($_POST['getDiagnosa3'], ':'),
        "nama_diagnosa3" => substr($_POST['getDiagnosa3'], strpos($_POST['getDiagnosa3'], ': ') + 1),
        // "kode_alergi_makanan" => strtok($_POST['getAlergi1'], ':'),
        // "nama_alergi_makanan" => substr($_POST['getAlergi1'], strpos($_POST['getAlergi1'], ': ') + 1),
        // "kode_alergi_udara" => strtok($_POST['getAlergi2'], ':'),
        // "nama_alergi_udara" => substr($_POST['getAlergi2'], strpos($_POST['getAlergi2'], ': ') + 1),
        // "kode_alergi_obat" => strtok($_POST['getAlergi3'], ':'),
        // "nama_alergi_obat" => substr($_POST['getAlergi3'], strpos($_POST['getAlergi3'], ': ') + 1),
        // "kode_prognosa" => strtok($_POST['getPrognosa'], ':'),
        // "nama_prognosa" => substr($_POST['getPrognosa'], strpos($_POST['getPrognosa'], ': ') + 1),
        // "terapi_obat" => $_POST['terapiObat'],
        // "terapi_non_obat" => $_POST['terapiNonObat'],
        "tgl_estimasi_rujuk" => $_POST['tglEstRujuk'],
        "kode_ppk" => strtok($_POST['getReferensiFaskesSpesialis'], ':'),
        "nama_ppk" => substr($_POST['getReferensiFaskesSpesialis'], strpos($_POST['getReferensiFaskesSpesialis'], ': ') + 1),
        "kode_spesialis" => strtok($_POST['getReferensiSpesialis'], ':'),
        "nama_spesialis" => substr($_POST['getReferensiSpesialis'], strpos($_POST['getReferensiSpesialis'], ': ') + 1),
        "kode_subspesialis" => strtok($_POST['getReferensiSubSpesialis'], ':'),
        "nama_subspesialis" => substr($_POST['getReferensiSubSpesialis'], strpos($_POST['getReferensiSubSpesialis'], ': ') + 1),
        "kode_sarana" => strtok($_POST['getReferensiSarana'], ':'),
        "nama_sarana" => substr($_POST['getReferensiSarana'], strpos($_POST['getReferensiSarana'], ': ') + 1),
        "kode_referensikhusus" => strtok($_POST['getReferensiKhusus'], ':'),
        "nama_referensikhusus" => substr($_POST['getReferensiKhusus'], strpos($_POST['getReferensiKhusus'], ': ') + 1),
        "kode_faskeskhusus" => strtok($_POST['getFaskesKhusus'], ':'),
        "nama_faskeskhusus" => substr($_POST['getFaskesKhusus'], strpos($_POST['getFaskesKhusus'], ': ') + 1),
        "catatan" => $_POST['catatan'],
        "kode_tacc" => strtok($_POST['getTACC'], ':'),
        "nama_tacc" => substr($_POST['getTACC'], strpos($_POST['getTACC'], ': ') + 1),
        "alasan_tacc" => substr($_POST['alasanTacc'], strpos($_POST['alasanTacc'], ': ') + 1)
      ]);
    }
    exit();
  }

  public function postBridgingPCareEdit()
  {
    $bridging_pcare = $this->db('mlite_bridging_pcare')->where('no_rawat', $_POST['id_pendaftaran'])->oneArray();
    $noKunjungan = $bridging_pcare['nomor_kunjungan'];
    $noUrut = $bridging_pcare['nomor_urut'];

    $kunjSakit = true;
    if(isset($_POST['kunjSakit']) && $_POST['kunjSakit'] == 'false') {
      $kunjSakit = false;
    }

    $alergimakanan = 'null';
    if(isset($_POST['getAlergi1']) && $_POST['getAlergi1'] !=''){
      $alergimakanan = strtok($_POST['getAlergi1'], ':');
    }

    $alergiudara = 'null';
    if(isset($_POST['getAlergi2']) && $_POST['getAlergi2'] !=''){
      $alergiudara = strtok($_POST['getAlergi2'], ':');
    }

    $alergiobat = 'null';
    if(isset($_POST['getAlergi3']) && $_POST['getAlergi3'] !=''){
      $alergiobat = strtok($_POST['getAlergi3'], ':');
    }

    $prognosa = 'null';
    if(isset($_POST['getPrognosa']) && $_POST['getPrognosa'] !=''){
      $prognosa = strtok($_POST['getPrognosa'], ':');
    }

    $diagnosa2 = 'null';
    if(isset($_POST['getDiagnosa2']) && $_POST['getDiagnosa2'] !=''){
      $diagnosa2 = strtok($_POST['getDiagnosa2'], ':');
    }

    $diagnosa3 = 'null';
    if(isset($_POST['getDiagnosa3']) && $_POST['getDiagnosa3'] !=''){
      $diagnosa3 = strtok($_POST['getDiagnosa3'], ':');
    }

    $_POST['terapiObat'] = !empty($_POST['terapiObat']) ? $_POST['terapiObat'] : 'tidak ada';
    $_POST['terapiNonObat'] = !empty($_POST['terapiNonObat']) ? $_POST['terapiNonObat'] : 'tidak ada';

    $code = '';
    if(isset($_POST['rujukanlanjut']) && $_POST['rujukanlanjut'] == 'false') {
      $data = [
        'noKunjungan' => $noKunjungan,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => strtok($_POST['getPoli'], ':'),
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => intval($_POST['sistole']),
        'diastole' => intval($_POST['diastole']),
        'beratBadan' => intval($_POST['berat']),
        'tinggiBadan' => intval($_POST['tinggi']),
        'respRate' => intval($_POST['respirasi']),
        'heartRate' => intval($_POST['nadi']),
        'lingkarPerut' => intval($_POST['lingkar_perut']),
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => null,
        'kdTacc' => -1,
        'alasanTacc' => null, 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      $data = json_encode($data);

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      // echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '200') {
          echo $message;
        } else {
          // Decrypt response untuk mendapat detail error
          $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
          $errorDetail = $errorResponse['message'] ?? null;
          $this->outputPcareError($code, $message, $errorDetail, ['action' => 'edit_kunjungan', 'noKunjungan' => $noKunjungan]);
        }
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'edit_kunjungan']);

      }

    }

    if(isset($_POST['rujukanlanjut']) && $_POST['rujukanlanjut'] == 'true' && isset($_POST['rujukankhusus']) && $_POST['rujukankhusus'] == 'false') {
      $data = [
        'noKunjungan' => $noKunjungan,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => strtok($_POST['getPoli'], ':'),
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => intval($_POST['sistole']),
        'diastole' => intval($_POST['diastole']),
        'beratBadan' => intval($_POST['berat']),
        'tinggiBadan' => intval($_POST['tinggi']),
        'respRate' => intval($_POST['respirasi']),
        'heartRate' => intval($_POST['nadi']),
        'lingkarPerut' => intval($_POST['lingkar_perut']),
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => [
            'kdppk' => strtok($_POST['getReferensiFaskesSpesialis'], ':'),
            'tglEstRujuk' => $_POST['tglEstRujuk'],
            'subSpesialis' => [
                'kdSubSpesialis1' => strtok($_POST['getReferensiSubSpesialis'], ':'),
                'kdSarana' => null
            ],
            'khusus' => null
        ],
        'kdTacc' => intval(strtok($_POST['getTACC'], ':')),
        'alasanTacc' => substr($_POST['alasanTacc'], strpos($_POST['alasanTacc'], ': ') + 1), 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      $data = json_encode($data);

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '200') {
          echo $message;
        } else {
          // Decrypt response untuk mendapat detail error
          $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
          $errorDetail = $errorResponse['message'] ?? null;
          $this->outputPcareError($code, $message, $errorDetail, ['action' => 'edit_kunjungan_rujukan', 'noKunjungan' => $noKunjungan]);
        }
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'edit_kunjungan_rujukan']);

      }

    }

    if(isset($_POST['rujukankhusus']) && $_POST['rujukankhusus'] == 'true') {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $data = [
        'noKunjungan' => $noKunjungan,
        'noKartu' => $_POST['noKartu'],
        'tglDaftar' => $_POST['tglDaftar'],
        'kdPoli' => null,
        'keluhan' => $_POST['keluhan'],
        'kdSadar' => strtok($_POST['getKesadaran'], ':'),
        'sistole' => 0,
        'diastole' => 0,
        'beratBadan' => 0,
        'tinggiBadan' => 0,
        'respRate' => 0,
        'heartRate' => 0,
        'lingkarPerut' => 0,
        // 'terapi' => $_POST['terapi'],
        'kdStatusPulang' => strtok($_POST['getStatusPulang'], ':'),
        'tglPulang' => $_POST['tglPulang'],
        'kdDokter' => strtok($_POST['getDokter'], ':'),
        'alergiMakan' => ($alergimakanan === 'null') ? null : $alergimakanan,
        'alergiUdara' => ($alergiudara === 'null') ? null : $alergiudara,
        'alergiObat' => ($alergiobat === 'null') ? null : $alergiobat,
        'kdPrognosa' => ($prognosa === 'null') ? null : $prognosa,
        'kdDiag1' => strtok($_POST['getDiagnosa1'], ':'),
        'kdDiag2' => ($diagnosa2 === 'null') ? null : $diagnosa2,
        'kdDiag3' => ($diagnosa3 === 'null') ? null : $diagnosa3,
        'kdPoliRujukInternal' => null,
        'rujukLanjut' => [
            'tglEstRujuk' => $_POST['tglEstRujuk'],
            'kdppk' => strtok($_POST['getFaskesKhusus'], ':'),
            'subSpesialis' => null,
            'khusus' => [
              'kdKhusus' => strtok($_POST['getReferensiKhusus'], ':'),
              'kdSubSpesialis' => null,
              'catatan' => $_POST['catatan']
            ]
        ],
        'kdTacc' => 0,
        'alasanTacc' => null, 
        'anamnesa' => $_POST['anamnesa'],
        'terapiObat' => $_POST['terapiObat'],
        'terapiNonObat' => $_POST['terapiNonObat'],
        'bmhp' => 'bmhp',
        'suhu' => intval($_POST['suhu_tubuh'])
      ];

      $data = json_encode($data);

      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      if ($json != null) {
        if($code == '200') {
          echo $message;
        } else {
          // Decrypt response untuk mendapat detail error
          $errorResponse = $this->decryptPcareResponse($json['response'] ?? null, $key);
          $errorDetail = $errorResponse['message'] ?? null;
          $this->outputPcareError($code, $message, $errorDetail, ['action' => 'edit_kunjungan_khusus', 'noKunjungan' => $noKunjungan]);
        }
      } else {
          $this->outputPcareError('5000', 'ERROR', 'Tidak ada response dari server BPJS atau koneksi terputus', ['action' => 'edit_kunjungan_khusus']);

      }
    }

    if($code == '200') {
      $this->db('mlite_bridging_pcare')
      ->where('no_rawat', $_POST['id_pendaftaran'])
      ->save([
        "sistole" => $_POST['sistole'],
        "diastole" => $_POST['diastole'],
        "nadi" => $_POST['nadi'],
        "respirasi" => $_POST['respirasi'],
        "tinggi" => $_POST['tinggi'],
        "berat" => $_POST['berat'],
        "lingkar_perut" => $_POST['lingkar_perut'],
        "rujuk_balik" => "",
        "kode_tkp" => $_POST['kode_tkp'],
        "nomor_urut" => $noUrut,
        "subyektif" => $_POST['keluhan'],
        "kode_kesadaran" => strtok($_POST['getKesadaran'], ':'),
        "nama_kesadaran" => substr($_POST['getKesadaran'], strpos($_POST['getKesadaran'], ': ') + 1),
        "terapi" => $_POST['terapi'],
        "kode_status_pulang" => strtok($_POST['getStatusPulang'], ':'),
        "nama_status_pulang" => substr($_POST['getStatusPulang'], strpos($_POST['getStatusPulang'], ': ') + 1),
        "tgl_pulang" => $_POST['tglPulang'],
        "tgl_kunjungan" => $_POST['tglKunjungan'],
        "kode_dokter" => strtok($_POST['getDokter'], ':'),
        "nama_dokter" => substr($_POST['getDokter'], strpos($_POST['getDokter'], ': ') + 1),
        "kode_diagnosa1" => strtok($_POST['getDiagnosa1'], ':'),
        "nama_diagnosa1" => substr($_POST['getDiagnosa1'], strpos($_POST['getDiagnosa1'], ': ') + 1),
        "kode_diagnosa2" => strtok($_POST['getDiagnosa2'], ':'),
        "nama_diagnosa2" => substr($_POST['getDiagnosa2'], strpos($_POST['getDiagnosa2'], ': ') + 1),
        "kode_diagnosa3" => strtok($_POST['getDiagnosa3'], ':'),
        "nama_diagnosa3" => substr($_POST['getDiagnosa3'], strpos($_POST['getDiagnosa3'], ': ') + 1),
        // "kode_alergi_makanan" => strtok($_POST['getAlergi1'], ':'),
        // "nama_alergi_makanan" => substr($_POST['getAlergi1'], strpos($_POST['getAlergi1'], ': ') + 1),
        // "kode_alergi_udara" => strtok($_POST['getAlergi2'], ':'),
        // "nama_alergi_udara" => substr($_POST['getAlergi2'], strpos($_POST['getAlergi2'], ': ') + 1),
        // "kode_alergi_obat" => strtok($_POST['getAlergi3'], ':'),
        // "nama_alergi_obat" => substr($_POST['getAlergi3'], strpos($_POST['getAlergi3'], ': ') + 1),
        // "kode_prognosa" => strtok($_POST['getPrognosa'], ':'),
        // "nama_prognosa" => substr($_POST['getPrognosa'], strpos($_POST['getPrognosa'], ': ') + 1),
        // "terapi_obat" => $_POST['terapiObat'],
        // "terapi_non_obat" => $_POST['terapiNonObat'],
        "tgl_estimasi_rujuk" => $_POST['tglEstRujuk'],
        "kode_ppk" => strtok($_POST['getReferensiFaskesSpesialis'], ':'),
        "nama_ppk" => substr($_POST['getReferensiFaskesSpesialis'], strpos($_POST['getReferensiFaskesSpesialis'], ': ') + 1),
        "kode_spesialis" => strtok($_POST['getReferensiSpesialis'], ':'),
        "nama_spesialis" => substr($_POST['getReferensiSpesialis'], strpos($_POST['getReferensiSpesialis'], ': ') + 1),
        "kode_subspesialis" => strtok($_POST['getReferensiSubSpesialis'], ':'),
        "nama_subspesialis" => substr($_POST['getReferensiSubSpesialis'], strpos($_POST['getReferensiSubSpesialis'], ': ') + 1),
        "kode_sarana" => strtok($_POST['getReferensiSarana'], ':'),
        "nama_sarana" => substr($_POST['getReferensiSarana'], strpos($_POST['getReferensiSarana'], ': ') + 1),
        "kode_referensikhusus" => strtok($_POST['getReferensiKhusus'], ':'),
        "nama_referensikhusus" => substr($_POST['getReferensiKhusus'], strpos($_POST['getReferensiKhusus'], ': ') + 1),
        "kode_faskeskhusus" => strtok($_POST['getFaskesKhusus'], ':'),
        "nama_faskeskhusus" => substr($_POST['getFaskesKhusus'], strpos($_POST['getFaskesKhusus'], ': ') + 1),
        "catatan" => $_POST['catatan'],
        "kode_tacc" => strtok($_POST['getTACC'], ':'),
        "nama_tacc" => substr($_POST['getTACC'], strpos($_POST['getTACC'], ': ') + 1),
        "alasan_tacc" => substr($_POST['alasanTacc'], strpos($_POST['alasanTacc'], ': ') + 1)
      ]);
    }
    exit();
  }

  public function getRefKelompok()
  {
      return $this->draw('kelompok.html');
  }

  public function getKelompok($kode)
  {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url.'kelompok/club/'.$kode;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      //echo json_encode($json);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '""';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      if ($json != null) {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
      } else {
          echo '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      }

      exit();
  }

  public function hitungUmur($tanggal_lahir)
  {
      $birthDate = new \DateTime($tanggal_lahir);
      $today = new \DateTime("today");
      $umur = "0 Th 0 Bl 0 Hr";
      if ($birthDate < $today) {
        $y = $today->diff($birthDate)->y;
        $m = $today->diff($birthDate)->m;
        $d = $today->diff($birthDate)->d;
        $umur =  $y." Th ".$m." Bl ".$d." Hr";
      }
      return $umur;
  }

  public function postSaveKunjunganJson()
  {
    // Ambil data JSON dari request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['kunjungan_data'])) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Data kunjungan tidak ditemukan'
      ]);
      exit;
    }
    
    $kunjunganData = $input['kunjungan_data'];
    $noRawat = isset($input['no_rawat']) ? $input['no_rawat'] : '';
    $noRkmMedis = isset($input['no_rkm_medis']) ? $input['no_rkm_medis'] : '';
    
    // Cek apakah ada response dan list data
    if (!isset($kunjunganData['response']['list']) || !is_array($kunjunganData['response']['list'])) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Format data tidak sesuai'
      ]);
      exit;
    }
    
    $kunjunganList = $kunjunganData['response']['list'];
    
    try {
      // Simpan setiap kunjungan dari list ke database
      $savedCount = 0;
      foreach ($kunjunganList as $kunjungan) {
        // Extract nested objects dari response API
        $providerPelayanan = isset($kunjungan['providerPelayanan']) ? $kunjungan['providerPelayanan'] : [];
        $peserta = isset($kunjungan['peserta']) ? $kunjungan['peserta'] : [];
        $poli = isset($kunjungan['poli']) ? $kunjungan['poli'] : [];
        $dokter = isset($kunjungan['dokter']) ? $kunjungan['dokter'] : [];
        $diagnosa1 = isset($kunjungan['diagnosa1']) ? $kunjungan['diagnosa1'] : [];
        $diagnosa2 = isset($kunjungan['diagnosa2']) ? $kunjungan['diagnosa2'] : [];
        $diagnosa3 = isset($kunjungan['diagnosa3']) ? $kunjungan['diagnosa3'] : [];
        $kesadaran = isset($kunjungan['kesadaran']) ? $kunjungan['kesadaran'] : [];
        $statusPulang = isset($kunjungan['statusPulang']) ? $kunjungan['statusPulang'] : [];
        
        // Validasi: hanya simpan jika provider adalah "Klinik Ar Rohman"
        $nmProvider = isset($providerPelayanan['nmProvider']) ? trim($providerPelayanan['nmProvider']) : '';
        if ($nmProvider !== 'Klinik Ar Rohman') {
          continue; // Skip kunjungan yang tidak dari provider ini
        }
        
        $savedCount++;
        $this->db('mlite_bridging_pcare')->save([
          'no_rawat' => $noRawat,
          'nomor_kunjungan' => isset($kunjungan['noKunjungan']) ? $kunjungan['noKunjungan'] : '',
          'tgl_kunjungan' => isset($kunjungan['tglKunjungan']) ? $kunjungan['tglKunjungan'] : '',
          'no_rkm_medis' => $noRkmMedis,
          'nomor_jaminan' => isset($peserta['noKartu']) ? $peserta['noKartu'] : '',
          'kode_provider_peserta' => isset($providerPelayanan['kdProvider']) ? $providerPelayanan['kdProvider'] : '',
          'nama_poli' => isset($poli['nmPoli']) ? $poli['nmPoli'] : '',
          'kode_poli' => isset($poli['kdPoli']) ? $poli['kdPoli'] : '',
          'subyektif' => isset($kunjungan['keluhan']) ? $kunjungan['keluhan'] : '',
          'kunjungan_sakit' => 'true',
          'sistole' => isset($kunjungan['sistole']) ? intval($kunjungan['sistole']) : 0,
          'diastole' => isset($kunjungan['diastole']) ? intval($kunjungan['diastole']) : 0,
          'berat' => isset($kunjungan['beratBadan']) ? intval($kunjungan['beratBadan']) : 0,
          'tinggi' => isset($kunjungan['tinggiBadan']) ? intval($kunjungan['tinggiBadan']) : 0,
          'respirasi' => isset($kunjungan['respRate']) ? intval($kunjungan['respRate']) : 0,
          'lingkar_perut' => isset($kunjungan['lingkarPerut']) ? intval($kunjungan['lingkarPerut']) : 0,
          'nadi' => isset($kunjungan['heartRate']) ? intval($kunjungan['heartRate']) : 0,
          'terapi' => '',
          'nomor_urut' => '',
          'kode_kesadaran' => isset($kesadaran['kdSadar']) ? $kesadaran['kdSadar'] : '',
          'nama_kesadaran' => isset($kesadaran['nmSadar']) ? $kesadaran['nmSadar'] : '',
          'kode_status_pulang' => isset($statusPulang['kdStatusPulang']) ? $statusPulang['kdStatusPulang'] : '',
          'nama_status_pulang' => isset($statusPulang['nmStatusPulang']) ? $statusPulang['nmStatusPulang'] : '',
          'tgl_pulang' => isset($kunjungan['tglPulang']) ? $kunjungan['tglPulang'] : '',
          'kode_dokter' => isset($dokter['kdDokter']) ? $dokter['kdDokter'] : '',
          'nama_dokter' => isset($dokter['nmDokter']) ? $dokter['nmDokter'] : '',
          'kode_diagnosa1' => isset($diagnosa1['kdDiag']) ? $diagnosa1['kdDiag'] : '',
          'nama_diagnosa1' => isset($diagnosa1['nmDiag']) ? $diagnosa1['nmDiag'] : '',
          'kode_diagnosa2' => isset($diagnosa2['kdDiag']) ? $diagnosa2['kdDiag'] : '',
          'nama_diagnosa2' => isset($diagnosa2['nmDiag']) ? $diagnosa2['nmDiag'] : '',
          'kode_diagnosa3' => isset($diagnosa3['kdDiag']) ? $diagnosa3['kdDiag'] : '',
          'nama_diagnosa3' => isset($diagnosa3['nmDiag']) ? $diagnosa3['nmDiag'] : '',
          'tgl_input' => date('Y-m-d'),
          'id_user' => $this->core->getUserInfo('id'),
          'status_kirim' => 'Terkirim dari API'
        ]);
      }
      
      echo json_encode([
        'status' => 'success',
        'message' => 'Data kunjungan berhasil disimpan (' . $savedCount . ' dari ' . count($kunjunganList) . ' record)',
        'count' => $savedCount
      ]);
    } catch (\Exception $e) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
      ]);
    }
    
    exit;
  }

  /**
   * Decrypt dan decompress response dari PCare API
   * @param string|null $response - Encrypted response dari BPJS
   * @param string $key - Decryption key (consumerID + consumerSecret + timestamp)
   * @return array ['raw' => string, 'data' => mixed, 'message' => string|null]
   */
  private function decryptPcareResponse($response, $key) {
      $result = ['raw' => null, 'data' => null, 'message' => null];
      
      if (empty($response)) {
          return $result;
      }

      // Jika response sudah berupa array (tidak terenkripsi), langsung proses
      if (is_array($response)) {
          $result['data'] = $response;
          if (isset($response['message'])) {
              $result['message'] = $response['message'];
          } elseif (isset($response[0]['message'])) {
              $result['message'] = $response[0]['message'];
          } elseif (isset($response['field']) && isset($response['message'])) {
              $result['message'] = $response['field'] . ': ' . $response['message'];
          }
          return $result;
      }
      
      try {
          $stringDecrypt = stringDecrypt($key, $response);
          $result['raw'] = $stringDecrypt;
          
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
              $result['data'] = json_decode($decompress, true);
              
              // Extract message dari berbagai kemungkinan struktur response
              if (is_array($result['data'])) {
                  if (isset($result['data']['message'])) {
                      $result['message'] = $result['data']['message'];
                  } elseif (isset($result['data'][0]['message'])) {
                      $result['message'] = $result['data'][0]['message'];
                  } elseif (isset($result['data']['field']) && isset($result['data']['message'])) {
                      $result['message'] = $result['data']['field'] . ': ' . $result['data']['message'];
                  }
              } elseif (is_string($result['data'])) {
                  $result['message'] = $result['data'];
              }
          }
      } catch (\Exception $e) {
          error_log('[PCare] Decrypt error: ' . $e->getMessage());
      }
      
      return $result;
  }

  /**
   * Output error response dan optional logging
   * @param string $code - Error code
   * @param string $message - Error message dari metaData
   * @param string|null $detail - Detail error dari decrypted response
   * @param array $logData - Additional data untuk logging
   * @return void
   */
  /**
   * Cek apakah error adalah "sudah di-entri" (duplicate kunjungan)
   */
  private function isDuplicateKunjunganError($message, $errorDetail) {
      $searchTerms = ['sudah di-entri', 'sudah di entri', 'sudah dientry', 'sudah ada'];
      $textToCheck = strtolower($message . ' ' . ($errorDetail ?? ''));
      foreach ($searchTerms as $term) {
          if (strpos($textToCheck, $term) !== false) {
              return true;
          }
      }
      return false;
  }

  /**
   * Ambil noKunjungan yang sudah ada dari database lokal atau dari PCare API
   */
  private function getExistingNoKunjungan($noKartu, $noRawat = '') {
      // 1. Coba dari database lokal
      if (!empty($noRawat)) {
          $existing = $this->db('mlite_bridging_pcare')->where('no_rawat', $noRawat)->oneArray();
          if (!empty($existing['nomor_kunjungan'])) {
              return $existing['nomor_kunjungan'];
          }
      }
      
      // 2. Coba dari database lokal by noKartu dan tanggal hari ini
      $today = date('d-m-Y');
      $existing = $this->db('mlite_bridging_pcare')
          ->where('nomor_jaminan', $noKartu)
          ->where('tgl_kunjungan', $today)
          ->oneArray();
      if (!empty($existing['nomor_kunjungan'])) {
          return $existing['nomor_kunjungan'];
      }

      // 3. Coba dari PCare API - ambil riwayat kunjungan
      try {
          date_default_timezone_set('UTC');
          $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
          $key = $this->consumerID . $this->consumerSecret . $tStamp;
          
          $url = $this->api_url . 'kunjungan/noKartu/' . $noKartu;
          $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
          $json = json_decode($output, true);
          
          if (isset($json['metaData']['code']) && $json['metaData']['code'] == '200') {
              $stringDecrypt = stringDecrypt($key, $json['response'] ?? '');
              if (!empty($stringDecrypt)) {
                  $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
                  $data = json_decode($decompress, true);
                  if (isset($data['list']) && is_array($data['list'])) {
                      // Cari kunjungan hari ini
                      $todayFormatted = date('d-m-Y');
                      foreach ($data['list'] as $item) {
                          if (isset($item['tglKunjungan']) && $item['tglKunjungan'] == $todayFormatted && !empty($item['noKunjungan'])) {
                              return $item['noKunjungan'];
                          }
                      }
                  }
              }
          }
      } catch (\Exception $e) {
          error_log('[PCare] Error getting existing noKunjungan: ' . $e->getMessage());
      }
      
      return null;
  }

  /**
   * Retry kunjungan sebagai PUT (update) jika POST gagal karena duplicate
   */
  private function retryKunjunganAsPut($dataArray, $noKunjungan) {
      $dataArray['noKunjungan'] = $noKunjungan;
      $data = json_encode($dataArray);
      
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;
      
      $url = $this->api_url . 'kunjungan/V1';
      $output = PcareService::put($url, $data, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);
      
      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';
      
      return [
          'json' => $json,
          'code' => $code,
          'message' => $message,
          'key' => $key,
          'noKunjungan' => $noKunjungan
      ];
  }

  private function outputPcareError($code, $message, $detail = null, $logData = []) {
      // Log error
      $logMessage = "[PCare] Error $code: $message";
      if ($detail) {
          $logMessage .= " | Detail: $detail";
      }
      if (!empty($logData)) {
          $logMessage .= " | Data: " . json_encode($logData);
      }
      error_log($logMessage);
      
      // Output response - gunakan detail jika ada, fallback ke message
      $errorMessage = $detail ?? $message;
      echo $errorMessage;
  }

  private function _addHeaderFiles()
  {
      $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
      $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
      $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
  }

  public function getCeksinkronisasi()
  {
      $this->_addHeaderFiles();
      return $this->draw('ceksinkronisasi.html');
  }

  public function getCeksinkronisasiDisplay()
  {
      $date_input = isset($_GET['date']) ? $_GET['date'] : date('d-m-Y');
      $date_parts = explode('-', $date_input);
      if (count($date_parts) == 3) {
          $date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
      } else {
          $date = date('Y-m-d');
      }

      // Ambil data bridging dari mLite
      $kd_pj_bpjs = $this->settings->get('jkn_mobile.kd_pj_bpjs');

      $query = "SELECT
          bp.nomor_urut,
          bp.no_rawat,
          bp.no_rkm_medis,
          bp.nomor_kunjungan,
          bp.kode_poli AS kode_poli_pcare,
          bp.status_kirim,
          reg_periksa.stts,
          reg_periksa.kd_poli,
          pasien.nm_pasien,
          pasien.no_peserta,
          poliklinik.nm_poli
      FROM mlite_bridging_pcare bp
      INNER JOIN reg_periksa ON reg_periksa.no_rawat = bp.no_rawat
      INNER JOIN pasien ON pasien.no_rkm_medis = bp.no_rkm_medis
      INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
      WHERE reg_periksa.tgl_registrasi = '" . addslashes($date) . "'";

      if (!empty($kd_pj_bpjs)) {
          $query .= " AND reg_periksa.kd_pj = '" . addslashes($kd_pj_bpjs) . "'";
      }

      $query .= " ORDER BY bp.nomor_urut ASC";
      $mlite_rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      // Analisis nomor urut per prefix (A=Umum, B=Gigi, dll)
      $prefix_max = [];
      $mlite_urut_list = [];
      foreach ($mlite_rows as $row) {
          $noUrut = $row['nomor_urut'];
          $mlite_urut_list[] = $noUrut;
          // Extract prefix dan angka, misal A32 -> prefix=A, num=32
          if (preg_match('/^([A-Z]+)(\d+)$/i', $noUrut, $m)) {
              $prefix = strtoupper($m[1]);
              $num = intval($m[2]);
              if (!isset($prefix_max[$prefix]) || $num > $prefix_max[$prefix]) {
                  $prefix_max[$prefix] = $num;
              }
          }
      }

      // Tidak tambah buffer - frontend akan cek dinamis dan berhenti setelah 2 berturut-turut tidak ditemukan

      echo $this->draw('ceksinkronisasi.display.html', [
          'date' => $date_input,
          'tglDaftar' => $date_input,
          'mlite_rows' => $mlite_rows,
          'mlite_urut_list' => $mlite_urut_list,
          'prefix_max' => $prefix_max,
          'total_mlite' => count($mlite_rows)
      ]);
      exit();
  }

  /**
   * AJAX endpoint: cek satu nomor urut ke API PCare
   * GET parameter: noUrut, tglDaftar (DD-MM-YYYY)
   * Return: JSON { status, data }
   */
  public function getCekNomorUrut()
  {
      header('Content-Type: application/json');

      $noUrut = isset($_GET['noUrut']) ? $_GET['noUrut'] : '';
      $tglDaftar = isset($_GET['tglDaftar']) ? $_GET['tglDaftar'] : '';

      if (empty($noUrut) || empty($tglDaftar)) {
          echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
          exit();
      }

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'pendaftaran/noUrut/' . $noUrut . '/tglDaftar/' . $tglDaftar;
      $output = \Systems\Lib\PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';

      if ($code == '200' || $code == '201') {
          $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
          $decompress = '';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
          $data = [];
          if (!empty($decompress)) {
              $data = json_decode($decompress, true);
          }
          echo json_encode(['status' => 'found', 'data' => $data]);
      } elseif (strpos($message, 'not found') !== false || strpos($message, 'Tidak') !== false || $code == '204' || $code == '404') {
          echo json_encode(['status' => 'not_found']);
      } else {
          echo json_encode(['status' => 'error', 'message' => $message, 'code' => $code]);
      }
      exit();
  }

  /**
   * AJAX endpoint: cek detail kunjungan + tindakan dari PCare berdasarkan noKunjungan
   * GET parameter: noKunjungan
   * Return: JSON { status, kunjungan, tindakan }
   */
  public function getCekKunjunganDetail()
  {
      header('Content-Type: application/json');

      $noKartu = isset($_GET['noKartu']) ? $_GET['noKartu'] : '';
      $tglCari = isset($_GET['tglDaftar']) ? $_GET['tglDaftar'] : '';

      if (empty($noKartu)) {
          echo json_encode(['status' => 'error', 'message' => 'Parameter noKartu tidak lengkap']);
          exit();
      }

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $result = ['status' => 'ok', 'kunjungan' => null, 'tindakan' => []];

      // 1. Ambil riwayat kunjungan peserta berdasarkan noKartu
      $url = $this->api_url . 'kunjungan/peserta/' . $noKartu;
      $output = \Systems\Lib\PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      if ($code == '200' || $code == '201') {
          $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
          $decompress = '';
          if (!empty($stringDecrypt)) {
              $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
          }
          if (!empty($decompress)) {
              $kunjData = json_decode($decompress, true);
              if (!empty($kunjData)) {
                  // Response bisa berupa { count, list: [...] } atau langsung array/object
                  $list = [];
                  if (isset($kunjData['list']) && is_array($kunjData['list'])) {
                      $list = $kunjData['list'];
                  } elseif (isset($kunjData[0])) {
                      $list = $kunjData;
                  } else {
                      // Single object langsung
                      $list = [$kunjData];
                  }

                  // Cari kunjungan yang sesuai tanggal
                  foreach ($list as $k) {
                      if (!empty($tglCari) && isset($k['tglKunjungan']) && $k['tglKunjungan'] == $tglCari) {
                          $result['kunjungan'] = $k;
                          break;
                      }
                  }
                  // Jika tidak ada yang cocok tanggal, ambil yang pertama
                  if (empty($result['kunjungan']) && !empty($list)) {
                      $result['kunjungan'] = $list[0];
                  }
              }
          }
      }

      // 2. Jika ada noKunjungan, ambil tindakan
      $noKunjungan = '';
      if (!empty($result['kunjungan']) && isset($result['kunjungan']['noKunjungan'])) {
          $noKunjungan = $result['kunjungan']['noKunjungan'];
      }
      if (!empty($noKunjungan)) {
          $tStamp3 = strval(time() - strtotime("1970-01-01 00:00:00"));
          $key3 = $this->consumerID . $this->consumerSecret . $tStamp3;
          $url2 = $this->api_url . 'tindakan/kunjungan/' . $noKunjungan;
          $output2 = \Systems\Lib\PcareService::get($url2, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
          $json2 = json_decode($output2, true);

          $code2 = isset($json2['metaData']['code']) ? $json2['metaData']['code'] : '5000';
          if ($code2 == '200' || $code2 == '201') {
              $stringDecrypt2 = stringDecrypt($key3, isset($json2['response']) ? $json2['response'] : '');
              $decompress2 = '';
              if (!empty($stringDecrypt2)) {
                  $decompress2 = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt2);
              }
              if (!empty($decompress2)) {
                  $tindakanData = json_decode($decompress2, true);
                  if (is_array($tindakanData)) {
                      $result['tindakan'] = $tindakanData;
                  }
              }
          }
      }

      echo json_encode($result);
      exit();
  }

  /**
   * AJAX endpoint: sinkronisasi nomor kunjungan dari PCare ke lokal
   * POST parameter: no_rawat, noKartu, tglDaftar (DD-MM-YYYY)
   * Cari kunjungan di PCare berdasarkan noKartu, cocokkan tanggal, simpan noKunjungan ke mlite_bridging_pcare
   */
  public function postSinkronKunjungan()
  {
      header('Content-Type: application/json');

      $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
      $noKartu = isset($_POST['noKartu']) ? $_POST['noKartu'] : '';
      $tglDaftar = isset($_POST['tglDaftar']) ? $_POST['tglDaftar'] : '';

      if (empty($no_rawat) || empty($noKartu) || empty($tglDaftar)) {
          echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
          exit();
      }

      // Cek apakah bridging record ada
      $bridging = $this->db('mlite_bridging_pcare')->where('no_rawat', $no_rawat)->oneArray();
      if (empty($bridging)) {
          echo json_encode(['status' => 'error', 'message' => 'Data bridging tidak ditemukan untuk no_rawat: ' . $no_rawat]);
          exit();
      }

      // Jika sudah ada nomor_kunjungan, skip
      if (!empty($bridging['nomor_kunjungan'])) {
          echo json_encode(['status' => 'skip', 'message' => 'Nomor kunjungan sudah ada: ' . $bridging['nomor_kunjungan'], 'nomor_kunjungan' => $bridging['nomor_kunjungan']]);
          exit();
      }

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      // Ambil riwayat kunjungan peserta dari PCare
      $url = $this->api_url . 'kunjungan/peserta/' . $noKartu;
      $output = \Systems\Lib\PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';

      if ($code != '200' && $code != '201') {
          echo json_encode(['status' => 'error', 'message' => 'Gagal ambil data kunjungan dari PCare: ' . $message]);
          exit();
      }

      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
      }

      if (empty($decompress)) {
          echo json_encode(['status' => 'error', 'message' => 'Response dari PCare kosong']);
          exit();
      }

      $kunjData = json_decode($decompress, true);
      if (empty($kunjData)) {
          echo json_encode(['status' => 'error', 'message' => 'Data kunjungan tidak ditemukan di PCare']);
          exit();
      }

      // Ambil list kunjungan
      $list = [];
      if (isset($kunjData['list']) && is_array($kunjData['list'])) {
          $list = $kunjData['list'];
      } elseif (isset($kunjData[0])) {
          $list = $kunjData;
      } else {
          $list = [$kunjData];
      }

      // Cari kunjungan yang cocok tanggal
      $matchedKunjungan = null;
      foreach ($list as $k) {
          if (isset($k['tglKunjungan']) && $k['tglKunjungan'] == $tglDaftar) {
              $matchedKunjungan = $k;
              break;
          }
      }

      if (empty($matchedKunjungan) || empty($matchedKunjungan['noKunjungan'])) {
          echo json_encode(['status' => 'error', 'message' => 'Tidak ditemukan kunjungan di PCare untuk tanggal ' . $tglDaftar]);
          exit();
      }

      $noKunjungan = $matchedKunjungan['noKunjungan'];

      // Update data lokal dari data PCare
      $updateData = [
          'nomor_kunjungan' => $noKunjungan,
          'status_kirim' => 'Sudah',
      ];

      // Simpan data tambahan dari PCare jika ada
      if (isset($matchedKunjungan['sistole'])) $updateData['sistole'] = $matchedKunjungan['sistole'];
      if (isset($matchedKunjungan['diastole'])) $updateData['diastole'] = $matchedKunjungan['diastole'];
      if (isset($matchedKunjungan['beratBadan'])) $updateData['berat'] = $matchedKunjungan['beratBadan'];
      if (isset($matchedKunjungan['tinggiBadan'])) $updateData['tinggi'] = $matchedKunjungan['tinggiBadan'];
      if (isset($matchedKunjungan['respRate'])) $updateData['respirasi'] = $matchedKunjungan['respRate'];
      if (isset($matchedKunjungan['heartRate'])) $updateData['nadi'] = $matchedKunjungan['heartRate'];
      if (isset($matchedKunjungan['lingkarPerut'])) $updateData['lingkar_perut'] = $matchedKunjungan['lingkarPerut'];
      if (isset($matchedKunjungan['keluhan'])) $updateData['subyektif'] = $matchedKunjungan['keluhan'];
      if (isset($matchedKunjungan['tglKunjungan'])) $updateData['tgl_kunjungan'] = $matchedKunjungan['tglKunjungan'];
      if (isset($matchedKunjungan['tglPulang'])) $updateData['tgl_pulang'] = $matchedKunjungan['tglPulang'];

      // Diagnosa
      if (isset($matchedKunjungan['diagnosa1']['kdDiag'])) {
          $updateData['kode_diagnosa1'] = $matchedKunjungan['diagnosa1']['kdDiag'];
          $updateData['nama_diagnosa1'] = isset($matchedKunjungan['diagnosa1']['nmDiag']) ? $matchedKunjungan['diagnosa1']['nmDiag'] : '';
      }
      if (isset($matchedKunjungan['diagnosa2']['kdDiag']) && !empty($matchedKunjungan['diagnosa2']['kdDiag'])) {
          $updateData['kode_diagnosa2'] = $matchedKunjungan['diagnosa2']['kdDiag'];
          $updateData['nama_diagnosa2'] = isset($matchedKunjungan['diagnosa2']['nmDiag']) ? $matchedKunjungan['diagnosa2']['nmDiag'] : '';
      }
      if (isset($matchedKunjungan['diagnosa3']['kdDiag']) && !empty($matchedKunjungan['diagnosa3']['kdDiag'])) {
          $updateData['kode_diagnosa3'] = $matchedKunjungan['diagnosa3']['kdDiag'];
          $updateData['nama_diagnosa3'] = isset($matchedKunjungan['diagnosa3']['nmDiag']) ? $matchedKunjungan['diagnosa3']['nmDiag'] : '';
      }

      // Kesadaran
      if (isset($matchedKunjungan['kesadaran']['kdSadar'])) {
          $updateData['kode_kesadaran'] = $matchedKunjungan['kesadaran']['kdSadar'];
          $updateData['nama_kesadaran'] = isset($matchedKunjungan['kesadaran']['nmSadar']) ? $matchedKunjungan['kesadaran']['nmSadar'] : '';
      }

      // Status Pulang
      if (isset($matchedKunjungan['statusPulang']['kdStatusPulang'])) {
          $updateData['kode_status_pulang'] = $matchedKunjungan['statusPulang']['kdStatusPulang'];
          $updateData['nama_status_pulang'] = isset($matchedKunjungan['statusPulang']['nmStatusPulang']) ? $matchedKunjungan['statusPulang']['nmStatusPulang'] : '';
      }

      // Dokter
      if (isset($matchedKunjungan['dokter']['kdDokter'])) {
          $updateData['kode_dokter'] = $matchedKunjungan['dokter']['kdDokter'];
          $updateData['nama_dokter'] = isset($matchedKunjungan['dokter']['nmDokter']) ? $matchedKunjungan['dokter']['nmDokter'] : '';
      }

      // Provider Rujuk Lanjut
      if (isset($matchedKunjungan['providerRujukLanjut']['kdProvider']) && !empty($matchedKunjungan['providerRujukLanjut']['kdProvider'])) {
          $updateData['kode_ppk'] = $matchedKunjungan['providerRujukLanjut']['kdProvider'];
          $updateData['nama_ppk'] = isset($matchedKunjungan['providerRujukLanjut']['nmProvider']) ? $matchedKunjungan['providerRujukLanjut']['nmProvider'] : '';
      }

      // Poli Rujuk Lanjut
      if (isset($matchedKunjungan['poliRujukLanjut']['kdPoli']) && !empty($matchedKunjungan['poliRujukLanjut']['kdPoli'])) {
          $updateData['kode_spesialis'] = $matchedKunjungan['poliRujukLanjut']['kdPoli'];
          $updateData['nama_spesialis'] = isset($matchedKunjungan['poliRujukLanjut']['nmPoli']) ? $matchedKunjungan['poliRujukLanjut']['nmPoli'] : '';
      }

      $this->db('mlite_bridging_pcare')
          ->where('no_rawat', $no_rawat)
          ->save($updateData);

      echo json_encode([
          'status' => 'success',
          'message' => 'Berhasil sinkronisasi! No. Kunjungan: ' . $noKunjungan,
          'nomor_kunjungan' => $noKunjungan,
          'data' => $updateData
      ]);
      exit();
  }

  /**
   * AJAX endpoint: hapus pendaftaran di PCare
   * POST parameter: noKartu, tglDaftar (DD-MM-YYYY), noUrut, kdPoli
   * Return: JSON { status, message }
   */
  public function postHapusPendaftaranPcare()
  {
      header('Content-Type: application/json');

      $noKartu = isset($_POST['noKartu']) ? $_POST['noKartu'] : '';
      $tglDaftar = isset($_POST['tglDaftar']) ? $_POST['tglDaftar'] : '';
      $noUrut = isset($_POST['noUrut']) ? $_POST['noUrut'] : '';
      $kdPoli = isset($_POST['kdPoli']) ? $_POST['kdPoli'] : '';
      $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';

      if (empty($noKartu) || empty($tglDaftar) || empty($noUrut) || empty($kdPoli)) {
          echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap (noKartu, tglDaftar, noUrut, kdPoli)']);
          exit();
      }

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'pendaftaran/peserta/' . $noKartu . '/tglDaftar/' . $tglDaftar . '/noUrut/' . $noUrut . '/kdPoli/' . $kdPoli;
      $output = \Systems\Lib\PcareService::delete($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';

      // Selalu update lokal setelah request DELETE dikirim ke PCare
      // Karena PCare kadang return error tapi data sudah terhapus
      if (!empty($no_rawat)) {
          $this->db('mlite_bridging_pcare')
              ->where('no_rawat', $no_rawat)
              ->save([
                  'nomor_urut' => '',
                  'status_kirim' => 'Hapus'
              ]);
      }

      if ($code == '200' || $code == '201') {
          echo json_encode(['status' => 'success', 'message' => 'Pendaftaran berhasil dihapus dari PCare']);
      } else {
          // Coba decrypt error detail
          $errorDetail = $message;
          if (isset($json['response']) && !empty($json['response'])) {
              $stringDecrypt = stringDecrypt($key, $json['response']);
              if (!empty($stringDecrypt)) {
                  $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
                  if (!empty($decompress)) {
                      $errorDetail = $decompress;
                  }
              }
          }
          // Tetap return success karena lokal sudah diupdate
          echo json_encode(['status' => 'success', 'message' => 'Pendaftaran dihapus (PCare response: ' . $errorDetail . ')']);
      }
      exit();
  }

  public function getCekpendaftaranprovider()
  {
      $this->_addHeaderFiles();
      return $this->draw('cekpendaftaranprovider.html');
  }

  public function getCekpendaftaranproviderdisplay()
  {
      $date_input = isset($_GET['date']) ? $_GET['date'] : date('d-m-Y');
      $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
      $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

      // Konversi format tanggal dari dd-mm-yyyy ke dd-mm-yyyy (PCare format)
      $date_parts = explode('-', $date_input);
      if (count($date_parts) == 3) {
          $tglDaftar = $date_parts[0] . '-' . $date_parts[1] . '-' . $date_parts[2];
      } else {
          $tglDaftar = date('d-m-Y');
      }

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consumerID . $this->consumerSecret . $tStamp;

      $url = $this->api_url . 'pendaftaran/tglDaftar/' . $tglDaftar . '/' . $offset . '/' . $limit;
      $output = PcareService::get($url, NULL, $this->consumerID, $this->consumerSecret, $this->consumerUserKey, $this->usernamePcare, $this->passwordPcare, $this->kdAplikasi);
      $json = json_decode($output, true);

      $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : '5000';
      $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'ERROR';

      $stringDecrypt = stringDecrypt($key, isset($json['response']) ? $json['response'] : '');
      $decompress = '';
      if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent($stringDecrypt);
      }

      $response = json_decode($decompress, true);

      $error = false;
      $count = 0;
      $list = [];

      if ($code != '200' && $code != '201') {
          $error = true;
      } else {
          if (isset($response['count'])) {
              $count = $response['count'];
          }
          if (isset($response['list']) && is_array($response['list'])) {
              $no = $offset + 1;
              foreach ($response['list'] as &$item) {
                  $item['no'] = $no++;
                  // Pastikan sub-array ada
                  if (!isset($item['peserta'])) $item['peserta'] = [];
                  if (!isset($item['poli'])) $item['poli'] = [];
                  if (!isset($item['tkp'])) $item['tkp'] = [];
                  // Default values
                  $item['peserta']['noKartu'] = isset($item['peserta']['noKartu']) ? $item['peserta']['noKartu'] : '-';
                  $item['peserta']['nama'] = isset($item['peserta']['nama']) ? $item['peserta']['nama'] : '-';
                  $item['peserta']['sex'] = isset($item['peserta']['sex']) ? $item['peserta']['sex'] : '-';
                  $item['peserta']['tglLahir'] = isset($item['peserta']['tglLahir']) ? $item['peserta']['tglLahir'] : '-';
                  $item['poli']['nmPoli'] = isset($item['poli']['nmPoli']) ? $item['poli']['nmPoli'] : '-';
                  $item['tkp']['nmTkp'] = isset($item['tkp']['nmTkp']) ? $item['tkp']['nmTkp'] : '-';
                  $item['noUrut'] = isset($item['noUrut']) ? $item['noUrut'] : '-';
                  $item['tglDaftar'] = isset($item['tglDaftar']) ? $item['tglDaftar'] : '-';
                  $item['keluhan'] = isset($item['keluhan']) ? $item['keluhan'] : '-';
                  $item['kunjSakit'] = isset($item['kunjSakit']) ? $item['kunjSakit'] : false;
                  $item['status'] = isset($item['status']) ? $item['status'] : '-';
                  $item['sistole'] = isset($item['sistole']) ? $item['sistole'] : 0;
                  $item['diastole'] = isset($item['diastole']) ? $item['diastole'] : 0;
                  $item['beratBadan'] = isset($item['beratBadan']) ? $item['beratBadan'] : 0;
                  $item['tinggiBadan'] = isset($item['tinggiBadan']) ? $item['tinggiBadan'] : 0;
                  $item['respRate'] = isset($item['respRate']) ? $item['respRate'] : 0;
                  $item['heartRate'] = isset($item['heartRate']) ? $item['heartRate'] : 0;
                  $item['providerPelayanan'] = isset($item['providerPelayanan']) ? $item['providerPelayanan'] : '-';
              }
              unset($item);
              $list = $response['list'];
          }
      }

      echo $this->draw('cekpendaftaranprovider.display.html', [
          'error' => $error,
          'code' => $code,
          'message' => $message,
          'count' => $count,
          'list' => $list,
          'date_display' => $tglDaftar,
          'offset' => $offset,
          'limit' => $limit
      ]);
      exit();
  }

}
