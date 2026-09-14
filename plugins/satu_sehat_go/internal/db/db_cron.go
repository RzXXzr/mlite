package db

import (
	"fmt"
)

// HutangBulan holds monthly pending data stats.
type HutangBulan struct {
	Bulan      string
	Total      int
	BelumKirim int
	SudahKirim int
	Persen     int
}

// GetHutangPerBulan returns monthly stats of sent vs unsent visits.
func (d *DB) GetHutangPerBulan() ([]HutangBulan, error) {
	rows, err := d.Query(`
		SELECT DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') as bulan,
		       COUNT(*) as total,
		       SUM(CASE WHEN sr.no_rawat IS NULL THEN 1 ELSE 0 END) as belum_kirim
		FROM reg_periksa rp
		LEFT JOIN mlite_satu_sehat_response sr ON rp.no_rawat = sr.no_rawat
		WHERE rp.stts != 'Batal'
		GROUP BY DATE_FORMAT(rp.tgl_registrasi, '%Y-%m')
		ORDER BY bulan`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var result []HutangBulan
	for rows.Next() {
		var h HutangBulan
		if err := rows.Scan(&h.Bulan, &h.Total, &h.BelumKirim); err != nil {
			return nil, err
		}
		h.SudahKirim = h.Total - h.BelumKirim
		if h.Total > 0 {
			h.Persen = (h.SudahKirim * 100) / h.Total
		}
		result = append(result, h)
	}
	return result, nil
}

// GetTotalHutang returns total unsent visits and total visits.
func (d *DB) GetTotalHutang() (totalHutang, totalKunjungan int, err error) {
	err = d.QueryRow(`
		SELECT COUNT(*) as total,
		       SUM(CASE WHEN sr.no_rawat IS NULL THEN 1 ELSE 0 END) as belum_kirim
		FROM reg_periksa rp
		LEFT JOIN mlite_satu_sehat_response sr ON rp.no_rawat = sr.no_rawat
		WHERE rp.stts != 'Batal'`).Scan(&totalKunjungan, &totalHutang)
	return
}

// GetResponseField reads a single field from mlite_satu_sehat_response.
// Only allows known column names to prevent SQL injection.
func (d *DB) GetResponseField(noRawat, column string) (string, error) {
	allowed := map[string]bool{
		"id_encounter":                true,
		"id_condition":                true,
		"id_observation_ttvtensi":     true,
		"id_observation_ttvnadi":      true,
		"id_observation_ttvrespirasi": true,
		"id_observation_ttvsuhu":      true,
		"id_observation_ttvspo2":      true,
		"id_observation_ttvgcs":       true,
		"id_observation_ttvtinggi":    true,
		"id_observation_ttvberat":     true,
		"id_observation_ttvperut":     true,
		"id_observation_ttvkesadaran": true,
		"id_procedure":                true,
		"id_composition":              true,
		"id_medication_request":       true,
		"id_medication_dispense":      true,
		"id_medication_statement":     true,
		"id_clinical_impression":      true,
		"id_careplan":                 true,
		"id_allergy":                  true,
		"id_questionnaire":            true,
		"id_lab_pk_request":           true,
		"id_lab_pk_specimen":          true,
		"id_lab_pk_observation":       true,
		"id_lab_pk_diagnostic":        true,
		"id_rad_request":              true,
		"id_rad_specimen":             true,
		"id_rad_observation":          true,
		"id_rad_diagnostic":           true,
	}
	if !allowed[column] {
		return "", fmt.Errorf("kolom tidak diizinkan: %s", column)
	}

	var val string
	err := d.QueryRow(
		fmt.Sprintf("SELECT IFNULL(%s,'') FROM mlite_satu_sehat_response WHERE no_rawat=?", column),
		noRawat,
	).Scan(&val)
	if err != nil {
		return "", err
	}
	return val, nil
}
