<?php

namespace Plugins\Satu_Sehat\Src;

/**
 * BatchProcessor — Server-side batch sender for Satu Sehat FHIR resources.
 *
 * Calls existing Site.php endpoints via HTTP (curl) so no code is duplicated
 * from Admin.php. Tracks progress, checks existing responses before sending,
 * and supports retry mode.
 */
class BatchProcessor
{
    /** @var object Main application core instance */
    private $core;

    /** @var string Base URL of the application, e.g. http://10.10.10.58 */
    private $baseUrl;

    /** @var int HTTP timeout per resource call in seconds */
    private $timeout;

    /**
     * Resource definitions: key => [endpoint pattern, response column(s), label]
     * The order matters — encounter must be first (dependency for all others).
     */
    private const RESOURCES = [
        'encounter'            => ['endpoint' => '/satu-sehat/encounter/',            'col' => 'id_encounter',              'label' => 'Encounter'],
        'condition'            => ['endpoint' => '/satu-sehat/condition/',             'col' => 'id_condition',              'label' => 'Condition'],
        'obs_tensi'            => ['endpoint' => '/satu-sehat/observation/{nr}/tensi',       'col' => 'id_observation_ttvtensi',    'label' => 'Obs Tensi',        'check' => 'tensi'],
        'obs_nadi'             => ['endpoint' => '/satu-sehat/observation/{nr}/nadi',        'col' => 'id_observation_ttvnadi',     'label' => 'Obs Nadi',         'check' => 'nadi'],
        'obs_respirasi'        => ['endpoint' => '/satu-sehat/observation/{nr}/respirasi',   'col' => 'id_observation_ttvrespirasi','label' => 'Obs Respirasi',    'check' => 'respirasi'],
        'obs_suhu'             => ['endpoint' => '/satu-sehat/observation/{nr}/suhu',        'col' => 'id_observation_ttvsuhu',     'label' => 'Obs Suhu',         'check' => 'suhu_tubuh'],
        'obs_spo2'             => ['endpoint' => '/satu-sehat/observation/{nr}/spo2',        'col' => 'id_observation_ttvspo2',     'label' => 'Obs SpO2',         'check' => 'spo2'],
        'obs_gcs'              => ['endpoint' => '/satu-sehat/observation/{nr}/gcs',         'col' => 'id_observation_ttvgcs',      'label' => 'Obs GCS',          'check' => 'gcs'],
        'obs_kesadaran'        => ['endpoint' => '/satu-sehat/observation/{nr}/kesadaran',   'col' => 'id_observation_ttvkesadaran','label' => 'Obs Kesadaran',    'check' => 'kesadaran'],
        'obs_berat'            => ['endpoint' => '/satu-sehat/observation/{nr}/berat',       'col' => 'id_observation_ttvberat',    'label' => 'Obs Berat',        'check' => 'berat'],
        'obs_tinggi'           => ['endpoint' => '/satu-sehat/observation/{nr}/tinggi',      'col' => 'id_observation_ttvtinggi',   'label' => 'Obs Tinggi',       'check' => 'tinggi'],
        'obs_perut'            => ['endpoint' => '/satu-sehat/observation/{nr}/perut',       'col' => 'id_observation_ttvperut',    'label' => 'Obs Lingkar Perut','check' => 'lingkar_perut'],
        'procedure'            => ['endpoint' => '/satu-sehat/procedure/',             'col' => 'id_procedure',              'label' => 'Procedure',        'check' => 'prosedur'],
        'clinical_impression'  => ['endpoint' => '/satu-sehat/clinical-impression/',   'col' => 'id_clinical_impression',    'label' => 'Clinical Impression', 'check' => 'penilaian'],
        'vaksin'               => ['endpoint' => '/satu-sehat/vaksin/',                'col' => 'id_immunization',           'label' => 'Vaksin',           'check' => 'immunization'],
        'diet_gizi'            => ['endpoint' => '/satu-sehat/diet-gizi/',             'col' => 'id_composition',            'label' => 'Diet Gizi',        'check' => 'adime_gizi'],
        'care_plan'            => ['endpoint' => '/satu-sehat/care-plan/',             'col' => 'id_careplan',               'label' => 'Care Plan',        'check' => 'rtl'],
        'allergy'              => ['endpoint' => '/satu-sehat/allergy/',               'col' => 'id_allergy',                'label' => 'Allergy',          'check' => 'allergy'],
        'questionnaire'        => ['endpoint' => '/satu-sehat/questionnaire/',         'col' => 'id_questionnaire',          'label' => 'Questionnaire',    'check' => 'questionnaire'],
        'med_request'          => ['endpoint' => '/satu-sehat/medication/{nr}/request',     'col' => 'id_medication_request',  'label' => 'Med Request',      'check' => 'medications'],
        'med_dispense'         => ['endpoint' => '/satu-sehat/medication/{nr}/dispense',    'col' => 'id_medication_dispense', 'label' => 'Med Dispense',     'check' => 'medications'],
        'med_statement'        => ['endpoint' => '/satu-sehat/medication/{nr}/statement',   'col' => 'id_medication_statement','label' => 'Med Statement',    'check' => 'medications'],
        'lab_request'          => ['endpoint' => '/satu-sehat/laboratory/{nr}/request',     'col' => 'id_lab_pk_request',      'label' => 'Lab Request',      'check' => 'permintaan_lab'],
        'lab_specimen'         => ['endpoint' => '/satu-sehat/laboratory/{nr}/specimen',    'col' => 'id_lab_pk_specimen',     'label' => 'Lab Specimen',     'check' => 'permintaan_lab'],
        'lab_observation'      => ['endpoint' => '/satu-sehat/laboratory/{nr}/observation',  'col' => 'id_lab_pk_observation',  'label' => 'Lab Observation',  'check' => 'permintaan_lab'],
        'lab_diagnostic'       => ['endpoint' => '/satu-sehat/laboratory/{nr}/diagnostic',   'col' => 'id_lab_pk_diagnostic',   'label' => 'Lab Diagnostic',   'check' => 'permintaan_lab'],
        'rad_request'          => ['endpoint' => '/satu-sehat/radiology/{nr}/request',       'col' => 'id_rad_request',         'label' => 'Rad Request',      'check' => 'permintaan_radiologi'],
        'rad_specimen'         => ['endpoint' => '/satu-sehat/radiology/{nr}/specimen',      'col' => 'id_rad_specimen',        'label' => 'Rad Specimen',     'check' => 'permintaan_radiologi'],
        'rad_observation'      => ['endpoint' => '/satu-sehat/radiology/{nr}/observation',   'col' => 'id_rad_observation',     'label' => 'Rad Observation',  'check' => 'permintaan_radiologi'],
        'rad_diagnostic'       => ['endpoint' => '/satu-sehat/radiology/{nr}/diagnostic',    'col' => 'id_rad_diagnostic',      'label' => 'Rad Diagnostic',   'check' => 'permintaan_radiologi'],
    ];

