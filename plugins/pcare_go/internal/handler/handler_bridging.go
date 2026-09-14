package handler

import (
	"encoding/json"
	"fmt"
	"log"
	"math"
	"net/http"
	"net/url"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"pcare-go/internal/db"
)

// === Bridging & Database Sync Handlers ===

// GetBridging retrieves bridging data for a patient visit.
func (h *Handler) GetBridging(w http.ResponseWriter, r *http.Request) {
	noRawat := r.URL.Query().Get("no_rawat")
	if noRawat == "" {
		errorJSON(w, 400, "no_rawat required")
		return
	}
	bridging, err := h.db.GetBridgingByNoRawat(noRawat)
	if err != nil {
		errorJSON(w, 404, "Bridging data not found")
		return
	}
	jsonResponse(w, 200, bridging)
}

// PostBridgingSave saves or updates bridging data.
func (h *Handler) PostBridgingSave(w http.ResponseWriter, r *http.Request) {
	var req struct {
		ID               int64  `json:"id"`
		NoRawat          string `json:"no_rawat"`
		NoRkmMedis       string `json:"no_rkm_medis"`
		NomorJaminan     string `json:"nomor_jaminan"`
		NomorUrut        string `json:"nomor_urut"`
		NomorKunjungan   string `json:"nomor_kunjungan"`
		KodePoli         string `json:"kode_poli"`
		KodeDokter       string `json:"kode_dokter"`
		KodeKesadaran    string `json:"kode_kesadaran"`
		KodeStatusPulang string `json:"kode_status_pulang"`
		Sistole          string `json:"sistole"`
		Diastole         string `json:"diastole"`
		BeratBadan       string `json:"berat"`
		TinggiBadan      string `json:"tinggi"`
		Nadi             string `json:"nadi"`
		Respirasi        string `json:"respirasi"`
		LingkarPerut     string `json:"lingkar_perut"`
		Subyektif        string `json:"subyektif"`
		Suhu             string `json:"suhu"`
		StatusKirim      string `json:"status_kirim"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}

	b := &db.BridgingPCare{
		ID:              req.ID,
		NoRawat:         req.NoRawat,
		NoRkmMedis:      req.NoRkmMedis,
		NomorJaminan:    req.NomorJaminan,
		NomorUrut:       req.NomorUrut,
		NomorKunjungan:  req.NomorKunjungan,
		KodePoli:        req.KodePoli,
		KodeDokter:      req.KodeDokter,
		KodeKesadaran:   req.KodeKesadaran,
		KodeStatusPulang: req.KodeStatusPulang,
		Sistole:         req.Sistole,
		Diastole:        req.Diastole,
		BeratBadan:      req.BeratBadan,
		TinggiBadan:     req.TinggiBadan,
		Nadi:            req.Nadi,
		Respirasi:       req.Respirasi,
		LingkarPerut:    req.LingkarPerut,
		Subyektif:       req.Subyektif,
		Suhu:            req.Suhu,
		StatusKirim:     req.StatusKirim,
	}

	if err := h.db.SaveBridging(b); err != nil {
		errorJSON(w, 500, "Failed to save bridging: "+err.Error())
		return
	}

	jsonResponse(w, 200, map[string]interface{}{
		"status":  "success",
		"message": "Data bridging berhasil disimpan",
		"id":      b.ID,
	})
}

// PostKirimKunjungan sends a visit to BPJS PCare API.
func (h *Handler) PostKirimKunjungan(w http.ResponseWriter, r *http.Request) {
	var req struct {
		ID              int64  `json:"id"`
		NoRawat         string `json:"no_rawat"`
		NoRkmMedis      string `json:"no_rkm_medis"`
		NomorJaminan    string `json:"nomor_jaminan"`
		TglKunjungan    string `json:"tgl_kunjungan"`
		TglPulang       string `json:"tgl_pulang"`
		KunjunganSakit  string `json:"kunjungan_sakit"`
		Subyektif       string `json:"subyektif"`
		Sistole         string `json:"sistole"`
		Diastole        string `json:"diastole"`
		Berat           string `json:"berat"`
		Tinggi          string `json:"tinggi"`
		Nadi            string `json:"nadi"`
		Respirasi       string `json:"respirasi"`
		LingkarPerut    string `json:"lingkar_perut"`
		KdDokter        string `json:"kdDokter"`
		KdKesadaran     string `json:"kdKesadaran"`
		KdStatusPulang  string `json:"kdStatusPulang"`
		KdDiagnosa1     string `json:"kdDiagnosa1"`
		KdDiagnosa2     string `json:"kdDiagnosa2"`
		KdDiagnosa3     string `json:"kdDiagnosa3"`
		KdPoli          string `json:"kdPoli"`
		TerapiObat      string `json:"terapiObat"`
		TerapiNonObat   string `json:"terapiNonObat"`
		Terapi          string `json:"terapi"`
		Suhu            string `json:"suhu"`
		Anamnesa        string `json:"anamnesa"`
		KdPrognosa      string `json:"kdPrognosa"`
		AlergiMakan     string `json:"kdAlergiMakan"`
		AlergiUdara     string `json:"kdAlergiUdara"`
		AlergiObat      string `json:"kdAlergiObat"`
		// Rujukan fields
		KdTacc          *int   `json:"kdTacc"`
		AlasanTacc      string `json:"alasanTacc"`
		KdSarana        string `json:"kdSarana"`
		KdSpesialis     string `json:"kdSpesialis"`
		KdSubSpesialis  string `json:"kdSubSpesialis"`
		KdPPK           string `json:"kdPPK"`
		NmPPK           string `json:"nmPPK"`
		TglEstRujuk     string `json:"tglEstRujuk"`
		Catatan         string `json:"catatan"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}

	if req.ID == 0 || req.NoRawat == "" {
		errorJSON(w, 400, "Data tidak lengkap")
		return
	}

	// Get bridging data
	bridging, err := h.db.GetBridgingByNoRawat(req.NoRawat)
	if err != nil {
		errorJSON(w, 404, "Data bridging tidak ditemukan")
		return
	}

	// Get reg_periksa
	regPeriksa, err := h.db.GetRegPeriksa(req.NoRawat)
	if err != nil {
		errorJSON(w, 404, "Data registrasi tidak ditemukan")
		return
	}

	// Validate: only send if patient examination is complete
	if regPeriksa.Stts != "Sudah" {
		errorJSON(w, 400, fmt.Sprintf("Pasien belum selesai diperiksa (status: %s). Kunjungan hanya bisa dikirim untuk pasien dengan status Sudah.", regPeriksa.Stts))
		return
	}

	// Get examination data - composite: nurse TTV + doctor SOAP
	nursePem, doctorPem := h.db.GetPemeriksaanRalanComposite(req.NoRawat)
	pemeriksaan, _ := h.db.GetPemeriksaanRalan(req.NoRawat)

	// TTV source: nurse first, fallback to doctor, then generic
	ttvSrc := nursePem
	if ttvSrc == nil {
		ttvSrc = doctorPem
	}
	if ttvSrc == nil {
		ttvSrc = pemeriksaan
	}

	// Resolve poli code
	kdPoli := req.KdPoli
	if kdPoli == "" {
		kdPoli = h.db.GetMappingPoliPCare(regPeriksa.KdPoli)
	}

	// Resolve doctor code
	kdDokter := req.KdDokter
	if kdDokter == "" {
		kdDokter = h.db.GetMappingDokterPCare(regPeriksa.KdDokter)
	}

	// Resolve diagnosis
	kdDiagnosa1 := req.KdDiagnosa1
	if kdDiagnosa1 == "" {
		kdDiagnosa1 = h.db.GetDiagnosaPasien(req.NoRawat)
	}

	// Format dates
	tglDaftar := req.TglKunjungan
	if tglDaftar == "" {
		tglDaftar = formatDateDDMMYYYY(regPeriksa.TglRegistrasi)
	}
	tglPulang := req.TglPulang
	if tglPulang == "" {
		tglPulang = tglDaftar
	}

	// Resolve keluhan from doctor SOAP, fallback to generic
	keluhan := req.Subyektif
	if keluhan == "" && doctorPem != nil && doctorPem.Keluhan != "" {
		keluhan = doctorPem.Keluhan
	}
	if keluhan == "" && pemeriksaan != nil && pemeriksaan.Keluhan != "" {
		keluhan = pemeriksaan.Keluhan
	}
	if keluhan == "" && bridging.Subyektif != "" {
		keluhan = bridging.Subyektif
	}
	if keluhan == "" {
		keluhan = "Keluhan umum"
	}

	// Resolve other defaults
	kdKesadaran := firstNonEmpty(req.KdKesadaran, bridging.KodeKesadaran, "01")
	kdStatusPulang := firstNonEmpty(req.KdStatusPulang, bridging.KodeStatusPulang, "3")
	kdPrognosa := firstNonEmpty(req.KdPrognosa, "01")
	anamnesa := req.Anamnesa
	if anamnesa == "" && doctorPem != nil && doctorPem.Pemeriksaan != "" {
		anamnesa = doctorPem.Pemeriksaan
	}
	if anamnesa == "" && pemeriksaan != nil && pemeriksaan.Pemeriksaan != "" {
		anamnesa = pemeriksaan.Pemeriksaan
	}
	if anamnesa == "" {
		anamnesa = "Anamnesa"
	}
	terapiObat := firstNonEmpty(req.TerapiObat, "tidak ada")
	// Terapi non-obat from doctor's Plan (RTL) field
	terapiNonObat := req.TerapiNonObat
	if terapiNonObat == "" && doctorPem != nil && doctorPem.RTL != "" {
		terapiNonObat = doctorPem.RTL
	}
	if terapiNonObat == "" && pemeriksaan != nil && pemeriksaan.RTL != "" {
		terapiNonObat = pemeriksaan.RTL
	}
	if terapiNonObat == "" {
		terapiNonObat = "tidak ada"
	}

	// TTV: use nurse source (ttvSrc) for vital signs, then bridging, then defaults
	suhu := intValOr(req.Suhu, "0")
	if suhu == 0 && ttvSrc != nil { suhu = intValOr(ttvSrc.Suhu, "0") }
	if suhu == 0 { suhu = intValOr(bridging.Suhu, "36") }

	sistole := intValOr(req.Sistole, "0")
	diastole := intValOr(req.Diastole, "0")
	if (sistole == 0 || diastole == 0) && ttvSrc != nil && ttvSrc.Tensi != "" && strings.Contains(ttvSrc.Tensi, "/") {
		parts := strings.Split(ttvSrc.Tensi, "/")
		if len(parts) == 2 {
			if s, e := strconv.Atoi(strings.TrimSpace(parts[0])); e == nil && s > 0 && sistole == 0 { sistole = s }
			if d, e := strconv.Atoi(strings.TrimSpace(parts[1])); e == nil && d > 0 && diastole == 0 { diastole = d }
		}
	}
	if sistole == 0 { sistole = intValOr(bridging.Sistole, "120") }
	if diastole == 0 { diastole = intValOr(bridging.Diastole, "80") }

	beratBadan := intValOr(req.Berat, "0")
	if beratBadan == 0 && ttvSrc != nil { beratBadan = intValOr(ttvSrc.BeratBadan, "0") }
	if beratBadan == 0 { beratBadan = intValOr(bridging.BeratBadan, "50") }

	tinggiBadan := intValOr(req.Tinggi, "0")
	if tinggiBadan == 0 && ttvSrc != nil { tinggiBadan = intValOr(ttvSrc.TinggiBadan, "0") }
	if tinggiBadan == 0 { tinggiBadan = intValOr(bridging.TinggiBadan, "160") }

	respRate := intValOr(req.Respirasi, "0")
	if respRate == 0 && ttvSrc != nil { respRate = intValOr(ttvSrc.Respirasi, "0") }
	if respRate == 0 { respRate = intValOr(bridging.Respirasi, "20") }

	heartRate := intValOr(req.Nadi, "0")
	if heartRate == 0 && ttvSrc != nil { heartRate = intValOr(ttvSrc.Nadi, "0") }
	if heartRate == 0 { heartRate = intValOr(bridging.Nadi, "80") }

	lingkarPerut := intValOr(req.LingkarPerut, "0")
	if lingkarPerut == 0 && ttvSrc != nil { lingkarPerut = intValOr(ttvSrc.LingkarPerut, "0") }
	if lingkarPerut == 0 { lingkarPerut = intValOr(bridging.LingkarPerut, "0") }

	// Build kunjungan data
	kunjunganData := map[string]interface{}{
		"noKunjungan":         nil,
		"noKartu":             req.NomorJaminan,
		"tglDaftar":           tglDaftar,
		"kdPoli":              kdPoli,
		"keluhan":             keluhan,
		"kdSadar":             kdKesadaran,
		"sistole":             sistole,
		"diastole":            diastole,
		"beratBadan":          beratBadan,
		"tinggiBadan":         tinggiBadan,
		"respRate":            respRate,
		"heartRate":           heartRate,
		"lingkarPerut":        lingkarPerut,
		"kdStatusPulang":      kdStatusPulang,
		"tglPulang":           tglPulang,
		"kdDokter":            kdDokter,
		"kdDiag1":             kdDiagnosa1,
		"kdDiag2":             nilIfEmpty(req.KdDiagnosa2),
		"kdDiag3":             nilIfEmpty(req.KdDiagnosa3),
		"kdPoliRujukInternal": nil,
		"rujukLanjut":         buildRujukLanjut(req.KdTacc, req.KdPPK, req.TglEstRujuk, req.KdSubSpesialis, req.KdSarana),
		"kdTacc":              kdTaccVal(req.KdTacc),
		"alasanTacc":          nilIfEmpty(req.AlasanTacc),
		"alergiMakan":         nilIfEmpty(req.AlergiMakan),
		"alergiUdara":         nilIfEmpty(req.AlergiUdara),
		"alergiObat":          nilIfEmpty(req.AlergiObat),
		"kdPrognosa":          kdPrognosa,
		"anamnesa":            anamnesa,
		"terapiObat":          terapiObat,
		"terapiNonObat":       terapiNonObat,
		"bmhp":                "bmhp",
		"suhu":                suhu,
	}

	// Send to PCare API
	result, err := h.client.Post("kunjungan/V1", kunjunganData)
	if err != nil {
		errorJSON(w, 500, "Gagal mengirim ke PCare: "+err.Error())
		return
	}

	// Parse response
	var resp struct {
		MetaData struct {
			Code    string `json:"code"`
			Message string `json:"message"`
		} `json:"metaData"`
		Response json.RawMessage `json:"response"`
	}
	if err := json.Unmarshal(result, &resp); err != nil {
		errorJSON(w, 500, "Gagal parse response: "+err.Error())
		return
	}

	if resp.MetaData.Code == "201" {
		// Extract noKunjungan from response
		noKunjungan := ""
		var responseArray []map[string]interface{}
		if err := json.Unmarshal(resp.Response, &responseArray); err == nil && len(responseArray) > 0 {
			if msg, ok := responseArray[0]["message"].(string); ok {
				noKunjungan = msg
			}
		}

		// Update database
		h.db.UpdateBridgingKunjungan(req.ID, noKunjungan, "Sudah")

		jsonResponse(w, 200, map[string]interface{}{
			"status":          "success",
			"message":         "Kunjungan berhasil dikirim",
			"nomor_kunjungan": noKunjungan,
		})
	} else {
		jsonResponse(w, 200, map[string]interface{}{
			"status":  "error",
			"message": "Gagal kirim: " + resp.MetaData.Message,
			"detail":  string(resp.Response),
		})
	}
}

