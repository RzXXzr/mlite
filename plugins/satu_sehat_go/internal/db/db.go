package db

import (
	"database/sql"
	"fmt"
	"strings"

	_ "github.com/go-sql-driver/mysql"
	"satu-sehat-go/internal/config"
)

// DB wraps the sql.DB connection pool.
type DB struct {
	*sql.DB
}

// New creates a new database connection.
func New(cfg *config.Config) (*DB, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4",
		cfg.DBUser, cfg.DBPass, cfg.DBHost, cfg.DBPort, cfg.DBName)
	conn, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, err
	}
	conn.SetMaxOpenConns(25)
	conn.SetMaxIdleConns(5)
	if err := conn.Ping(); err != nil {
		return nil, err
	}
	return &DB{conn}, nil
}

// === Settings ===

// GetSetting retrieves a setting value from mlite_settings.
func (d *DB) GetSetting(module, field string) (string, error) {
	var val string
	err := d.QueryRow("SELECT `value` FROM mlite_settings WHERE `module`=? AND `field`=?", module, field).Scan(&val)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return val, err
}

// SaveSetting upserts a setting value into mlite_settings.
func (d *DB) SaveSetting(module, field, value string) error {
	_, err := d.Exec(`INSERT INTO mlite_settings (module, field, value) VALUES (?,?,?)
		ON DUPLICATE KEY UPDATE value=?`, module, field, value, value)
	return err
}

// === Registration / Periksa ===

// RegPeriksa holds registration info.
type RegPeriksa struct {
	NoRawat       string
	TglRegistrasi string
	JamReg        string
	KdPoli        string
	KdDokter      string
	NoRkmMedis    string
	StatusLanjut  string
	Stts          string
}

// GetRegPeriksa retrieves the registration row by no_rawat.
func (d *DB) GetRegPeriksa(noRawat string) (*RegPeriksa, error) {
	r := &RegPeriksa{}
	err := d.QueryRow(`SELECT no_rawat, DATE_FORMAT(tgl_registrasi,'%Y-%m-%d') as tgl_registrasi, jam_reg, kd_poli, kd_dokter, no_rkm_medis, status_lanjut, stts
		FROM reg_periksa WHERE no_rawat=?`, noRawat).Scan(
		&r.NoRawat, &r.TglRegistrasi, &r.JamReg, &r.KdPoli, &r.KdDokter, &r.NoRkmMedis, &r.StatusLanjut, &r.Stts)
	if err != nil {
		return nil, err
	}
	return r, nil
}