    public function __construct($core, string $baseUrl, int $timeout = 120)
    {
        $this->core    = $core;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Get a query builder for the given table (delegates to core->db()).
     */
    private function db(string $table = null)
    {
        return $this->core->db($table);
    }

    /**
     * Get all no_rawat for a given date (or date range), excluding cancelled registrations.
     * @param string      $tanggalDari  Start date (YYYY-MM-DD)
     * @param string|null $tanggalSampai End date (YYYY-MM-DD). If null, uses $tanggalDari (single day).
     * @return array [['no_rawat' => '...', 'status_lanjut' => '...', ...], ...]
     */
    public function getNoRawatByDate(string $tanggalDari, ?string $tanggalSampai = null): array
    {
        $q = $this->db('reg_periksa')
            ->select(['no_rawat', 'no_rkm_medis', 'kd_dokter', 'kd_poli', 'status_lanjut', 'tgl_registrasi'])
            ->where('stts', '!=', 'Batal');

        if ($tanggalSampai !== null && $tanggalSampai !== $tanggalDari) {
            $q->where('tgl_registrasi', '>=', $tanggalDari)
              ->where('tgl_registrasi', '<=', $tanggalSampai);
        } else {
            $q->where('tgl_registrasi', $tanggalDari);
        }

        return $q->asc('tgl_registrasi')->toArray();
    }

    /**
     * Check existing response IDs for a no_rawat.
     * @return array|null  Column values from mlite_satu_sehat_response, or null if no record.
     */
    public function getExistingResponse(string $no_rawat): ?array
    {
        $row = $this->db('mlite_satu_sehat_response')
            ->where('no_rawat', $no_rawat)
            ->oneArray();
        return $row ?: null;
    }

    /**
     * Check which clinical data exists for this no_rawat so we know which resources
     * are relevant (don't send observation if TTV is empty, etc.)
     * @return array keyed by check-name => bool
     */
    public function getDataAvailability(string $no_rawat, string $status_lanjut): array
    {
        $table = ($status_lanjut === 'Ranap') ? 'pemeriksaan_ranap' : 'pemeriksaan_ralan';

        // Merge ALL rows — one visit may have multiple rows from different staff
        $pemeriksaan = $this->getMergedPemeriksaan($no_rawat, $table);

        $diagnosa = $this->db('diagnosa_pasien')
            ->where('no_rawat', $no_rawat)
            ->where('status', $status_lanjut)
            ->oneArray();

        $prosedur = $this->db('prosedur_pasien')
            ->where('no_rawat', $no_rawat)
            ->where('status', $status_lanjut)
            ->oneArray();

        $medications = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
            ->where('no_rawat', $no_rawat)
            ->oneArray();

        $permintaan_lab = $this->db('permintaan_lab')
            ->where('no_rawat', $no_rawat)
            ->oneArray();

        $permintaan_rad = $this->db('permintaan_radiologi')
            ->where('no_rawat', $no_rawat)
            ->oneArray();

        $adime_gizi = $this->db('catatan_adime_gizi')
            ->where('no_rawat', $no_rawat)
            ->oneArray();

        $immunization = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
            ->join('mlite_satu_sehat_mapping_obat', 'mlite_satu_sehat_mapping_obat.kode_brng=resep_dokter.kode_brng')
            ->where('mlite_satu_sehat_mapping_obat.type', 'vaksin')
            ->where('no_rawat', $no_rawat)
            ->oneArray();

        $questionnaire = $this->db('catatan_perawatan')
            ->where('no_rawat', $no_rawat)
            ->where('catatan', 'KPS')
            ->oneArray();

        // Allergy check via diagnosa ICD-10
        $allergy_icd10 = ['T78.1', 'T88.7', 'J30.1', 'J30.8', 'T78.4'];
        $allergy = $this->db('diagnosa_pasien')
            ->where('no_rawat', $no_rawat)
            ->where('status', $status_lanjut)
            ->in('kd_penyakit', $allergy_icd10)
            ->oneArray();

        $notEmpty = function ($val) {
            return !empty($val) && $val !== '-' && $val !== '0';
        };

        return [
            // TTV fields
            'tensi'         => $notEmpty($pemeriksaan['tensi'] ?? ''),
            'nadi'          => $notEmpty($pemeriksaan['nadi'] ?? ''),
            'respirasi'     => $notEmpty($pemeriksaan['respirasi'] ?? ''),
            'suhu_tubuh'    => $notEmpty($pemeriksaan['suhu_tubuh'] ?? ''),
            'spo2'          => $notEmpty($pemeriksaan['spo2'] ?? ''),
            'gcs'           => $notEmpty($pemeriksaan['gcs'] ?? ''),
            'kesadaran'     => $notEmpty($pemeriksaan['kesadaran'] ?? ''),
            'berat'         => $notEmpty($pemeriksaan['berat'] ?? ''),
            'tinggi'        => $notEmpty($pemeriksaan['tinggi'] ?? ''),
            'lingkar_perut' => $notEmpty($pemeriksaan['lingkar_perut'] ?? ''),
            // Other resources
            'diagnosa'              => !empty($diagnosa),
            'prosedur'              => !empty($prosedur),
            'penilaian'             => $notEmpty($pemeriksaan['penilaian'] ?? ''),
            'rtl'                   => $notEmpty($pemeriksaan['rtl'] ?? ''),
            'medications'           => !empty($medications),
            'permintaan_lab'        => !empty($permintaan_lab),
            'permintaan_radiologi'  => !empty($permintaan_rad),
            'adime_gizi'            => !empty($adime_gizi),
            'immunization'          => !empty($immunization),
            'allergy'               => !empty($allergy),
            'questionnaire'         => !empty($questionnaire),
        ];
    }

    /**
     * Merge all pemeriksaan rows for a no_rawat, taking the first non-empty value
     * per field (ordered by most recent first). This is needed because one visit
     * may have multiple rows from different staff members filling different fields.
     * Mirrors Admin.php::getMergedPemeriksaan().
     */
    private function getMergedPemeriksaan(string $no_rawat, string $table): array
    {
        $all_rows = $this->db($table)
            ->where('no_rawat', $no_rawat)
            ->desc('tgl_perawatan')
            ->desc('jam_rawat')
            ->toArray();

        if (empty($all_rows)) {
            return [];
        }

        $fields = [
            'keluhan', 'pemeriksaan', 'penilaian', 'rtl', 'tensi', 'nadi',
            'suhu_tubuh', 'respirasi', 'spo2', 'gcs', 'tinggi', 'berat',
            'lingkar_perut', 'kesadaran', 'alergi', 'instruksi', 'evaluasi',
            'tgl_perawatan', 'jam_rawat', 'nip'
        ];

        $merged = [];
        foreach ($fields as $f) {
            $merged[$f] = '';
            foreach ($all_rows as $row) {
                if (isset($row[$f]) && !in_array($row[$f], ['', '-', null], true)) {
                    $merged[$f] = $row[$f];
                    break; // take the most recent non-empty value
                }
            }
        }

        return $merged;
    }

    /**
     * Determine which resources to send for a given no_rawat.
     * @param array $existingResponse  Current mlite_satu_sehat_response row (or empty)
     * @param array $dataAvail         Output of getDataAvailability()
     * @param bool  $force             If true, resend even if already has an ID
     * @return array  [resourceKey => ['action' => 'send'|'skip_exists'|'skip_nodata', ...], ...]
     */
    public function determineResources(?array $existingResponse, array $dataAvail, bool $force = false): array
    {
        $plan = [];

        foreach (self::RESOURCES as $key => $def) {
            $col   = $def['col'];
            $check = $def['check'] ?? null;
            $label = $def['label'];

            // Already sent?
            $alreadySent = !empty($existingResponse[$col]);

            // Data available?
            $hasData = true;
            if ($check !== null) {
                $hasData = !empty($dataAvail[$check]);
            }
            // Encounter & condition always attempt (they check internally)
            if ($key === 'encounter') {
                $hasData = true;
            }
            if ($key === 'condition') {
                $hasData = !empty($dataAvail['diagnosa']);
            }

            if (!$hasData) {
                $plan[$key] = ['action' => 'skip_nodata', 'label' => $label, 'col' => $col];
            } elseif ($alreadySent && !$force) {
                $plan[$key] = ['action' => 'skip_exists', 'label' => $label, 'col' => $col, 'id' => $existingResponse[$col]];
            } else {
                $plan[$key] = ['action' => 'send', 'label' => $label, 'col' => $col];
            }
        }

        return $plan;
    }

    /**
     * Build the URL for a given resource and no_rawat.
     */
    private function buildUrl(string $key, string $noRawatUrl): string
    {
        $def = self::RESOURCES[$key];
        $endpoint = $def['endpoint'];

        // Endpoints with {nr} placeholder use the no_rawat inside the path
        if (strpos($endpoint, '{nr}') !== false) {
            return $this->baseUrl . str_replace('{nr}', $noRawatUrl, $endpoint);
        }

        // Simple endpoints: append no_rawat
        return $this->baseUrl . $endpoint . $noRawatUrl;
    }

    /**
     * Call a single resource endpoint via HTTP and return parsed result.
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null, 'raw' => string]
     */
    public function sendResource(string $key, string $noRawatUrl): array
    {
        $url = $this->buildUrl($key, $noRawatUrl);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'id' => null, 'error' => 'Curl error: ' . $curlError, 'raw' => ''];
        }