// PostSyncKunjungan syncs kunjungan data from PCare to local bridging record.
func (h *Handler) PostSyncKunjungan(w http.ResponseWriter, r *http.Request) {
	var req struct {
		BridgingID         int64  `json:"bridging_id"`
		NomorKunjungan     string `json:"nomor_kunjungan"`
		KodeDokter         string `json:"kode_dokter"`
		NamaDokter         string `json:"nama_dokter"`
		KodeKesadaran      string `json:"kode_kesadaran"`
		NamaKesadaran      string `json:"nama_kesadaran"`
		KodeStatusPulang   string `json:"kode_status_pulang"`
		NamaStatusPulang   string `json:"nama_status_pulang"`
		KodeDiagnosa1      string `json:"kode_diagnosa1"`
		NamaDiagnosa1      string `json:"nama_diagnosa1"`
		KodeDiagnosa2      string `json:"kode_diagnosa2"`
		NamaDiagnosa2      string `json:"nama_diagnosa2"`
		KodeDiagnosa3      string `json:"kode_diagnosa3"`
		NamaDiagnosa3      string `json:"nama_diagnosa3"`
		Sistole            string `json:"sistole"`
		Diastole           string `json:"diastole"`
		BeratBadan         string `json:"berat"`
		TinggiBadan        string `json:"tinggi"`
		Nadi               string `json:"nadi"`
		Respirasi          string `json:"respirasi"`
		LingkarPerut       string `json:"lingkar_perut"`
		Subyektif          string `json:"subyektif"`
		TglKunjungan       string `json:"tgl_kunjungan"`
		TglPulang          string `json:"tgl_pulang"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	if req.BridgingID == 0 || req.NomorKunjungan == "" {
		errorJSON(w, 400, "bridging_id dan nomor_kunjungan wajib diisi")
		return
	}

	data := map[string]string{
		"nomor_kunjungan":     req.NomorKunjungan,
		"kode_dokter":         req.KodeDokter,
		"nama_dokter":         req.NamaDokter,
		"kode_kesadaran":      firstNonEmpty(req.KodeKesadaran, "01"),
		"nama_kesadaran":      req.NamaKesadaran,
		"kode_status_pulang":  firstNonEmpty(req.KodeStatusPulang, "3"),
		"nama_status_pulang":  req.NamaStatusPulang,
		"kode_diagnosa1":      req.KodeDiagnosa1,
		"nama_diagnosa1":      req.NamaDiagnosa1,
		"kode_diagnosa2":      req.KodeDiagnosa2,
		"nama_diagnosa2":      req.NamaDiagnosa2,
		"kode_diagnosa3":      req.KodeDiagnosa3,
		"nama_diagnosa3":      req.NamaDiagnosa3,
		"sistole":             firstNonEmpty(req.Sistole, "0"),
		"diastole":            firstNonEmpty(req.Diastole, "0"),
		"berat":               firstNonEmpty(req.BeratBadan, "0"),
		"tinggi":              firstNonEmpty(req.TinggiBadan, "0"),
		"nadi":                firstNonEmpty(req.Nadi, "0"),
		"respirasi":           firstNonEmpty(req.Respirasi, "0"),
		"lingkar_perut":       firstNonEmpty(req.LingkarPerut, "0"),
		"subyektif":           req.Subyektif,
		"tgl_kunjungan":       req.TglKunjungan,
		"tgl_pulang":          req.TglPulang,
		"status_kirim":        "Sudah",
	}

	if err := h.db.SyncBridgingFromPCare(req.BridgingID, data); err != nil {
		errorJSON(w, 500, "Gagal sinkronisasi: "+err.Error())
		return
	}

	jsonResponse(w, 200, map[string]interface{}{
		"status":  "success",
		"message": "Data kunjungan berhasil disinkronisasi dari PCare",
	})
}

// PostSyncPendaftaran syncs PCare pendaftaran data to local bridging records.
// Matches PCare entries to local records by no_kartu (no_peserta) and updates nomor_urut.
func (h *Handler) PostSyncPendaftaran(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Items []struct {
			NoRawat    string `json:"no_rawat"`
			NoRkmMedis string `json:"no_rkm_medis"`
			NoKartu    string `json:"no_kartu"`
			NomorUrut  string `json:"nomor_urut"`
			KdPoli     string `json:"kd_poli"`
		} `json:"items"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	if len(req.Items) == 0 {
		errorJSON(w, 400, "Tidak ada data untuk disinkronisasi")
		return
	}

	successCount := 0
	failCount := 0
	var results []map[string]string
	for _, item := range req.Items {
		if item.NoRawat == "" || item.NomorUrut == "" {
			failCount++
			results = append(results, map[string]string{
				"no_rawat": item.NoRawat,
				"status":   "error",
				"message":  "no_rawat atau nomor_urut kosong",
			})
			continue
		}
		kdPoli := h.db.GetMappingPoliPCare(item.KdPoli)
		bridgingID, err := h.db.EnsureBridgingExists(item.NoRawat, item.NoRkmMedis, item.NoKartu, kdPoli)
		if err != nil {
			failCount++
			results = append(results, map[string]string{
				"no_rawat": item.NoRawat,
				"status":   "error",
				"message":  "Gagal buat bridging: " + err.Error(),
			})
			continue
		}
		if err := h.db.UpdateBridgingPendaftaran(bridgingID, item.NomorUrut); err != nil {
			failCount++
			results = append(results, map[string]string{
				"no_rawat": item.NoRawat,
				"status":   "error",
				"message":  "Gagal update: " + err.Error(),
			})
			continue
		}
		successCount++
		results = append(results, map[string]string{
			"no_rawat":   item.NoRawat,
			"nomor_urut": item.NomorUrut,
			"status":     "success",
			"message":    "Berhasil",
		})
	}

	jsonResponse(w, 200, map[string]interface{}{
		"status":  "success",
		"message": fmt.Sprintf("%d berhasil, %d gagal", successCount, failCount),
		"success": successCount,
		"fail":    failCount,
		"results": results,
	})
}

// GetPrefillKunjungan returns pre-filled data for the Kirim Kunjungan modal.
// Uses composite pemeriksaan: nurse TTV (priority) + doctor SOAP/Plan.
func (h *Handler) GetPrefillKunjungan(w http.ResponseWriter, r *http.Request) {
	noRawat := r.URL.Query().Get("no_rawat")
	if noRawat == "" {
		errorJSON(w, 400, "no_rawat wajib diisi")
		return
	}

	// Get reg_periksa
	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		errorJSON(w, 404, "Data registrasi tidak ditemukan")
		return
	}

	// Get bridging
	bridging, _ := h.db.GetBridgingByNoRawat(noRawat)

	// Get composite pemeriksaan: nurse (TTV priority) + doctor (SOAP/Plan)
	nursePem, doctorPem := h.db.GetPemeriksaanRalanComposite(noRawat)

	// Fallback: generic pemeriksaan if both are nil
	fallbackPem, _ := h.db.GetPemeriksaanRalan(noRawat)

	// Get diagnosa 1/2/3
	d1, d2, d3 := h.db.GetDiagnosaPasienAll(noRawat)

	// Get mapping poli & dokter
	kdPoli := h.db.GetMappingPoliPCare(reg.KdPoli)
	kdDokter := h.db.GetMappingDokterPCare(reg.KdDokter)

	// Get resep obat
	terapiObat := h.db.GetResepObat(noRawat)

	// Get alergi
	alergiMakan, alergiUdara, alergiObat := h.db.GetAlergiPasien(reg.NoRkmMedis)

	// === TTV: prioritize nurse, fallback to doctor, then generic ===
	ttvSource := nursePem
	if ttvSource == nil {
		ttvSource = doctorPem
	}
	if ttvSource == nil {
		ttvSource = fallbackPem
	}

	// === SOAP: prioritize doctor for keluhan/anamnesa/plan ===
	soapSource := doctorPem
	if soapSource == nil {
		soapSource = fallbackPem
	}

	// Parse tensi -> sistole/diastole from TTV source
	sistole, diastole := "120", "80"
	if ttvSource != nil && ttvSource.Tensi != "" && strings.Contains(ttvSource.Tensi, "/") {
		parts := strings.Split(ttvSource.Tensi, "/")
		if len(parts) == 2 {
			if s := strings.TrimSpace(parts[0]); s != "" && s != "0" {
				sistole = s
			}
			if d := strings.TrimSpace(parts[1]); d != "" && d != "0" {
				diastole = d
			}
		}
	}

	// Map kesadaran text -> PCare code (from TTV source)
	kesadaranMap := map[string]string{
		"Compos Mentis": "01", "Somnolence": "02", "Sopor": "03", "Coma": "04",
	}
	kdKesadaran := "01"
	if ttvSource != nil && ttvSource.Kesadaran != "" {
		if code, ok := kesadaranMap[ttvSource.Kesadaran]; ok {
			kdKesadaran = code
		}
	}

	// Format dates
	tglDaftar := formatDateDDMMYYYY(reg.TglRegistrasi)

	// Resolve TTV fields from nurse/fallback
	suhu := "36"
	nadi := "80"
	respirasi := "20"
	berat := "50"
	tinggi := "160"
	lingkarPerut := "0"

	if ttvSource != nil {
		if ttvSource.Suhu != "" && ttvSource.Suhu != "0" {
			suhu = ttvSource.Suhu
		}
		if ttvSource.Nadi != "" && ttvSource.Nadi != "0" {
			nadi = ttvSource.Nadi
		}
		if ttvSource.Respirasi != "" && ttvSource.Respirasi != "0" {
			respirasi = ttvSource.Respirasi
		}
		if ttvSource.BeratBadan != "" && ttvSource.BeratBadan != "0" {
			berat = ttvSource.BeratBadan
		}
		if ttvSource.TinggiBadan != "" && ttvSource.TinggiBadan != "0" {
			tinggi = ttvSource.TinggiBadan
		}
		if ttvSource.LingkarPerut != "" && ttvSource.LingkarPerut != "0" {
			lingkarPerut = ttvSource.LingkarPerut
		}
	}

	// Resolve SOAP fields from doctor/fallback
	keluhan := ""
	anamnesa := ""
	terapiNonObat := "tidak ada"

	if soapSource != nil {
		if soapSource.Keluhan != "" {
			keluhan = soapSource.Keluhan
		}
		if soapSource.Pemeriksaan != "" {
			anamnesa = soapSource.Pemeriksaan
		}
		if soapSource.RTL != "" {
			terapiNonObat = soapSource.RTL
		}
	}

	// Fallback keluhan from bridging
	if keluhan == "" && bridging != nil && bridging.Subyektif != "" {
		keluhan = bridging.Subyektif
	}

	result := map[string]interface{}{
		"kdPoli":          kdPoli,
		"kdDokter":        kdDokter,
		"tglDaftar":       tglDaftar,
		"keluhan":         keluhan,
		"anamnesa":        anamnesa,
		"kdKesadaran":     kdKesadaran,
		"sistole":         sistole,
		"diastole":        diastole,
		"nadi":            nadi,
		"respirasi":       respirasi,
		"suhu":            suhu,
		"berat":           berat,
		"tinggi":          tinggi,
		"lingkarPerut":    lingkarPerut,
		"terapiObat":      terapiObat,
		"terapiNonObat":   terapiNonObat,
		"terapi":          terapiNonObat,
		"kdDiagnosa1":     d1.KdPenyakit,
		"nmDiagnosa1":     d1.NmPenyakit,
		"kdDiagnosa2":     func() string { if isValidICD10(d2.KdPenyakit) { return d2.KdPenyakit }; return "" }(),
		"nmDiagnosa2":     func() string { if isValidICD10(d2.KdPenyakit) { return d2.NmPenyakit }; return "" }(),
		"kdDiagnosa3":     func() string { if isValidICD10(d3.KdPenyakit) { return d3.KdPenyakit }; return "" }(),
		"nmDiagnosa3":     func() string { if isValidICD10(d3.KdPenyakit) { return d3.NmPenyakit }; return "" }(),
		"alergiMakan":     alergiMakan,
		"alergiUdara":     alergiUdara,
		"alergiObat":      alergiObat,
		"hasPemeriksaan":  ttvSource != nil || soapSource != nil,
	}

	jsonResponse(w, 200, result)
}