// GetRegPeriksaByDate retrieves registrations for a date that haven't been fully sent yet.
// Excludes visits that already have a response record with id_encounter set.
func (d *DB) GetRegPeriksaByDate(tanggal string) ([]RegPeriksa, error) {
	rows, err := d.Query(`SELECT rp.no_rawat, DATE_FORMAT(rp.tgl_registrasi,'%Y-%m-%d') as tgl_registrasi, rp.jam_reg, rp.kd_poli, rp.kd_dokter, rp.no_rkm_medis, rp.status_lanjut, rp.stts
		FROM reg_periksa rp
		LEFT JOIN mlite_satu_sehat_response sr ON rp.no_rawat = sr.no_rawat
		WHERE rp.tgl_registrasi=? AND rp.stts!='Batal'
		AND (sr.no_rawat IS NULL OR sr.id_encounter IS NULL OR sr.id_encounter = '')`, tanggal)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []RegPeriksa
	for rows.Next() {
		var r RegPeriksa
		if err := rows.Scan(&r.NoRawat, &r.TglRegistrasi, &r.JamReg, &r.KdPoli, &r.KdDokter, &r.NoRkmMedis, &r.StatusLanjut, &r.Stts); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// === Pasien ===

// Pasien holds patient information.
type Pasien struct {
	NoRkmMedis string
	NmPasien   string
	NoKTP      string
}

// GetPasien retrieves patient information.
func (d *DB) GetPasien(noRkmMedis string) (*Pasien, error) {
	p := &Pasien{}
	err := d.QueryRow("SELECT no_rkm_medis, nm_pasien, no_ktp FROM pasien WHERE no_rkm_medis=?", noRkmMedis).Scan(
		&p.NoRkmMedis, &p.NmPasien, &p.NoKTP)
	if err != nil {
		return nil, err
	}
	return p, nil
}

// === Pegawai / Dokter ===

// GetPegawaiNama retrieves the pegawai name by kd_dokter (nik).
func (d *DB) GetPegawaiNama(kdDokter string) (string, error) {
	var nama string
	err := d.QueryRow("SELECT nama FROM pegawai WHERE nik=?", kdDokter).Scan(&nama)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return nama, err
}

// === Poliklinik ===

// GetPoliklinikNama retrieves poliklinik name by kd_poli.
func (d *DB) GetPoliklinikNama(kdPoli string) (string, error) {
	var name string
	err := d.QueryRow("SELECT nm_poli FROM poliklinik WHERE kd_poli=?", kdPoli).Scan(&name)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return name, err
}

// === Bangsal/Kamar ===

// GetKamarInapKdKamar retrieves kamar code from kamar_inap.
func (d *DB) GetKamarInapKdKamar(noRawat string) (string, error) {
	var kd string
	err := d.QueryRow("SELECT kd_kamar FROM kamar_inap WHERE no_rawat=? LIMIT 1", noRawat).Scan(&kd)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return kd, err
}

// GetKamarBangsal retrieves kd_bangsal from kamar table.
func (d *DB) GetKamarBangsal(kdKamar string) (string, error) {
	var kd string
	err := d.QueryRow("SELECT kd_bangsal FROM kamar WHERE kd_kamar=?", kdKamar).Scan(&kd)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return kd, err
}

// GetBangsalNama retrieves bangsal name.
func (d *DB) GetBangsalNama(kdBangsal string) (string, error) {
	var name string
	err := d.QueryRow("SELECT nm_bangsal FROM bangsal WHERE kd_bangsal=?", kdBangsal).Scan(&name)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return name, err
}

// === Departemen ===

// GetDepartemenNama retrieves departemen name by dep_id.
func (d *DB) GetDepartemenNama(depID string) (string, error) {
	var name string
	err := d.QueryRow("SELECT nama FROM departemen WHERE dep_id=?", depID).Scan(&name)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return name, err
}

// === Satu Sehat Mapping Tables ===

// MappingPraktisi represents a practitioner mapping.
type MappingPraktisi struct {
	KdDokter       string
	PractitionerID string
}

// GetMappingPraktisi gets practitioner IHS ID by kd_dokter.
func (d *DB) GetMappingPraktisi(kdDokter string) (string, error) {
	var id string
	err := d.QueryRow("SELECT practitioner_id FROM mlite_satu_sehat_mapping_praktisi WHERE kd_dokter=?", kdDokter).Scan(&id)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return id, err
}

// SaveMappingPraktisi saves practitioner mapping.
func (d *DB) SaveMappingPraktisi(kdDokter, practitionerID string) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_mapping_praktisi (kd_dokter, practitioner_id) VALUES (?,?)
		ON DUPLICATE KEY UPDATE practitioner_id=?`, kdDokter, practitionerID, practitionerID)
	return err
}

// GetAllMappingPraktisi returns all practitioner mappings.
func (d *DB) GetAllMappingPraktisi() ([]MappingPraktisi, error) {
	rows, err := d.Query("SELECT kd_dokter, practitioner_id FROM mlite_satu_sehat_mapping_praktisi")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []MappingPraktisi
	for rows.Next() {
		var m MappingPraktisi
		if err := rows.Scan(&m.KdDokter, &m.PractitionerID); err != nil {
			return nil, err
		}
		result = append(result, m)
	}
	return result, nil
}

// Lokasi represents a location mapping.
type Lokasi struct {
	Kode             string
	IDLokasiSatuSehat string
}

// GetLokasi retrieves location IHS ID.
func (d *DB) GetLokasi(kode string) (string, error) {
	var id string
	err := d.QueryRow("SELECT id_lokasi_satusehat FROM mlite_satu_sehat_lokasi WHERE kode=?", kode).Scan(&id)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return id, err
}

// SaveLokasi saves a location mapping.
func (d *DB) SaveLokasi(kode, idLokasi string) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_lokasi (kode, id_lokasi_satusehat) VALUES (?,?)
		ON DUPLICATE KEY UPDATE id_lokasi_satusehat=?`, kode, idLokasi, idLokasi)
	return err
}

