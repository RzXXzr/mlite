package db

import (
	"database/sql"
	"fmt"
	"strings"

	_ "github.com/go-sql-driver/mysql"
	"pcare-go/internal/config"
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

// GetSettings retrieves all settings for a module from mlite_settings.
func (d *DB) GetSettings(module string) (map[string]string, error) {
	rows, err := d.Query("SELECT `field`, `value` FROM mlite_settings WHERE `module`=?", module)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	result := make(map[string]string)
	for rows.Next() {
		var field, value string
		if err := rows.Scan(&field, &value); err != nil {
			return nil, err
		}
		result[field] = value
	}
	return result, nil
}

// SaveSetting upserts a setting value into mlite_settings.
func (d *DB) SaveSetting(module, field, value string) error {
	_, err := d.Exec(`INSERT INTO mlite_settings (module, field, value) VALUES (?,?,?)
		ON DUPLICATE KEY UPDATE value=?`, module, field, value, value)
	return err
}

// SaveSettings saves multiple settings for a module.
func (d *DB) SaveSettings(module string, settings map[string]string) error {
	for field, value := range settings {
		if err := d.SaveSetting(module, field, value); err != nil {
			return err
		}
	}
	return nil
}

// === Bridging PCare ===

// BridgingPCare holds bridging data.
type BridgingPCare struct {
	ID              int64
	NoRawat         string
	NoRkmMedis      string
	NomorJaminan    string
	NomorUrut       string
	NomorKunjungan  string
	KodePoli        string
	KodeDokter      string
	KodeKesadaran   string
	KodeStatusPulang string
	Sistole         string
	Diastole        string
	BeratBadan      string
	TinggiBadan     string
	Nadi            string
	Respirasi       string
	LingkarPerut    string
	Subyektif       string
	Suhu            string
	StatusKirim     string
	TglInput        string
}

// GetBridgingByNoRawat retrieves bridging data by no_rawat.
func (d *DB) GetBridgingByNoRawat(noRawat string) (*BridgingPCare, error) {
	b := &BridgingPCare{}
	err := d.QueryRow(`SELECT id, no_rawat, no_rkm_medis, IFNULL(nomor_jaminan,''), IFNULL(nomor_urut,''),
		IFNULL(nomor_kunjungan,''), IFNULL(kode_poli,''), IFNULL(kode_dokter,''),
		IFNULL(kode_kesadaran,''), IFNULL(kode_status_pulang,''),
		IFNULL(sistole,'0'), IFNULL(diastole,'0'), IFNULL(berat,'0'), IFNULL(tinggi,'0'),
		IFNULL(nadi,'0'), IFNULL(respirasi,'0'), IFNULL(lingkar_perut,'0'),
		IFNULL(subyektif,''), IFNULL(status_kirim,'Belum'), IFNULL(tgl_input,'')
		FROM mlite_bridging_pcare WHERE no_rawat=?`, noRawat).Scan(
		&b.ID, &b.NoRawat, &b.NoRkmMedis, &b.NomorJaminan, &b.NomorUrut,
		&b.NomorKunjungan, &b.KodePoli, &b.KodeDokter,
		&b.KodeKesadaran, &b.KodeStatusPulang,
		&b.Sistole, &b.Diastole, &b.BeratBadan, &b.TinggiBadan,
		&b.Nadi, &b.Respirasi, &b.LingkarPerut,
		&b.Subyektif, &b.StatusKirim, &b.TglInput)
	if err != nil {
		return nil, err
	}
	return b, nil
}

// SaveBridging inserts or updates bridging data.
func (d *DB) SaveBridging(b *BridgingPCare) error {
	if b.ID > 0 {
		_, err := d.Exec(`UPDATE mlite_bridging_pcare SET
			nomor_jaminan=?, nomor_urut=?, nomor_kunjungan=?, kode_poli=?, kode_dokter=?,
			kode_kesadaran=?, kode_status_pulang=?, sistole=?, diastole=?, berat=?, tinggi=?,
			nadi=?, respirasi=?, lingkar_perut=?, subyektif=?, status_kirim=?, tgl_input=NOW()
			WHERE id=?`,
			b.NomorJaminan, b.NomorUrut, b.NomorKunjungan, b.KodePoli, b.KodeDokter,
			b.KodeKesadaran, b.KodeStatusPulang, b.Sistole, b.Diastole, b.BeratBadan, b.TinggiBadan,
			b.Nadi, b.Respirasi, b.LingkarPerut, b.Subyektif, b.StatusKirim,
			b.ID)
		return err
	}
	result, err := d.Exec(`INSERT INTO mlite_bridging_pcare
		(no_rawat, no_rkm_medis, nomor_jaminan, nomor_urut, nomor_kunjungan, kode_poli, kode_dokter,
		kode_kesadaran, kode_status_pulang, sistole, diastole, berat, tinggi,
		nadi, respirasi, lingkar_perut, subyektif, status_kirim, tgl_input)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())`,
		b.NoRawat, b.NoRkmMedis, b.NomorJaminan, b.NomorUrut, b.NomorKunjungan, b.KodePoli, b.KodeDokter,
		b.KodeKesadaran, b.KodeStatusPulang, b.Sistole, b.Diastole, b.BeratBadan, b.TinggiBadan,
		b.Nadi, b.Respirasi, b.LingkarPerut, b.Subyektif, b.StatusKirim)
	if err != nil {
		return err
	}
	b.ID, _ = result.LastInsertId()
	return nil
}

// UpdateBridgingKunjungan updates nomor_kunjungan and status_kirim after successful send.
func (d *DB) UpdateBridgingKunjungan(id int64, nomorKunjungan, statusKirim string) error {
	_, err := d.Exec(`UPDATE mlite_bridging_pcare SET nomor_kunjungan=?, status_kirim=?, tgl_input=NOW() WHERE id=?`,
		nomorKunjungan, statusKirim, id)
	return err
}

// SyncBridgingFromPCare updates bridging record with full kunjungan data from PCare.
func (d *DB) SyncBridgingFromPCare(id int64, data map[string]string) error {
	_, err := d.Exec(`UPDATE mlite_bridging_pcare SET
		nomor_kunjungan=?, kode_kesadaran=?, nama_kesadaran=?,
		kode_status_pulang=?, nama_status_pulang=?,
		sistole=?, diastole=?, berat=?, tinggi=?,
		nadi=?, respirasi=?, lingkar_perut=?,
		subyektif=?, kode_dokter=?, nama_dokter=?,
		kode_diagnosa1=?, nama_diagnosa1=?,
		kode_diagnosa2=?, nama_diagnosa2=?,
		kode_diagnosa3=?, nama_diagnosa3=?,
		tgl_kunjungan=?, tgl_pulang=?,
		status_kirim=?, tgl_input=NOW()
		WHERE id=?`,
		data["nomor_kunjungan"], data["kode_kesadaran"], data["nama_kesadaran"],
		data["kode_status_pulang"], data["nama_status_pulang"],
		data["sistole"], data["diastole"], data["berat"], data["tinggi"],
		data["nadi"], data["respirasi"], data["lingkar_perut"],
		data["subyektif"], data["kode_dokter"], data["nama_dokter"],
		data["kode_diagnosa1"], data["nama_diagnosa1"],
		data["kode_diagnosa2"], data["nama_diagnosa2"],
		data["kode_diagnosa3"], data["nama_diagnosa3"],
		data["tgl_kunjungan"], data["tgl_pulang"],
		data["status_kirim"], id)
	return err
}

// === Dashboard Queries ===

// DashboardRow holds a row for dashboard display.
type DashboardRow struct {
	NoRawat        string `json:"no_rawat"`
	NoRkmMedis     string `json:"no_rkm_medis"`
	TglRegistrasi  string `json:"tgl_registrasi"`
	JamReg         string `json:"jam_reg"`
	KdDokter       string `json:"kd_dokter"`
	KdPoli         string `json:"kd_poli"`
	Stts           string `json:"stts"`
	NoReg          string `json:"no_reg"`
	NmPasien       string `json:"nm_pasien"`
	NoPeserta      string `json:"no_peserta"`
	NmPoli         string `json:"nm_poli"`
	NmDokter       string `json:"nm_dokter"`
	PngJawab       string `json:"png_jawab"`
	BridgingID     sql.NullInt64  `json:"-"`
	BridgingIDVal  int64  `json:"bridging_id"`
	NomorUrut      sql.NullString `json:"-"`
	NomorUrutVal   string `json:"nomor_urut"`
	NomorKunjungan sql.NullString `json:"-"`
	NomorKunjunganVal string `json:"nomor_kunjungan"`
	NomorJaminan   sql.NullString `json:"-"`
	NomorJaminanVal string `json:"nomor_jaminan"`
	StatusKirim    sql.NullString `json:"-"`
	StatusKirimVal string `json:"status_kirim"`
	ApotekServed      bool   `json:"apotek_served"`
	TglPenyerahanObat sql.NullString `json:"-"`
	TglPenyerahanObatVal string `json:"tgl_penyerahan_obat"`
}

func (r *DashboardRow) populateVals() {
	if r.BridgingID.Valid { r.BridgingIDVal = r.BridgingID.Int64 }
	if r.NomorUrut.Valid { r.NomorUrutVal = r.NomorUrut.String }
	if r.NomorKunjungan.Valid { r.NomorKunjunganVal = r.NomorKunjungan.String }
	if r.NomorJaminan.Valid { r.NomorJaminanVal = r.NomorJaminan.String }
	if r.StatusKirim.Valid { r.StatusKirimVal = r.StatusKirim.String }
	if r.TglPenyerahanObat.Valid && r.TglPenyerahanObat.String != "" {
		r.TglPenyerahanObatVal = r.TglPenyerahanObat.String
		r.ApotekServed = true
	}
}

// GetDashboardRows retrieves dashboard data for a given date or date range. Uses parameterized queries.
// If dateTo is empty, queries for a single date. If dateTo is provided, uses BETWEEN.
func (d *DB) GetDashboardRows(date string, kdPjBpjs string, dateTo ...string) ([]DashboardRow, error) {
	query := `SELECT
		reg_periksa.no_rawat, reg_periksa.no_rkm_medis,
		DATE_FORMAT(reg_periksa.tgl_registrasi,'%d-%m-%Y') as tgl_registrasi,
		reg_periksa.jam_reg, reg_periksa.kd_dokter, reg_periksa.kd_poli,
		reg_periksa.stts, reg_periksa.no_reg,
		pasien.nm_pasien, IFNULL(pasien.no_peserta,'') as no_peserta,
		poliklinik.nm_poli, IFNULL(dokter.nm_dokter,'') as nm_dokter,
		penjab.png_jawab,
		bp.id AS bridging_id, bp.nomor_urut, bp.nomor_kunjungan,
		bp.nomor_jaminan, bp.status_kirim,
		(SELECT DATE_FORMAT(MIN(ro.tgl_penyerahan),'%d-%m-%Y %H:%i')
		 FROM resep_obat ro WHERE ro.no_rawat = reg_periksa.no_rawat
		 AND ro.tgl_penyerahan > '0000-00-00') as tgl_penyerahan_obat
		FROM reg_periksa
		INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
		INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
		INNER JOIN penjab ON penjab.kd_pj = reg_periksa.kd_pj
		LEFT JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
		LEFT JOIN mlite_bridging_pcare bp ON bp.no_rawat = reg_periksa.no_rawat`

	var args []interface{}
	if len(dateTo) > 0 && dateTo[0] != "" {
		query += " WHERE reg_periksa.tgl_registrasi BETWEEN ? AND ?"
		args = append(args, date, dateTo[0])
	} else {
		query += " WHERE reg_periksa.tgl_registrasi = ?"
		args = append(args, date)
	}

	if kdPjBpjs != "" {
		query += " AND reg_periksa.kd_pj = ?"
		args = append(args, kdPjBpjs)
	} else {
		query += " AND (penjab.png_jawab LIKE '%BPJS%' OR penjab.png_jawab LIKE '%JKN%')"
	}
	query += " ORDER BY reg_periksa.tgl_registrasi ASC, reg_periksa.no_reg ASC"

	rows, err := d.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var result []DashboardRow
	for rows.Next() {
		var r DashboardRow
		if err := rows.Scan(
			&r.NoRawat, &r.NoRkmMedis, &r.TglRegistrasi, &r.JamReg,
			&r.KdDokter, &r.KdPoli, &r.Stts, &r.NoReg,
			&r.NmPasien, &r.NoPeserta, &r.NmPoli, &r.NmDokter, &r.PngJawab,
			&r.BridgingID, &r.NomorUrut, &r.NomorKunjungan,
			&r.NomorJaminan, &r.StatusKirim, &r.TglPenyerahanObat,
		); err != nil {
			return nil, err
		}
		r.populateVals()
		result = append(result, r)
	}
	return result, nil
}

// GetKdPjBpjs retrieves the BPJS penjamin code from settings.
func (d *DB) GetKdPjBpjs() string {
	val, _ := d.GetSetting("jkn_mobile", "kd_pj_bpjs")
	return val
}

// GetSetting retrieves a single setting value.
func (d *DB) GetSetting(module, field string) (string, error) {
	var val string
	err := d.QueryRow("SELECT `value` FROM mlite_settings WHERE `module`=? AND `field`=?", module, field).Scan(&val)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return val, err
}

// GetMappingPoliPCare retrieves the PCare poli code for a local poli code.
func (d *DB) GetMappingPoliPCare(kdPoliRS string) string {
	var val string
	err := d.QueryRow("SELECT kd_poli_pcare FROM maping_poliklinik_pcare WHERE kd_poli_rs=?", kdPoliRS).Scan(&val)
	if err != nil {
		return "001"
	}
	return val
}

// GetMappingDokterPCare retrieves the PCare doctor code for a local doctor code.
func (d *DB) GetMappingDokterPCare(kdDokter string) string {
	var val string
	err := d.QueryRow("SELECT kd_dokter_pcare FROM maping_dokter_pcare WHERE kd_dokter=?", kdDokter).Scan(&val)
	if err != nil {
		return ""
	}
	return val
}

// GetDiagnosaPasien retrieves the primary diagnosis for a visit.
func (d *DB) GetDiagnosaPasien(noRawat string) string {
	var val string
	err := d.QueryRow("SELECT kd_penyakit FROM diagnosa_pasien WHERE no_rawat=? AND status='Ralan' AND prioritas=1 LIMIT 1", noRawat).Scan(&val)
	if err != nil {
		return "Z00.0"
	}
	return val
}

// DiagnosaPasien holds diagnosis code and name.
type DiagnosaPasien struct {
	KdPenyakit string
	NmPenyakit string
}

// GetDiagnosaPasienAll retrieves diagnoses (priority 1,2,3) for a visit.
func (d *DB) GetDiagnosaPasienAll(noRawat string) (d1, d2, d3 DiagnosaPasien) {
	rows, err := d.Query(`SELECT dp.kd_penyakit, IFNULL(p.nm_penyakit,''), dp.prioritas
		FROM diagnosa_pasien dp
		LEFT JOIN penyakit p ON p.kd_penyakit=dp.kd_penyakit
		WHERE dp.no_rawat=? AND dp.status='Ralan' AND dp.prioritas IN (1,2,3)
		AND dp.kd_penyakit NOT IN ('kode','diagnosa','')
		AND LENGTH(dp.kd_penyakit) >= 2
		ORDER BY dp.prioritas`, noRawat)
	if err != nil {
		return
	}
	defer rows.Close()
	for rows.Next() {
		var kd, nm, pri string
		if err := rows.Scan(&kd, &nm, &pri); err == nil {
			switch pri {
			case "1":
				d1 = DiagnosaPasien{kd, nm}
			case "2":
				d2 = DiagnosaPasien{kd, nm}
			case "3":
				d3 = DiagnosaPasien{kd, nm}
			}
		}
	}
	return
}

// GetResepObat retrieves drug names from prescriptions as comma-separated string.
func (d *DB) GetResepObat(noRawat string) string {
	rows, err := d.Query(`SELECT db.nama_brng
		FROM resep_dokter rd
		JOIN databarang db ON db.kode_brng=rd.kode_brng
		JOIN resep_obat ro ON ro.no_resep=rd.no_resep
		WHERE ro.no_rawat=?`, noRawat)
	if err != nil {
		return "tidak ada"
	}
	defer rows.Close()
	var names []string
	for rows.Next() {
		var nm string
		if err := rows.Scan(&nm); err == nil && nm != "" {
			names = append(names, nm)
		}
	}
	if len(names) == 0 {
		return "tidak ada"
	}
	return strings.Join(names, ", ")
}

// GetPemeriksaanRalan retrieves outpatient examination data.
type PemeriksaanRalan struct {
	Keluhan      string
	Pemeriksaan  string
	Suhu         string
	Nadi         string
	Tensi        string
	Respirasi    string
	BeratBadan   string
	TinggiBadan  string
	Kesadaran    string
	LingkarPerut string
	RTL          string
}

func (d *DB) GetPemeriksaanRalan(noRawat string) (*PemeriksaanRalan, error) {
	p := &PemeriksaanRalan{}
	err := d.QueryRow(`SELECT IFNULL(keluhan,''), IFNULL(pemeriksaan,''), IFNULL(suhu_tubuh,'36'),
		IFNULL(nadi,'80'), IFNULL(tensi,'120/80'), IFNULL(respirasi,'20'),
		IFNULL(berat,'0'), IFNULL(tinggi,'0'), IFNULL(kesadaran,'Compos Mentis'),
		IFNULL(lingkar_perut,'0'), IFNULL(rtl,'')
		FROM pemeriksaan_ralan WHERE no_rawat=?
		ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1`, noRawat).Scan(
		&p.Keluhan, &p.Pemeriksaan, &p.Suhu, &p.Nadi, &p.Tensi, &p.Respirasi,
		&p.BeratBadan, &p.TinggiBadan, &p.Kesadaran, &p.LingkarPerut, &p.RTL)
	if err != nil {
		return nil, err
	}
	return p, nil
}

// GetPemeriksaanRalanComposite returns nurse TTV data and doctor SOAP data separately.
// Nurse entry: nip NOT IN dokter table (for TTV priority).
// Doctor entry: nip IN dokter table (for Plan/RTL).
func (d *DB) GetPemeriksaanRalanComposite(noRawat string) (nurse *PemeriksaanRalan, doctor *PemeriksaanRalan) {
	// Get latest nurse/non-doctor entry (for TTV)
	nurse = &PemeriksaanRalan{}
	err := d.QueryRow(`SELECT IFNULL(keluhan,''), IFNULL(pemeriksaan,''), IFNULL(suhu_tubuh,'0'),
		IFNULL(nadi,'0'), IFNULL(tensi,''), IFNULL(respirasi,'0'),
		IFNULL(berat,'0'), IFNULL(tinggi,'0'), IFNULL(kesadaran,'Compos Mentis'),
		IFNULL(lingkar_perut,'0'), IFNULL(rtl,'')
		FROM pemeriksaan_ralan
		WHERE no_rawat=? AND nip NOT IN (SELECT kd_dokter FROM dokter)
		ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1`, noRawat).Scan(
		&nurse.Keluhan, &nurse.Pemeriksaan, &nurse.Suhu, &nurse.Nadi, &nurse.Tensi, &nurse.Respirasi,
		&nurse.BeratBadan, &nurse.TinggiBadan, &nurse.Kesadaran, &nurse.LingkarPerut, &nurse.RTL)
	if err != nil {
		nurse = nil
	}

	// Get latest doctor entry (for SOAP/Plan)
	doctor = &PemeriksaanRalan{}
	err = d.QueryRow(`SELECT IFNULL(keluhan,''), IFNULL(pemeriksaan,''), IFNULL(suhu_tubuh,'0'),
		IFNULL(nadi,'0'), IFNULL(tensi,''), IFNULL(respirasi,'0'),
		IFNULL(berat,'0'), IFNULL(tinggi,'0'), IFNULL(kesadaran,'Compos Mentis'),
		IFNULL(lingkar_perut,'0'), IFNULL(rtl,'')
		FROM pemeriksaan_ralan
		WHERE no_rawat=? AND nip IN (SELECT kd_dokter FROM dokter)
		ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1`, noRawat).Scan(
		&doctor.Keluhan, &doctor.Pemeriksaan, &doctor.Suhu, &doctor.Nadi, &doctor.Tensi, &doctor.Respirasi,
		&doctor.BeratBadan, &doctor.TinggiBadan, &doctor.Kesadaran, &doctor.LingkarPerut, &doctor.RTL)
	if err != nil {
		doctor = nil
	}
	return
}

// IsApotekServed checks if pharmacy has dispensed medication for this visit.
func (d *DB) IsApotekServed(noRawat string) bool {
	var count int
	_ = d.QueryRow(`SELECT COUNT(*) FROM resep_obat WHERE no_rawat=? AND tgl_penyerahan > '0000-00-00'`, noRawat).Scan(&count)
	return count > 0
}

// GetApotekInfo returns pharmacy dispensing info: tgl_penyerahan and drug names.
func (d *DB) GetApotekInfo(noRawat string) (tglPenyerahan string, obatNames string) {
	_ = d.QueryRow(`SELECT DATE_FORMAT(tgl_penyerahan,'%d-%m-%Y %H:%i')
		FROM resep_obat WHERE no_rawat=? AND tgl_penyerahan > '0000-00-00'
		ORDER BY tgl_penyerahan DESC LIMIT 1`, noRawat).Scan(&tglPenyerahan)
	obatNames = d.GetResepObat(noRawat)
	return
}

// GetRegPeriksa retrieves registration row by no_rawat.
type RegPeriksa struct {
	NoRawat       string
	TglRegistrasi string
	JamReg        string
	KdPoli        string
	KdDokter      string
	NoRkmMedis    string
	Stts          string
	NoReg         string
}

func (d *DB) GetRegPeriksa(noRawat string) (*RegPeriksa, error) {
	r := &RegPeriksa{}
	err := d.QueryRow(`SELECT no_rawat, DATE_FORMAT(tgl_registrasi,'%Y-%m-%d'), jam_reg, kd_poli, kd_dokter, no_rkm_medis, stts, no_reg
		FROM reg_periksa WHERE no_rawat=?`, noRawat).Scan(
		&r.NoRawat, &r.TglRegistrasi, &r.JamReg, &r.KdPoli, &r.KdDokter, &r.NoRkmMedis, &r.Stts, &r.NoReg)
	if err != nil {
		return nil, err
	}
	return r, nil
}

// GetPasienByNoRkm retrieves patient data.
type Pasien struct {
	NoRkmMedis string
	NmPasien   string
	NoPeserta  string
	Alamat     string
	TglLahir   string
	JK         string
	NoTlp      string
}

func (d *DB) GetPasienByNoRkm(noRkmMedis string) (*Pasien, error) {
	p := &Pasien{}
	err := d.QueryRow(`SELECT no_rkm_medis, nm_pasien, IFNULL(no_peserta,''), IFNULL(alamat,''),
		DATE_FORMAT(tgl_lahir,'%d-%m-%Y'), jk, IFNULL(no_tlp,'')
		FROM pasien WHERE no_rkm_medis=?`, noRkmMedis).Scan(
		&p.NoRkmMedis, &p.NmPasien, &p.NoPeserta, &p.Alamat, &p.TglLahir, &p.JK, &p.NoTlp)
	if err != nil {
		return nil, err
	}
	return p, nil
}

// MonitorRow holds sync monitoring data.
type MonitorRow struct {
	NomorUrut      string
	NoRawat        string
	NoRkmMedis     string
	NomorKunjungan string
	KodePoliPCare  string
	StatusKirim    string
	Stts           string
	KdPoli         string
	NmPasien       string
	NoPeserta      string
	NmPoli         string
}

// GetMonitorRows retrieves bridging sync data for monitoring.
func (d *DB) GetMonitorRows(date string, kdPjBpjs string) ([]MonitorRow, error) {
	query := `SELECT
		IFNULL(bp.nomor_urut,'') as nomor_urut, bp.no_rawat, bp.no_rkm_medis,
		IFNULL(bp.nomor_kunjungan,'') as nomor_kunjungan,
		IFNULL(bp.kode_poli,'') as kode_poli_pcare,
		IFNULL(bp.status_kirim,'') as status_kirim,
		reg_periksa.stts, reg_periksa.kd_poli,
		pasien.nm_pasien, IFNULL(pasien.no_peserta,'') as no_peserta,
		poliklinik.nm_poli
		FROM mlite_bridging_pcare bp
		INNER JOIN reg_periksa ON reg_periksa.no_rawat = bp.no_rawat
		INNER JOIN pasien ON pasien.no_rkm_medis = bp.no_rkm_medis
		INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
		WHERE reg_periksa.tgl_registrasi = ?`

	args := []interface{}{date}
	if kdPjBpjs != "" {
		query += " AND reg_periksa.kd_pj = ?"
		args = append(args, kdPjBpjs)
	}
	query += " ORDER BY bp.nomor_urut ASC"

	rows, err := d.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var result []MonitorRow
	for rows.Next() {
		var r MonitorRow
		if err := rows.Scan(
			&r.NomorUrut, &r.NoRawat, &r.NoRkmMedis, &r.NomorKunjungan,
			&r.KodePoliPCare, &r.StatusKirim, &r.Stts, &r.KdPoli,
			&r.NmPasien, &r.NoPeserta, &r.NmPoli,
		); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetAlergiPasien retrieves patient allergy data.
func (d *DB) GetAlergiPasien(noRkmMedis string) (makanan, udara, obat string) {
	_ = d.QueryRow(`SELECT IFNULL(alergi_makanan,''), IFNULL(alergi_udara,''), IFNULL(alergi_obat,'')
		FROM alergi_pasien WHERE no_rkm_medis=? LIMIT 1`, noRkmMedis).Scan(&makanan, &udara, &obat)
	return
}

// NullStr safely dereferences sql.NullString.
func NullStr(ns sql.NullString) string {
	if ns.Valid {
		return ns.String
	}
	return ""
}

// NullInt64 safely dereferences sql.NullInt64.
func NullInt64Val(ni sql.NullInt64) int64 {
	if ni.Valid {
		return ni.Int64
	}
	return 0
}

// GetDataKunjunganBpjs retrieves BPJS visit data (bridging rows with kunjungan numbers).
func (d *DB) GetDataKunjunganBpjs(date string, kdPjBpjs string) ([]DashboardRow, error) {
	query := `SELECT
		reg_periksa.no_rawat, reg_periksa.no_rkm_medis,
		DATE_FORMAT(reg_periksa.tgl_registrasi,'%d-%m-%Y') as tgl_registrasi,
		reg_periksa.jam_reg, reg_periksa.kd_dokter, reg_periksa.kd_poli,
		reg_periksa.stts, reg_periksa.no_reg,
		pasien.nm_pasien, IFNULL(pasien.no_peserta,'') as no_peserta,
		poliklinik.nm_poli, IFNULL(dokter.nm_dokter,'') as nm_dokter,
		penjab.png_jawab,
		bp.id AS bridging_id, bp.nomor_urut, bp.nomor_kunjungan,
		bp.nomor_jaminan, bp.status_kirim,
		(SELECT DATE_FORMAT(MIN(ro.tgl_penyerahan),'%d-%m-%Y %H:%i')
		 FROM resep_obat ro WHERE ro.no_rawat = reg_periksa.no_rawat
		 AND ro.tgl_penyerahan > '0000-00-00') as tgl_penyerahan_obat
		FROM reg_periksa
		INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
		INNER JOIN poliklinik ON poliklinik.kd_poli = reg_periksa.kd_poli
		INNER JOIN penjab ON penjab.kd_pj = reg_periksa.kd_pj
		LEFT JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
		INNER JOIN mlite_bridging_pcare bp ON bp.no_rawat = reg_periksa.no_rawat
		WHERE reg_periksa.tgl_registrasi = ? AND bp.nomor_kunjungan IS NOT NULL AND bp.nomor_kunjungan != ''`

	args := []interface{}{date}
	if kdPjBpjs != "" {
		query += " AND reg_periksa.kd_pj = ?"
		args = append(args, kdPjBpjs)
	}
	query += " ORDER BY reg_periksa.no_reg ASC"

	rows, err := d.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var result []DashboardRow
	for rows.Next() {
		var r DashboardRow
		if err := rows.Scan(
			&r.NoRawat, &r.NoRkmMedis, &r.TglRegistrasi, &r.JamReg,
			&r.KdDokter, &r.KdPoli, &r.Stts, &r.NoReg,
			&r.NmPasien, &r.NoPeserta, &r.NmPoli, &r.NmDokter, &r.PngJawab,
			&r.BridgingID, &r.NomorUrut, &r.NomorKunjungan,
			&r.NomorJaminan, &r.StatusKirim, &r.TglPenyerahanObat,
		); err != nil {
			return nil, err
		}
		r.populateVals()
		result = append(result, r)
	}
	return result, nil
}

// CekSinkronisasiRows retrieves rows for synchronization check between local and BPJS data.
func (d *DB) CekSinkronisasiRows(date string) ([]DashboardRow, error) {
	return d.GetDashboardRows(date, d.GetKdPjBpjs())
}

// CekPendaftaranProviderRows retrieves rows for provider registration check.
func (d *DB) CekPendaftaranProviderRows(date string) ([]DashboardRow, error) {
	return d.GetDashboardRows(date, d.GetKdPjBpjs())
}

// GetAllPoliMapping retrieves all poli mappings.
type PoliMapping struct {
	KdPoliRS    string
	NmPoli      string
	KdPoliPCare string
}

func (d *DB) GetAllPoliMapping() ([]PoliMapping, error) {
	rows, err := d.Query(`SELECT m.kd_poli_rs, p.nm_poli, m.kd_poli_pcare
		FROM maping_poliklinik_pcare m
		LEFT JOIN poliklinik p ON p.kd_poli = m.kd_poli_rs`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []PoliMapping
	for rows.Next() {
		var r PoliMapping
		if err := rows.Scan(&r.KdPoliRS, &r.NmPoli, &r.KdPoliPCare); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetAllDokterMapping retrieves all doctor mappings.
type DokterMapping struct {
	KdDokter      string
	NmDokter      string
	KdDokterPCare string
}

func (d *DB) GetAllDokterMapping() ([]DokterMapping, error) {
	rows, err := d.Query(`SELECT m.kd_dokter, d.nm_dokter, m.kd_dokter_pcare
		FROM maping_dokter_pcare m
		LEFT JOIN dokter d ON d.kd_dokter = m.kd_dokter`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []DokterMapping
	for rows.Next() {
		var r DokterMapping
		if err := rows.Scan(&r.KdDokter, &r.NmDokter, &r.KdDokterPCare); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// UpdateBridgingPendaftaran updates nomor_urut and kode_provider_peserta after successful PCare registration.
func (d *DB) UpdateBridgingPendaftaran(id int64, nomorUrut string) error {
	_, err := d.Exec(`UPDATE mlite_bridging_pcare SET nomor_urut=?, tgl_input=NOW() WHERE id=?`,
		nomorUrut, id)
	return err
}

// EnsureBridgingExists creates a bridging record if one doesn't exist for the given no_rawat.
// Returns the bridging ID.
func (d *DB) EnsureBridgingExists(noRawat, noRkmMedis, nomorJaminan, kodePoli string) (int64, error) {
	// Check if bridging record exists
	var id int64
	err := d.QueryRow("SELECT id FROM mlite_bridging_pcare WHERE no_rawat=?", noRawat).Scan(&id)
	if err == nil {
		return id, nil
	}
	// Insert new record
	result, err := d.Exec(`INSERT INTO mlite_bridging_pcare
		(no_rawat, no_rkm_medis, nomor_jaminan, kode_poli, status_kirim, tgl_input, tgl_daftar, id_user)
		VALUES (?,?,?,?,'Belum',NOW(),CURDATE(),'admin')`,
		noRawat, noRkmMedis, nomorJaminan, kodePoli)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

// Helper: build LIKE clause for multiple terms.
func buildLikeClause(column string, terms []string) (string, []interface{}) {
	if len(terms) == 0 {
		return "1=1", nil
	}
	clauses := make([]string, len(terms))
	args := make([]interface{}, len(terms))
	for i, t := range terms {
		clauses[i] = column + " LIKE ?"
		args[i] = "%" + t + "%"
	}
	return "(" + strings.Join(clauses, " OR ") + ")", args
}