        // Try to parse JSON response
        $data = json_decode($response, true);

        // Determine success: response has an 'id' field (FHIR resource created)
        $id = null;
        $success = false;
        $error = null;

        if (is_array($data)) {
            if (!empty($data['id'])) {
                $id = $data['id'];
                $success = true;
            } elseif (!empty($data['resourceID'])) {
                $id = $data['resourceID'];
                $success = true;
            } elseif (isset($data['entry']) && is_array($data['entry'])) {
                // Bundle response — check first entry
                foreach ($data['entry'] as $entry) {
                    if (!empty($entry['response']['resourceID'])) {
                        $id = $entry['response']['resourceID'];
                        $success = true;
                        break;
                    }
                }
            }
            // Check for error indicators
            if (!$success) {
                if (!empty($data['issue'])) {
                    $error = $data['issue'][0]['diagnostics'] ?? json_encode($data['issue'][0] ?? $data);
                } elseif (!empty($data['error'])) {
                    $error = $data['error'];
                } elseif (!empty($data['pesan'])) {
                    // Some methods return a pesan field
                    if (stripos($data['pesan'], 'Gagal') !== false) {
                        $error = $data['pesan'];
                    } else {
                        $success = true; // "Sukses" message without explicit ID
                    }
                } else {
                    $error = 'No resource ID in response';
                }
            }
        } else {
            // Try to extract ID from raw text that might be JSON with backticks
            $cleaned = str_replace('`', '', $response);
            $data2 = json_decode($cleaned, true);
            if (is_array($data2) && !empty($data2['id'])) {
                $id = $data2['id'];
                $success = true;
            } else {
                $error = 'Non-JSON response (HTTP ' . $httpCode . ')';
            }
        }

