package db

import (
	"database/sql"
	"fmt"
	"strings"
	"time"
)

// ResponseRow holds the rich joined data for the response DataTable.
type ResponseRow struct {
	TglRegistrasi string `json:"tgl_registrasi"`
	NoRawat       string `json:"no_rawat"`
	NoRkmMedis    string `json:"no_rkm_medis"`
	NmPasien      string `json:"nm_pasien"`
	NoKTPPasien   string `json:"no_ktp_pasien"`
	KdDokter      string `json:"kd_dokter"`
	NmDokter      string `json:"nm_dokter"`
	NoKTPDokter   string `json:"no_ktp_dokter"`
	KdPoli        string `json:"kd_poli"`
	NmPoli        string `json:"nm_poli"`
	Stts          string `json:"stts"`
	StatusLanjut  string `json:"status_lanjut"`
	StatusBayar   string `json:"status_bayar"`
	TglPulang     string `json:"tgl_pulang"`
	PraktisiID    string `json:"praktisi_id"`

	// Lokasi/Org
	IDOrganisasi string `json:"id_organisasi"`
	IDLokasi     string `json:"id_lokasi"`

	// Pemeriksaan (nested)
	Pemeriksaan map[string]string `json:"pemeriksaan"`

	// Diagnosa (nested)
	DiagnosaPasien map[string]string `json:"diagnosa_pasien"`

	// Prosedur (nested)
	ProsedurPasien map[string]string `json:"prosedur_pasien"`

	// Other data availability flags
	AdimeGizi      string `json:"adime_gizi"`
	ClinicalImpr   string `json:"clinical_impression"`
	MedRequest     string `json:"medication_request"`
	MedDispense    string `json:"medication_dispense"`
	MedStatement   string `json:"medication_statement"`
	SvcReqRad      string `json:"service_request_radiologi"`
	SpecimenRad    string `json:"specimen_radiologi"`
	ObservationRad string `json:"observation_radiologi"`
	DiagReportRad  string `json:"diagnostic_report_radiologi"`
	SvcReqLabPK    string `json:"service_request_lab_pk"`
	SpecimenLabPK  string `json:"specimen_lab_pk"`
	ObsLabPK       string `json:"observation_lab_pk"`
	DiagLabPK      string `json:"diagnostic_report_lab_pk"`
	CarePlan       string `json:"care_plan"`
	Allergy        interface{} `json:"allergy"`
	Questionnaire  string `json:"questionnaire"`

	// Response IDs (from mlite_satu_sehat_response)
	IDEncounter           string `json:"id_encounter"`
	IDCondition           string `json:"id_condition"`
	IDClinicalImpression  string `json:"id_clinical_impression"`
	IDObsTensi            string `json:"id_observation_ttvtensi"`
	IDObsNadi             string `json:"id_observation_ttvnadi"`
	IDObsRespirasi        string `json:"id_observation_ttvrespirasi"`
	IDObsSuhu             string `json:"id_observation_ttvsuhu"`
	IDObsSpo2             string `json:"id_observation_ttvspo2"`
	IDObsGCS              string `json:"id_observation_ttvgcs"`
	IDObsTinggi           string `json:"id_observation_ttvtinggi"`
	IDObsBerat            string `json:"id_observation_ttvberat"`
	IDObsPerut            string `json:"id_observation_ttvperut"`
	IDObsKesadaran        string `json:"id_observation_ttvkesadaran"`
	IDProcedure           string `json:"id_procedure"`
	IDComposition         string `json:"id_composition"`
	IDMedRequest          string `json:"id_medication_request"`
	IDMedDispense         string `json:"id_medication_dispense"`
	IDMedStatement        string `json:"id_medication_statement"`
	IDRadRequest          string `json:"id_rad_request"`
	IDRadSpecimen         string `json:"id_rad_specimen"`
	IDRadObservation      string `json:"id_rad_observation"`
	IDRadDiagnostic       string `json:"id_rad_diagnostic"`
	IDLabPKRequest        string `json:"id_lab_pk_request"`
	IDLabPKSpecimen       string `json:"id_lab_pk_specimen"`
	IDLabPKObservation    string `json:"id_lab_pk_observation"`
	IDLabPKDiagnostic     string `json:"id_lab_pk_diagnostic"`
	IDCarePlan            string `json:"id_careplan"`
	IDAllergy             string `json:"id_allergy"`
	IDQuestionnaire       string `json:"id_questionnaire"`

	// Data availability flags for frontend
	HasData map[string]bool `json:"has_data"`
}