// GetRiwayatPasien returns local examination history and PCare visit history for a patient.
func (h *Handler) GetRiwayatPasien(w http.ResponseWriter, r *http.Request) {
	noRkmMedis := r.URL.Query().Get("no_rkm_medis")
	noKartu := r.URL.Query().Get("no_kartu")

	// If no_rkm_medis not provided but no_kartu available, look up from pasien table
	if noRkmMedis == "" && noKartu != "" {
		_ = h.db.QueryRow("SELECT no_rkm_medis FROM pasien WHERE no_peserta = ? LIMIT 1", noKartu).Scan(&noRkmMedis)
	}

	response := map[string]interface{}{
		"local":        []interface{}{},
		"pcare":        []interface{}{},
		"no_rkm_medis": noRkmMedis,
	}

	// Fetch local examination history with nurse + doctor details
	if noRkmMedis != "" {
		rows, err := h.db.Query(`SELECT
			rp.no_rawat, DATE_FORMAT(rp.tgl_registrasi,'%d-%m-%Y') as tgl,
			rp.jam_reg, IFNULL(pol.nm_poli,'') as poli,
			IFNULL(dok.nm_dokter,'') as dokter_periksa, rp.stts,
			IFNULL(bp.nomor_kunjungan,'') as nomor_kunjungan,
			IFNULL(bp.nomor_urut,'') as nomor_urut
			FROM reg_periksa rp
			LEFT JOIN poliklinik pol ON pol.kd_poli = rp.kd_poli
			LEFT JOIN dokter dok ON dok.kd_dokter = rp.kd_dokter
			LEFT JOIN mlite_bridging_pcare bp ON bp.no_rawat = rp.no_rawat
			WHERE rp.no_rkm_medis = ?
			ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
			LIMIT 20`, noRkmMedis)
		if err == nil {
			defer rows.Close()
			var localHistory []map[string]interface{}
			for rows.Next() {
				var noRawat, tgl, jam, poli, dokter, stts string
				var nomorKunjungan, nomorUrut string
				if err := rows.Scan(&noRawat, &tgl, &jam, &poli, &dokter, &stts,
					&nomorKunjungan, &nomorUrut); err != nil {
					continue
				}

				visit := map[string]interface{}{
					"no_rawat":         noRawat,
					"tgl":              tgl,
					"jam":              jam,
					"poli":             poli,
					"dokter":           dokter,
					"stts":             stts,
					"nomor_kunjungan":  nomorKunjungan,
					"nomor_urut":       nomorUrut,
					"pemeriksaan":      []map[string]string{},
					"diagnosa":         []map[string]string{},
				}

				// Fetch all pemeriksaan_ralan entries for this no_rawat
				pemRows, pemErr := h.db.Query(`SELECT
					DATE_FORMAT(pr.tgl_perawatan,'%d-%m-%Y') as tgl_pem,
					pr.jam_rawat, pr.nip,
					IFNULL(d.nm_dokter, IFNULL(pt.nama, pr.nip)) as nama_petugas,
					CASE WHEN d.kd_dokter IS NOT NULL THEN 'Dokter' ELSE 'Perawat' END as tipe,
					IFNULL(pr.keluhan,'') as keluhan,
					IFNULL(pr.pemeriksaan,'') as pemeriksaan,
					IFNULL(pr.penilaian,'') as penilaian,
					IFNULL(pr.rtl,'') as rtl,
					IFNULL(pr.instruksi,'') as instruksi,
					IFNULL(pr.evaluasi,'') as evaluasi,
					IFNULL(pr.suhu_tubuh,'') as suhu,
					IFNULL(pr.nadi,'') as nadi,
					IFNULL(pr.tensi,'') as tensi,
					IFNULL(pr.respirasi,'') as respirasi,
					IFNULL(pr.berat,'') as berat,
					IFNULL(pr.tinggi,'') as tinggi,
					IFNULL(pr.spo2,'') as spo2,
					IFNULL(pr.gcs,'') as gcs,
					pr.kesadaran,
					IFNULL(pr.alergi,'') as alergi,
					IFNULL(pr.lingkar_perut,'') as lingkar_perut
					FROM pemeriksaan_ralan pr
					LEFT JOIN dokter d ON d.kd_dokter = pr.nip
					LEFT JOIN petugas pt ON pt.nip = pr.nip
					WHERE pr.no_rawat = ?
					ORDER BY pr.tgl_perawatan, pr.jam_rawat`, noRawat)
				if pemErr == nil {
					var pemList []map[string]string
					for pemRows.Next() {
						var tglPem, jamRawat, nip, namaPetugas, tipe string
						var kel, pem, pen, rtl, instruksi, evaluasi string
						var suhu, nad, ten, resp, bb, tb, spo2, gcs, kesadaran, alergi, lp string
						if err := pemRows.Scan(&tglPem, &jamRawat, &nip, &namaPetugas, &tipe,
							&kel, &pem, &pen, &rtl, &instruksi, &evaluasi,
							&suhu, &nad, &ten, &resp, &bb, &tb, &spo2, &gcs,
							&kesadaran, &alergi, &lp); err != nil {
							continue
						}
						pemList = append(pemList, map[string]string{
							"tgl_perawatan": tglPem,
							"jam_rawat":     jamRawat,
							"nip":           nip,
							"nama_petugas":  namaPetugas,
							"tipe":          tipe,
							"keluhan":       kel,
							"pemeriksaan":   pem,
							"penilaian":     pen,
							"rtl":           rtl,
							"instruksi":     instruksi,
							"evaluasi":      evaluasi,
							"suhu":          suhu,
							"nadi":          nad,
							"tensi":         ten,
							"respirasi":     resp,
							"berat":         bb,
							"tinggi":        tb,
							"spo2":          spo2,
							"gcs":           gcs,
							"kesadaran":     kesadaran,
							"alergi":        alergi,
							"lingkar_perut": lp,
						})
					}
					pemRows.Close()
					visit["pemeriksaan"] = pemList
				}

				// Fetch all diagnoses for this no_rawat
				diagRows, diagErr := h.db.Query(`SELECT dp.kd_penyakit,
					IFNULL(p.nm_penyakit,'') as nm_penyakit, dp.prioritas
					FROM diagnosa_pasien dp
					LEFT JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
					WHERE dp.no_rawat = ? AND dp.status='Ralan'
					ORDER BY dp.prioritas`, noRawat)
				if diagErr == nil {
					var diagList []map[string]string
					for diagRows.Next() {
						var kd, nm, pri string
						if err := diagRows.Scan(&kd, &nm, &pri); err != nil {
							continue
						}
						diagList = append(diagList, map[string]string{
							"kd_penyakit":  kd,
							"nm_penyakit":  nm,
							"prioritas":    pri,
						})
					}
					diagRows.Close()
					visit["diagnosa"] = diagList
				}

				localHistory = append(localHistory, visit)
			}
			response["local"] = localHistory
		}
	}

	// Fetch PCare riwayat kunjungan
	if noKartu != "" {
		apiResult, err := h.client.Get("kunjungan/peserta/" + url.PathEscape(noKartu))
		if err == nil {
			var pcareResp struct {
				MetaData struct {
					Code    json.RawMessage `json:"code"`
					Message string          `json:"message"`
				} `json:"metaData"`
				Response json.RawMessage `json:"response"`
			}
			if json.Unmarshal(apiResult, &pcareResp) == nil {
				var visits []map[string]interface{}
				// Response may be {"count":N,"list":[...]} or a flat array
				var listWrapper struct {
					Count int                      `json:"count"`
					List  []map[string]interface{} `json:"list"`
				}
				if json.Unmarshal(pcareResp.Response, &listWrapper) == nil && listWrapper.List != nil {
					visits = listWrapper.List
				} else {
					json.Unmarshal(pcareResp.Response, &visits)
				}

				// For each visit, fetch tindakan
				for i, v := range visits {
					noKunjungan, _ := v["noKunjungan"].(string)
					if noKunjungan == "" {
						continue
					}
					tindakanData, tErr := h.client.Get("tindakan/kunjungan/" + url.PathEscape(noKunjungan))
					if tErr != nil {
						continue
					}
					var tResp struct {
						Response struct {
							Count int                      `json:"count"`
							List  []map[string]interface{} `json:"list"`
						} `json:"response"`
					}
					if json.Unmarshal(tindakanData, &tResp) == nil && tResp.Response.List != nil {
						visits[i]["tindakan"] = tResp.Response.List
					}
				}
				response["pcare"] = visits
			}
		}
	}

	jsonResponse(w, 200, response)
}

