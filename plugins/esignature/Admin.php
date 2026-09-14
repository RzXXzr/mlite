<?php
namespace Plugins\Esignature;

use Systems\AdminModule;

class Admin extends AdminModule
{
    public function navigation()
    {
        return [
            'Kelola' => 'manage',
            'Pengaturan' => 'settings'
        ];
    }

    public function getManage()
    {
        $signatures = $this->db('esignatures')->desc('id')->limit(50)->toArray();
        return $this->draw('manage.html', ['signatures' => $signatures]);
    }

    public function getSettings()
    {
        $master_berkas_digital = $this->db('master_berkas_digital')->toArray();
        return $this->draw('settings.html', ['settings' => $this->settings('esignature'), 'master_berkas_digital' => $master_berkas_digital]);
    }

    public function postSettings()
    {
        foreach ($_POST['esignature'] as $key => $val) {
            $this->settings('esignature', $key, $val);
        }
        $this->notify('success', 'Pengaturan telah disimpan');
        redirect(url([ADMIN, 'esignature', 'settings']));
    }

    public function getSign($ref_type, $ref_id)
    {
        // $this->core->addJS(url('plugins/esignature/assets/signature_pad.min.js'));
        // $this->core->addJS(url('plugins/esignature/assets/esignature.js'));

        $signer_role = 'dokter'; // Default for admin/staff
        $signer_id = $this->core->getUserInfo('username');
        $signer_name = $this->core->getUserInfo('fullname');

        // Logic to detect if patient (if this was a public portal or kiosk)
        // For now, assume logged in user is signing
        
        exit($this->draw('sign.html', [
            'ref_type' => $ref_type,
            'ref_id' => $ref_id,
            'signer_role' => $signer_role,
            'signer_id' => $signer_id,
            'signer_name' => $signer_name
        ]));
    }

    public function postSaveSignature()
    {
        // Disable error reporting for JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clear buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');

        try {
            if (!isset($_POST['ref_type']) || !isset($_POST['ref_id']) || !isset($_POST['image_data'])) {
                throw new \Exception("Missing parameters");
            }

            $ref_type = $_POST['ref_type'];
            $ref_id = $_POST['ref_id'];
            $image_data = $_POST['image_data']; 
            
            $filename = 'sign_' . time() . '_' . uniqid() . '.png';
            $dir = WEBAPPS_PATH . '/berkas/esignature/';
            
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \Exception("Failed to create directory: $dir");
                }
            }

            if (!is_writable($dir)) {
                throw new \Exception("Directory not writable: $dir");
            }

            $path = $dir . $filename;
            $image_data = str_replace('data:image/png;base64,', '', $image_data);
            $image_data = str_replace(' ', '+', $image_data);
            
            if (file_put_contents($path, base64_decode($image_data)) === false) {
                 throw new \Exception("Failed to save image file");
            }

            $hash = hash_file('sha256', $path);

            $save = $this->db('esignatures')->save([
                'ref_type' => $ref_type,
                'ref_id' => $ref_id,
                'signer_role' => $_POST['signer_role'] ?? 'unknown',
                'signer_id' => $_POST['signer_id'] ?? 'unknown',
                'signer_name' => $_POST['signer_name'] ?? 'unknown',
                'signature_path' => $filename,
                'signature_hash' => $hash,
                'signed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'legal_basis' => 'UU ITE No 11 Tahun 2008',
                'audit_json' => json_encode([
                    'server' => $_SERVER,
                    'headers' => function_exists('apache_request_headers') ? apache_request_headers() : []
                ])
            ]);
            
            if (!$save) {
                 throw new \Exception("Database save failed");
            }

            echo json_encode(['status' => 'success', 'hash' => $hash]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        
        exit;
    }

    public function getGeneratePdf($ref_type, $ref_id)
    {
        $logFile = WEBAPPS_PATH . '/../tmp/esign_error.log';
        try {
        $signatures = $this->db('esignatures')
            ->where('ref_type', $ref_type)
            ->where('ref_id', $ref_id)
            ->toArray();

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 25,
            'margin_bottom' => 25,
            'margin_left'   => 30,
            'margin_right'  => 20,
        ]);

        $mpdf->SetWatermarkText('SIGNED ELECTRONICALLY');
        $mpdf->showWatermarkText  = true;
        $mpdf->watermark_font     = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.05;