// GetResponseDataTable returns response data for DataTable with server-side paging.
func (d *DB) GetResponseDataTable(startDate, endDate, sortCol, sortDir, search string, start, length int) ([]ResponseRow, int, int, error) {
	if startDate == "" {
		startDate = fmt.Sprintf("%s", nowDate())
	}
	if endDate == "" {
		endDate = startDate
	}

	// Whitelist sort columns
	allowedCols := map[string]string{
		"tgl_registrasi": "r.tgl_registrasi",
		"no_rawat":       "r.no_rawat",
		"no_rkm_medis":   "r.no_rkm_medis",
		"nm_pasien":      "p.nm_pasien",
		"nm_dokter":      "d.nm_dokter",
		"nm_poli":        "pol.nm_poli",
		"stts":           "r.stts",
	}
	orderClause := "r.tgl_registrasi DESC, r.no_rawat DESC"
	if col, ok := allowedCols[sortCol]; ok {
		dir := "ASC"
		if strings.ToUpper(sortDir) == "DESC" {
			dir = "DESC"
		}
		orderClause = col + " " + dir
	}

	// Base WHERE
	where := "r.tgl_registrasi >= ? AND r.tgl_registrasi <= ? AND r.stts != 'Batal' AND r.status_lanjut = 'Ralan'"
	args := []interface{}{startDate, endDate}

	// Search filter
	searchWhere := ""
	var searchArgs []interface{}
	if search != "" {
		like := "%" + search + "%"
		searchWhere = " AND (r.no_rawat LIKE ? OR r.no_rkm_medis LIKE ? OR p.nm_pasien LIKE ? OR d.nm_dokter LIKE ?)"
		searchArgs = []interface{}{like, like, like, like}
	}

	joinClause := `LEFT JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
		LEFT JOIN dokter d ON d.kd_dokter = r.kd_dokter
		LEFT JOIN poliklinik pol ON pol.kd_poli = r.kd_poli`

	// Count total (unfiltered)
	var total int
	err := d.QueryRow(`SELECT COUNT(*) FROM reg_periksa r WHERE `+where, args...).Scan(&total)
	if err != nil {
		return nil, 0, 0, err
	}

	// Count filtered
	filtered := total
	if search != "" {
		allArgs := append(args, searchArgs...)
		err = d.QueryRow(`SELECT COUNT(*) FROM reg_periksa r `+joinClause+` WHERE `+where+searchWhere, allArgs...).Scan(&filtered)
		if err != nil {
			return nil, 0, 0, err
		}
	}

	// Main query
	allArgs := append(args, searchArgs...)
	allArgs = append(allArgs, length, start)
	rows, err := d.Query(`SELECT r.no_rawat, DATE_FORMAT(r.tgl_registrasi,'%Y-%m-%d') AS tgl_registrasi, 
		r.no_rkm_medis, r.kd_dokter, r.kd_poli, 
		r.stts, r.status_lanjut, r.status_bayar
		FROM reg_periksa r
		`+joinClause+`
		WHERE `+where+searchWhere+`
		ORDER BY `+orderClause+`
		LIMIT ? OFFSET ?`, allArgs...)
	if err != nil {
		return nil, 0, 0, err
	}
	defer rows.Close()

	var result []ResponseRow
	for rows.Next() {
		var rr ResponseRow
		if err := rows.Scan(&rr.NoRawat, &rr.TglRegistrasi, &rr.NoRkmMedis, &rr.KdDokter,
			&rr.KdPoli, &rr.Stts, &rr.StatusLanjut, &rr.StatusBayar); err != nil {
			return nil, 0, 0, err
		}
		result = append(result, rr)
	}

	// Enrich each row
	for i := range result {
		d.enrichResponseRow(&result[i])
	}

	return result, total, filtered, nil
}