// GetDashboardData returns dashboard data for a date or date range.
func (h *Handler) GetDashboardData(w http.ResponseWriter, r *http.Request) {
	dateInput := r.URL.Query().Get("date")
	dateToInput := r.URL.Query().Get("date_to")
	if dateInput == "" {
		dateInput = time.Now().Format("02-01-2006")
	}

	date := convertDateFormat(dateInput)
	var dateTo string
	if dateToInput != "" {
		dateTo = convertDateFormat(dateToInput)
	}
	kdPjBpjs := h.db.GetKdPjBpjs()

	var rows []db.DashboardRow
	var err error
	if dateTo != "" {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs, dateTo)
	} else {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs)
	}
	if err != nil {
		errorJSON(w, 500, "Failed to query dashboard: "+err.Error())
		return
	}

	// Calculate summary
	total := len(rows)
	var sudahDaftar, sudahKunjungan, belumKirim, siapKirim, sudahPeriksa, batal int
	for _, row := range rows {
		if row.Stts == "Batal" {
			batal++
		}
		if row.Stts == "Sudah" {
			sudahPeriksa++
		}
		if db.NullStr(row.NomorUrut) != "" {
			sudahDaftar++
		}
		if db.NullStr(row.NomorKunjungan) != "" {
			sudahKunjungan++
		}
		if db.NullStr(row.NomorKunjungan) == "" && row.Stts != "Batal" {
			belumKirim++
		}
		if db.NullStr(row.NomorUrut) != "" && db.NullStr(row.NomorKunjungan) == "" && row.Stts == "Sudah" {
			siapKirim++
		}
	}

	jsonResponse(w, 200, map[string]interface{}{
		"rows":            rows,
		"date":            dateInput,
		"total":           total,
		"sudah_daftar":    sudahDaftar,
		"sudah_kunjungan": sudahKunjungan,
		"belum_kirim":     belumKirim,
		"siap_kirim":      siapKirim,
		"sudah_periksa":   sudahPeriksa,
		"batal":           batal,
	})
}

// GetMonitorData returns monitoring data for a date.
func (h *Handler) GetMonitorData(w http.ResponseWriter, r *http.Request) {
	dateInput := r.URL.Query().Get("date")
	if dateInput == "" {
		dateInput = time.Now().Format("02-01-2006")
	}

	date := convertDateFormat(dateInput)
	kdPjBpjs := h.db.GetKdPjBpjs()

	dashRows, err := h.db.GetDashboardRows(date, kdPjBpjs)
	if err != nil {
		errorJSON(w, 500, "Failed to query: "+err.Error())
		return
	}

	monitorRows, err := h.db.GetMonitorRows(date, kdPjBpjs)
	if err != nil {
		errorJSON(w, 500, "Failed to query monitor: "+err.Error())
		return
	}

	total := len(dashRows)
	var sudahDaftar, sudahKunjungan, belumKirim, siapKirim, sudahPeriksa, batal int
	for _, row := range dashRows {
		if row.Stts == "Batal" {
			batal++
		}
		if row.Stts == "Sudah" {
			sudahPeriksa++
		}
		if db.NullStr(row.NomorUrut) != "" {
			sudahDaftar++
		}
		if db.NullStr(row.NomorKunjungan) != "" {
			sudahKunjungan++
		}
		if db.NullStr(row.NomorKunjungan) == "" && row.Stts != "Batal" {
			belumKirim++
		}
		if db.NullStr(row.NomorUrut) != "" && db.NullStr(row.NomorKunjungan) == "" && row.Stts == "Sudah" {
			siapKirim++
		}
	}

	jsonResponse(w, 200, map[string]interface{}{
		"rows":            dashRows,
		"monitor_rows":    monitorRows,
		"date":            dateInput,
		"total":           total,
		"sudah_daftar":    sudahDaftar,
		"sudah_kunjungan": sudahKunjungan,
		"belum_kirim":     belumKirim,
		"siap_kirim":      siapKirim,
		"sudah_periksa":   sudahPeriksa,
		"batal":           batal,
		"total_mlite":     len(monitorRows),
	})
}

