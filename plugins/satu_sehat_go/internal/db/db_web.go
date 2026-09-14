package db

import "database/sql"

// ---- Types for web frontend ----

// DepartemenRow holds a departemen record.
type DepartemenRow struct {
	DepID string
	Nama  string
}

// DepartemenMappedRow holds mapped departemen with org ID.
type DepartemenMappedRow struct {
	DepID        string
	Nama         string
	IDOrganisasi string
}

// PoliklinikRow holds poliklinik list item.
type PoliklinikRow struct {
	KdPoli string
	NmPoli string
}

// BangsalRow holds bangsal list item.
type BangsalRow struct {
	KdBangsal string
	NmBangsal string
}

// LokasiMappedRow holds mapped lokasi with full detail.
type LokasiMappedRow struct {
	Kode         string
	Lokasi       string
	DepID        string
	IDOrganisasi string
	IDLokasi     string
	Nama         string
	Longitude    string
	Latitude     string
	Altitude     string
}

// LokasiSimple is a simple lokasi item for settings dropdowns.
type LokasiSimple struct {
	Kode   string
	Lokasi string
}

// DokterRow holds doctor list item.
type DokterRow struct {
	KdDokter string
	NmDokter string
}

// ApotekerRow holds apoteker/petugas medis item.
type ApotekerRow struct {
	NIK  string
	Nama string
}

// MappingPraktisiDetail holds practitioner mapping with name.
type MappingPraktisiDetail struct {
	KdDokter       string
	NmDokter       string
	PractitionerID string
}

// ObatWithMapping holds barang with its mapping status.
type ObatWithMapping struct {
	KodeBrng          string
	NamaBrng          string
	KodeKFA           string
	NamaKFA           string
	Type              string
	IDMedication      string
	UpdatedAfterPush  int
}

// BarangRow holds databarang item.
type BarangRow struct {
	KodeBrng string
	NamaBrng string
}

// LabTemplateRow holds lab template pemeriksaan record.
type LabTemplateRow struct {
	IDTemplate       string
	KdJenisPerawatan string
	Pemeriksaan      string
}

// LabMappingRow holds lab mapping with detail.
type LabMappingRow struct {
	IDTemplate       string
	KdJenisPerawatan string
	Pemeriksaan      string
	Code             string
	Display          string
	SampelCode       string
	SampelSystem     string
	SampelDisplay    string
}

// RadPerawatanRow holds radiology perawatan type.
type RadPerawatanRow struct {
	KdJenisPerawatan string
	NmPerawatan      string
}

// RadMappingRow holds radiology mapping with detail.
type RadMappingRow struct {
	KdJenisPerawatan string
	NmPerawatan      string
	Code             string
	Display          string
	SampelCode       string
	SampelSystem     string
	SampelDisplay    string
}

// ---- Additional queries for web frontend ----

// GetBidang returns unique departemen names for bidang dropdown.
func (d *DB) GetBidang() ([]string, error) {
	rows, err := d.Query("SELECT DISTINCT nama FROM departemen ORDER BY nama")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []string
	for rows.Next() {
		var s string
		if err := rows.Scan(&s); err != nil {
			return nil, err
		}
		result = append(result, s)
	}
	return result, nil
}