func (d *DB) enrichResponseRow(rr *ResponseRow) {
	// Pasien
	rr.NmPasien = d.getStr("SELECT nm_pasien FROM pasien WHERE no_rkm_medis=?", rr.NoRkmMedis)
	rr.NoKTPPasien = d.getStr("SELECT no_ktp FROM pasien WHERE no_rkm_medis=?", rr.NoRkmMedis)

	// Dokter
	rr.NmDokter = d.getStr("SELECT nm_dokter FROM dokter WHERE kd_dokter=?", rr.KdDokter)
	rr.NoKTPDokter = d.getStr("SELECT no_ktp FROM pegawai WHERE nik=?", rr.KdDokter)

	// Poli
	rr.NmPoli = d.getStr("SELECT nm_poli FROM poliklinik WHERE kd_poli=?", rr.KdPoli)

	// Praktisi mapping
	rr.PraktisiID = d.getStr("SELECT practitioner_id FROM mlite_satu_sehat_mapping_praktisi WHERE kd_dokter=?", rr.KdDokter)

	// Pemeriksaan
	rr.Pemeriksaan = d.getMergedPemeriksaanMap(rr.NoRawat, "pemeriksaan_ralan")

	// Tgl Pulang - from billing or pemeriksaan
	rr.TglPulang = d.getStr("SELECT DATE_FORMAT(tgl_billing,'%Y-%m-%d') FROM mlite_billing WHERE no_rawat=?", rr.NoRawat)
	if rr.TglPulang == "" {
		rr.TglPulang = cleanDateStr(rr.Pemeriksaan["tgl_perawatan"])
	}

	// Lokasi & Organisasi
	rr.IDOrganisasi = d.getStr("SELECT id_organisasi_satusehat FROM mlite_satu_sehat_lokasi WHERE kode=?", rr.KdPoli)
	rr.IDLokasi = d.getStr("SELECT id_lokasi_satusehat FROM mlite_satu_sehat_lokasi WHERE kode=?", rr.KdPoli)

	// Diagnosa primer
	rr.DiagnosaPasien = d.getDiagnosaPrimerMap(rr.NoRawat, rr.StatusLanjut)

	// Prosedur
	rr.ProsedurPasien = d.getProsedurMap(rr.NoRawat, rr.StatusLanjut)

	// Adime Gizi
	rr.AdimeGizi = d.getStr("SELECT no_rawat FROM catatan_adime_gizi WHERE no_rawat=? LIMIT 1", rr.NoRawat)

	// Clinical impression (from pemeriksaan penilaian)
	rr.ClinicalImpr = rr.Pemeriksaan["penilaian"]

	// Medications
	medTgl := d.getMedicationDates(rr.NoRawat)
	rr.MedRequest = medTgl["tgl_peresepan"]
	rr.MedDispense = medTgl["tgl_perawatan"]
	rr.MedStatement = medTgl["tgl_penyerahan"]

	// Radiologi
	radDates := d.getRadDates(rr.NoRawat)
	rr.SvcReqRad = radDates["tgl_permintaan"]
	rr.SpecimenRad = radDates["tgl_sampel"]
	rr.ObservationRad = radDates["tgl_hasil"]
	rr.DiagReportRad = radDates["tgl_hasil"]

	// Lab PK
	labDates := d.getLabDates(rr.NoRawat)
	rr.SvcReqLabPK = labDates["tgl_permintaan"]
	rr.SpecimenLabPK = labDates["tgl_sampel"]
	rr.ObsLabPK = labDates["tgl_hasil"]
	rr.DiagLabPK = labDates["tgl_hasil"]

	// Care Plan
	rr.CarePlan = rr.Pemeriksaan["rtl"]

	// Allergy (simplified)
	allocnt := 0
	d.QueryRow(`SELECT COUNT(*) FROM diagnosa_pasien WHERE no_rawat=? AND status=? 
		AND kd_penyakit IN ('T78.1','T88.7','J30.1','J30.8','T78.4')`, rr.NoRawat, rr.StatusLanjut).Scan(&allocnt)
	if allocnt > 0 {
		rr.Allergy = []string{"allergy_found"}
	} else {
		rr.Allergy = []string{}
	}

	// Questionnaire
	rr.Questionnaire = d.getStr("SELECT no_rawat FROM catatan_perawatan WHERE no_rawat=? AND catatan='KPS' LIMIT 1", rr.NoRawat)

	// Response IDs
	d.fillResponseIDs(rr)

	// Build has_data map
	pem := rr.Pemeriksaan
	rr.HasData = map[string]bool{
		"condition":   len(rr.DiagnosaPasien) > 0 && rr.DiagnosaPasien["kd_penyakit"] != "",
		"procedure":   len(rr.ProsedurPasien) > 0 && rr.ProsedurPasien["kode"] != "",
		"observation_tensi":     pem["tensi"] != "" && pem["tensi"] != "0" && pem["tensi"] != "-",
		"observation_nadi":      pem["nadi"] != "" && pem["nadi"] != "0" && pem["nadi"] != "-",
		"observation_respirasi": pem["respirasi"] != "" && pem["respirasi"] != "0" && pem["respirasi"] != "-",
		"observation_suhu":      pem["suhu_tubuh"] != "" && pem["suhu_tubuh"] != "0" && pem["suhu_tubuh"] != "-",
		"observation_spo2":      pem["spo2"] != "" && pem["spo2"] != "0" && pem["spo2"] != "-",
		"observation_gcs":       pem["gcs"] != "" && pem["gcs"] != "0" && pem["gcs"] != "-",
		"observation_kesadaran": pem["kesadaran"] != "" && pem["kesadaran"] != "0" && pem["kesadaran"] != "-",
		"observation_berat":     pem["berat"] != "" && pem["berat"] != "0" && pem["berat"] != "0.0" && pem["berat"] != "-",
		"observation_tinggi":    pem["tinggi"] != "" && pem["tinggi"] != "0" && pem["tinggi"] != "0.0" && pem["tinggi"] != "-",
		"observation_perut":     pem["lingkar_perut"] != "" && pem["lingkar_perut"] != "0" && pem["lingkar_perut"] != "0.0" && pem["lingkar_perut"] != "-",
		"clinical_impression":   true,
		"careplan":              true,
		"allergy":               allocnt > 0 || (pem["alergi"] != "" && pem["alergi"] != "-"),
		"medication_request":    rr.MedRequest != "",
		"medication_dispense":   rr.MedDispense != "",
		"medication_statement":  rr.MedRequest != "",
		"composition":           rr.AdimeGizi != "",
		"questionnaire":         rr.Questionnaire != "",
		"lab_request":           rr.SvcReqLabPK != "",
		"lab_specimen":          rr.SpecimenLabPK != "",
		"lab_observation":       rr.ObsLabPK != "",
		"lab_diagnostic":        rr.DiagLabPK != "",
		"rad_request":           rr.SvcReqRad != "",
		"rad_specimen":          rr.SpecimenRad != "",
		"rad_observation":       rr.ObservationRad != "",
		"rad_diagnostic":        rr.DiagReportRad != "",
	}
}