        return [
            'success'  => $success,
            'id'       => $id,
            'error'    => $error,
            'raw'      => mb_substr($response, 0, 500),
            'httpCode' => $httpCode,
        ];
    }

    /**
     * Process a single no_rawat: check existing, determine what to send, send sequentially.
     *
     * @param string $no_rawat    The raw no_rawat (with slashes)
     * @param string $status_lanjut 'Ralan' or 'Ranap'
     * @param bool   $force       Force resend all
     * @param callable|null $onResource  Optional callback($key, $label, $result) for streaming progress
     * @return array ['no_rawat' => ..., 'status' => 'success'|'partial'|'failed'|'skipped', 'resources' => [...]]
     */
    public function processNoRawat(string $no_rawat, string $status_lanjut, bool $force = false, ?callable $onResource = null): array
    {
        $noRawatUrl = str_replace('/', '', $no_rawat);

        $existing = $this->getExistingResponse($no_rawat);
        $dataAvail = $this->getDataAvailability($no_rawat, $status_lanjut);
        $plan = $this->determineResources($existing, $dataAvail, $force);

        $results = [];
        $sentCount = 0;
        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;

        foreach ($plan as $key => $info) {
            if ($info['action'] !== 'send') {
                $results[$key] = [
                    'label'  => $info['label'],
                    'action' => $info['action'],
                    'id'     => $info['id'] ?? null,
                ];
                $skipCount++;
                if ($onResource) {
                    $onResource($key, $info['label'], $results[$key]);
                }
                continue;
            }

            // Encounter must be sent first — if encounter send failed and this is not encounter, skip
            if ($key !== 'encounter') {
                $encResult = $results['encounter'] ?? null;
                $encHasId = !empty($existing['id_encounter']) || ($encResult && !empty($encResult['id']));
                if (!$encHasId) {
                    $results[$key] = [
                        'label'  => $info['label'],
                        'action' => 'skip_no_encounter',
                        'id'     => null,
                        'error'  => 'Encounter belum berhasil',
                    ];
                    $failCount++;
                    if ($onResource) {
                        $onResource($key, $info['label'], $results[$key]);
                    }
                    continue;
                }
            }

            // Send the resource
            $sentCount++;
            $sendResult = $this->sendResource($key, $noRawatUrl);
            $results[$key] = array_merge(['label' => $info['label'], 'action' => 'sent'], $sendResult);

            if ($sendResult['success']) {
                $successCount++;
            } else {
                $failCount++;
            }

            if ($onResource) {
                $onResource($key, $info['label'], $results[$key]);
            }
        }

        // Determine overall status
        $status = 'skipped';
        if ($sentCount > 0) {
            if ($failCount === 0) {
                $status = 'success';
            } elseif ($successCount > 0) {
                $status = 'partial';
            } else {
                $status = 'failed';
            }
        }

        return [
            'no_rawat'      => $no_rawat,
            'status'        => $status,
            'sent'          => $sentCount,
            'success'       => $successCount,
            'failed'        => $failCount,
            'skipped'       => $skipCount,
            'resources'     => $results,
        ];
    }

    /**
     * Get the resource definitions (for rendering labels, etc.)
     */
    public static function getResourceDefinitions(): array
    {
        return self::RESOURCES;
    }
}