// GetAllLokasi returns all location mappings.
func (d *DB) GetAllLokasi() ([]Lokasi, error) {
	rows, err := d.Query("SELECT kode, id_lokasi_satusehat FROM mlite_satu_sehat_lokasi")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []Lokasi
	for rows.Next() {
		var l Lokasi
		if err := rows.Scan(&l.Kode, &l.IDLokasiSatuSehat); err != nil {
			return nil, err
		}
		result = append(result, l)
	}
	return result, nil
}

// Departemen mapping
type Departemen struct {
	DepID                 string
	IDOrganisasiSatuSehat string
}

// GetDepartemenMapping retrieves organization IHS ID by dep_id.
func (d *DB) GetDepartemenMapping(depID string) (string, error) {
	var id string
	err := d.QueryRow("SELECT id_organisasi_satusehat FROM mlite_satu_sehat_departemen WHERE dep_id=?", depID).Scan(&id)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return id, err
}

// SaveDepartemen saves departemen mapping.
func (d *DB) SaveDepartemen(depID, idOrg string) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_departemen (dep_id, id_organisasi_satusehat) VALUES (?,?)
		ON DUPLICATE KEY UPDATE id_organisasi_satusehat=?`, depID, idOrg, idOrg)
	return err
}

// === Response ===

// SatuSehatResponse holds encounter response data.
type SatuSehatResponse struct {
	NoRawat          string
	IDEncounter      string
	IDCondition      sql.NullString
	IDMedRequest     sql.NullString
	IDMedDispense    sql.NullString
	IDMedStatement   sql.NullString
}

// GetResponse retrieves encounter response.
func (d *DB) GetResponse(noRawat string) (*SatuSehatResponse, error) {
	r := &SatuSehatResponse{}
	err := d.QueryRow(`SELECT no_rawat, id_encounter, id_condition,
		IFNULL(id_medication_request,''), IFNULL(id_medication_dispense,''), IFNULL(id_medication_statement,'')
		FROM mlite_satu_sehat_response WHERE no_rawat=?`, noRawat).Scan(
		&r.NoRawat, &r.IDEncounter, &r.IDCondition,
		&r.IDMedRequest, &r.IDMedDispense, &r.IDMedStatement)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return r, nil
}

// SaveResponse saves encounter response.
func (d *DB) SaveResponse(noRawat, idEncounter string) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_response (no_rawat, id_encounter) VALUES (?,?)
		ON DUPLICATE KEY UPDATE id_encounter=?`, noRawat, idEncounter, idEncounter)
	return err
}

// UpdateResponseCondition updates the condition ID(s) on a response row.
func (d *DB) UpdateResponseCondition(noRawat, idCondition string) error {
	_, err := d.Exec("UPDATE mlite_satu_sehat_response SET id_condition=? WHERE no_rawat=?", idCondition, noRawat)
	return err
}

// UpdateResponseField updates a single field in the response row by column name.
// Only allows known column names to prevent SQL injection.
func (d *DB) UpdateResponseField(noRawat, column, value string) error {
	allowed := map[string]bool{
		"id_encounter":               true,
		"id_condition":               true,
		"id_observation_ttvtensi":    true,
		"id_observation_ttvnadi":     true,
		"id_observation_ttvrespirasi": true,
		"id_observation_ttvsuhu":     true,
		"id_observation_ttvspo2":     true,
		"id_observation_ttvgcs":      true,
		"id_observation_ttvtinggi":   true,
		"id_observation_ttvberat":    true,
		"id_observation_ttvperut":    true,
		"id_observation_ttvkesadaran": true,
		"id_procedure":               true,
		"id_composition":             true,
		"id_medication_request":      true,
		"id_medication_dispense":     true,
		"id_medication_statement":    true,
		"id_clinical_impression":     true,
		"id_careplan":                true,
		"id_allergy":                 true,
		"id_questionnaire":           true,
		"id_lab_pk_request":          true,
		"id_lab_pk_specimen":         true,
		"id_lab_pk_observation":      true,
		"id_lab_pk_diagnostic":       true,
		"id_rad_request":             true,
		"id_rad_specimen":            true,
		"id_rad_observation":         true,
		"id_rad_diagnostic":          true,
	}
	if !allowed[column] {
		return fmt.Errorf("kolom tidak diizinkan: %s", column)
	}
	_, err := d.Exec("UPDATE mlite_satu_sehat_response SET "+column+"=? WHERE no_rawat=?", value, noRawat)
	return err
}