// GetSettings returns current settings.
func (h *Handler) GetSettings(w http.ResponseWriter, r *http.Request) {
	settings, err := h.db.GetSettings("pcare")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonResponse(w, 200, settings)
}

// PostSaveSettings saves settings.
func (h *Handler) PostSaveSettings(w http.ResponseWriter, r *http.Request) {
	var settings map[string]string
	if err := json.NewDecoder(r.Body).Decode(&settings); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	if err := h.db.SaveSettings("pcare", settings); err != nil {
		errorJSON(w, 500, "Failed to save settings: "+err.Error())
		return
	}

	// Reload config from DB
	dbSettings, _ := h.db.GetSettings("pcare")
	h.cfg.LoadFromDB(dbSettings)

	jsonResponse(w, 200, map[string]string{"status": "success", "message": "Pengaturan berhasil disimpan"})
}

// GetDataKunjunganBpjs returns BPJS visit data.
func (h *Handler) GetDataKunjunganBpjs(w http.ResponseWriter, r *http.Request) {
	dateInput := r.URL.Query().Get("date")
	if dateInput == "" {
		dateInput = time.Now().Format("02-01-2006")
	}
	date := convertDateFormat(dateInput)
	kdPjBpjs := h.db.GetKdPjBpjs()

	rows, err := h.db.GetDataKunjunganBpjs(date, kdPjBpjs)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonResponse(w, 200, map[string]interface{}{
		"rows":  rows,
		"date":  dateInput,
		"total": len(rows),
	})
}