func (d *DB) fillResponseIDs(rr *ResponseRow) {
	row := d.QueryRow(`SELECT 
		IFNULL(id_encounter,''), IFNULL(id_condition,''), IFNULL(id_clinical_impression,''),
		IFNULL(id_observation_ttvtensi,''), IFNULL(id_observation_ttvnadi,''), IFNULL(id_observation_ttvrespirasi,''),
		IFNULL(id_observation_ttvsuhu,''), IFNULL(id_observation_ttvspo2,''), IFNULL(id_observation_ttvgcs,''),
		IFNULL(id_observation_ttvtinggi,''), IFNULL(id_observation_ttvberat,''), IFNULL(id_observation_ttvperut,''),
		IFNULL(id_observation_ttvkesadaran,''), IFNULL(id_procedure,''), IFNULL(id_composition,''),
		IFNULL(id_medication_request,''), IFNULL(id_medication_dispense,''), IFNULL(id_medication_statement,''),
		IFNULL(id_rad_request,''), IFNULL(id_rad_specimen,''), IFNULL(id_rad_observation,''), IFNULL(id_rad_diagnostic,''),
		IFNULL(id_lab_pk_request,''), IFNULL(id_lab_pk_specimen,''), IFNULL(id_lab_pk_observation,''), IFNULL(id_lab_pk_diagnostic,''),
		IFNULL(id_careplan,'')
		FROM mlite_satu_sehat_response WHERE no_rawat=?`, rr.NoRawat)

	err := row.Scan(
		&rr.IDEncounter, &rr.IDCondition, &rr.IDClinicalImpression,
		&rr.IDObsTensi, &rr.IDObsNadi, &rr.IDObsRespirasi,
		&rr.IDObsSuhu, &rr.IDObsSpo2, &rr.IDObsGCS,
		&rr.IDObsTinggi, &rr.IDObsBerat, &rr.IDObsPerut,
		&rr.IDObsKesadaran, &rr.IDProcedure, &rr.IDComposition,
		&rr.IDMedRequest, &rr.IDMedDispense, &rr.IDMedStatement,
		&rr.IDRadRequest, &rr.IDRadSpecimen, &rr.IDRadObservation, &rr.IDRadDiagnostic,
		&rr.IDLabPKRequest, &rr.IDLabPKSpecimen, &rr.IDLabPKObservation, &rr.IDLabPKDiagnostic,
		&rr.IDCarePlan,
	)
	if err != nil {
		// no response row, IDs stay empty
		return
	}

	// id_allergy and id_questionnaire might not be columns yet, ignore errors
	var tmp sql.NullString
	if d.QueryRow("SELECT id_allergy FROM mlite_satu_sehat_response WHERE no_rawat=?", rr.NoRawat).Scan(&tmp) == nil && tmp.Valid {
		rr.IDAllergy = tmp.String
	}
	tmp = sql.NullString{}
	if d.QueryRow("SELECT id_questionnaire FROM mlite_satu_sehat_response WHERE no_rawat=?", rr.NoRawat).Scan(&tmp) == nil && tmp.Valid {
		rr.IDQuestionnaire = tmp.String
	}
}

