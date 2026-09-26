<?php

namespace Plugins\Surat;

use Systems\AdminModule;

class Admin extends AdminModule
{
    public $assign;

    public function init()
    {
        \Systems\Lib\Event::add('rawat_jalan.surat_menu', function ($row) {
            echo '<li><a href="'.url([ADMIN, 'surat', 'suratrujukan', convertNorawat($row['no_rawat'])]).'" target="_blank">Surat Rujukan</a></li>';
            echo '<li><a href="'.url([ADMIN, 'surat', 'suratsehat', convertNorawat($row['no_rawat'])]).'" target="_blank">Surat Keterangan Sehat</a></li>';
            echo '<li><a href="'.url([ADMIN, 'surat', 'suratsakit', convertNorawat($row['no_rawat'])]).'" target="_blank">Surat Keterangan Sakit</a></li>';
        });
    }

    public function navigation()
    {
        return [
            'Kelola' => 'manage',
            'Surat Rujukan' => 'rujukan',
            'Surat Sakit' => 'sakit',
            'Surat Sehat' => 'sehat',
        ];
    }

    public function getManage()
    {
        $sub_modules = [
            ['name' => 'Surat Rujukan', 'url' => url([ADMIN, 'surat', 'rujukan']), 'icon' => 'share', 'desc' => 'Kelola Surat Rujukan'],
            ['name' => 'Surat Sakit', 'url' => url([ADMIN, 'surat', 'sakit']), 'icon' => 'medkit', 'desc' => 'Kelola Surat Sakit'],
            ['name' => 'Surat Sehat', 'url' => url([ADMIN, 'surat', 'sehat']), 'icon' => 'heart', 'desc' => 'Kelola Surat Sehat'],
        ];
        
        return $this->draw('manage.html', ['sub_modules' => $sub_modules]);
    }