// PostKirimPendaftaran sends a single patient registration to PCare Pendaftaran API.
// It auto-resolves patient data from local DB and sends to PCare, then updates bridging with noUrut.
func (h *Handler) PostKirimPendaftaran(w http.ResponseWriter, r *http.Request) {
	var req struct {
		NoRawat        string `json:"no_rawat"`
		NoKartu        string `json:"noKartu"`
		KdPoli         string `json:"kdPoli"`
		TglDaftar      string `json:"tglDaftar"`
		Keluhan        string `json:"keluhan"`
		KunjSakit      *bool  `json:"kunjSakit"`
		Sistole        int    `json:"sistole"`
		Diastole       int    `json:"diastole"`
		BeratBadan     int    `json:"beratBadan"`
		TinggiBadan    int    `json:"tinggiBadan"`
		RespRate       int    `json:"respRate"`
		HeartRate      int    `json:"heartRate"`
		LingkarPerut   int    `json:"lingkarPerut"`
		RujukBalik     int    `json:"rujukBalik"`
		KdTkp          string `json:"kdTkp"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}

	if req.NoRawat == "" {
		errorJSON(w, 400, "no_rawat wajib diisi")
		return
	}

	// Get reg_periksa
	regPeriksa, err := h.db.GetRegPeriksa(req.NoRawat)
	if err != nil {
		errorJSON(w, 404, "Data registrasi tidak ditemukan")
		return
	}

	// Get patient data
	pasien, err := h.db.GetPasienByNoRkm(regPeriksa.NoRkmMedis)
	if err != nil {
		errorJSON(w, 404, "Data pasien tidak ditemukan")
		return
	}

	// Resolve noKartu (BPJS card number)
	noKartu := req.NoKartu
	if noKartu == "" {
		noKartu = pasien.NoPeserta
	}
	if noKartu == "" {
		errorJSON(w, 400, "No. Kartu BPJS tidak ditemukan untuk pasien ini")
		return
	}

	// Resolve poli code
	kdPoli := req.KdPoli
	if kdPoli == "" {
		kdPoli = h.db.GetMappingPoliPCare(regPeriksa.KdPoli)
	}

	// Format date
	tglDaftar := req.TglDaftar
	if tglDaftar == "" {
		tglDaftar = formatDateDDMMYYYY(regPeriksa.TglRegistrasi)
	}

	// Resolve defaults
	keluhan := req.Keluhan
	if keluhan == "" {
		keluhan = "Keluhan umum"
	}
	kunjSakit := true
	if req.KunjSakit != nil {
		kunjSakit = *req.KunjSakit
	}
	sistole := req.Sistole
	if sistole == 0 { sistole = 120 }
	diastole := req.Diastole
	if diastole == 0 { diastole = 80 }
	beratBadan := req.BeratBadan
	if beratBadan == 0 { beratBadan = 60 }
	tinggiBadan := req.TinggiBadan
	if tinggiBadan == 0 { tinggiBadan = 165 }
	respRate := req.RespRate
	if respRate == 0 { respRate = 20 }
	heartRate := req.HeartRate
	if heartRate == 0 { heartRate = 80 }
	lingkarPerut := req.LingkarPerut
	kdTkp := req.KdTkp
	if kdTkp == "" { kdTkp = "10" }

	// Get pemeriksaan data if available for better defaults
	pem, _ := h.db.GetPemeriksaanRalan(req.NoRawat)
	if pem != nil {
		if pem.Keluhan != "" && req.Keluhan == "" {
			keluhan = pem.Keluhan
		}
		if pem.Tensi != "" && strings.Contains(pem.Tensi, "/") && req.Sistole == 0 {
			parts := strings.Split(pem.Tensi, "/")
			if len(parts) == 2 {
				if s, err := strconv.Atoi(strings.TrimSpace(parts[0])); err == nil && s > 0 { sistole = s }
				if d, err := strconv.Atoi(strings.TrimSpace(parts[1])); err == nil && d > 0 { diastole = d }
			}
		}
		if pem.BeratBadan != "" && pem.BeratBadan != "0" && req.BeratBadan == 0 {
			if bb, err := strconv.Atoi(pem.BeratBadan); err == nil && bb > 0 { beratBadan = bb }
		}
		if pem.TinggiBadan != "" && pem.TinggiBadan != "0" && req.TinggiBadan == 0 {
			if tb, err := strconv.Atoi(pem.TinggiBadan); err == nil && tb > 0 { tinggiBadan = tb }
		}
		if pem.Respirasi != "" && pem.Respirasi != "0" && req.RespRate == 0 {
			if rr, err := strconv.Atoi(pem.Respirasi); err == nil && rr > 0 { respRate = rr }
		}
		if pem.Nadi != "" && pem.Nadi != "0" && req.HeartRate == 0 {
			if hr, err := strconv.Atoi(pem.Nadi); err == nil && hr > 0 { heartRate = hr }
		}
		if pem.LingkarPerut != "" && pem.LingkarPerut != "0" && req.LingkarPerut == 0 {
			if lp, err := strconv.Atoi(pem.LingkarPerut); err == nil && lp > 0 { lingkarPerut = lp }
		}
	}

	// Build pendaftaran payload
	pendaftaranData := map[string]interface{}{
		"kdProviderPeserta": h.cfg.KodeFKTP,
		"tglDaftar":         tglDaftar,
		"noKartu":           noKartu,
		"kdPoli":            kdPoli,
		"keluhan":           keluhan,
		"kunjSakit":         kunjSakit,
		"sistole":           sistole,
		"diastole":          diastole,
		"beratBadan":        beratBadan,
		"tinggiBadan":       tinggiBadan,
		"respRate":          respRate,
		"heartRate":         heartRate,
		"lingkarPerut":      lingkarPerut,
		"rujukBalik":        req.RujukBalik,
		"kdTkp":             kdTkp,
	}

	// Send to PCare API
	result, err := h.client.Post("pendaftaran", pendaftaranData)
	if err != nil {
		errorJSON(w, 500, "Gagal mengirim ke PCare: "+err.Error())
		return
	}

	// Parse response
	var resp struct {
		MetaData struct {
			Code    json.RawMessage `json:"code"`
			Message string          `json:"message"`
		} `json:"metaData"`
		Response json.RawMessage `json:"response"`
	}
	if err := json.Unmarshal(result, &resp); err != nil {
		errorJSON(w, 500, "Gagal parse response: "+err.Error())
		return
	}

	// Check code (can be string or number)
	respCode := ""
	var codeStr string
	if json.Unmarshal(resp.MetaData.Code, &codeStr) == nil {
		respCode = codeStr
	} else {
		var codeNum float64
		if json.Unmarshal(resp.MetaData.Code, &codeNum) == nil {
			respCode = fmt.Sprintf("%.0f", codeNum)
		}
	}

	if respCode == "201" {
		// Extract noUrut from response
		noUrut := ""
		var respObj struct {
			Field   string `json:"field"`
			Message string `json:"message"`
		}
		if json.Unmarshal(resp.Response, &respObj) == nil && respObj.Message != "" {
			noUrut = respObj.Message
		}

		// Ensure bridging record exists and update with noUrut
		bridgingID, err := h.db.EnsureBridgingExists(req.NoRawat, regPeriksa.NoRkmMedis, noKartu, kdPoli)
		if err != nil {
			// Registration succeeded at PCare but local save failed
			jsonResponse(w, 200, map[string]interface{}{
				"status":    "partial",
				"message":   "Pendaftaran berhasil di PCare tapi gagal simpan lokal: " + err.Error(),
				"nomor_urut": noUrut,
			})
			return
		}
		h.db.UpdateBridgingPendaftaran(bridgingID, noUrut)

		jsonResponse(w, 200, map[string]interface{}{
			"status":     "success",
			"message":    "Pendaftaran berhasil dikirim ke PCare",
			"nomor_urut": noUrut,
		})
	} else {
		jsonResponse(w, 200, map[string]interface{}{
			"status":  "error",
			"message": "Gagal daftar: " + resp.MetaData.Message,
			"detail":  string(resp.Response),
		})
	}
}

// PostKirimSemuaPendaftaran sends registration to PCare for all unregistered patients on a given date.
// Uses parallel goroutines with a worker pool for better performance.
func (h *Handler) PostKirimSemuaPendaftaran(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Date    string `json:"date"`
		DateTo  string `json:"date_to"`
		Workers int    `json:"workers"` // optional: number of parallel workers (default 5, max 10)
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}

	dateInput := req.Date
	if dateInput == "" {
		dateInput = time.Now().Format("02-01-2006")
	}

	numWorkers := req.Workers
	if numWorkers <= 0 {
		numWorkers = 5
	}
	if numWorkers > 10 {
		numWorkers = 10
	}

	date := convertDateFormat(dateInput)
	var dateTo string
	if req.DateTo != "" {
		dateTo = convertDateFormat(req.DateTo)
	}
	kdPjBpjs := h.db.GetKdPjBpjs()

	var rows []db.DashboardRow
	var err error
	if dateTo != "" {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs, dateTo)
	} else {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs)
	}
	if err != nil {
		errorJSON(w, 500, "Gagal query data: "+err.Error())
		return
	}

	type resultItem struct {
		Index    int    `json:"index"`
		NoRawat  string `json:"no_rawat"`
		NmPasien string `json:"nm_pasien"`
		Status   string `json:"status"`
		Message  string `json:"message"`
		NoUrut   string `json:"nomor_urut"`
	}

	// Filter rows that need registration
	type pendaftaranJob struct {
		index int
		row   db.DashboardRow
	}
	var jobs []pendaftaranJob
	for i, row := range rows {
		if db.NullStr(row.NomorUrut) != "" || row.Stts == "Batal" {
			continue
		}
		jobs = append(jobs, pendaftaranJob{index: i, row: row})
	}

	if len(jobs) == 0 {
		jsonResponse(w, 200, map[string]interface{}{
			"status":  "success",
			"message": "Tidak ada pasien yang perlu didaftarkan",
			"success": 0,
			"fail":    0,
			"results": []resultItem{},
		})
		return
	}

	// Channel for jobs and results
	jobCh := make(chan pendaftaranJob, len(jobs))
	resultCh := make(chan resultItem, len(jobs))

	// Start worker goroutines
	var wg sync.WaitGroup
	for w := 0; w < numWorkers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for job := range jobCh {
				row := job.row
				res := resultItem{Index: job.index, NoRawat: row.NoRawat, NmPasien: row.NmPasien}

				// Get patient data
				pasien, err := h.db.GetPasienByNoRkm(row.NoRkmMedis)
				if err != nil || pasien.NoPeserta == "" {
					res.Status = "error"
					res.Message = "No. Kartu BPJS tidak ditemukan"
					resultCh <- res
					continue
				}

				// Resolve poli & date
				kdPoli := h.db.GetMappingPoliPCare(row.KdPoli)
				tglDaftar := row.TglRegistrasi // already dd-MM-yyyy from SQL DATE_FORMAT

				// Get pemeriksaan for better defaults
				sistole, diastole, beratBadan, tinggiBadan := 120, 80, 60, 165
				respRate, heartRate, lingkarPerut := 20, 80, 0
				keluhan := "Keluhan umum"

				pem, _ := h.db.GetPemeriksaanRalan(row.NoRawat)
				if pem != nil {
					if pem.Keluhan != "" { keluhan = pem.Keluhan }
					if pem.Tensi != "" && strings.Contains(pem.Tensi, "/") {
						parts := strings.Split(pem.Tensi, "/")
						if len(parts) == 2 {
							if s, e := strconv.Atoi(strings.TrimSpace(parts[0])); e == nil && s > 0 { sistole = s }
							if d, e := strconv.Atoi(strings.TrimSpace(parts[1])); e == nil && d > 0 { diastole = d }
						}
					}
					if pem.BeratBadan != "" && pem.BeratBadan != "0" {
						if bb, e := strconv.Atoi(pem.BeratBadan); e == nil && bb > 0 { beratBadan = bb }
					}
					if pem.TinggiBadan != "" && pem.TinggiBadan != "0" {
						if tb, e := strconv.Atoi(pem.TinggiBadan); e == nil && tb > 0 { tinggiBadan = tb }
					}
					if pem.Respirasi != "" && pem.Respirasi != "0" {
						if rr, e := strconv.Atoi(pem.Respirasi); e == nil && rr > 0 { respRate = rr }
					}
					if pem.Nadi != "" && pem.Nadi != "0" {
						if hr, e := strconv.Atoi(pem.Nadi); e == nil && hr > 0 { heartRate = hr }
					}
					if pem.LingkarPerut != "" && pem.LingkarPerut != "0" {
						if lp, e := strconv.Atoi(pem.LingkarPerut); e == nil && lp > 0 { lingkarPerut = lp }
					}
				}

				pendaftaranData := map[string]interface{}{
					"kdProviderPeserta": h.cfg.KodeFKTP,
					"tglDaftar":         tglDaftar,
					"noKartu":           pasien.NoPeserta,
					"kdPoli":            kdPoli,
					"keluhan":           keluhan,
					"kunjSakit":         true,
					"sistole":           sistole,
					"diastole":          diastole,
					"beratBadan":        beratBadan,
					"tinggiBadan":       tinggiBadan,
					"respRate":          respRate,
					"heartRate":         heartRate,
					"lingkarPerut":      lingkarPerut,
					"rujukBalik":        0,
					"kdTkp":             "10",
				}

				apiResult, err := h.client.Post("pendaftaran", pendaftaranData)
				if err != nil {
					res.Status = "error"
					res.Message = "Gagal kirim: " + err.Error()
					log.Printf("[BULK DAFTAR] %s (%s) API ERROR: %s", row.NoRawat, row.NmPasien, err.Error())
					resultCh <- res
					continue
				}

				var pcareResp struct {
					MetaData struct {
						Code    json.RawMessage `json:"code"`
						Message string          `json:"message"`
					} `json:"metaData"`
					Response json.RawMessage `json:"response"`
				}
				if err := json.Unmarshal(apiResult, &pcareResp); err != nil {
					res.Status = "error"
					res.Message = "Gagal parse response"
					resultCh <- res
					continue
				}

				respCode := ""
				var cs string
				if json.Unmarshal(pcareResp.MetaData.Code, &cs) == nil {
					respCode = cs
				} else {
					var cn float64
					if json.Unmarshal(pcareResp.MetaData.Code, &cn) == nil {
						respCode = fmt.Sprintf("%.0f", cn)
					}
				}

				if respCode == "201" {
					noUrut := ""
					var respObj struct {
						Field   string `json:"field"`
						Message string `json:"message"`
					}
					if json.Unmarshal(pcareResp.Response, &respObj) == nil && respObj.Message != "" {
						noUrut = respObj.Message
					}

					bridgingID, _ := h.db.EnsureBridgingExists(row.NoRawat, row.NoRkmMedis, pasien.NoPeserta, kdPoli)
					if bridgingID > 0 {
						h.db.UpdateBridgingPendaftaran(bridgingID, noUrut)
					}

					res.Status = "success"
					res.Message = "Berhasil"
					res.NoUrut = noUrut
				} else {
					res.Status = "error"
					// Extract detail from PCare response field
					detail := string(pcareResp.Response)
					var detailStr string
					if json.Unmarshal(pcareResp.Response, &detailStr) == nil && detailStr != "" {
						detail = detailStr
					}
					if detail != "" && detail != "null" && detail != "\"\"" {
						res.Message = pcareResp.MetaData.Message + ": " + detail
					} else {
						res.Message = pcareResp.MetaData.Message
					}
					log.Printf("[BULK DAFTAR] %s (%s) ERROR code=%s msg=%s response=%s", row.NoRawat, row.NmPasien, respCode, pcareResp.MetaData.Message, detail)
				}
				resultCh <- res
			}
		}()
	}

	// Send all jobs to workers
	for _, job := range jobs {
		jobCh <- job
	}
	close(jobCh)

	// Wait for all workers to finish, then close results channel
	go func() {
		wg.Wait()
		close(resultCh)
	}()

	// Collect results
	var results []resultItem
	successCount := 0
	failCount := 0
	for res := range resultCh {
		results = append(results, res)
		if res.Status == "success" {
			successCount++
		} else {
			failCount++
		}
	}

	// Sort results by original index for consistent ordering
	sort.Slice(results, func(i, j int) bool {
		return results[i].Index < results[j].Index
	})

	jsonResponse(w, 200, map[string]interface{}{
		"status":  "success",
		"message": fmt.Sprintf("Selesai: %d berhasil, %d gagal (parallel %d workers)", successCount, failCount, numWorkers),
		"success": successCount,
		"fail":    failCount,
		"total":   len(jobs),
		"results": results,
	})
}

// PostKirimSemuaKunjungan sends kunjungan to PCare for all eligible patients on a given date.
// Eligible: nomor_urut exists, nomor_kunjungan empty, stts=Sudah, apotek has served.
func (h *Handler) PostKirimSemuaKunjungan(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Date    string `json:"date"`
		DateTo  string `json:"date_to"`
		Workers int    `json:"workers"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}

	dateInput := req.Date
	if dateInput == "" {
		dateInput = time.Now().Format("02-01-2006")
	}

	numWorkers := req.Workers
	if numWorkers <= 0 {
		numWorkers = 5
	}
	if numWorkers > 10 {
		numWorkers = 10
	}

	date := convertDateFormat(dateInput)
	var dateTo string
	if req.DateTo != "" {
		dateTo = convertDateFormat(req.DateTo)
	}
	kdPjBpjs := h.db.GetKdPjBpjs()

	var rows []db.DashboardRow
	var err error
	if dateTo != "" {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs, dateTo)
	} else {
		rows, err = h.db.GetDashboardRows(date, kdPjBpjs)
	}
	if err != nil {
		errorJSON(w, 500, "Gagal query data: "+err.Error())
		return
	}

	type resultItem struct {
		Index         int    `json:"index"`
		NoRawat       string `json:"no_rawat"`
		NmPasien      string `json:"nm_pasien"`
		Status        string `json:"status"`
		Message       string `json:"message"`
		NoKunjungan   string `json:"nomor_kunjungan"`
	}

	type kunjunganJob struct {
		index int
		row   db.DashboardRow
	}

	// Filter: registered (nomor_urut), no kunjungan yet, stts=Sudah, apotek served
	var jobs []kunjunganJob
	for i, row := range rows {
		if db.NullStr(row.NomorUrut) == "" || db.NullStr(row.NomorKunjungan) != "" || row.Stts != "Sudah" {
			continue
		}
		if !row.ApotekServed {
			continue
		}
		jobs = append(jobs, kunjunganJob{index: i, row: row})
	}

	if len(jobs) == 0 {
		jsonResponse(w, 200, map[string]interface{}{
			"status":  "success",
			"message": "Tidak ada pasien yang memenuhi syarat kirim kunjungan",
			"success": 0,
			"fail":    0,
			"results": []resultItem{},
		})
		return
	}

	jobCh := make(chan kunjunganJob, len(jobs))
	resultCh := make(chan resultItem, len(jobs))

	var wg sync.WaitGroup
	for w := 0; w < numWorkers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for job := range jobCh {
				row := job.row
				res := resultItem{Index: job.index, NoRawat: row.NoRawat, NmPasien: row.NmPasien}

				// Get bridging data
				bridging, err := h.db.GetBridgingByNoRawat(row.NoRawat)
				if err != nil {
					res.Status = "error"
					res.Message = "Data bridging tidak ditemukan: " + err.Error()
					resultCh <- res
					continue
				}

				regPeriksa, err := h.db.GetRegPeriksa(row.NoRawat)
				if err != nil {
					res.Status = "error"
					res.Message = "Data registrasi tidak ditemukan"
					resultCh <- res
					continue
				}

				// Use composite pemeriksaan
				nursePem, doctorPem := h.db.GetPemeriksaanRalanComposite(row.NoRawat)
				fallbackPem, _ := h.db.GetPemeriksaanRalan(row.NoRawat)

				ttvSrc := nursePem
				if ttvSrc == nil { ttvSrc = doctorPem }
				if ttvSrc == nil { ttvSrc = fallbackPem }

				soapSrc := doctorPem
				if soapSrc == nil { soapSrc = fallbackPem }

				// Resolve codes
				kdPoli := h.db.GetMappingPoliPCare(regPeriksa.KdPoli)
				kdDokter := h.db.GetMappingDokterPCare(regPeriksa.KdDokter)
				kdDiagnosa1 := h.db.GetDiagnosaPasien(row.NoRawat)
				d1, d2, d3 := h.db.GetDiagnosaPasienAll(row.NoRawat)
				terapiObat := h.db.GetResepObat(row.NoRawat)
				alergiMakan, alergiUdara, alergiObat := h.db.GetAlergiPasien(regPeriksa.NoRkmMedis)

				tglDaftar := formatDateDDMMYYYY(regPeriksa.TglRegistrasi)

				// SOAP from doctor
				keluhan := "Keluhan umum"
				anamnesa := "Anamnesa"
				terapiNonObat := "tidak ada"
				if soapSrc != nil {
					if soapSrc.Keluhan != "" { keluhan = soapSrc.Keluhan }
					if soapSrc.Pemeriksaan != "" { anamnesa = soapSrc.Pemeriksaan }
					if soapSrc.RTL != "" { terapiNonObat = soapSrc.RTL }
				}

				// TTV from nurse
				sistole, diastole := 120, 80
				if ttvSrc != nil && ttvSrc.Tensi != "" && strings.Contains(ttvSrc.Tensi, "/") {
					parts := strings.Split(ttvSrc.Tensi, "/")
					if len(parts) == 2 {
						if s, e := strconv.Atoi(strings.TrimSpace(parts[0])); e == nil && s > 0 { sistole = s }
						if d, e := strconv.Atoi(strings.TrimSpace(parts[1])); e == nil && d > 0 { diastole = d }
					}
				}

				suhuFloat := 36.0
				nadi, respRate := 80, 20
				beratBadan, tinggiBadan, lingkarPerut := 50, 160, 0
				if ttvSrc != nil {
					suhuFloat = parseSuhuFloat(ttvSrc.Suhu)
					if v := parseFloatToInt(ttvSrc.Nadi); v > 0 { nadi = v }
					if v := parseFloatToInt(ttvSrc.Respirasi); v > 0 { respRate = v }
					if v := parseFloatToInt(ttvSrc.BeratBadan); v > 0 { beratBadan = v }
					if v := parseFloatToInt(ttvSrc.TinggiBadan); v > 0 { tinggiBadan = v }
					if v := parseFloatToInt(ttvSrc.LingkarPerut); v > 0 { lingkarPerut = v }
				}

				// Clamp TTV to PCare valid ranges
				sistole = clampInt(sistole, 60, 300)
				diastole = clampInt(diastole, 30, 200)
				respRate = clampInt(respRate, 5, 70)
				nadi = clampInt(nadi, 20, 200)
				beratBadan = clampInt(beratBadan, 1, 300)
				tinggiBadan = clampInt(tinggiBadan, 20, 300)

				_ = kdDiagnosa1 // already used for d1

				// Validate diagnosa
				if !isValidICD10(d1.KdPenyakit) {
					res.Status = "error"
					res.Message = "Diagnosa utama tidak valid: " + d1.KdPenyakit
					resultCh <- res
					continue
				}

				kunjunganData := map[string]interface{}{
					"noKunjungan":         nil,
					"noKartu":             db.NullStr(row.NomorJaminan),
					"tglDaftar":           tglDaftar,
					"kdPoli":              kdPoli,
					"keluhan":             keluhan,
					"kdSadar":             "01",
					"sistole":             sistole,
					"diastole":            diastole,
					"beratBadan":          beratBadan,
					"tinggiBadan":         tinggiBadan,
					"respRate":            respRate,
					"heartRate":           nadi,
					"lingkarPerut":        lingkarPerut,
					"kdStatusPulang":      "3",
					"tglPulang":           tglDaftar,
					"kdDokter":            kdDokter,
					"kdDiag1":             d1.KdPenyakit,
					"kdDiag2":             nilIfValidICD10(d2.KdPenyakit),
					"kdDiag3":             nilIfValidICD10(d3.KdPenyakit),
					"kdPoliRujukInternal": nil,
					"rujukLanjut":         nil,
					"kdTacc":              -1,
					"alasanTacc":          nil,
					"alergiMakan":         firstNonEmpty(alergiMakan, "00"),
					"alergiUdara":         firstNonEmpty(alergiUdara, "00"),
					"alergiObat":          firstNonEmpty(alergiObat, "00"),
					"kdPrognosa":          "01",
					"anamnesa":            anamnesa,
					"terapiObat":          terapiObat,
					"terapiNonObat":       terapiNonObat,
					"bmhp":               "bmhp",
					"suhu":               suhuFloat,
				}

				log.Printf("[BULK KUNJ] %s suhu=%.1f resp=%d nadi=%d bb=%d tb=%d diag=%s",
					row.NoRawat, suhuFloat, respRate, nadi, beratBadan, tinggiBadan, d1.KdPenyakit)

				// Retry loop: PK_DatKunjungan can be a race condition from parallel sends
				const maxRetries = 3
				for attempt := 1; attempt <= maxRetries; attempt++ {

				apiResult, err := h.client.Post("kunjungan/V1", kunjunganData)
				if err != nil {
					res.Status = "error"
					res.Message = "Gagal kirim: " + err.Error()
					log.Printf("[BULK KUNJ] %s ERROR: %s", row.NoRawat, err.Error())
					break
				}

				log.Printf("[BULK KUNJ] %s RESPONSE (attempt %d): %s", row.NoRawat, attempt, string(apiResult))

				var pcareResp struct {
					MetaData struct {
						Code    json.RawMessage `json:"code"`
						Message string          `json:"message"`
					} `json:"metaData"`
					Response json.RawMessage `json:"response"`
				}
				if err := json.Unmarshal(apiResult, &pcareResp); err != nil {
					res.Status = "error"
					res.Message = "Gagal parse response"
					break
				}

				respCode := ""
				var cs string
				if json.Unmarshal(pcareResp.MetaData.Code, &cs) == nil {
					respCode = cs
				} else {
					var cn float64
					if json.Unmarshal(pcareResp.MetaData.Code, &cn) == nil {
						respCode = fmt.Sprintf("%.0f", cn)
					}
				}

				if respCode == "201" {
					noKunjungan := ""
					var responseArray []map[string]interface{}
					if json.Unmarshal(pcareResp.Response, &responseArray) == nil && len(responseArray) > 0 {
						if msg, ok := responseArray[0]["message"].(string); ok {
							noKunjungan = msg
						}
					}
					h.db.UpdateBridgingKunjungan(bridging.ID, noKunjungan, "Sudah")
					res.Status = "success"
					res.Message = "Berhasil"
					res.NoKunjungan = noKunjungan
					break
				} else if strings.Contains(pcareResp.MetaData.Message, "PK_DatKunjungan") ||
					strings.Contains(pcareResp.MetaData.Message, "duplicate key") {
					// PK_DatKunjungan can be a race condition from parallel workers
					// Retry with delay before falling back to riwayat sync
					if attempt < maxRetries {
						log.Printf("[BULK KUNJ] %s PK_DatKunjungan race condition, retry %d/%d", row.NoRawat, attempt, maxRetries)
						time.Sleep(time.Duration(attempt) * time.Second)
						continue
					}
					// Final attempt failed — try to sync from riwayat
					log.Printf("[BULK KUNJ] %s PK_DatKunjungan after %d retries, checking riwayat", row.NoRawat, maxRetries)
					noKartu := db.NullStr(row.NomorJaminan)
					riwayatData, rErr := h.client.Get("kunjungan/peserta/" + url.PathEscape(noKartu))
					if rErr == nil {
						var rResp struct {
							Response struct {
								List []map[string]interface{} `json:"list"`
							} `json:"response"`
						}
						if json.Unmarshal(riwayatData, &rResp) == nil {
							for _, k := range rResp.Response.List {
								if tglK, _ := k["tglKunjungan"].(string); tglK == tglDaftar {
									if nk, _ := k["noKunjungan"].(string); nk != "" {
										h.db.UpdateBridgingKunjungan(bridging.ID, nk, "Sudah")
										res.Status = "success"
										res.Message = "Sudah ada di PCare, sinkronisasi"
										res.NoKunjungan = nk
										break
									}
								}
							}
						}
					}
					if res.Status != "success" {
						res.Status = "error"
						res.Message = "Kunjungan sudah ada di PCare (duplikat)"
					}
					break
				} else {
					res.Status = "error"
					res.Message = pcareResp.MetaData.Message
					break
				}

				} // end retry loop
				resultCh <- res
			}
		}()
	}

	for _, job := range jobs {
		jobCh <- job
	}
	close(jobCh)

	go func() {
		wg.Wait()
		close(resultCh)
	}()

	var results []resultItem
	successCount := 0
	failCount := 0
	for res := range resultCh {
		results = append(results, res)
		if res.Status == "success" {
			successCount++
		} else {
			failCount++
		}
	}

	sort.Slice(results, func(i, j int) bool {
		return results[i].Index < results[j].Index
	})

	jsonResponse(w, 200, map[string]interface{}{
		"status":  "success",
		"message": fmt.Sprintf("Selesai: %d berhasil, %d gagal (parallel %d workers)", successCount, failCount, numWorkers),
		"success": successCount,
		"fail":    failCount,
		"total":   len(jobs),
		"results": results,
	})
}