        $html = '
        <style>
            body { font-family: sans-serif; }
            .content { margin-bottom: 30px; }
            .signature-box { border: 1px solid #ccc; padding: 10px; display: inline-block; width: 48%; margin: 5px; vertical-align: top; }
            table td, table th { padding: 5px; }
            .tbl_form { border-collapse: collapse; }
            .tbl_form td { border: 1px solid #000; }
        </style>
        <div class="content">';

        $contentFile = WEBAPPS_PATH . '/../admin/tmp/' . $ref_type . '.html';
        if (file_exists($contentFile)) {
            $rawContent = file_get_contents($contentFile);

            // Strip tag yang tidak bisa dirender mPDF
            $rawContent = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $rawContent);
            $rawContent = preg_replace('/<link\b[^>]*>/si', '', $rawContent);
            $rawContent = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $rawContent);
            $rawContent = preg_replace('/<input\b[^>]*>/si', '', $rawContent);
            $rawContent = preg_replace('/<button\b[^>]*>.*?<\/button>/si', '', $rawContent);
            $rawContent = preg_replace('/<del\b[^>]*>.*?<\/del>/si', '', $rawContent);
            $rawContent = preg_replace('/<a\b[^>]*class="btn[^"]*"[^>]*>.*?<\/a>/si', '', $rawContent);

            // Ganti URL gambar https:// ke path file lokal agar mPDF bisa membaca
            $baseUrl = url();
            $rawContent = str_replace('src="' . $baseUrl . '/', 'src="' . BASE_DIR . '/', $rawContent);

            $html .= $rawContent;
        } else {
            $html .= '<p>Konten dokumen tidak ditemukan.</p>';
        }

        $html .= '</div><div><h3>Tanda Tangan Elektronik:</h3><div style="width:100%;">';

        foreach ($signatures as $sig) {
            $path      = WEBAPPS_PATH . '/berkas/esignature/' . $sig['signature_path'];
            $verifyUrl = url(['esignature', 'verify', $sig['signature_hash']]);

            if (file_exists($path)) {
                $html .= '
                <div class="signature-box">
                    <table width="100%">
                        <tr>
                            <td width="60%" align="center">
                                <img src="' . $path . '" height="80" /><br>
                                <strong>' . htmlspecialchars($sig['signer_name']) . '</strong><br>
                                <small>' . htmlspecialchars($sig['signer_role']) . '</small><br>
                                <small>' . date('d-m-Y H:i', strtotime($sig['signed_at'])) . '</small>
                            </td>
                            <td width="40%" align="center">
                                <barcode code="' . $verifyUrl . '" type="QR" class="barcode" size="0.8" error="M" disableborder="1" />
                                <br><small>Scan to Verify</small>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="font-size:8px;color:#888;word-break:break-all;text-align:center;">
                                Hash: ' . substr($sig['signature_hash'], 0, 20) . '...
                            </td>
                        </tr>
                    </table>
                </div>';
            }
        }

        $html .= '</div></div>';

        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->use_kwt              = true;

        $namaInstansi = $this->settings('settings.nama_instansi') ?: '';
        $legalBasis   = !empty($signatures) ? ($signatures[0]['legal_basis'] ?? '') : '';

        // SetHTMLFooter harus dipanggil sebelum WriteHTML
        $mpdf->SetHTMLFooter('
        <p style="font-size:9px;color:#888;word-break:break-all;">
            Dokumen ini resmi dan telah ditandatangani secara elektronik sesuai dengan Peraturan Direktur
            ' . htmlspecialchars($namaInstansi) . ' dan ' . htmlspecialchars($legalBasis) . '
        </p>
        <table width="100%"><tr>
            <td width="50%">Dicetak pada: ' . date('d-m-Y H:i') . '</td>
            <td width="50%" align="right">Halaman {PAGENO} dari {nbpg}</td>
        </tr></table>');

        $mpdf->WriteHTML($html);

        $uploadDir = WEBAPPS_PATH . '/berkasrawat/pages/upload/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $timestamp = date('YmdHis');
        $fileName  = 'doc_' . $ref_id . '_' . $timestamp . '.pdf';
        $filePath  = $uploadDir . $fileName;

        $mpdf->Output($filePath, 'F');

        // Simpan ke berkas digital hanya jika kode sudah dikonfigurasi dan no_rawat valid di reg_periksa
        $kodeBerkas = $this->settings('esignature.kode_berkasdigital');
        if (!empty($kodeBerkas) && file_exists($filePath)) {
            $noRawat = revertNorawat($ref_id);
            $regExists = $this->db('reg_periksa')->where('no_rawat', $noRawat)->count();
            $kodeExists = $this->db('master_berkas_digital')->where('kode', $kodeBerkas)->count();
            if ($regExists && $kodeExists) {
                try {
                    $this->db('berkas_digital_perawatan')->save([
                        'no_rawat'    => $noRawat,
                        'kode'        => $kodeBerkas,
                        'lokasi_file' => 'pages/upload/' . $fileName,
                    ]);
                } catch (\Exception $e) {
                    // Simpan gagal (misal: duplikat) — tidak membatalkan output PDF
                }
            }
        }

        $mpdf->Output($fileName, 'I');
        exit;
        } catch (\Throwable $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' [' . $ref_type . '/' . $ref_id . '] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            throw $e;
        }
    }
}
