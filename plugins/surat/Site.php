<?php
namespace Plugins\Surat;

use Systems\SiteModule;

class Site extends SiteModule
{
    public function routes()
    {
        $this->route('surat/verifikasi-sakit/(:any)', 'getVerifikasiSakit');
        $this->route('surat/verifikasi-sehat/(:any)', 'getVerifikasiSehat');
    }

    public function getVerifikasiSakit($token)
    {
        $instansi = $this->settings->get('settings.nama_instansi');

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            exit($this->draw('verify.suratsakit.html', ['valid' => false, 'instansi' => $instansi]));
        }

        $surat = $this->db('mlite_surat_sakit')
            ->where('verification_token', $token)
            ->oneArray();

        if (!$surat) {
            exit($this->draw('verify.suratsakit.html', ['valid' => false, 'instansi' => $instansi]));
        }

        $reg = $this->db('reg_periksa')
            ->where('no_rawat', $surat['no_rawat'])
            ->oneArray();

        $nm_poli = '';
        if (!empty($reg['kd_poli'])) {
            $poli    = $this->db('poliklinik')->where('kd_poli', $reg['kd_poli'])->oneArray();
            $nm_poli = $poli['nm_poli'] ?? '';
        }

        $nm_dokter = '';
        $sip_dokter = '';
        if (!empty($reg['kd_dokter'])) {
            $nm_dokter = $this->core->getPegawaiInfo('nama', $reg['kd_dokter']);
            $dokter    = $this->db('dokter')->where('kd_dokter', $reg['kd_dokter'])->oneArray();
            $sip_dokter = $dokter['no_ijn_praktek'] ?? '';
        }

        $pemeriksaan = $this->db('pemeriksaan_ralan')
            ->where('no_rawat', $surat['no_rawat'])
            ->desc('tgl_perawatan')
            ->desc('jam_rawat')
            ->oneArray();

        $tgl_pelayanan = '';
        $jam_pelayanan = '';
        if ($pemeriksaan) {
            $tgl_pelayanan = date('d/m/Y', strtotime($pemeriksaan['tgl_perawatan']));
            $jam_pelayanan = $pemeriksaan['jam_rawat'];
        }

        $tanggal_selesai_formatted = '';
        if (!empty($surat['tanggal_selesai'])) {
            $tanggal_selesai_formatted = date('d/m/Y', strtotime($surat['tanggal_selesai']));
        }

        exit($this->draw('verify.suratsakit.html', [
            'valid'                     => true,
            'surat'                     => $surat,
            'reg'                       => $reg ?: [],
            'nm_poli'                   => $nm_poli,
            'nm_dokter'                 => $nm_dokter,
            'sip_dokter'                => $sip_dokter,
            'tgl_pelayanan'             => $tgl_pelayanan,
            'jam_pelayanan'             => $jam_pelayanan,
            'tanggal_selesai_formatted' => $tanggal_selesai_formatted,
            'instansi'                  => $instansi,
        ]));
    }

    public function getVerifikasiSehat($token)
    {
        $instansi = $this->settings->get('settings.nama_instansi');

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            exit($this->draw('verify.suratsehat.html', ['valid' => false, 'instansi' => $instansi]));
        }

        $surat = $this->db('mlite_surat_sehat')
            ->where('verification_token', $token)
            ->oneArray();

        if (!$surat) {
            exit($this->draw('verify.suratsehat.html', ['valid' => false, 'instansi' => $instansi]));
        }

        $reg = $this->db('reg_periksa')
            ->where('no_rawat', $surat['no_rawat'])
            ->oneArray();

        $nm_dokter = '';
        $sip_dokter = '';
        if (!empty($reg['kd_dokter'])) {
            $nm_dokter = $this->core->getPegawaiInfo('nama', $reg['kd_dokter']);
            $dokter    = $this->db('dokter')->where('kd_dokter', $reg['kd_dokter'])->oneArray();
            $sip_dokter = $dokter['no_ijn_praktek'] ?? '';
        }

        $tanggal_surat_formatted = '';
        if (!empty($surat['tanggal_surat'])) {
            $tanggal_surat_formatted = date('d/m/Y', strtotime($surat['tanggal_surat']));
        }

        $berlaku_sampai_formatted = '';
        if (!empty($surat['berlaku_sampai'])) {
            $berlaku_sampai_formatted = date('d/m/Y', strtotime($surat['berlaku_sampai']));
        }

        exit($this->draw('verify.suratsehat.html', [
            'valid'                    => true,
            'surat'                    => $surat,
            'reg'                      => $reg ?: [],
            'nm_dokter'                => $nm_dokter,
            'sip_dokter'               => $sip_dokter,
            'tanggal_surat_formatted'  => $tanggal_surat_formatted,
            'berlaku_sampai_formatted' => $berlaku_sampai_formatted,
            'instansi'                 => $instansi,
        ]));
    }
}