// Helper: get single string value
func (d *DB) getStr(q string, args ...interface{}) string {
	var s sql.NullString
	d.QueryRow(q, args...).Scan(&s)
	if s.Valid {
		return s.String
	}
	return ""
}

func nowDate() string {
	return time.Now().Format("2006-01-02")
}

// cleanDateStr strips time portion from date strings like "2026-03-05T00:00:00Z" → "2026-03-05"
func cleanDateStr(s string) string {
	if idx := strings.Index(s, "T"); idx > 0 {
		return s[:idx]
	}
	return s
}

// GetSettings loads all settings from mlite_settings for the given module.
func (d *DB) GetSettings(module string) (map[string]string, error) {
	result := make(map[string]string)
	rows, err := d.Query("SELECT field, value FROM mlite_settings WHERE module=?", module)
	if err != nil {
		return result, err
	}
	defer rows.Close()
	for rows.Next() {
		var field, value string
		if err := rows.Scan(&field, &value); err != nil {
			continue
		}
		result[field] = value
	}
	return result, nil
}

func (d *DB) getMergedPemeriksaanMap(noRawat, table string) map[string]string {
	if table != "pemeriksaan_ralan" && table != "pemeriksaan_ranap" {
		table = "pemeriksaan_ralan"
	}
	result := map[string]string{}
	fields := []string{"tgl_perawatan", "jam_rawat", "keluhan", "pemeriksaan", "penilaian", "rtl",
		"tensi", "nadi", "suhu_tubuh", "respirasi", "spo2", "gcs",
		"tinggi", "berat", "lingkar_perut", "kesadaran", "alergi", "instruksi", "evaluasi"}

	q := fmt.Sprintf("SELECT %s FROM %s WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC",
		strings.Join(fields, ","), table)
	rows, err := d.Query(q, noRawat)
	if err != nil {
		return result
	}
	defer rows.Close()

	filled := make(map[string]bool)
	for rows.Next() {
		vals := make([]sql.NullString, len(fields))
		dest := make([]interface{}, len(fields))
		for i := range vals {
			dest[i] = &vals[i]
		}
		if err := rows.Scan(dest...); err != nil {
			continue
		}
		for i, f := range fields {
			if !filled[f] && vals[i].Valid && vals[i].String != "" && vals[i].String != "-" {
				result[f] = vals[i].String
				filled[f] = true
			}
		}
	}
	return result
}

