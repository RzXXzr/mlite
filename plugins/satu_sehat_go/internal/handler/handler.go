package handler

import (
	"encoding/json"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"

	"satu-sehat-go/internal/auth"
	"satu-sehat-go/internal/config"
	"satu-sehat-go/internal/db"
	"satu-sehat-go/internal/fhir"
)

// Handler aggregates all HTTP handlers for the Satu Sehat module.
type Handler struct {
	cfg    *config.Config
	db     *db.DB
	client *auth.Client
}

// New creates a new Handler.
func New(cfg *config.Config, database *db.DB, client *auth.Client) *Handler {
	return &Handler{cfg: cfg, db: database, client: client}
}

// jsonOK writes a JSON response with 200 status.
func jsonOK(w http.ResponseWriter, v interface{}) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(v)
}

// jsonError writes a JSON error response.
func jsonError(w http.ResponseWriter, status int, msg string) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(map[string]string{"error": msg})
}

// rawJSON writes raw JSON bytes to the response.
func rawJSON(w http.ResponseWriter, data []byte) {
	w.Header().Set("Content-Type", "application/json")
	w.Write(data)
}

// jsonSkipped returns a 200 response indicating no data to send.
func jsonSkipped(w http.ResponseWriter, pesan string) {
	jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": pesan})
}

// fhirResult wraps a FHIR API response with status info.
func fhirResult(w http.ResponseWriter, resp []byte, resourceName, id string) {
	if id != "" {
		result := map[string]interface{}{
			"status": "success",
			"pesan":  "Sukses mengirim " + resourceName,
			"id":     id,
		}
		if resp != nil {
			result["response"] = json.RawMessage(resp)
		}
		jsonOK(w, result)
	} else {
		result := map[string]interface{}{
			"status": "error",
			"pesan":  "Gagal mengirim " + resourceName,
		}
		if resp != nil {
			result["response"] = json.RawMessage(resp)
		}
		jsonOK(w, result)
	}
}

// =========================================================================
// Token / Auth
// =========================================================================

// GetToken returns the current access token.
func (h *Handler) GetToken(w http.ResponseWriter, r *http.Request) {
	token, err := h.client.GetAccessToken()
	if err != nil {
		jsonError(w, 500, "Gagal mendapatkan access token: "+err.Error())
		return
	}
	jsonOK(w, map[string]string{"access_token": token})
}

// =========================================================================
// Practitioner
// =========================================================================