// GetDepartemen returns all departemen.
func (d *DB) GetDepartemen() ([]DepartemenRow, error) {
	rows, err := d.Query("SELECT dep_id, nama FROM departemen ORDER BY nama")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []DepartemenRow
	for rows.Next() {
		var r DepartemenRow
		if err := rows.Scan(&r.DepID, &r.Nama); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetMappingDepartemen returns all mapped departments with names.
func (d *DB) GetMappingDepartemen() ([]DepartemenMappedRow, error) {
	rows, err := d.Query(`
		SELECT d.dep_id, IFNULL(dept.nama,'') AS nama, d.id_organisasi_satusehat
		FROM mlite_satu_sehat_departemen d
		LEFT JOIN departemen dept ON dept.dep_id = d.dep_id
		ORDER BY dept.nama`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []DepartemenMappedRow
	for rows.Next() {
		var r DepartemenMappedRow
		if err := rows.Scan(&r.DepID, &r.Nama, &r.IDOrganisasi); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetPoliklinik returns all poliklinik.
func (d *DB) GetPoliklinik() ([]PoliklinikRow, error) {
	rows, err := d.Query("SELECT kd_poli, nm_poli FROM poliklinik ORDER BY nm_poli")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []PoliklinikRow
	for rows.Next() {
		var r PoliklinikRow
		if err := rows.Scan(&r.KdPoli, &r.NmPoli); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetBangsal returns all bangsal.
func (d *DB) GetBangsal() ([]BangsalRow, error) {
	rows, err := d.Query("SELECT kd_bangsal, nm_bangsal FROM bangsal ORDER BY nm_bangsal")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []BangsalRow
	for rows.Next() {
		var r BangsalRow
		if err := rows.Scan(&r.KdBangsal, &r.NmBangsal); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetMappingLokasi returns lokasi list for settings dropdown (kode, lokasi name).
func (d *DB) GetMappingLokasi() ([]LokasiSimple, error) {
	rows, err := d.Query(`
		SELECT kode, IFNULL(lokasi, kode) AS lokasi
		FROM mlite_satu_sehat_lokasi
		ORDER BY lokasi`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []LokasiSimple
	for rows.Next() {
		var r LokasiSimple
		if err := rows.Scan(&r.Kode, &r.Lokasi); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetAllMappingLokasi returns all lokasi mappings with full detail.
func (d *DB) GetAllMappingLokasi() ([]LokasiMappedRow, error) {
	rows, err := d.Query(`
		SELECT l.kode,
		       IFNULL(l.lokasi, l.kode) AS lokasi,
		       '' AS dep_id,
		       IFNULL(l.id_organisasi_satusehat, '') AS id_organisasi,
		       IFNULL(l.id_lokasi_satusehat, '') AS id_lokasi,
		       IFNULL(l.lokasi, l.kode) AS nama,
		       IFNULL(l.longitude, '') AS longitude,
		       IFNULL(l.latitude, '') AS latitude,
		       IFNULL(l.altitude, '') AS altitude
		FROM mlite_satu_sehat_lokasi l
		ORDER BY lokasi`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []LokasiMappedRow
	for rows.Next() {
		var r LokasiMappedRow
		if err := rows.Scan(&r.Kode, &r.Lokasi, &r.DepID, &r.IDOrganisasi, &r.IDLokasi, &r.Nama, &r.Longitude, &r.Latitude, &r.Altitude); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetDokter returns all doctors.
func (d *DB) GetDokter() ([]DokterRow, error) {
	rows, err := d.Query("SELECT kd_dokter, nm_dokter FROM dokter ORDER BY nm_dokter")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []DokterRow
	for rows.Next() {
		var r DokterRow
		if err := rows.Scan(&r.KdDokter, &r.NmDokter); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetApoteker returns petugas for apoteker / tenaga medis list.
func (d *DB) GetApoteker() ([]ApotekerRow, error) {
	rows, err := d.Query("SELECT nik, nama FROM pegawai ORDER BY nama")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []ApotekerRow
	for rows.Next() {
		var r ApotekerRow
		if err := rows.Scan(&r.NIK, &r.Nama); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetMappingPraktisiAll returns all practitioner mappings with dokter names.
func (d *DB) GetMappingPraktisiAll() ([]MappingPraktisiDetail, error) {
	rows, err := d.Query(`
		SELECT mp.kd_dokter, IFNULL(d.nm_dokter, p.nama) AS nm_dokter, mp.practitioner_id
		FROM mlite_satu_sehat_mapping_praktisi mp
		LEFT JOIN dokter d ON d.kd_dokter = mp.kd_dokter
		LEFT JOIN pegawai p ON p.nik = mp.kd_dokter
		ORDER BY nm_dokter`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []MappingPraktisiDetail
	for rows.Next() {
		var r MappingPraktisiDetail
		var nm sql.NullString
		if err := rows.Scan(&r.KdDokter, &nm, &r.PractitionerID); err != nil {
			return nil, err
		}
		if nm.Valid {
			r.NmDokter = nm.String
		}
		result = append(result, r)
	}
	return result, nil
}

// GetAllObatWithMapping returns all active databarang joined with mapping.
func (d *DB) GetAllObatWithMapping() ([]ObatWithMapping, error) {
	rows, err := d.Query(`
		SELECT b.kode_brng, b.nama_brng,
		       IFNULL(m.kode_kfa, '') AS kode_kfa,
		       IFNULL(m.nama_kfa, '') AS nama_kfa,
		       IFNULL(m.type, '') AS type,
		       IFNULL(m.id_medication, '') AS id_medication,
		       IFNULL(m.updated_after_push, 0) AS updated_after_push
		FROM databarang b
		LEFT JOIN mlite_satu_sehat_mapping_obat m ON m.kode_brng = b.kode_brng
		WHERE b.status = '1'
		ORDER BY b.nama_brng`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []ObatWithMapping
	for rows.Next() {
		var r ObatWithMapping
		if err := rows.Scan(&r.KodeBrng, &r.NamaBrng, &r.KodeKFA, &r.NamaKFA, &r.Type, &r.IDMedication, &r.UpdatedAfterPush); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetDataBarang returns barang list.
func (d *DB) GetDataBarang() ([]BarangRow, error) {
	rows, err := d.Query("SELECT kode_brng, nama_brng FROM databarang WHERE status='1' ORDER BY nama_brng LIMIT 5000")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []BarangRow
	for rows.Next() {
		var r BarangRow
		if err := rows.Scan(&r.KodeBrng, &r.NamaBrng); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetTemplateLab returns template_laboratorium as lab template list.
func (d *DB) GetTemplateLab() ([]LabTemplateRow, error) {
	rows, err := d.Query(`
		SELECT tl.id_template, tl.kd_jenis_prw, IFNULL(jpl.nm_perawatan, tl.kd_jenis_prw) AS pemeriksaan
		FROM template_laboratorium tl
		LEFT JOIN jns_perawatan_lab jpl ON jpl.kd_jenis_prw = tl.kd_jenis_prw
		GROUP BY tl.kd_jenis_prw
		ORDER BY pemeriksaan`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []LabTemplateRow
	for rows.Next() {
		var r LabTemplateRow
		if err := rows.Scan(&r.IDTemplate, &r.KdJenisPerawatan, &r.Pemeriksaan); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetMappingLabAll returns all lab mappings with pemeriksaan name and specimen data.
func (d *DB) GetMappingLabAll() ([]LabMappingRow, error) {
	rows, err := d.Query(`
		SELECT ml.kd_jenis_prw,
		       IFNULL(ml.id_template, '') AS id_template,
		       IFNULL(jpl.nm_perawatan, ml.kd_jenis_prw) AS pemeriksaan,
		       IFNULL(ml.code, '') AS code,
		       IFNULL(ml.display, '') AS display,
		       IFNULL(ml.sampel_code, '') AS sampel_code,
		       IFNULL(ml.sampel_system, '') AS sampel_system,
		       IFNULL(ml.sampel_display, '') AS sampel_display
		FROM mlite_satu_sehat_mapping_lab ml
		LEFT JOIN jns_perawatan_lab jpl ON jpl.kd_jenis_prw = ml.kd_jenis_prw
		GROUP BY ml.kd_jenis_prw
		ORDER BY pemeriksaan`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []LabMappingRow
	for rows.Next() {
		var r LabMappingRow
		if err := rows.Scan(&r.KdJenisPerawatan, &r.IDTemplate, &r.Pemeriksaan, &r.Code, &r.Display, &r.SampelCode, &r.SampelSystem, &r.SampelDisplay); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetJnsPerawatanRad returns radiology perawatan types.
func (d *DB) GetJnsPerawatanRad() ([]RadPerawatanRow, error) {
	rows, err := d.Query("SELECT kd_jenis_prw, nm_perawatan FROM jns_perawatan_radiologi ORDER BY nm_perawatan")
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []RadPerawatanRow
	for rows.Next() {
		var r RadPerawatanRow
		if err := rows.Scan(&r.KdJenisPerawatan, &r.NmPerawatan); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}

// GetMappingRadAll returns all radiology mappings with detail.
func (d *DB) GetMappingRadAll() ([]RadMappingRow, error) {
	rows, err := d.Query(`
		SELECT mr.kd_jenis_prw,
		       IFNULL(jr.nm_perawatan, mr.kd_jenis_prw) AS nm_perawatan,
		       IFNULL(mr.code, '') AS code,
		       IFNULL(mr.display, '') AS display,
		       IFNULL(mr.sampel_code, '') AS sampel_code,
		       IFNULL(mr.sampel_system, '') AS sampel_system,
		       IFNULL(mr.sampel_display, '') AS sampel_display
		FROM mlite_satu_sehat_mapping_rad mr
		LEFT JOIN jns_perawatan_radiologi jr ON jr.kd_jenis_prw = mr.kd_jenis_prw
		ORDER BY nm_perawatan`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var result []RadMappingRow
	for rows.Next() {
		var r RadMappingRow
		if err := rows.Scan(&r.KdJenisPerawatan, &r.NmPerawatan, &r.Code, &r.Display, &r.SampelCode, &r.SampelSystem, &r.SampelDisplay); err != nil {
			return nil, err
		}
		result = append(result, r)
	}
	return result, nil
}