func (d *DB) getDiagnosaPrimerMap(noRawat, statusLanjut string) map[string]string {
	result := map[string]string{}
	var kdPenyakit, nmPenyakit sql.NullString
	// Try priority 1 first
	err := d.QueryRow(`SELECT dp.kd_penyakit, p.nm_penyakit 
		FROM diagnosa_pasien dp
		JOIN penyakit p ON p.kd_penyakit=dp.kd_penyakit
		WHERE dp.no_rawat=? AND dp.status=? AND dp.prioritas='1' LIMIT 1`, noRawat, statusLanjut).Scan(&kdPenyakit, &nmPenyakit)
	if err != nil || !kdPenyakit.Valid {
		// Fallback: get any diagnosa
		err = d.QueryRow(`SELECT dp.kd_penyakit, p.nm_penyakit 
			FROM diagnosa_pasien dp
			JOIN penyakit p ON p.kd_penyakit=dp.kd_penyakit
			WHERE dp.no_rawat=? AND dp.status=? ORDER BY dp.prioritas ASC LIMIT 1`, noRawat, statusLanjut).Scan(&kdPenyakit, &nmPenyakit)
	}
	if err == nil && kdPenyakit.Valid {
		result["kd_penyakit"] = kdPenyakit.String
		if nmPenyakit.Valid {
			result["nm_penyakit"] = nmPenyakit.String
		}
	}
	return result
}

func (d *DB) getProsedurMap(noRawat, statusLanjut string) map[string]string {
	result := map[string]string{}
	var kode, desc sql.NullString
	err := d.QueryRow(`SELECT i.kode, i.deskripsi_pendek 
		FROM prosedur_pasien pp
		JOIN icd9 i ON i.kode=pp.kode
		WHERE pp.no_rawat=? AND pp.status=? AND pp.prioritas='1' LIMIT 1`, noRawat, statusLanjut).Scan(&kode, &desc)
	if err == nil && kode.Valid {
		result["kode"] = kode.String
		if desc.Valid {
			result["deskripsi_pendek"] = desc.String
		}
	}
	return result
}

func (d *DB) getMedicationDates(noRawat string) map[string]string {
	result := map[string]string{}
	var tglPeresepan, tglPerawatan, tglPenyerahan sql.NullString
	d.QueryRow(`SELECT ro.tgl_peresepan, ro.tgl_perawatan, ro.tgl_penyerahan 
		FROM resep_obat ro
		JOIN resep_dokter rd ON rd.no_resep=ro.no_resep
		WHERE ro.no_rawat=? LIMIT 1`, noRawat).Scan(&tglPeresepan, &tglPerawatan, &tglPenyerahan)
	if tglPeresepan.Valid {
		result["tgl_peresepan"] = tglPeresepan.String
	}
	if tglPerawatan.Valid {
		result["tgl_perawatan"] = tglPerawatan.String
	}
	if tglPenyerahan.Valid {
		result["tgl_penyerahan"] = tglPenyerahan.String
	}
	return result
}

func (d *DB) getRadDates(noRawat string) map[string]string {
	result := map[string]string{}
	var tglPermintaan, tglSampel, tglHasil sql.NullString
	d.QueryRow(`SELECT tgl_permintaan, tgl_sampel, tgl_hasil FROM permintaan_radiologi WHERE no_rawat=? LIMIT 1`,
		noRawat).Scan(&tglPermintaan, &tglSampel, &tglHasil)
	if tglPermintaan.Valid {
		result["tgl_permintaan"] = tglPermintaan.String
	}
	if tglSampel.Valid {
		result["tgl_sampel"] = tglSampel.String
	}
	if tglHasil.Valid {
		result["tgl_hasil"] = tglHasil.String
	}
	return result
}

func (d *DB) getLabDates(noRawat string) map[string]string {
	result := map[string]string{}
	var tglPermintaan, tglSampel, tglHasil sql.NullString
	d.QueryRow(`SELECT tgl_permintaan, tgl_sampel, tgl_hasil FROM permintaan_lab WHERE no_rawat=? LIMIT 1`,
		noRawat).Scan(&tglPermintaan, &tglSampel, &tglHasil)
	if tglPermintaan.Valid {
		result["tgl_permintaan"] = tglPermintaan.String
	}
	if tglSampel.Valid {
		result["tgl_sampel"] = tglSampel.String
	}
	if tglHasil.Valid {
		result["tgl_hasil"] = tglHasil.String
	}
	return result
}