// PostBatchRiwayat checks riwayat kunjungan for multiple patients in parallel.
func (h *Handler) PostBatchRiwayat(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Items   []struct {
			NoKartu  string `json:"noKartu"`
			TglDaftar string `json:"tglDaftar"`
		} `json:"items"`
		Workers int `json:"workers"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	if len(req.Items) == 0 {
		jsonResponse(w, 200, map[string]interface{}{"results": []interface{}{}})
		return
	}
	if len(req.Items) > 200 {
		errorJSON(w, 400, "Maksimal 200 item per batch")
		return
	}

	numWorkers := req.Workers
	if numWorkers <= 0 {
		numWorkers = 5
	}
	if numWorkers > 10 {
		numWorkers = 10
	}

	type riwayatItem struct {
		NoKartu   string `json:"noKartu"`
		TglDaftar string `json:"tglDaftar"`
	}
	type riwayatResult struct {
		NoKartu     string      `json:"noKartu"`
		TglDaftar   string      `json:"tglDaftar"`
		Found       bool        `json:"found"`
		Kunjungan   interface{} `json:"kunjungan"`
		Error       string      `json:"error,omitempty"`
	}

	// Deduplicate by noKartu (same noKartu may appear multiple times)
	type job struct {
		index int
		item  riwayatItem
	}

	jobCh := make(chan job, len(req.Items))
	resultCh := make(chan riwayatResult, len(req.Items))

	var wg sync.WaitGroup
	for w := 0; w < numWorkers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for j := range jobCh {
				res := riwayatResult{NoKartu: j.item.NoKartu, TglDaftar: j.item.TglDaftar}
				if j.item.NoKartu == "" {
					res.Error = "noKartu kosong"
					resultCh <- res
					continue
				}
				apiResult, err := h.client.Get("kunjungan/peserta/" + url.PathEscape(j.item.NoKartu))
				if err != nil {
					res.Error = err.Error()
					resultCh <- res
					continue
				}

				var pcareResp struct {
					MetaData struct {
						Code    json.RawMessage `json:"code"`
						Message string          `json:"message"`
					} `json:"metaData"`
					Response json.RawMessage `json:"response"`
				}
				if err := json.Unmarshal(apiResult, &pcareResp); err != nil {
					res.Error = "parse error"
					resultCh <- res
					continue
				}

				// Parse visits list — response may be {"count":N,"list":[...]} or a flat array
				var visits []map[string]interface{}
				var listWrapper struct {
					Count int                      `json:"count"`
					List  []map[string]interface{} `json:"list"`
				}
				if err := json.Unmarshal(pcareResp.Response, &listWrapper); err == nil && listWrapper.List != nil {
					visits = listWrapper.List
				} else {
					json.Unmarshal(pcareResp.Response, &visits)
				}
				for _, k := range visits {
					tglKunj := ""
					if v, ok := k["tglKunjungan"].(string); ok {
						tglKunj = v
					} else if v, ok := k["tglDaftar"].(string); ok {
						tglKunj = v
					}
					if tglKunj == j.item.TglDaftar {
						res.Found = true
						res.Kunjungan = k
						break
					}
				}
				resultCh <- res
			}
		}()
	}

	for i, item := range req.Items {
		jobCh <- job{index: i, item: riwayatItem{NoKartu: item.NoKartu, TglDaftar: item.TglDaftar}}
	}
	close(jobCh)

	go func() {
		wg.Wait()
		close(resultCh)
	}()

	var results []riwayatResult
	for res := range resultCh {
		results = append(results, res)
	}

	jsonResponse(w, 200, map[string]interface{}{"results": results})
}

// === Helper Functions ===

func firstNonEmpty(vals ...string) string {
	for _, v := range vals {
		if v != "" && v != "0" {
			return v
		}
	}
	if len(vals) > 0 {
		return vals[len(vals)-1]
	}
	return ""
}

func nilIfEmpty(s string) interface{} {
	if s == "" {
		return nil
	}
	return s
}

func kdTaccVal(kdTacc *int) interface{} {
	if kdTacc == nil {
		return -1
	}
	return *kdTacc
}

func buildRujukLanjut(kdTacc *int, kdPPK, tglEstRujuk, kdSubSpesialis, kdSarana string) interface{} {
	if kdTacc == nil || *kdTacc < 0 || kdPPK == "" {
		return nil
	}
	tgl := tglEstRujuk
	if tgl == "" {
		tgl = time.Now().Format("02-01-2006")
	}
	return map[string]interface{}{
		"kdPPK":          kdPPK,
		"tglEstRujuk":    tgl,
		"kdSubSpesialis": nilIfEmpty(kdSubSpesialis),
		"kdSarana":       nilIfEmpty(kdSarana),
	}
}

func intValOr(vals ...string) int {
	for _, v := range vals {
		if v != "" && v != "0" {
			n, err := strconv.Atoi(v)
			if err == nil && n > 0 {
				return n
			}
		}
	}
	return 0
}

// parseSuhu parses suhu string ("35.7", ".36.2", "36") and returns PCare format "35,7".
func parseSuhu(s string) string {
	s = strings.TrimSpace(s)
	for strings.HasPrefix(s, ".") {
		s = s[1:]
	}
	if s == "" || s == "0" {
		return "36,0"
	}
	f, err := strconv.ParseFloat(s, 64)
	if err != nil || f <= 0 {
		return "36,0"
	}
	if f < 30 { f = 36.0 }
	if f > 42 { f = 42.0 }
	return strings.Replace(fmt.Sprintf("%.1f", f), ".", ",", 1)
}

// parseSuhuFloat parses suhu string and returns float64 for PCare JSON payload.
func parseSuhuFloat(s string) float64 {
	s = strings.TrimSpace(s)
	for strings.HasPrefix(s, ".") {
		s = s[1:]
	}
	if s == "" || s == "0" {
		return 36.0
	}
	f, err := strconv.ParseFloat(s, 64)
	if err != nil || f <= 0 {
		return 36.0
	}
	if f < 30 { f = 36.0 }
	if f > 42 { f = 42.0 }
	// Round to 1 decimal place
	return math.Round(f*10) / 10
}

// parseFloatToInt parses a numeric string (int or float) and returns rounded int.
func parseFloatToInt(s string) int {
	s = strings.TrimSpace(s)
	for strings.HasPrefix(s, ".") {
		s = s[1:]
	}
	if s == "" || s == "0" {
		return 0
	}
	f, err := strconv.ParseFloat(s, 64)
	if err != nil || f <= 0 {
		return 0
	}
	return int(math.Round(f))
}

// clampInt clamps value to [min,max].
func clampInt(v, mn, mx int) int {
	if v < mn { return mn }
	if v > mx { return mx }
	return v
}

// isValidICD10 checks if a diagnosa code looks valid (not placeholder like "kode").
func isValidICD10(code string) bool {
	code = strings.TrimSpace(code)
	if code == "" {
		return false
	}
	if strings.EqualFold(code, "kode") || strings.EqualFold(code, "diagnosa") {
		return false
	}
	if len(code) < 2 {
		return false
	}
	c := code[0]
	return (c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z')
}

// nilIfValidICD10 returns nil if code is empty or invalid ICD-10.
func nilIfValidICD10(s string) interface{} {
	if !isValidICD10(s) {
		return nil
	}
	return s
}

// convertDateFormat converts dd-mm-yyyy to yyyy-mm-dd.
func convertDateFormat(dateInput string) string {
	parts := strings.Split(dateInput, "-")
	if len(parts) == 3 {
		return parts[2] + "-" + parts[1] + "-" + parts[0]
	}
	return time.Now().Format("2006-01-02")
}

// formatDateDDMMYYYY converts yyyy-mm-dd to dd-mm-yyyy.
func formatDateDDMMYYYY(date string) string {
	parts := strings.Split(date, "-")
	if len(parts) == 3 {
		return parts[2] + "-" + parts[1] + "-" + parts[0]
	}
	return time.Now().Format("02-01-2006")
}