    // SURAT RUJUKAN METHODS
    public function anyRujukan($page = 1)
    {
        $this->_addHeaderFiles();
        $perpage = '10';
        $phrase = '';
        if (isset($_POST['s'])) {
            $phrase = $_POST['s'];
        }

        // pagination
        $totalRecords = $this->db('mlite_surat_rujukan')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->toArray();
        $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'surat', 'rujukan', '%d']));
        $this->assign['pagination'] = $pagination->nav('pagination', '5');
        $this->assign['totalRecords'] = $totalRecords;

        $offset = $pagination->offset();
        $rows = $this->db('mlite_surat_rujukan')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->offset($offset)
            ->limit($perpage)
            ->toArray();

        $this->assign['list'] = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row = htmlspecialchars_array($row);
                $row['editURL'] = url([ADMIN, 'surat', 'rujukanedit', $row['id']]);
                $row['deleteURL'] = url([ADMIN, 'surat', 'rujukanhapus', $row['id']]);
                $this->assign['list'][] = $row;
            }
        }

        $this->assign['searchURL'] = url([ADMIN, 'surat', 'rujukan']);
        $this->assign['addURL'] = url([ADMIN, 'surat', 'rujukanadd']);
        $this->assign['phrase'] = $phrase;
        return $this->draw('rujukan.manage.html', ['rujukan' => $this->assign]);
    }

    public function getRujukanAdd()
    {
        $this->_addHeaderFiles();
        if (!empty($redirectData = getRedirectData())) {
            $this->assign['form'] = filter_var_array($redirectData, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $this->assign['form'] = [
                'id' => '',
                'nomor_surat' => '',
                'no_rawat' => '',
                'no_rkm_medis' => '',
                'nm_pasien' => '',
                'tgl_lahir' => '',
                'umur' => '',
                'jk' => '',
                'alamat' => '',
                'kepada' => '',
                'di' => '',
                'anamnesa' => '',
                'pemeriksaan_fisik' => '',
                'pemeriksaan_penunjang' => '',
                'diagnosa' => '',
                'terapi' => '',
                'alasan_dirujuk' => '',
                'dokter' => '',
                'petugas' => ''
            ];
        }

        return $this->draw('rujukan.form.html', ['rujukan' => $this->assign]);
    }

    public function getRujukanEdit($id)
    {
        $this->_addHeaderFiles();
        $row = $this->db('mlite_surat_rujukan')->where('id', $id)->oneArray();
        if (!empty($row)) {
            $this->assign['form'] = $row;
            return $this->draw('rujukan.form.html', ['rujukan' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'surat', 'rujukan']));
        }
    }

    public function postRujukanSave($id = null)
    {
        $errors = 0;

        if (!$id) {
            $location = url([ADMIN, 'surat', 'rujukan']);
        } else {
            $location = url([ADMIN, 'surat', 'rujukanedit', $id]);
        }

        if (!$errors) {
            unset($_POST['save']);

            if (!$id) {
                $query = $this->db('mlite_surat_rujukan')->save($_POST);
            } else {
                $query = $this->db('mlite_surat_rujukan')->where('id', $id)->save($_POST);
            }

            if ($query) {
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location, $_POST);
        }

        redirect($location, $_POST);
    }

    public function getRujukanHapus($id)
    {
        if ($this->db('mlite_surat_rujukan')->where('id', $id)->delete()) {
            $this->notify('success', 'Hapus sukses');
        } else {
            $this->notify('failure', 'Hapus gagal');
        }
        redirect(url([ADMIN, 'surat', 'rujukan']));
    }

    public function getSuratRujukan($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->db('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $sip_dokter = $this->core->getDokterInfo('no_ijn_praktek', $kd_dokter);
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('sip_dokter', $sip_dokter);
        $this->tpl->set('no_rawat', revertNoRawat($no_rawat));
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        $this->tpl->set('surat', $this->db('mlite_surat_rujukan')->where('no_rawat', revertNoRawat($no_rawat))->oneArray());
        echo $this->tpl->draw(MODULES.'/surat/view/admin/surat.rujukan.html', true);
        exit();
    }

    // SURAT SAKIT METHODS
    public function anySakit($page = 1)
    {
        $this->_addHeaderFiles();
        $perpage = '10';
        $phrase = '';
        if (isset($_POST['s'])) {
            $phrase = $_POST['s'];
        }

        // pagination
        $totalRecords = $this->db('mlite_surat_sakit')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->toArray();
        $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'surat', 'sakit', '%d']));
        $this->assign['pagination'] = $pagination->nav('pagination', '5');
        $this->assign['totalRecords'] = $totalRecords;

        $offset = $pagination->offset();
        $rows = $this->db('mlite_surat_sakit')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->offset($offset)
            ->limit($perpage)
            ->toArray();

        $this->assign['list'] = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row = htmlspecialchars_array($row);
                $row['editURL'] = url([ADMIN, 'surat', 'sakitedit', $row['id']]);
                $row['deleteURL'] = url([ADMIN, 'surat', 'sakithapus', $row['id']]);
                $this->assign['list'][] = $row;
            }
        }

        $this->assign['searchURL'] = url([ADMIN, 'surat', 'sakit']);
        $this->assign['addURL'] = url([ADMIN, 'surat', 'sakitadd']);
        $this->assign['phrase'] = $phrase;
        return $this->draw('sakit.manage.html', ['sakit' => $this->assign]);
    }

    public function getSakitAdd()
    {
        $this->_addHeaderFiles();
        if (!empty($redirectData = getRedirectData())) {
            $this->assign['form'] = filter_var_array($redirectData, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $this->assign['form'] = [
                'id' => '',
                'nomor_surat' => '',
                'no_rawat' => '',
                'no_rkm_medis' => '',
                'nm_pasien' => '',
                'tgl_lahir' => '',
                'umur' => '',
                'jk' => '',
                'alamat' => '',
                'keadaan' => '',
                'diagnosa' => '',
                'lama_angka' => '',
                'lama_huruf' => '',
                'tanggal_mulai' => '',
                'tanggal_selesai' => '',
                'dokter' => '',
                'petugas' => ''
            ];
        }

        return $this->draw('sakit.form.html', ['sakit' => $this->assign]);
    }

    public function getSakitEdit($id)
    {
        $this->_addHeaderFiles();
        $row = $this->db('mlite_surat_sakit')->where('id', $id)->oneArray();
        if (!empty($row)) {
            $this->assign['form'] = $row;
            return $this->draw('sakit.form.html', ['sakit' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'surat', 'sakit']));
        }
    }

    public function postSakitSave($id = null)
    {
        $errors = 0;

        if (!$id) {
            $location = url([ADMIN, 'surat', 'sakit']);
        } else {
            $location = url([ADMIN, 'surat', 'sakit_edit', $id]);
        }

        if (!$errors) {
            unset($_POST['save']);

            if (!$id) {
                $query = $this->db('mlite_surat_sakit')->save($_POST);
            } else {
                $query = $this->db('mlite_surat_sakit')->where('id', $id)->save($_POST);
            }

            if ($query) {
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location, $_POST);
        }

        redirect($location, $_POST);
    }

    public function getSakitHapus($id)
    {
        if ($this->db('mlite_surat_sakit')->where('id', $id)->delete()) {
            $this->notify('success', 'Hapus sukses');
        } else {
            $this->notify('failure', 'Hapus gagal');
        }
        redirect(url([ADMIN, 'surat', 'sakit']));
    }

    public function getSuratSakit($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->db('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $sip_dokter = $this->core->getDokterInfo('no_ijn_praktek', $kd_dokter);
        
        // Sanitize data for JavaScript/JSON usage
        $pasien_clean = sanitizeForJson($pasien);
        $nm_dokter_clean = sanitizeForJson($nm_dokter);
        $no_rawat_clean = sanitizeForJson(revertNoRawat($no_rawat));
        
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('pasien_clean', $pasien_clean);
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('nm_dokter_clean', $nm_dokter_clean);
        $this->tpl->set('sip_dokter', $sip_dokter);
        $this->tpl->set('no_rawat', revertNoRawat($no_rawat));
        $this->tpl->set('no_rawat_clean', $no_rawat_clean);
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        $kd_poli  = $this->core->getRegPeriksaInfo('kd_poli', revertNoRawat($no_rawat));
        $poli     = $this->db('poliklinik')->where('kd_poli', $kd_poli)->oneArray();
        $this->tpl->set('nm_poli', isset($poli['nm_poli']) ? $poli['nm_poli'] : '');
        
        // Get tanggal registrasi pasien untuk konsistensi nomor surat
        $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', revertNoRawat($no_rawat));
        error_log("getSuratSakit - no_rawat: " . revertNoRawat($no_rawat) . ", tgl_registrasi: $tgl_registrasi");
        
        $surat     = $this->db('mlite_surat_sakit')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
        $pre_token = !empty($surat['verification_token']) ? $surat['verification_token'] : bin2hex(random_bytes(32));
        
        if (empty($surat['nomor_surat'])) {
            error_log("Generating new nomor surat for date: $tgl_registrasi");
            $ni = generateNomorSuratSakit($this->db()->pdo(), $tgl_registrasi);
            $pre_nomor = $ni['nomor_surat'];
            error_log("Generated pre_nomor: $pre_nomor");
        } else {
            $pre_nomor = $surat['nomor_surat'];
            error_log("Using existing nomor: $pre_nomor");
        }
        
        // Sanitize all data for JSON/JavaScript safety
        $surat_clean = is_array($surat) ? sanitizeForJson($surat) : [];
        $pre_token_clean = sanitizeForJson($pre_token);
        $pre_nomor_clean = sanitizeForJson($pre_nomor);
        
        $this->tpl->set('surat', $surat);
        $this->tpl->set('surat_clean', $surat_clean);
        $this->tpl->set('pre_token', $pre_token);
        $this->tpl->set('pre_token_clean', $pre_token_clean);
        $this->tpl->set('pre_nomor', $pre_nomor);
        $this->tpl->set('pre_nomor_clean', $pre_nomor_clean);
        $this->tpl->set('tgl_registrasi', $tgl_registrasi);
        
        echo $this->tpl->draw(MODULES.'/surat/view/admin/surat.sakit.html', true);
        exit();
    }

    public function postSimpanSuratSakit()
    {
      $no_rawat        = $_POST['no_rawat'] ?? '';
      $tgl_lahir       = $_POST['tgl_lahir'] ?? '';
      
      // Use tanggal registrasi pasien sebagai tanggal surat untuk konsistensi
      $tanggal_surat   = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat) ?: date('Y-m-d');
      error_log("postSimpanSuratSakit - no_rawat: $no_rawat, tanggal_surat: $tanggal_surat");
      
      $tanggal_selesai = $_POST['tanggal_selesai'] ?? $tanggal_surat;
      $tanggal_mulai   = $_POST['tanggal_mulai'] ?? $tanggal_surat;
      $max_mulai       = date('Y-m-d', strtotime('+3 days', strtotime($tanggal_surat)));
      if ($tanggal_mulai < $tanggal_surat || $tanggal_mulai > $max_mulai) $tanggal_mulai = $tanggal_surat;
      if ($tanggal_selesai < $tanggal_mulai) $tanggal_selesai = $tanggal_mulai;
      $max_selesai     = date('Y-m-d', strtotime('+3 days', strtotime($tanggal_mulai)));
      if ($tanggal_selesai > $max_selesai) {
        echo json_encode(['status' => 'error', 'msg' => 'Tanggal selesai maksimal 3 hari dari tanggal mulai.']);
        exit();
      }
      $lama_angka = (new \DateTime($tanggal_mulai))->diff(new \DateTime($tanggal_selesai))->days + 1;
      $lama_huruf = strtolower(terbilang($lama_angka));
      $umur       = hitungUmurPadaTanggal($tgl_lahir, $tanggal_surat);
      $existing   = $this->db('mlite_surat_sakit')->where('no_rawat', $no_rawat)->oneArray();
      if ($existing) {
        // Surat lama tanpa token: generate dan simpan sekarang
        if (empty($existing['verification_token'])) {
          $verification_token = (!empty($_POST['pre_token']) && preg_match('/^[a-f0-9]{64}$/', $_POST['pre_token']))
              ? $_POST['pre_token'] : bin2hex(random_bytes(32));
          $extra_update = ['verification_token' => $verification_token, 'tanggal_surat' => $tanggal_surat, 'created_at' => date('Y-m-d H:i:s')];
        } else {
          $verification_token = $existing['verification_token'];
          $extra_update = [];
        }
        $this->db('mlite_surat_sakit')->where('no_rawat', $no_rawat)->save(array_merge([
          'no_rkm_medis' => $_POST['no_rkm_medis'] ?? '', 'nm_pasien' => $_POST['nm_pasien'] ?? '',
          'tgl_lahir' => $tgl_lahir, 'umur' => $umur, 'jk' => $_POST['jk'] ?? '',
          'alamat' => $_POST['alamat'] ?? '', 'lama_angka' => $lama_angka, 'lama_huruf' => $lama_huruf,
          'tanggal_mulai' => $tanggal_mulai, 'tanggal_selesai' => $tanggal_selesai,
          'dokter' => $_POST['dokter'] ?? '', 'petugas' => $_POST['petugas'] ?? '',
          'updated_at' => date('Y-m-d H:i:s'),
        ], $extra_update));
        $nomor_surat = $existing['nomor_surat'];
      } else {
        $info               = generateNomorSuratSakit($this->db()->pdo(), $tanggal_surat);
        $nomor_surat        = $info['nomor_surat'];
        $verification_token = (!empty($_POST['pre_token']) && preg_match('/^[a-f0-9]{64}$/', $_POST['pre_token']))
            ? $_POST['pre_token'] : bin2hex(random_bytes(32));
        $this->db('mlite_surat_sakit')->save([
          'nomor_surat' => $nomor_surat, 'no_rawat' => $no_rawat,
          'no_rkm_medis' => $_POST['no_rkm_medis'] ?? '', 'nm_pasien' => $_POST['nm_pasien'] ?? '',
          'tgl_lahir' => $tgl_lahir, 'umur' => $umur, 'jk' => $_POST['jk'] ?? '',
          'alamat' => $_POST['alamat'] ?? '', 'keadaan' => '', 'diagnosa' => '',
          'lama_angka' => $lama_angka, 'lama_huruf' => $lama_huruf,
          'tanggal_mulai' => $tanggal_mulai, 'tanggal_selesai' => $tanggal_selesai,
          'dokter' => $_POST['dokter'] ?? '', 'petugas' => $_POST['petugas'] ?? '',
          'tanggal_surat' => $tanggal_surat, 'nomor_urut_harian' => $info['nomor_urut_harian'],
          'verification_token' => $verification_token,
          'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
      }
      echo json_encode(['status' => 'success', 'nomor_surat' => $nomor_surat, 'verification_token' => $verification_token]);
      exit();
    }
    public function anySehat($page = 1)
    {
        $this->_addHeaderFiles();
        $perpage = '10';
        $phrase = '';
        if (isset($_POST['s'])) {
            $phrase = $_POST['s'];
        }

        // pagination
        $totalRecords = $this->db('mlite_surat_sehat')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->toArray();
        $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'surat', 'sehat', '%d']));
        $this->assign['pagination'] = $pagination->nav('pagination', '5');
        $this->assign['totalRecords'] = $totalRecords;

        $offset = $pagination->offset();
        $rows = $this->db('mlite_surat_sehat')
            ->like('nomor_surat', '%' . $phrase . '%')
            ->orLike('nm_pasien', '%' . $phrase . '%')
            ->offset($offset)
            ->limit($perpage)
            ->toArray();

        $this->assign['list'] = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row = htmlspecialchars_array($row);
                $row['editURL'] = url([ADMIN, 'surat', 'sehatedit', $row['id']]);
                $row['deleteURL'] = url([ADMIN, 'surat', 'sehathapus', $row['id']]);
                $this->assign['list'][] = $row;
            }
        }

        $this->assign['searchURL'] = url([ADMIN, 'surat', 'sehat']);
        $this->assign['addURL'] = url([ADMIN, 'surat', 'sehatadd']);
        $this->assign['phrase'] = $phrase;
        return $this->draw('sehat.manage.html', ['sehat' => $this->assign]);
    }

    public function getSehatAdd()
    {
        $this->_addHeaderFiles();
        if (!empty($redirectData = getRedirectData())) {
            $this->assign['form'] = filter_var_array($redirectData, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $this->assign['form'] = [
                'id' => '',
                'nomor_surat' => '',
                'no_rawat' => '',
                'no_rkm_medis' => '',
                'nm_pasien' => '',
                'tgl_lahir' => '',
                'umur' => '',
                'jk' => '',
                'alamat' => '',
                'tanggal' => '',
                'berat_badan' => '',
                'tinggi_badan' => '',
                'tensi' => '',
                'gol_darah' => '',
                'riwayat_penyakit' => '',
                'keperluan' => '',
                'dokter' => '',
                'petugas' => ''
            ];
        }

        return $this->draw('sehat.form.html', ['sehat' => $this->assign]);
    }

    public function getSehatEdit($id)
    {
        $this->_addHeaderFiles();
        $row = $this->db('mlite_surat_sehat')->where('id', $id)->oneArray();
        if (!empty($row)) {
            $this->assign['form'] = $row;
            return $this->draw('sehat.form.html', ['sehat' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'surat', 'sehat']));
        }
    }

    public function postSehatSave($id = null)
    {
        $errors = 0;

        if (!$id) {
            $location = url([ADMIN, 'surat', 'sehat']);
        } else {
            $location = url([ADMIN, 'surat', 'sehat_edit', $id]);
        }

        if (!$errors) {
            unset($_POST['save']);

            if (!$id) {
                $query = $this->db('mlite_surat_sehat')->save($_POST);
            } else {
                $query = $this->db('mlite_surat_sehat')->where('id', $id)->save($_POST);
            }

            if ($query) {
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location, $_POST);
        }

        redirect($location, $_POST);
    }

    public function getSehatHapus($id)
    {
        if ($this->db('mlite_surat_sehat')->where('id', $id)->delete()) {
            $this->notify('success', 'Hapus sukses');
        } else {
            $this->notify('failure', 'Hapus gagal');
        }
        redirect(url([ADMIN, 'surat', 'sehat']));
    }

    public function getSuratSehat($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->db('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $sip_dokter = $this->core->getDokterInfo('no_ijn_praktek', $kd_dokter);
        
        // Get SOAP data hari ini
        $soap = $this->db('pemeriksaan_ralan')
          ->where('no_rawat', revertNoRawat($no_rawat))
          ->oneArray();
        
        $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', revertNoRawat($no_rawat));
        $poli = $this->db('poliklinik')->where('kd_poli', $kd_poli)->oneArray();
        
        // Get tanggal registrasi pasien
        $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', revertNoRawat($no_rawat));
        error_log("getSuratSehat - no_rawat: " . revertNoRawat($no_rawat) . ", tgl_registrasi: $tgl_registrasi");
        
        // Sanitize data for JavaScript/JSON usage
        $pasien_clean = sanitizeForJson($pasien);
        $nm_dokter_clean = sanitizeForJson($nm_dokter);
        $no_rawat_clean = sanitizeForJson(revertNoRawat($no_rawat));
        $soap_clean = is_array($soap) ? sanitizeForJson($soap) : [];
        
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('pasien_clean', $pasien_clean);
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('nm_dokter_clean', $nm_dokter_clean);
        $this->tpl->set('sip_dokter', $sip_dokter);
        $this->tpl->set('no_rawat', revertNoRawat($no_rawat));
        $this->tpl->set('no_rawat_clean', $no_rawat_clean);
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        $this->tpl->set('nm_poli', isset($poli['nm_poli']) ? $poli['nm_poli'] : '');
        $this->tpl->set('soap', $soap ?: []);
        $this->tpl->set('soap_clean', $soap_clean);
        
        $surat = $this->db('mlite_surat_sehat')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
        // Tambah kolom jika belum ada (one-time migration)
        try { $this->db()->pdo()->exec("ALTER TABLE mlite_surat_sehat ADD COLUMN status_kesehatan varchar(200) DEFAULT 'SEHAT'"); } catch (\Exception $e) {}
        $pre_token = !empty($surat['verification_token']) ? $surat['verification_token'] : bin2hex(random_bytes(32));
        
        if (empty($surat['nomor_surat'])) {
            error_log("Generating new nomor surat sehat for date: $tgl_registrasi");
            $ni = generateNomorSuratSehat($this->db()->pdo(), $tgl_registrasi);
            $pre_nomor = $ni['nomor_surat'];
            error_log("Generated pre_nomor: $pre_nomor");
        } else {
            $pre_nomor = $surat['nomor_surat'];
            error_log("Using existing nomor: $pre_nomor");
        }
        
        // Sanitize all data for JSON/JavaScript safety
        $surat_clean = is_array($surat) ? sanitizeForJson($surat) : [];
        $pre_token_clean = sanitizeForJson($pre_token);
        $pre_nomor_clean = sanitizeForJson($pre_nomor);
        
        $this->tpl->set('surat', $surat);
        $this->tpl->set('surat_clean', $surat_clean);
        $this->tpl->set('pre_token', $pre_token);
        $this->tpl->set('pre_token_clean', $pre_token_clean);
        $this->tpl->set('pre_nomor', $pre_nomor);
        $this->tpl->set('pre_nomor_clean', $pre_nomor_clean);
        $this->tpl->set('tgl_registrasi', $tgl_registrasi);
        
        echo $this->tpl->draw(MODULES.'/surat/view/admin/surat.sehat.html', true);
        exit();
    }

    public function postSimpanSuratSehat()
    {
      $no_rawat = $_POST['no_rawat'] ?? '';
      $tgl_lahir = $_POST['tgl_lahir'] ?? '';
      
      // Use tanggal registrasi pasien sebagai tanggal surat untuk konsistensi
      $tanggal_surat = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat) ?: date('Y-m-d');
      error_log("postSimpanSuratSehat - no_rawat: $no_rawat, tanggal_surat: $tanggal_surat");
      
      $berlaku_sampai = $_POST['berlaku_sampai'] ?? date('Y-m-d', strtotime('+30 days'));
      
      // Validasi berlaku sampai tidak lebih dari 1 tahun
      $max_berlaku = date('Y-m-d', strtotime('+365 days', strtotime($tanggal_surat)));
      if ($berlaku_sampai > $max_berlaku) {
        echo json_encode(['status' => 'error', 'msg' => 'Tanggal berlaku maksimal 1 tahun dari tanggal surat.']);
        exit();
      }
      
      $umur = hitungUmurPadaTanggal($tgl_lahir, $tanggal_surat);
      $existing = $this->db('mlite_surat_sehat')->where('no_rawat', $no_rawat)->oneArray();
      
      if ($existing) {
        // Update existing record
        if (empty($existing['verification_token'])) {
          $verification_token = (!empty($_POST['pre_token']) && preg_match('/^[a-f0-9]{64}$/', $_POST['pre_token']))
              ? $_POST['pre_token'] : bin2hex(random_bytes(32));
          $extra_update = [
            'verification_token' => $verification_token,
            'tanggal_surat' => $tanggal_surat,
            'created_at' => date('Y-m-d H:i:s')
          ];
        } else {
          $verification_token = $existing['verification_token'];
          $extra_update = [];
        }
        
        $query = $this->db('mlite_surat_sehat')->where('no_rawat', $no_rawat)->save(array_merge([
          'nomor_surat' => $existing['nomor_surat'],
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'nm_pasien' => $_POST['nm_pasien'],
          'tgl_lahir' => $tgl_lahir,
          'umur' => $umur,
          'jk' => $_POST['jk'],
          'alamat' => $_POST['alamat'],
          'tanggal' => $tanggal_surat,
          'berat_badan' => $_POST['berat_badan'],
          'tinggi_badan' => $_POST['tinggi_badan'],
          'tensi' => $_POST['tensi'],
          'suhu_badan' => $_POST['suhu_badan'] ?? '',
          'riwayat_penyakit' => $_POST['riwayat_penyakit'] ?? '',
          'agama' => $_POST['agama'] ?? '',
          'pekerjaan' => $_POST['pekerjaan'] ?? '',
          'keperluan' => $_POST['keperluan'],
          'berlaku_sampai' => $berlaku_sampai,
          'status_kesehatan' => $_POST['status_kesehatan'] ?? 'SEHAT',
          'dokter' => $_POST['dokter'],
          'petugas' => $_POST['petugas'],
          'updated_at' => date('Y-m-d H:i:s')
        ], $extra_update));
        
        $nomor_surat = $existing['nomor_surat'];
      } else {
        // Insert new record
        $info = generateNomorSuratSehat($this->db()->pdo(), $tanggal_surat);
        $nomor_surat = $info['nomor_surat'];
        $verification_token = (!empty($_POST['pre_token']) && preg_match('/^[a-f0-9]{64}$/', $_POST['pre_token']))
            ? $_POST['pre_token'] : bin2hex(random_bytes(32));
        
        $query = $this->db('mlite_surat_sehat')->save([
          'nomor_surat' => $nomor_surat,
          'no_rawat' => $no_rawat,
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'nm_pasien' => $_POST['nm_pasien'],
          'tgl_lahir' => $tgl_lahir,
          'umur' => $umur,
          'jk' => $_POST['jk'],
          'alamat' => $_POST['alamat'],
          'tanggal' => $tanggal_surat,
          'berat_badan' => $_POST['berat_badan'],
          'tinggi_badan' => $_POST['tinggi_badan'],
          'tensi' => $_POST['tensi'],
          'suhu_badan' => $_POST['suhu_badan'] ?? '',
          'riwayat_penyakit' => $_POST['riwayat_penyakit'] ?? '',
          'agama' => $_POST['agama'] ?? '',
          'pekerjaan' => $_POST['pekerjaan'] ?? '',
          'keperluan' => $_POST['keperluan'],
          'berlaku_sampai' => $berlaku_sampai,
          'status_kesehatan' => $_POST['status_kesehatan'] ?? 'SEHAT',
          'dokter' => $_POST['dokter'],
          'petugas' => $_POST['petugas'],
          'tanggal_surat' => $tanggal_surat,
          'nomor_urut_harian' => $info['nomor_urut_harian'],
          'verification_token' => $verification_token,
          'created_at' => date('Y-m-d H:i:s'),
          'updated_at' => date('Y-m-d H:i:s')
        ]);
      }
      
      if ($query) {
        echo json_encode(['status' => 'success', 'nomor_surat' => $nomor_surat, 'verification_token' => $verification_token]);
      } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan surat sehat.']);
      }
      
      exit();
    }

    // PENGATURAN METHODS
    public function getSettings()
    {
        $this->_addHeaderFiles();
        $this->assign['title'] = 'Pengaturan Surat';
        $this->assign['settings'] = [
            'surat_rujukan_template' => $this->settings->get('surat.rujukan_template', ''),
            'surat_sakit_template' => $this->settings->get('surat.sakit_template', ''),
            'surat_sehat_template' => $this->settings->get('surat.sehat_template', ''),
            'kepala_surat' => $this->settings->get('surat.kepala_surat', ''),
            'footer_surat' => $this->settings->get('surat.footer_surat', '')
        ];
        return $this->draw('settings.html', ['settings' => $this->assign]);
    }

    public function postSettingsSave()
    {
        foreach ($_POST as $key => $value) {
            $this->settings->set('surat.' . $key, $value);
        }
        $this->notify('success', 'Pengaturan berhasil disimpan');
        redirect(url([ADMIN, 'surat', 'settings']));
    }

    private function _addHeaderFiles()
    {
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
    }
}