// GetAllResponses returns all responses with optional limit.
func (d *DB) GetAllResponses(limit int) ([]SatuSehatResponse, error) {
	q := "SELECT no_rawat, id_encounter, id_condition FROM mlite_satu_sehat_response ORDER BY no_rawat DESC"
	if limit > 0 {
		q += fmt.Sprintf(" LIMIT %d", limit)
	}
	rows, err := d.Query(q)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []SatuSehatResponse
	for rows.Next() {
		var r SatuSehatResponse
		if err := rows.Scan(&r.NoRawat, &r.IDEncounter, &r.IDCondition); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// === Diagnosa ===

// Diagnosa is a patient diagnosis.
type Diagnosa struct {
	KdPenyakit    string
	NmPenyakit    string
	Prioritas     string
	StatusLanjut  string
}

// GetDiagnosaList retrieves all diagnoses for a registration.
func (d *DB) GetDiagnosaList(noRawat, statusLanjut string) ([]Diagnosa, error) {
	rows, err := d.Query(`SELECT diagnosa_pasien.kd_penyakit, penyakit.nm_penyakit, diagnosa_pasien.prioritas, diagnosa_pasien.status
		FROM diagnosa_pasien
		JOIN penyakit ON penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit
		WHERE diagnosa_pasien.no_rawat=? AND diagnosa_pasien.status=?
		ORDER BY diagnosa_pasien.prioritas ASC`, noRawat, statusLanjut)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []Diagnosa
	for rows.Next() {
		var diag Diagnosa
		if err := rows.Scan(&diag.KdPenyakit, &diag.NmPenyakit, &diag.Prioritas, &diag.StatusLanjut); err != nil {
			return nil, err
		}
		result = append(result, diag)
	}
	return result, nil
}

// AutoPromoteDiagnosa ensures at least one diagnosa has priority 1.
// If none has priority 1, promotes the first one (lowest priority number).
// Returns true if a promotion was done.
func (d *DB) AutoPromoteDiagnosa(noRawat, statusLanjut string) bool {
	var cnt int
	d.QueryRow(`SELECT COUNT(*) FROM diagnosa_pasien WHERE no_rawat=? AND status=? AND prioritas='1'`,
		noRawat, statusLanjut).Scan(&cnt)
	if cnt > 0 {
		return false // already has priority 1
	}
	// Get the first diagnosa by current priority
	var kdPenyakit string
	err := d.QueryRow(`SELECT kd_penyakit FROM diagnosa_pasien WHERE no_rawat=? AND status=? ORDER BY prioritas ASC LIMIT 1`,
		noRawat, statusLanjut).Scan(&kdPenyakit)
	if err != nil || kdPenyakit == "" {
		return false
	}
	// Promote to priority 1
	_, err = d.Exec(`UPDATE diagnosa_pasien SET prioritas='1' WHERE no_rawat=? AND status=? AND kd_penyakit=?`,
		noRawat, statusLanjut, kdPenyakit)
	return err == nil
}

// === Pemeriksaan ===

// Pemeriksaan holds examination data.
type Pemeriksaan struct {
	TglPerawatan string
	JamRawat     string
	Keluhan      string
	Pemeriksaan  string
	Penilaian    string
	Rtl          string
	Tensi        string
	Nadi         string
	SuhuTubuh    string
	Respirasi    string
	Spo2         string
	GCS          string
	Tinggi       string
	Berat        string
	LingkarPerut string
	Kesadaran    string
	Alergi       string
	Instruksi    string
	Evaluasi     string
}

// GetMergedPemeriksaan retrieves the latest non-empty examination data (merged across multiple rows).
func (d *DB) GetMergedPemeriksaan(noRawat, table string) (*Pemeriksaan, error) {
	if table != "pemeriksaan_ralan" && table != "pemeriksaan_ranap" {
		table = "pemeriksaan_ralan"
	}
	q := fmt.Sprintf(`SELECT DATE_FORMAT(tgl_perawatan,'%%Y-%%m-%%d') as tgl_perawatan, jam_rawat, keluhan, pemeriksaan, penilaian, rtl, tensi, nadi,
		suhu_tubuh, respirasi, spo2, gcs, tinggi, berat, lingkar_perut, kesadaran, alergi, instruksi, evaluasi
		FROM %s WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC`, table)
	rows, err := d.Query(q, noRawat)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	merged := &Pemeriksaan{}
	fields := []*string{
		&merged.TglPerawatan, &merged.JamRawat, &merged.Keluhan, &merged.Pemeriksaan,
		&merged.Penilaian, &merged.Rtl, &merged.Tensi, &merged.Nadi,
		&merged.SuhuTubuh, &merged.Respirasi, &merged.Spo2, &merged.GCS,
		&merged.Tinggi, &merged.Berat, &merged.LingkarPerut, &merged.Kesadaran,
		&merged.Alergi, &merged.Instruksi, &merged.Evaluasi,
	}
	// Track which fields we've filled
	filled := make([]bool, len(fields))

	for rows.Next() {
		var vals [19]sql.NullString
		scanDest := make([]interface{}, 19)
		for i := range vals {
			scanDest[i] = &vals[i]
		}
		if err := rows.Scan(scanDest...); err != nil {
			return nil, err
		}
		for i, v := range vals {
			if !filled[i] && v.Valid && v.String != "" && v.String != "-" {
				*fields[i] = v.String
				filled[i] = true
			}
		}
	}
	return merged, nil
}

// === Prosedur ===

// Prosedur holds procedure data.
type Prosedur struct {
	Kode             string
	DeskripsiPendek  string
}

// GetProsedurPasien retrieves patient procedures.
func (d *DB) GetProsedurPasien(noRawat, status string) ([]Prosedur, error) {
	rows, err := d.Query(`SELECT icd9.kode, icd9.deskripsi_pendek
		FROM prosedur_pasien
		JOIN icd9 ON icd9.kode=prosedur_pasien.kode
		WHERE prosedur_pasien.no_rawat=? AND prosedur_pasien.status=?`, noRawat, status)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []Prosedur
	for rows.Next() {
		var p Prosedur
		if err := rows.Scan(&p.Kode, &p.DeskripsiPendek); err != nil {
			return nil, err
		}
		result = append(result, p)
	}
	return result, nil
}

// === Resep Obat (Medications for a no_rawat) ===

// ResepObatItem holds medication data for a patient visit.
type ResepObatItem struct {
	NoResep        string
	KodeBrng       string
	KodeKfa        string
	NamaKfa        string
	KodeSediaan    string
	NamaSediaan    string
	KodeBahan      string
	NamaBahan      string
	Numerator      string
	SatuanNum      string
	Denominator    string
	SatuanDen      string
	NamaSatuanDen  string
	KodeRoute      string
	NamaRoute      string
	IDMedication   string
	Jml            float64
	AturanPakai    string
	TglPeresepan   string
	JamPeresepan   string
	TglPerawatan   string
	Jam            string
	TglPenyerahan  string
	JamPenyerahan  string
}

// GetResepObat retrieves medication prescriptions for a no_rawat.
func (d *DB) GetResepObat(noRawat string) ([]ResepObatItem, error) {
	rows, err := d.Query(`
		SELECT rd.no_resep, rd.kode_brng,
			IFNULL(m.kode_kfa,''), IFNULL(m.nama_kfa,''),
			IFNULL(m.kode_sediaan,''), IFNULL(m.nama_sediaan,''),
			IFNULL(m.kode_bahan,''), IFNULL(m.nama_bahan,''),
			IFNULL(m.numerator,''), IFNULL(m.satuan_num,''),
			IFNULL(m.denominator,''), IFNULL(m.satuan_den,''), IFNULL(m.nama_satuan_den,''),
			IFNULL(m.kode_route,''), IFNULL(m.nama_route,''),
			IFNULL(m.id_medication,''),
			IFNULL(rd.jml,0), IFNULL(rd.aturan_pakai,''),
			DATE_FORMAT(ro.tgl_peresepan,'%Y-%m-%d'), TIME_FORMAT(ro.jam_peresepan,'%H:%i:%s'),
			DATE_FORMAT(ro.tgl_perawatan,'%Y-%m-%d'), TIME_FORMAT(ro.jam,'%H:%i:%s'),
			DATE_FORMAT(ro.tgl_penyerahan,'%Y-%m-%d'), TIME_FORMAT(ro.jam_penyerahan,'%H:%i:%s')
		FROM resep_obat ro
		JOIN resep_dokter rd ON rd.no_resep = ro.no_resep AND rd.kode_brng IS NOT NULL
		JOIN mlite_satu_sehat_mapping_obat m ON m.kode_brng = rd.kode_brng
		WHERE ro.no_rawat=? AND m.type='obat'`, noRawat)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []ResepObatItem
	for rows.Next() {
		var r ResepObatItem
		if err := rows.Scan(
			&r.NoResep, &r.KodeBrng,
			&r.KodeKfa, &r.NamaKfa,
			&r.KodeSediaan, &r.NamaSediaan,
			&r.KodeBahan, &r.NamaBahan,
			&r.Numerator, &r.SatuanNum,
			&r.Denominator, &r.SatuanDen, &r.NamaSatuanDen,
			&r.KodeRoute, &r.NamaRoute,
			&r.IDMedication,
			&r.Jml, &r.AturanPakai,
			&r.TglPeresepan, &r.JamPeresepan,
			&r.TglPerawatan, &r.Jam,
			&r.TglPenyerahan, &r.JamPenyerahan,
		); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// UpdateMappingObatMedicationID updates the id_medication in the mapping table.
func (d *DB) UpdateMappingObatMedicationID(kodeBrng, idMedication string) error {
	_, err := d.Exec(`UPDATE mlite_satu_sehat_mapping_obat SET id_medication=?, updated_after_push=0 WHERE kode_brng=?`, idMedication, kodeBrng)
	return err
}

// === Obat Mapping ===

// MappingObat represents medication mapping.
type MappingObat struct {
	KodeObat     string
	NamaObat     string
	KfaCoding    string
	KfaDisplay   string
	FormCoding   string
	FormDisplay  string
	IDMedication string
}

// GetMappingObat retrieves a medication mapping by kode_brng.
func (d *DB) GetMappingObat(kodeObat string) (*MappingObat, error) {
	m := &MappingObat{}
	err := d.QueryRow(`SELECT kode_brng, IFNULL(nama_kfa,''), IFNULL(kode_kfa,''), IFNULL(nama_kfa,''), IFNULL(kode_sediaan,''), IFNULL(nama_sediaan,''), IFNULL(id_medication,'')
		FROM mlite_satu_sehat_mapping_obat WHERE kode_brng=?`, kodeObat).Scan(
		&m.KodeObat, &m.NamaObat, &m.KfaCoding, &m.KfaDisplay, &m.FormCoding, &m.FormDisplay, &m.IDMedication)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return m, nil
}

// GetAllMappingObat returns all medication mappings.
func (d *DB) GetAllMappingObat() ([]MappingObat, error) {
	rows, err := d.Query("SELECT kode_brng, IFNULL(nama_kfa,''), IFNULL(kode_kfa,''), IFNULL(nama_kfa,''), IFNULL(kode_sediaan,''), IFNULL(nama_sediaan,'') FROM mlite_satu_sehat_mapping_obat")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []MappingObat
	for rows.Next() {
		var m MappingObat
		if err := rows.Scan(&m.KodeObat, &m.NamaObat, &m.KfaCoding, &m.KfaDisplay, &m.FormCoding, &m.FormDisplay); err != nil {
			return nil, err
		}
		result = append(result, m)
	}
	return result, nil
}

// SaveMappingObat saves a medication mapping.
func (d *DB) SaveMappingObat(m *MappingObat) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_mapping_obat (kode_brng, kode_kfa, nama_kfa, kode_sediaan, nama_sediaan) VALUES (?,?,?,?,?)
		ON DUPLICATE KEY UPDATE kode_kfa=?, nama_kfa=?, kode_sediaan=?, nama_sediaan=?`,
		m.KodeObat, m.KfaCoding, m.KfaDisplay, m.FormCoding, m.FormDisplay,
		m.KfaCoding, m.KfaDisplay, m.FormCoding, m.FormDisplay)
	return err
}

// DeleteMappingObat deletes a medication mapping.
func (d *DB) DeleteMappingObat(kodeObat string) error {
	_, err := d.Exec("DELETE FROM mlite_satu_sehat_mapping_obat WHERE kode_brng=?", kodeObat)
	return err
}

// === Lab Mapping ===

// MappingLab represents a laboratory mapping.
type MappingLab struct {
	KdJenisPrw string
	CodeLoinc  string
	Display    string
}

// GetMappingLab retrieves a lab mapping.
func (d *DB) GetMappingLab(kdJenisPrw string) (*MappingLab, error) {
	m := &MappingLab{}
	err := d.QueryRow("SELECT kd_jenis_prw, code, display FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw=?", kdJenisPrw).Scan(
		&m.KdJenisPrw, &m.CodeLoinc, &m.Display)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return m, nil
}

// GetAllMappingLab returns all lab mappings.
func (d *DB) GetAllMappingLab() ([]MappingLab, error) {
	rows, err := d.Query("SELECT kd_jenis_prw, code, display FROM mlite_satu_sehat_mapping_lab")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []MappingLab
	for rows.Next() {
		var m MappingLab
		if err := rows.Scan(&m.KdJenisPrw, &m.CodeLoinc, &m.Display); err != nil {
			return nil, err
		}
		result = append(result, m)
	}
	return result, nil
}

// SaveMappingLab saves a lab mapping.
func (d *DB) SaveMappingLab(m *MappingLab) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_mapping_lab (kd_jenis_prw, code, display) VALUES (?,?,?)
		ON DUPLICATE KEY UPDATE code=?, display=?`,
		m.KdJenisPrw, m.CodeLoinc, m.Display, m.CodeLoinc, m.Display)
	return err
}

// === Rad Mapping ===

// MappingRad represents a radiology mapping.
type MappingRad struct {
	KdJenisPrw string
	CodeLoinc  string
	Display    string
}

// GetAllMappingRad returns all radiology mappings.
func (d *DB) GetAllMappingRad() ([]MappingRad, error) {
	rows, err := d.Query("SELECT kd_jenis_prw, code, display FROM mlite_satu_sehat_mapping_rad")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []MappingRad
	for rows.Next() {
		var m MappingRad
		if err := rows.Scan(&m.KdJenisPrw, &m.CodeLoinc, &m.Display); err != nil {
			return nil, err
		}
		result = append(result, m)
	}
	return result, nil
}

// SaveMappingRad saves a radiology mapping.
func (d *DB) SaveMappingRad(m *MappingRad) error {
	_, err := d.Exec(`INSERT INTO mlite_satu_sehat_mapping_rad (kd_jenis_prw, code, display) VALUES (?,?,?)
		ON DUPLICATE KEY UPDATE code=?, display=?`,
		m.KdJenisPrw, m.CodeLoinc, m.Display, m.CodeLoinc, m.Display)
	return err
}

// === Billing ===

// GetBilling retrieves billing info by no_rawat.
func (d *DB) GetBilling(noRawat string) (map[string]string, error) {
	rows, err := d.Query("SELECT * FROM mlite_billing WHERE no_rawat=? LIMIT 1", noRawat)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	cols, _ := rows.Columns()
	if !rows.Next() {
		return nil, nil
	}
	vals := make([]sql.NullString, len(cols))
	scanDest := make([]interface{}, len(cols))
	for i := range vals {
		scanDest[i] = &vals[i]
	}
	if err := rows.Scan(scanDest...); err != nil {
		return nil, err
	}
	result := make(map[string]string)
	for i, col := range cols {
		if vals[i].Valid {
			result[col] = vals[i].String
		}
	}
	return result, nil
}

// RevertNoRawat converts a URL-safe no_rawat back to the original format with slashes.
// The JS frontend replaces "/" with "--" to make it URL-safe, e.g. "2026/03/05/000003" => "2026--03--05--000003"
func RevertNoRawat(noRawat string) string {
	// Handle double-dash format from JS: "2026--03--05--000003" => "2026/03/05/000003"
	if strings.Contains(noRawat, "--") {
		return strings.ReplaceAll(noRawat, "--", "/")
	}
	if strings.Contains(noRawat, "/") {
		return noRawat // already has slashes
	}
	// Fallback: compact format without slashes "20260305000003" => "2026/03/05/000003"
	if len(noRawat) >= 13 {
		return noRawat[:4] + "/" + noRawat[4:6] + "/" + noRawat[6:8] + "/" + noRawat[8:]
	}
	return noRawat
}