// GetPraktisi searches for a practitioner by NIK.
func (h *Handler) GetPraktisi(w http.ResponseWriter, r *http.Request) {
	nik := r.URL.Query().Get("nik")
	if nik == "" {
		nik = r.FormValue("nik_dokter")
	}
	if nik == "" {
		jsonError(w, 400, "parameter nik kosong")
		return
	}
	resp, err := h.client.GetPractitionerByNIK(nik)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// GetPraktisiID returns only the IHS ID for a practitioner.
func (h *Handler) GetPraktisiID(w http.ResponseWriter, r *http.Request) {
	nik := chi.URLParam(r, "nik")
	resp, err := h.client.GetPractitionerByNIK(nik)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	id := auth.ExtractPractitionerIHS(resp)
	w.Header().Set("Content-Type", "text/plain")
	fmt.Fprint(w, id)
}

// GetPraktisiByID retrieves a practitioner by FHIR ID.
func (h *Handler) GetPraktisiByID(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	resp, err := h.client.GetPractitionerByID(id)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// =========================================================================
// Patient
// =========================================================================

// GetPasien searches for a patient by NIK.
func (h *Handler) GetPasien(w http.ResponseWriter, r *http.Request) {
	nik := r.URL.Query().Get("nik")
	if nik == "" {
		nik = r.FormValue("nik_pasien")
	}
	if nik == "" {
		jsonError(w, 400, "parameter nik kosong")
		return
	}
	resp, err := h.client.GetPatientByNIK(nik)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// GetPasienID returns only the IHS ID for a patient.
func (h *Handler) GetPasienID(w http.ResponseWriter, r *http.Request) {
	nik := chi.URLParam(r, "nik")
	resp, err := h.client.GetPatientByNIK(nik)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	id := auth.ExtractPatientIHS(resp)
	w.Header().Set("Content-Type", "text/plain")
	fmt.Fprint(w, id)
}

// GetPasienByID retrieves a patient by FHIR ID.
func (h *Handler) GetPasienByID(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	resp, err := h.client.GetPatientByID(id)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// =========================================================================
// Organization
// =========================================================================

// PostOrganization creates an Organization resource.
func (h *Handler) PostOrganization(w http.ResponseWriter, r *http.Request) {
	depCode := r.URL.Query().Get("dep_id")
	partOf := h.cfg.OrganizationID
	if po := r.URL.Query().Get("part_of"); po != "" {
		partOf = po
	}

	name, _ := h.db.GetDepartemenNama(depCode)
	if name == "" {
		name, _ = h.db.GetPoliklinikNama(depCode)
	}

	orgData := fhir.BuildOrganization(fhir.OrganizationParams{
		OrgID:     h.cfg.OrganizationID,
		DepCode:   depCode,
		Name:      name,
		Phone:     h.cfg.NomorTelepon,
		Email:     h.cfg.Email,
		Address:   h.cfg.Alamat,
		City:      h.cfg.Kota,
		PostalCode: h.cfg.KodePos,
		Province:  h.cfg.Propinsi,
		Kabupaten: h.cfg.Kabupaten,
		Kecamatan: h.cfg.Kecamatan,
		Kelurahan: h.cfg.Kelurahan,
		PartOf:    partOf,
	})

	body, _ := fhir.ToJSONCompact(orgData)
	resp, err := h.client.FHIRPost("/Organization", body)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// =========================================================================
// Location
// =========================================================================

// PostLocation creates a Location resource.
func (h *Handler) PostLocation(w http.ResponseWriter, r *http.Request) {
	kode := r.URL.Query().Get("kode")
	orgRefID := r.URL.Query().Get("org_id")
	if orgRefID == "" {
		orgRefID = h.cfg.OrganizationID
	}

	name, _ := h.db.GetPoliklinikNama(kode)
	if name == "" {
		name, _ = h.db.GetBangsalNama(kode)
	}

	locData := fhir.BuildLocation(fhir.LocationParams{
		OrgID:     h.cfg.OrganizationID,
		LocCode:   kode,
		Name:      name,
		Phone:     h.cfg.NomorTelepon,
		Email:     h.cfg.Email,
		Address:   h.cfg.Alamat,
		City:      h.cfg.Kota,
		PostalCode: h.cfg.KodePos,
		Province:  h.cfg.Propinsi,
		Kabupaten: h.cfg.Kabupaten,
		Kecamatan: h.cfg.Kecamatan,
		Kelurahan: h.cfg.Kelurahan,
		Longitude: h.cfg.Longitude,
		Latitude:  h.cfg.Latitude,
		OrgRefID:  orgRefID,
	})

	body, _ := fhir.ToJSONCompact(locData)
	resp, err := h.client.FHIRPost("/Location", body)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}

	id := auth.ExtractResourceID(resp)
	if id != "" {
		h.db.SaveLokasi(kode, id)
	}

	rawJSON(w, resp)
}

// =========================================================================
// Encounter
// =========================================================================

// GetEncounter creates and sends an Encounter to Satu Sehat.
func (h *Handler) GetEncounter(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	if noRawatRaw == "" {
		jsonError(w, 400, "no_rawat kosong")
		return
	}

	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah encounter sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_encounter"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Encounter sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan: "+err.Error())
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Data pasien tidak ditemukan"})
		return
	}

	// Validasi: NIK harus tepat 16 digit
	nik := strings.TrimSpace(pasien.NoKTP)
	if nik == "" {
		jsonOK(w, map[string]interface{}{
			"status": "skipped",
			"pesan":  fmt.Sprintf("NIK pasien %s kosong", pasien.NmPasien),
		})
		return
	}
	if len(nik) != 16 {
		jsonOK(w, map[string]interface{}{
			"status": "skipped",
			"pesan":  fmt.Sprintf("NIK pasien %s tidak valid (%d digit, harus 16): %s", pasien.NmPasien, len(nik), nik),
		})
		return
	}

	// Lookup patient IHS
	patResp, patErr := h.client.GetPatientByNIK(pasien.NoKTP)
	if patErr != nil {
		jsonOK(w, map[string]interface{}{
			"status": "error",
			"pesan":  fmt.Sprintf("Gagal lookup NIK %s di Satu Sehat: %v", pasien.NoKTP, patErr),
		})
		return
	}
	ihsPatient := auth.ExtractPatientIHS(patResp)

	// Cek apakah response mengandung error (rate limit, server error, dll)
	if ihsPatient == "" && len(patResp) > 0 {
		respStr := string(patResp)
		// Jika response mengandung OperationOutcome atau error, itu bukan "not found"
		if strings.Contains(respStr, "OperationOutcome") || strings.Contains(respStr, "\"error\"") || strings.Contains(respStr, "Too Many") || strings.Contains(respStr, "rate") {
			// Potong response agar tidak terlalu panjang
			detail := respStr
			if len(detail) > 300 {
				detail = detail[:300] + "..."
			}
			jsonOK(w, map[string]interface{}{
				"status": "error",
				"pesan":  fmt.Sprintf("Error dari Satu Sehat saat lookup NIK %s: %s", pasien.NoKTP, detail),
			})
			return
		}
	}

	// Jika ihsPatient kosong dan tidak ada error API, artinya pasien memang tidak terdaftar
	if ihsPatient == "" {
		jsonOK(w, map[string]interface{}{
			"status": "skipped",
			"pesan":  fmt.Sprintf("Pasien %s (NIK: %s) tidak ditemukan di platform Satu Sehat", pasien.NmPasien, pasien.NoKTP),
		})
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	namaDokter, _ := h.db.GetPegawaiNama(reg.KdDokter)

	// Determine class
	classCode, classDisplay := "AMB", "ambulatory"
	kdPoli := reg.KdPoli
	nmPoli, _ := h.db.GetPoliklinikNama(kdPoli)

	if reg.StatusLanjut == "Ranap" {
		classCode, classDisplay = "IMP", "inpatient encounter"
		kdKamar, _ := h.db.GetKamarInapKdKamar(noRawat)
		kdBangsal, _ := h.db.GetKamarBangsal(kdKamar)
		nmPoli, _ = h.db.GetBangsalNama(kdBangsal)
		kdPoli = kdKamar
	}

	locationID, _ := h.db.GetLokasi(kdPoli)

	encData := fhir.BuildEncounter(fhir.EncounterParams{
		NoRawat:          noRawat,
		OrgID:            h.cfg.OrganizationID,
		PatientIHS:       ihsPatient,
		PatientName:      pasien.NmPasien,
		PractitionerID:   practitionerID,
		PractitionerName: namaDokter,
		LocationID:       locationID,
		LocationName:     kdPoli + " " + nmPoli,
		TglRegistrasi:    reg.TglRegistrasi,
		JamReg:           reg.JamReg,
		ClassCode:        classCode,
		ClassDisplay:     classDisplay,
		TimezoneOffset:   h.cfg.TimezoneOffset(),
	})

	body, _ := fhir.ToJSONCompact(encData)
	resp, err := h.client.FHIRPost("/Encounter", body)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}

	idEncounter := auth.ExtractResourceID(resp)
	if idEncounter != "" {
		h.db.SaveResponse(noRawat, idEncounter)
	}

	result := map[string]interface{}{
		"status":   "success",
		"pesan":    "Sukses mengirim encounter platform Satu Sehat!!",
		"id":       idEncounter,
		"response": json.RawMessage(resp),
		"request":  json.RawMessage(body),
	}
	if idEncounter == "" {
		result["status"] = "error"
		result["pesan"] = "Gagal mengirim encounter platform Satu Sehat!!"
	}
	jsonOK(w, result)
}

// =========================================================================
// Condition
// =========================================================================

// GetCondition creates and sends a Condition to Satu Sehat.
func (h *Handler) GetCondition(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah condition sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_condition"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Condition sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	// Get encounter response
	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	diagnosaList, _ := h.db.GetDiagnosaList(noRawat, reg.StatusLanjut)
	if len(diagnosaList) == 0 {
		jsonSkipped(w, "Tidak ada data diagnosa")
		return
	}

	// Auto-promote one diagnosa to priority 1 if none exists
	h.db.AutoPromoteDiagnosa(noRawat, reg.StatusLanjut)
	// Reload to get updated priorities
	diagnosaList, _ = h.db.GetDiagnosaList(noRawat, reg.StatusLanjut)

	var condIDs []string
	var responses []json.RawMessage

	for _, diag := range diagnosaList {
		condData := fhir.BuildCondition(fhir.ConditionParams{
			UUIDEncounter:    respData.IDEncounter,
			UUIDCondition:    fhir.GenUUID(),
			Code:             diag.KdPenyakit,
			Display:          diag.NmPenyakit,
			PatientIHS:       ihsPatient,
			PatientName:      pasien.NmPasien,
			EncounterDisplay: "Kunjungan " + pasien.NmPasien,
		})

		// Fix encounter reference: use the actual encounter ID not urn:uuid
		condData["encounter"] = fhir.M{
			"reference": "Encounter/" + respData.IDEncounter,
			"display":   "Kunjungan " + pasien.NmPasien,
		}

		body, _ := fhir.ToJSONCompact(condData)
		resp, err := h.client.FHIRPost("/Condition", body)
		if err != nil {
			continue
		}

		condID := auth.ExtractResourceID(resp)
		if condID != "" {
			condIDs = append(condIDs, condID)
		}
		responses = append(responses, resp)
	}

	if len(condIDs) > 0 {
		h.db.UpdateResponseCondition(noRawat, strings.Join(condIDs, ","))
		jsonOK(w, map[string]interface{}{
			"status":    "success",
			"pesan":     fmt.Sprintf("%d condition berhasil dikirim", len(condIDs)),
			"id":        strings.Join(condIDs, ","),
			"responses": responses,
		})
	} else {
		jsonOK(w, map[string]interface{}{
			"status":    "error",
			"pesan":     "Gagal mengirim condition",
			"responses": responses,
		})
	}
}

// =========================================================================
// Observation (Vital Signs)
// =========================================================================

// GetObservation sends an observation for a specific vital sign type.
func (h *Handler) GetObservation(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	parts := strings.Split(noRawatRaw, "/")

	// The last part is the TTV type
	ttv := ""
	if len(parts) > 1 {
		lastPart := parts[len(parts)-1]
		validTTV := []string{"tensi", "nadi", "respirasi", "suhu", "spo2", "gcs", "kesadaran", "berat", "tinggi", "perut"}
		for _, v := range validTTV {
			if lastPart == v {
				ttv = lastPart
				parts = parts[:len(parts)-1]
				break
			}
		}
	}

	noRawat := db.RevertNoRawat(strings.Join(parts, "/"))
	if ttv == "" {
		ttv = r.URL.Query().Get("ttv")
	}
	if noRawat == "" || ttv == "" {
		jsonError(w, 400, "parameter kosong")
		return
	}

	// Cek apakah observation ini sudah dikirim sebelumnya
	obsTTVColumn := map[string]string{
		"tensi": "id_observation_ttvtensi", "nadi": "id_observation_ttvnadi",
		"respirasi": "id_observation_ttvrespirasi", "suhu": "id_observation_ttvsuhu",
		"spo2": "id_observation_ttvspo2", "gcs": "id_observation_ttvgcs",
		"kesadaran": "id_observation_ttvkesadaran", "berat": "id_observation_ttvberat",
		"tinggi": "id_observation_ttvtinggi", "perut": "id_observation_ttvperut",
	}
	if col, ok := obsTTVColumn[ttv]; ok {
		if existingID, _ := h.db.GetResponseField(noRawat, col); existingID != "" {
			jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Observation " + ttv + " sudah dikirim sebelumnya", "id": existingID})
			return
		}
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	table := "pemeriksaan_ralan"
	if reg.StatusLanjut == "Ranap" {
		table = "pemeriksaan_ranap"
	}
	merged, _ := h.db.GetMergedPemeriksaan(noRawat, table)
	if merged == nil {
		jsonSkipped(w, "Tidak ada data pemeriksaan")
		return
	}

	effectiveTime := fhir.ConvertTimeSatset(
		merged.TglPerawatan+" "+merged.JamRawat,
		h.cfg.TimezoneModifyHours(),
	)
	tzOffset := "+00:00" // UTC after conversion

	obsParams := fhir.ObservationParams{
		UUIDEncounter:   respData.IDEncounter,
		UUIDObservation: fhir.GenUUID(),
		PatientIHS:      ihsPatient,
		PractitionerID:  practitionerID,
		EffectiveTime:   effectiveTime,
		TimezoneOffset:  tzOffset,
	}

	var obsData fhir.M
	display := fmt.Sprintf("Pemeriksaan Fisik %s %s di %s", strings.Title(ttv), pasien.NmPasien, reg.TglRegistrasi)

	// Check if the specific value exists before sending
	valueMap := map[string]string{
		"nadi": merged.Nadi, "respirasi": merged.Respirasi, "suhu": merged.SuhuTubuh,
		"spo2": merged.Spo2, "gcs": merged.GCS, "berat": merged.Berat,
		"tinggi": merged.Tinggi, "perut": merged.LingkarPerut, "kesadaran": merged.Kesadaran,
		"tensi": merged.Tensi,
	}
	rawVal := strings.TrimSpace(valueMap[ttv])
	if rawVal == "" || rawVal == "0" || rawVal == "0.0" || rawVal == "-" {
		jsonSkipped(w, fmt.Sprintf("Tidak ada data %s", ttv))
		return
	}

	switch ttv {
	case "nadi":
		val, _ := strconv.ParseFloat(merged.Nadi, 64)
		obsData = fhir.BuildObservationHeartRate(obsParams, val, display)
	case "respirasi":
		val, _ := strconv.ParseFloat(merged.Respirasi, 64)
		obsData = fhir.BuildObservationRespiration(obsParams, val, display)
	case "suhu":
		val, _ := strconv.ParseFloat(merged.SuhuTubuh, 64)
		obsData = fhir.BuildObservationTemperature(obsParams, val, display)
	case "spo2":
		val, _ := strconv.ParseFloat(merged.Spo2, 64)
		obsData = fhir.BuildObservationSpO2(obsParams, val, display)
	case "gcs":
		val, _ := strconv.ParseFloat(merged.GCS, 64)
		obsData = fhir.BuildObservationGCS(obsParams, val, display)
	case "kesadaran":
		obsData = fhir.BuildObservationKesadaran(obsParams, merged.Kesadaran, display)
	case "berat":
		val, _ := strconv.ParseFloat(merged.Berat, 64)
		obsData = fhir.BuildObservationBodyWeight(obsParams, val, display)
	case "tinggi":
		val, _ := strconv.ParseFloat(merged.Tinggi, 64)
		obsData = fhir.BuildObservationBodyHeight(obsParams, val, display)
	case "perut":
		val, _ := strconv.ParseFloat(merged.LingkarPerut, 64)
		obsData = fhir.BuildObservationWaistCircumference(obsParams, val, display)
	case "tensi":
		parts := strings.SplitN(merged.Tensi, "/", 2)
		if len(parts) == 2 {
			sys, _ := strconv.ParseFloat(parts[0], 64)
			dia, _ := strconv.ParseFloat(parts[1], 64)
			// Build Blood Pressure Panel with components
			obsData = fhir.BuildObservationBloodPressure(obsParams, sys, dia, display)
			obsData["encounter"] = fhir.M{
				"reference": "Encounter/" + respData.IDEncounter,
				"display":   display,
			}
			body, _ := fhir.ToJSONCompact(obsData)
			resp, err := h.client.FHIRPost("/Observation", body)
			if err != nil {
				jsonOK(w, map[string]interface{}{"status": "error", "pesan": "Gagal mengirim observation tensi: " + err.Error()})
				return
			}
			obsID := auth.ExtractResourceID(resp)
			if obsID != "" {
				h.db.UpdateResponseField(noRawat, "id_observation_ttvtensi", obsID)
			}
			fhirResult(w, resp, "observation tensi", obsID)
			return
		}
		jsonError(w, 400, "Format tensi tidak valid")
		return
	default:
		jsonError(w, 400, "TTV type tidak dikenali: "+ttv)
		return
	}

	// Fix encounter reference from urn:uuid to Encounter/
	obsData["encounter"] = fhir.M{
		"reference": "Encounter/" + respData.IDEncounter,
		"display":   display,
	}

	body, _ := fhir.ToJSONCompact(obsData)
	resp, err := h.client.FHIRPost("/Observation", body)
	if err != nil {
		jsonOK(w, map[string]interface{}{"status": "error", "pesan": "Gagal mengirim observation " + ttv + ": " + err.Error()})
		return
	}

	// Save observation ID to response table
	obsID := auth.ExtractResourceID(resp)
	ttvColumnMap := map[string]string{
		"nadi":      "id_observation_ttvnadi",
		"respirasi": "id_observation_ttvrespirasi",
		"suhu":      "id_observation_ttvsuhu",
		"spo2":      "id_observation_ttvspo2",
		"gcs":       "id_observation_ttvgcs",
		"kesadaran": "id_observation_ttvkesadaran",
		"berat":     "id_observation_ttvberat",
		"tinggi":    "id_observation_ttvtinggi",
		"perut":     "id_observation_ttvperut",
	}
	if obsID != "" {
		if col, ok := ttvColumnMap[ttv]; ok {
			h.db.UpdateResponseField(noRawat, col, obsID)
		}
	}

	fhirResult(w, resp, "observation "+ttv, obsID)
}

// =========================================================================
// Procedure
// =========================================================================

// GetProcedure sends a Procedure resource.
func (h *Handler) GetProcedure(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah procedure sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_procedure"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Procedure sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	status := "Ralan"
	if reg.StatusLanjut == "Ranap" {
		status = "Ranap"
	}

	prosedurs, _ := h.db.GetProsedurPasien(noRawat, status)
	if len(prosedurs) == 0 {
		jsonSkipped(w, "Tidak ada data prosedur")
		return
	}

	table := "pemeriksaan_ralan"
	if reg.StatusLanjut == "Ranap" {
		table = "pemeriksaan_ranap"
	}
	merged, _ := h.db.GetMergedPemeriksaan(noRawat, table)
	effectiveTime := fhir.ConvertTimeSatset(
		merged.TglPerawatan+" "+merged.JamRawat,
		h.cfg.TimezoneModifyHours(),
	)

	var responses []json.RawMessage
	var procIDs []string
	for _, proc := range prosedurs {
		code := fhir.FormatICD9Code(proc.Kode)
		procData := fhir.BuildProcedure(fhir.ProcedureParams{
			UUIDEncounter:     respData.IDEncounter,
			UUIDProcedure:     fhir.GenUUID(),
			PatientIHS:        ihsPatient,
			PatientName:       pasien.NmPasien,
			PractitionerID:    practitionerID,
			Code:              code,
			Display:           proc.DeskripsiPendek,
			PerformedDateTime: effectiveTime,
			TimezoneOffset:    "+00:00",
		})

		procData["encounter"] = fhir.M{"reference": "Encounter/" + respData.IDEncounter}

		body, _ := fhir.ToJSONCompact(procData)
		resp, _ := h.client.FHIRPost("/Procedure", body)
		procID := auth.ExtractResourceID(resp)
		if procID != "" {
			procIDs = append(procIDs, procID)
		}
		responses = append(responses, resp)
	}

	if len(procIDs) > 0 {
		h.db.UpdateResponseField(noRawat, "id_procedure", strings.Join(procIDs, ","))
		jsonOK(w, map[string]interface{}{
			"status":    "success",
			"pesan":     fmt.Sprintf("%d prosedur berhasil dikirim", len(procIDs)),
			"id":        strings.Join(procIDs, ","),
			"responses": responses,
		})
	} else {
		jsonOK(w, map[string]interface{}{
			"status":    "error",
			"pesan":     "Gagal mengirim prosedur",
			"responses": responses,
		})
	}
}

// =========================================================================
// Medication
// =========================================================================

// pushMappingMedication pushes a Medication resource to Satu Sehat from the mapping page.
// It uses kode_brng (drug code) instead of no_rawat.
func (h *Handler) pushMappingMedication(w http.ResponseWriter, parts []string) {
	kodeBrng := strings.Join(parts, "/")
	if kodeBrng == "" {
		jsonError(w, 400, "kode_brng kosong")
		return
	}

	mapping, err := h.db.GetMappingObat(kodeBrng)
	if err != nil || mapping == nil {
		jsonError(w, 404, "Mapping obat tidak ditemukan")
		return
	}

	if mapping.KfaCoding == "" {
		jsonError(w, 400, "Kode KFA belum diisi pada mapping")
		return
	}

	// Build FHIR Medication resource (simple version for mapping push)
	medData := fhir.BuildMedication(fhir.MedicationParams{
		OrgID:           h.cfg.OrganizationID,
		IdentifierValue: kodeBrng,
		KfaCoding:       mapping.KfaCoding,
		KfaDisplay:      mapping.KfaDisplay,
		FormCoding:      mapping.FormCoding,
		FormDisplay:     mapping.FormDisplay,
	})

	medBody, _ := fhir.ToJSONCompact(medData)

	var resp []byte
	var medID string

	if mapping.IDMedication != "" {
		// PUT update existing Medication
		var decoded map[string]interface{}
		if err := json.Unmarshal(medBody, &decoded); err == nil {
			decoded["id"] = mapping.IDMedication
			medBody, _ = json.Marshal(decoded)
		}
		resp, err = h.client.FHIRPut("/Medication/"+mapping.IDMedication, medBody)
		if err == nil {
			medID = auth.ExtractResourceID(resp)
			if medID == "" {
				medID = mapping.IDMedication
			}
		}
	} else {
		// POST new Medication
		resp, err = h.client.FHIRPost("/Medication", medBody)
		if err == nil {
			medID = auth.ExtractResourceID(resp)
		}
	}

	if medID != "" {
		h.db.UpdateMappingObatMedicationID(kodeBrng, medID)
		result := map[string]interface{}{
			"status": "success",
			"pesan":  "Sukses mengirim mapping medication ke Satu Sehat",
			"id":     medID,
		}
		if resp != nil {
			result["response"] = json.RawMessage(resp)
		}
		jsonOK(w, result)
	} else {
		errMsg := "Gagal mengirim medication"
		if err != nil {
			errMsg += ": " + err.Error()
		}
		result := map[string]interface{}{
			"status": "error",
			"pesan":  errMsg,
		}
		if resp != nil {
			result["response"] = json.RawMessage(resp)
		}
		jsonOK(w, result)
	}
}

// GetMedication handles medication request/dispense/statement.
func (h *Handler) GetMedication(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	parts := strings.Split(noRawatRaw, "/")

	tipe := "request"
	if len(parts) > 0 {
		lastPart := parts[len(parts)-1]
		if lastPart == "request" || lastPart == "dispense" || lastPart == "statement" || lastPart == "mapping" {
			tipe = lastPart
			parts = parts[:len(parts)-1]
		}
	}

	// Handle mapping push: push a Medication resource by kode_brng (not no_rawat)
	if tipe == "mapping" {
		h.pushMappingMedication(w, parts)
		return
	}

	noRawat := db.RevertNoRawat(strings.Join(parts, "/"))
	if noRawat == "" {
		jsonError(w, 400, "no_rawat kosong")
		return
	}

	// Cek apakah medication tipe ini sudah dikirim sebelumnya
	medColumnMap := map[string]string{
		"request": "id_medication_request", "dispense": "id_medication_dispense", "statement": "id_medication_statement",
	}
	if col, ok := medColumnMap[tipe]; ok {
		if existingID, _ := h.db.GetResponseField(noRawat, col); existingID != "" {
			jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Medication " + tipe + " sudah dikirim sebelumnya", "id": existingID})
			return
		}
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	namaDokter, _ := h.db.GetPegawaiNama(reg.KdDokter)
	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	condID := ""
	if respData.IDCondition.Valid {
		condID = respData.IDCondition.String
	}

	// Get actual medications for this patient
	medications, err := h.db.GetResepObat(noRawat)
	if err != nil || len(medications) == 0 {
		jsonSkipped(w, "Tidak ada data resep obat")
		return
	}

	zonawaktu := h.cfg.TimezoneOffset()

	result := map[string]interface{}{
		"tipe":     tipe,
		"no_rawat": noRawat,
	}

	switch tipe {
	case "request":
		var lastMedResp, lastReqResp []byte
		var lastReqID string

		for i, obat := range medications {
			// Resolve satuan_den for system
			satuanDen := obat.SatuanDen
			if satuanDen == "" {
				satuanDen = obat.NamaSatuanDen
			}
			if satuanDen == "" {
				satuanDen = obat.KodeSediaan
			}
			if satuanDen == "" {
				continue
			}
			systemCek := "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm"
			if isAllDigits(satuanDen) {
				systemCek = "http://snomed.info/sct"
			}

			// Parse aturan_pakai for frequency/dose
			frequency, doseValue := parseAturanPakai(obat.AturanPakai)

			// Get or create Medication ID
			medID := obat.IDMedication
			if medID == "" {
				// POST new Medication to Satu Sehat
				numVal := parseFloat(obat.Numerator)
				denVal := parseFloat(obat.Denominator)
				denSystem := "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm"
				if isAllDigits(obat.SatuanDen) {
					denSystem = "http://snomed.info/sct"
				}

				medData := fhir.BuildMedication(fhir.MedicationParams{
					OrgID:             h.cfg.OrganizationID,
					IdentifierValue:   obat.NoResep + obat.KodeBrng,
					KfaCoding:         obat.KodeKfa,
					KfaDisplay:        obat.NamaKfa,
					FormCoding:        obat.KodeSediaan,
					FormDisplay:       obat.NamaSediaan,
					IngredientCoding:  obat.KodeBahan,
					IngredientDisplay: obat.NamaBahan,
					NumeratorValue:    numVal,
					NumeratorCode:     obat.SatuanNum,
					DenominatorValue:  denVal,
					DenominatorSystem: denSystem,
					DenominatorCode:   obat.SatuanDen,
				})
				medBody, _ := fhir.ToJSONCompact(medData)
				medResp, _ := h.client.FHIRPost("/Medication", medBody)
				lastMedResp = medResp
				medID = auth.ExtractResourceID(medResp)
				if medID != "" {
					// Save medication ID back to mapping table
					h.db.UpdateMappingObatMedicationID(obat.KodeBrng, medID)
				}
			}

			if medID == "" {
				continue
			}

			// Build MedicationRequest
			authoredOn := obat.TglPeresepan + "T" + obat.JamPeresepan + zonawaktu

			reqData := fhir.M{
				"resourceType": "MedicationRequest",
				"identifier": []fhir.M{
					{"system": "http://sys-ids.kemkes.go.id/prescription/" + h.cfg.OrganizationID, "use": "official", "value": obat.NoResep},
					{"system": "http://sys-ids.kemkes.go.id/prescription-item/" + h.cfg.OrganizationID, "use": "official", "value": obat.KodeBrng},
				},
				"status": "completed",
				"intent": "order",
				"category": []fhir.M{{"coding": []fhir.M{{"system": "http://terminology.hl7.org/CodeSystem/medicationrequest-category", "code": "outpatient", "display": "Outpatient"}}}},
				"priority": "routine",
				"medicationReference": fhir.M{
					"reference": "Medication/" + medID,
					"display":   obat.NamaKfa,
				},
				"subject": fhir.M{
					"reference": "Patient/" + ihsPatient,
					"display":   pasien.NmPasien,
				},
				"encounter": fhir.M{
					"reference": "Encounter/" + respData.IDEncounter,
				},
				"authoredOn": authoredOn,
				"requester": fhir.M{
					"reference": "Practitioner/" + practitionerID,
					"display":   namaDokter,
				},
				"dosageInstruction": []fhir.M{
					{
						"sequence":           1,
						"patientInstruction": obat.AturanPakai,
						"timing": fhir.M{
							"repeat": fhir.M{
								"frequency":  doseValue,
								"period":     1,
								"periodUnit": "d",
							},
						},
						"route": fhir.M{
							"coding": []fhir.M{
								{"system": "http://www.whocc.no/atc", "code": obat.KodeRoute, "display": obat.NamaRoute},
							},
						},
						"doseAndRate": []fhir.M{
							{
								"doseQuantity": fhir.M{
									"value":  frequency,
									"unit":   satuanDen,
									"system": systemCek,
									"code":   satuanDen,
								},
							},
						},
					},
				},
				"dispenseRequest": fhir.M{
					"quantity": fhir.M{
						"value":  obat.Jml,
						"unit":   satuanDen,
						"system": systemCek,
						"code":   satuanDen,
					},
					"performer": fhir.M{
						"reference": "Organization/" + h.cfg.OrganizationID,
					},
				},
			}
			if condID != "" {
				condIDs := strings.Split(condID, ",")
				var refs []fhir.M
				for _, cid := range condIDs {
					cid = strings.TrimSpace(cid)
					if cid != "" {
						refs = append(refs, fhir.M{"reference": "Condition/" + cid})
					}
				}
				if len(refs) > 0 {
					reqData["reasonReference"] = refs
				}
			}

			reqBody, _ := fhir.ToJSONCompact(reqData)
			reqResp, _ := h.client.FHIRPost("/MedicationRequest", reqBody)
			lastReqResp = reqResp
			lastReqID = auth.ExtractResourceID(reqResp)

			_ = i
		}

		if lastReqID != "" {
			h.db.UpdateResponseField(noRawat, "id_medication_request", lastReqID)
			result["status"] = "success"
			result["pesan"] = "Sukses mengirim medication request platform Satu Sehat!!"
			result["id"] = lastReqID
		} else {
			result["status"] = "error"
			result["pesan"] = "Gagal mengirim medication request platform Satu Sehat!!"
		}
		if lastMedResp != nil {
			result["medication_response"] = json.RawMessage(lastMedResp)
		}
		if lastReqResp != nil {
			result["request_response"] = json.RawMessage(lastReqResp)
		}

	case "dispense":
		var lastDispResp []byte
		var lastDispID string

		// Get farmasi location
		farmasiKode := h.cfg.Farmasi
		var farmasiLocationID, farmasiLocationName string
		if farmasiKode != "" {
			loc, _ := h.db.GetLokasi(farmasiKode)
			if loc != "" {
				farmasiLocationID = loc
			}
			farmasiLocationName = farmasiKode
		}

		medReqID := ""
		if respData.IDMedRequest.Valid && respData.IDMedRequest.String != "" {
			medReqID = respData.IDMedRequest.String
		}

		for _, obat := range medications {
			satuanDen := obat.SatuanDen
			if satuanDen == "" {
				satuanDen = obat.NamaSatuanDen
			}
			if satuanDen == "" {
				satuanDen = obat.KodeSediaan
			}
			if satuanDen == "" {
				continue
			}
			systemCek := "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm"
			if isAllDigits(satuanDen) {
				systemCek = "http://snomed.info/sct"
			}

			frequency, doseValue := parseAturanPakai(obat.AturanPakai)

			medID := obat.IDMedication
			if medID == "" {
				continue
			}

			whenPrepared := obat.TglPeresepan + "T" + obat.JamPeresepan + zonawaktu
			whenHanded := obat.TglPerawatan + "T" + obat.Jam + zonawaktu

			dispData := fhir.M{
				"resourceType": "MedicationDispense",
				"identifier": []fhir.M{
					{"system": "http://sys-ids.kemkes.go.id/prescription/" + h.cfg.OrganizationID, "use": "official", "value": obat.NoResep},
					{"system": "http://sys-ids.kemkes.go.id/prescription-item/" + h.cfg.OrganizationID, "use": "official", "value": obat.KodeBrng},
				},
				"status": "completed",
				"category": fhir.M{
					"coding": []fhir.M{{"system": "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category", "code": "outpatient", "display": "Outpatient"}},
				},
				"medicationReference": fhir.M{
					"reference": "Medication/" + medID,
					"display":   obat.NamaKfa,
				},
				"subject": fhir.M{
					"reference": "Patient/" + ihsPatient,
					"display":   pasien.NmPasien,
				},
				"context": fhir.M{
					"reference": "Encounter/" + respData.IDEncounter,
				},
				"performer": []fhir.M{
					{"actor": fhir.M{"reference": "Practitioner/" + practitionerID, "display": namaDokter}},
				},
				"quantity": fhir.M{
					"system": systemCek,
					"code":   satuanDen,
					"value":  obat.Jml,
				},
				"whenPrepared":   whenPrepared,
				"whenHandedOver": whenHanded,
				"dosageInstruction": []fhir.M{
					{
						"sequence": 1,
						"text":     obat.AturanPakai,
						"timing": fhir.M{
							"repeat": fhir.M{
								"frequency":  doseValue,
								"period":     1,
								"periodUnit": "d",
							},
						},
						"route": fhir.M{
							"coding": []fhir.M{
								{"system": "http://www.whocc.no/atc", "code": obat.KodeRoute, "display": obat.NamaRoute},
							},
						},
						"doseAndRate": []fhir.M{
							{
								"doseQuantity": fhir.M{
									"value":  frequency,
									"unit":   satuanDen,
									"system": systemCek,
									"code":   satuanDen,
								},
							},
						},
					},
				},
			}
			if farmasiLocationID != "" {
				dispData["location"] = fhir.M{
					"reference": "Location/" + farmasiLocationID,
					"display":   farmasiLocationName,
				}
			}
			if medReqID != "" {
				dispData["authorizingPrescription"] = []fhir.M{
					{"reference": "MedicationRequest/" + medReqID},
				}
			}

			dispBody, _ := fhir.ToJSONCompact(dispData)
			dispResp, _ := h.client.FHIRPost("/MedicationDispense", dispBody)
			lastDispResp = dispResp
			lastDispID = auth.ExtractResourceID(dispResp)
		}

		if lastDispID != "" {
			h.db.UpdateResponseField(noRawat, "id_medication_dispense", lastDispID)
			result["status"] = "success"
			result["pesan"] = "Sukses mengirim medication dispense platform Satu Sehat!!"
			result["id"] = lastDispID
		} else {
			result["status"] = "error"
			result["pesan"] = "Gagal mengirim medication dispense platform Satu Sehat!!"
		}
		if lastDispResp != nil {
			result["dispense_response"] = json.RawMessage(lastDispResp)
		}

	case "statement":
		var lastStmtResp []byte
		var lastStmtID string

		for _, obat := range medications {
			satuanDen := obat.SatuanDen
			if satuanDen == "" {
				satuanDen = obat.NamaSatuanDen
			}
			if satuanDen == "" {
				satuanDen = obat.KodeSediaan
			}
			if satuanDen == "" {
				continue
			}
			systemCek := "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm"
			if isAllDigits(satuanDen) {
				systemCek = "http://snomed.info/sct"
			}

			frequency, doseValue := parseAturanPakai(obat.AturanPakai)

			medID := obat.IDMedication
			if medID == "" {
				continue
			}

			// Fallback: if tgl_penyerahan is empty/invalid, use tgl_perawatan or tgl_peresepan
			tglPenyerahan := obat.TglPenyerahan
			jamPenyerahan := obat.JamPenyerahan
			if tglPenyerahan == "" || tglPenyerahan == "0000-00-00" {
				if obat.TglPerawatan != "" && obat.TglPerawatan != "0000-00-00" {
					tglPenyerahan = obat.TglPerawatan
					if obat.Jam != "" && obat.Jam != "00:00:00" {
						jamPenyerahan = obat.Jam
					}
				} else {
					tglPenyerahan = obat.TglPeresepan
					if obat.JamPeresepan != "" {
						jamPenyerahan = obat.JamPeresepan
					}
				}
			}
			if jamPenyerahan == "" || jamPenyerahan == "00:00:00" {
				jamPenyerahan = "00:00:00"
			}

			dateAsserted := tglPenyerahan + "T" + jamPenyerahan + zonawaktu

			stmtData := fhir.M{
				"resourceType": "MedicationStatement",
				"identifier": []fhir.M{
					{"system": "http://sys-ids.kemkes.go.id/medication-statement", "use": "official", "value": obat.NoResep + "-" + obat.KodeBrng},
				},
				"status": "completed",
				"category": fhir.M{
					"coding": []fhir.M{
						{"system": "http://terminology.kemkes.go.id/CodeSystem/medication-statement-category", "code": "outpatient", "display": "Outpatient"},
					},
				},
				"medicationReference": fhir.M{
					"reference": "Medication/" + medID,
					"display":   obat.NamaKfa,
				},
				"subject": fhir.M{
					"reference": "Patient/" + ihsPatient,
					"display":   pasien.NmPasien,
				},
				"dateAsserted": dateAsserted,
				"informationSource": fhir.M{
					"reference": "Patient/" + ihsPatient,
					"display":   pasien.NmPasien,
				},
				"context": fhir.M{
					"reference": "Encounter/" + respData.IDEncounter,
				},
				"dosage": []fhir.M{
					{
						"text": obat.AturanPakai,
						"timing": fhir.M{
							"repeat": fhir.M{
								"frequency":  doseValue,
								"period":     1,
								"periodUnit": "d",
							},
						},
						"route": fhir.M{
							"coding": []fhir.M{
								{"system": "http://www.whocc.no/atc", "code": obat.KodeRoute, "display": obat.NamaRoute},
							},
						},
						"doseAndRate": []fhir.M{
							{
								"doseQuantity": fhir.M{
									"value":  frequency,
									"unit":   satuanDen,
									"system": systemCek,
									"code":   satuanDen,
								},
							},
						},
					},
				},
				"note": []fhir.M{
					{"text": "Sudah dilakukan proses telaah obat oleh petugas dan obat sudah diserahkan ke pasien."},
				},
			}

			stmtBody, _ := fhir.ToJSONCompact(stmtData)
			stmtResp, _ := h.client.FHIRPost("/MedicationStatement", stmtBody)
			lastStmtResp = stmtResp
			lastStmtID = auth.ExtractResourceID(stmtResp)
		}

		if lastStmtID != "" {
			h.db.UpdateResponseField(noRawat, "id_medication_statement", lastStmtID)
			result["status"] = "success"
			result["pesan"] = "Sukses mengirim medication statement platform Satu Sehat!!"
			result["id"] = lastStmtID
		} else {
			result["status"] = "error"
			result["pesan"] = "Gagal mengirim medication statement platform Satu Sehat!!"
		}
		if lastStmtResp != nil {
			result["statement_response"] = json.RawMessage(lastStmtResp)
		}
	}

	jsonOK(w, result)
}

// isAllDigits returns true if the string contains only digits.
func isAllDigits(s string) bool {
	for _, c := range s {
		if c < '0' || c > '9' {
			return false
		}
	}
	return len(s) > 0
}

// parseAturanPakai extracts frequency and dose from aturan_pakai string like "3 x 1".
func parseAturanPakai(s string) (int, int) {
	re := regexp.MustCompile(`\d+`)
	matches := re.FindAllString(s, -1)
	if len(matches) >= 2 {
		f, _ := strconv.Atoi(matches[0])
		d, _ := strconv.Atoi(matches[1])
		if f == 0 {
			f = 1
		}
		if d == 0 {
			d = 1
		}
		return f, d
	}
	return 1, 1
}

// parseFloat converts a string to float64, returning 0 on error.
func parseFloat(s string) float64 {
	v, _ := strconv.ParseFloat(strings.TrimSpace(s), 64)
	return v
}

// =========================================================================
// Laboratory
// =========================================================================

// GetLaboratory handles laboratory resource requests.
func (h *Handler) GetLaboratory(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	parts := strings.Split(noRawatRaw, "/")

	tipe := "request"
	if len(parts) > 0 {
		lastPart := parts[len(parts)-1]
		validTypes := []string{"request", "specimen", "observation", "diagnostic"}
		for _, v := range validTypes {
			if lastPart == v {
				tipe = lastPart
				parts = parts[:len(parts)-1]
				break
			}
		}
	}

	noRawat := db.RevertNoRawat(strings.Join(parts, "/"))
	_ = noRawat
	jsonSkipped(w, "Laboratorium "+tipe+" belum tersedia")
}

// =========================================================================
// Radiology
// =========================================================================

// GetRadiology handles radiology resource requests.
func (h *Handler) GetRadiology(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	parts := strings.Split(noRawatRaw, "/")

	tipe := "request"
	if len(parts) > 0 {
		lastPart := parts[len(parts)-1]
		validTypes := []string{"request", "specimen", "observation", "diagnostic", "image"}
		for _, v := range validTypes {
			if lastPart == v {
				tipe = lastPart
				parts = parts[:len(parts)-1]
				break
			}
		}
	}

	noRawat := db.RevertNoRawat(strings.Join(parts, "/"))
	_ = noRawat
	jsonSkipped(w, "Radiologi "+tipe+" belum tersedia")
}

// =========================================================================
// ClinicalImpression
// =========================================================================

// GetClinicalImpression sends a ClinicalImpression resource.
func (h *Handler) GetClinicalImpression(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah clinical impression sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_clinical_impression"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Clinical Impression sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	table := "pemeriksaan_ralan"
	if reg.StatusLanjut == "Ranap" {
		table = "pemeriksaan_ranap"
	}
	merged, _ := h.db.GetMergedPemeriksaan(noRawat, table)
	keluhan := ""
	if merged != nil {
		keluhan = merged.Keluhan
	}

	ciData := fhir.BuildClinicalImpression(fhir.ClinicalImpressionParams{
		UUIDClinicalImpression: fhir.GenUUID(),
		OrgID:                  h.cfg.OrganizationID,
		NoRawat:                noRawat,
		PatientIHS:             ihsPatient,
		PatientName:            pasien.NmPasien,
		UUIDEncounter:          respData.IDEncounter,
		Description:            keluhan,
		Status:                 "completed",
	})
	ciData["encounter"] = fhir.M{"reference": "Encounter/" + respData.IDEncounter}

	body, _ := fhir.ToJSONCompact(ciData)
	resp, _ := h.client.FHIRPost("/ClinicalImpression", body)
	ciID := auth.ExtractResourceID(resp)
	if ciID != "" {
		h.db.UpdateResponseField(noRawat, "id_clinical_impression", ciID)
	}
	fhirResult(w, resp, "clinical impression", ciID)
}

// =========================================================================
// CarePlan
// =========================================================================

// GetCarePlan sends a CarePlan resource.
func (h *Handler) GetCarePlan(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah care plan sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_careplan"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Care Plan sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	namaDokter, _ := h.db.GetPegawaiNama(reg.KdDokter)
	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	table := "pemeriksaan_ralan"
	if reg.StatusLanjut == "Ranap" {
		table = "pemeriksaan_ranap"
	}
	merged, _ := h.db.GetMergedPemeriksaan(noRawat, table)
	rtl := ""
	if merged != nil {
		rtl = merged.Rtl
	}
	if rtl == "" {
		rtl = "Instruksi Medik dan Keperawatan Pasien"
	}

	kunjungan := "Kunjungan"
	if reg.StatusLanjut == "Ranap" {
		kunjungan = "Perawatan"
	}
	encounterDisplay := kunjungan + " " + pasien.NmPasien + " dari tanggal " + reg.TglRegistrasi

	zonawaktu := h.cfg.TimezoneOffset()
	created := ""
	if merged != nil && merged.TglPerawatan != "" {
		created = merged.TglPerawatan + "T" + merged.JamRawat + zonawaktu
	}

	cpData := fhir.BuildCarePlan(fhir.CarePlanParams{
		UUIDCarePlan:     fhir.GenUUID(),
		EncounterID:      respData.IDEncounter,
		PatientIHS:       ihsPatient,
		PatientName:      pasien.NmPasien,
		PractitionerID:   practitionerID,
		PractitionerName: namaDokter,
		OrgID:            h.cfg.OrganizationID,
		NoRawat:          noRawat,
		Title:            "Instruksi Medik dan Keperawatan Pasien",
		Description:      rtl,
		EncounterDisplay: encounterDisplay,
		Created:          created,
	})

	body, _ := fhir.ToJSONCompact(cpData)
	resp, _ := h.client.FHIRPost("/CarePlan", body)
	cpID := auth.ExtractResourceID(resp)
	if cpID != "" {
		h.db.UpdateResponseField(noRawat, "id_careplan", cpID)
	}
	fhirResult(w, resp, "care plan", cpID)
}

// =========================================================================
// Allergy
// =========================================================================

// GetAllergy sends an AllergyIntolerance resource.
func (h *Handler) GetAllergy(w http.ResponseWriter, r *http.Request) {
	noRawatRaw := chi.URLParam(r, "*")
	if noRawatRaw == "" {
		noRawatRaw = r.URL.Query().Get("no_rawat")
	}
	noRawat := db.RevertNoRawat(noRawatRaw)

	// Cek apakah allergy sudah dikirim sebelumnya
	if existingID, _ := h.db.GetResponseField(noRawat, "id_allergy"); existingID != "" {
		jsonOK(w, map[string]interface{}{"status": "skipped", "pesan": "Allergy sudah dikirim sebelumnya", "id": existingID})
		return
	}

	reg, err := h.db.GetRegPeriksa(noRawat)
	if err != nil {
		jsonError(w, 404, "Registrasi tidak ditemukan")
		return
	}

	respData, _ := h.db.GetResponse(noRawat)
	if respData == nil {
		jsonError(w, 404, "Encounter belum dikirim")
		return
	}

	pasien, _ := h.db.GetPasien(reg.NoRkmMedis)
	if pasien == nil {
		jsonError(w, 404, "Pasien tidak ditemukan")
		return
	}

	practitionerID, _ := h.db.GetMappingPraktisi(reg.KdDokter)
	patResp, _ := h.client.GetPatientByNIK(pasien.NoKTP)
	ihsPatient := auth.ExtractPatientIHS(patResp)

	table := "pemeriksaan_ralan"
	if reg.StatusLanjut == "Ranap" {
		table = "pemeriksaan_ranap"
	}
	merged, _ := h.db.GetMergedPemeriksaan(noRawat, table)
	alergi := ""
	if merged != nil {
		alergi = merged.Alergi
	}
	if alergi == "" || alergi == "-" {
		jsonSkipped(w, "Tidak ada data alergi")
		return
	}

	allergyData := fhir.BuildAllergyIntolerance(fhir.AllergyParams{
		PatientIHS:     ihsPatient,
		PatientName:    pasien.NmPasien,
		PractitionerID: practitionerID,
		UUIDEncounter:  respData.IDEncounter,
		Code:           "419199007",
		Display:        alergi,
		Category:       "medication",
	})
	allergyData["encounter"] = fhir.M{"reference": "Encounter/" + respData.IDEncounter}

	body, _ := fhir.ToJSONCompact(allergyData)
	resp, _ := h.client.FHIRPost("/AllergyIntolerance", body)
	allergyID := auth.ExtractResourceID(resp)
	if allergyID != "" {
		h.db.UpdateResponseField(noRawat, "id_allergy", allergyID)
	}
	fhirResult(w, resp, "allergy", allergyID)
}

// =========================================================================
// Batch / Forward by Date
// =========================================================================

// ForwardByDate forwards all registrations for a given date.
func (h *Handler) ForwardByDate(w http.ResponseWriter, r *http.Request) {
	tanggal := chi.URLParam(r, "tanggal")
	if tanggal == "" {
		tanggal = r.URL.Query().Get("tanggal")
	}
	if tanggal == "" {
		tanggal = time.Now().Format("2006-01-02")
	}

	regs, err := h.db.GetRegPeriksaByDate(tanggal)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}

	var noRawatList []map[string]string
	for _, reg := range regs {
		noRawatList = append(noRawatList, map[string]string{
			"no_rawat": reg.NoRawat,
			"url":      strings.ReplaceAll(reg.NoRawat, "/", ""),
		})
	}

	jsonOK(w, map[string]interface{}{
		"tanggal":      tanggal,
		"total":        len(regs),
		"registrasi":   noRawatList,
	})
}

// =========================================================================
// Settings
// =========================================================================

// GetSettings returns current settings.
func (h *Handler) GetSettings(w http.ResponseWriter, r *http.Request) {
	jsonOK(w, map[string]interface{}{
		"organization_id": h.cfg.OrganizationID,
		"auth_url":        h.cfg.AuthURL,
		"fhir_url":        h.cfg.FhirURL,
		"zona_waktu":      h.cfg.ZonaWaktu,
		"kelurahan":       h.cfg.Kelurahan,
		"kecamatan":       h.cfg.Kecamatan,
		"kabupaten":       h.cfg.Kabupaten,
		"propinsi":        h.cfg.Propinsi,
		"kodepos":         h.cfg.KodePos,
		"longitude":       h.cfg.Longitude,
		"latitude":        h.cfg.Latitude,
	})
}

// PostSaveSettings updates settings.
func (h *Handler) PostSaveSettings(w http.ResponseWriter, r *http.Request) {
	var settings map[string]string
	if err := json.NewDecoder(r.Body).Decode(&settings); err != nil {
		jsonError(w, 400, "Invalid JSON: "+err.Error())
		return
	}

	for key, val := range settings {
		if err := h.db.SaveSetting("satu_sehat", key, val); err != nil {
			jsonError(w, 500, "Gagal menyimpan setting: "+err.Error())
			return
		}
	}

	jsonOK(w, map[string]string{"pesan": "Pengaturan telah disimpan"})
}

// =========================================================================
// Mapping Endpoints
// =========================================================================

// GetMappingPraktisi returns all practitioner mappings.
func (h *Handler) GetMappingPraktisi(w http.ResponseWriter, r *http.Request) {
	mappings, err := h.db.GetAllMappingPraktisi()
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, mappings)
}

// PostSaveMappingPraktisi saves a practitioner mapping.
func (h *Handler) PostSaveMappingPraktisi(w http.ResponseWriter, r *http.Request) {
	var req struct {
		KdDokter       string `json:"kd_dokter"`
		PractitionerID string `json:"practitioner_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, 400, "Invalid JSON")
		return
	}
	if err := h.db.SaveMappingPraktisi(req.KdDokter, req.PractitionerID); err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, map[string]string{"pesan": "Mapping praktisi berhasil disimpan"})
}

// GetMappingObat returns all medication mappings.
func (h *Handler) GetMappingObat(w http.ResponseWriter, r *http.Request) {
	mappings, err := h.db.GetAllMappingObat()
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, mappings)
}

// PostSaveMappingObat saves a medication mapping.
func (h *Handler) PostSaveMappingObat(w http.ResponseWriter, r *http.Request) {
	contentType := r.Header.Get("Content-Type")

	var m db.MappingObat

	if strings.Contains(contentType, "application/json") {
		if err := json.NewDecoder(r.Body).Decode(&m); err != nil {
			jsonError(w, 400, "Invalid JSON")
			return
		}
	} else {
		// Handle form-urlencoded
		if err := r.ParseForm(); err != nil {
			jsonError(w, 400, "Invalid form data")
			return
		}
		m.KodeObat = r.FormValue("kode_brng")
		m.KfaCoding = r.FormValue("kode_kfa")
		m.KfaDisplay = r.FormValue("nama_kfa")
	}

	if m.KodeObat == "" {
		jsonError(w, 400, "kode_brng kosong")
		return
	}

	if m.KfaCoding == "" {
		jsonError(w, 400, "kode_kfa kosong")
		return
	}

	// Validate kode_kfa is numeric
	for _, c := range m.KfaCoding {
		if c < '0' || c > '9' {
			jsonError(w, 400, "Kode KFA tidak valid, harus berupa angka")
			return
		}
	}

	if err := h.db.SaveMappingObat(&m); err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, map[string]string{"pesan": "Mapping obat berhasil disimpan"})
}

// GetMappingLab returns all lab mappings.
func (h *Handler) GetMappingLab(w http.ResponseWriter, r *http.Request) {
	mappings, err := h.db.GetAllMappingLab()
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, mappings)
}

// PostSaveMappingLab saves a lab mapping.
func (h *Handler) PostSaveMappingLab(w http.ResponseWriter, r *http.Request) {
	var m db.MappingLab
	if err := json.NewDecoder(r.Body).Decode(&m); err != nil {
		jsonError(w, 400, "Invalid JSON")
		return
	}
	if err := h.db.SaveMappingLab(&m); err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, map[string]string{"pesan": "Mapping lab berhasil disimpan"})
}

// GetMappingRad returns all radiology mappings.
func (h *Handler) GetMappingRad(w http.ResponseWriter, r *http.Request) {
	mappings, err := h.db.GetAllMappingRad()
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, mappings)
}

// PostSaveMappingRad saves a radiology mapping.
func (h *Handler) PostSaveMappingRad(w http.ResponseWriter, r *http.Request) {
	var m db.MappingRad
	if err := json.NewDecoder(r.Body).Decode(&m); err != nil {
		jsonError(w, 400, "Invalid JSON")
		return
	}
	if err := h.db.SaveMappingRad(&m); err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, map[string]string{"pesan": "Mapping radiologi berhasil disimpan"})
}

// GetMappingLokasi returns all location mappings.
func (h *Handler) GetMappingLokasi(w http.ResponseWriter, r *http.Request) {
	lokasis, err := h.db.GetAllLokasi()
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, lokasis)
}

// PostSaveMappingLokasi saves a location mapping.
func (h *Handler) PostSaveMappingLokasi(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Kode             string `json:"kode"`
		IDLokasiSatuSehat string `json:"id_lokasi_satusehat"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, 400, "Invalid JSON")
		return
	}
	if err := h.db.SaveLokasi(req.Kode, req.IDLokasiSatuSehat); err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	jsonOK(w, map[string]string{"pesan": "Mapping lokasi berhasil disimpan"})
}

// =========================================================================
// Response
// =========================================================================

// GetResponseList returns encounter response data in DataTables server-side format.
func (h *Handler) GetResponseList(w http.ResponseWriter, r *http.Request) {
	startDate := r.URL.Query().Get("tanggal_awal")
	endDate := r.URL.Query().Get("tanggal_akhir")
	draw, _ := strconv.Atoi(r.URL.Query().Get("draw"))
	if draw == 0 {
		draw = 1
	}
	start, _ := strconv.Atoi(r.URL.Query().Get("start"))
	length, _ := strconv.Atoi(r.URL.Query().Get("length"))
	if length <= 0 {
		length = 15
	}

	// DataTable sort params
	colMap := []string{"" , "tgl_registrasi", "no_rawat", "no_rkm_medis", "nm_pasien", "nm_dokter", "nm_poli", "stts", ""}
	sortColIdx, _ := strconv.Atoi(r.URL.Query().Get("order[0][column]"))
	sortCol := ""
	if sortColIdx >= 0 && sortColIdx < len(colMap) {
		sortCol = colMap[sortColIdx]
	}
	sortDir := r.URL.Query().Get("order[0][dir]")
	search := r.URL.Query().Get("search[value]")

	data, total, filtered, err := h.db.GetResponseDataTable(startDate, endDate, sortCol, sortDir, search, start, length)
	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}

	resp := map[string]interface{}{
		"draw":            draw,
		"recordsTotal":    total,
		"recordsFiltered": filtered,
		"data":            data,
	}
	jsonOK(w, resp)
}

// =========================================================================
// KFA Search
// =========================================================================

// SearchKFA searches KFA (medication catalog) by code or keyword.
func (h *Handler) SearchKFA(w http.ResponseWriter, r *http.Request) {
	code := r.URL.Query().Get("code")
	keyword := r.URL.Query().Get("keyword")

	var resp []byte
	var err error

	if keyword != "" {
		productType := r.URL.Query().Get("product_type")
		if productType == "" {
			productType = "farmasi"
		}
		resp, err = h.client.SearchKFAByKeyword(keyword, productType)
	} else if code != "" {
		resp, err = h.client.SearchKFA(code)
	} else {
		jsonError(w, 400, "parameter code atau keyword kosong")
		return
	}

	if err != nil {
		jsonError(w, 500, err.Error())
		return
	}
	rawJSON(w, resp)
}

// =========================================================================
// Health Check
// =========================================================================

// HealthCheck returns a simple health status.
func (h *Handler) HealthCheck(w http.ResponseWriter, r *http.Request) {
	jsonOK(w, map[string]string{
		"status":  "ok",
		"service": "satu-sehat-go",
		"time":    time.Now().Format(time.RFC3339),
	})
}

// fixEncounterRef replaces urn:uuid encounter references with Encounter/ real references.
func fixEncounterRef(body []byte, encounterID string) []byte {
	s := strings.ReplaceAll(string(body), "urn:uuid:"+encounterID, "Encounter/"+encounterID)
	return []byte(s)
}
