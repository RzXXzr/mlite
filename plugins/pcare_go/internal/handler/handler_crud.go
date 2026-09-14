package handler

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"

	"github.com/go-chi/chi/v5"
)

// === Pendaftaran (Registration) CRUD ===

// GetPendaftaranNoUrut retrieves registration by sequence number.
func (h *Handler) GetPendaftaranNoUrut(w http.ResponseWriter, r *http.Request) {
	noUrut := chi.URLParam(r, "noUrut")
	tglDaftar := chi.URLParam(r, "tglDaftar")
	if noUrut == "" || tglDaftar == "" {
		errorJSON(w, 400, "noUrut and tglDaftar required")
		return
	}
	endpoint := fmt.Sprintf("pendaftaran/noUrut/%s/tglDaftar/%s",
		url.PathEscape(noUrut), url.PathEscape(tglDaftar))
	data, err := h.client.Get(endpoint)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// GetPendaftaranProvider retrieves registrations by date.
func (h *Handler) GetPendaftaranProvider(w http.ResponseWriter, r *http.Request) {
	tglDaftar := chi.URLParam(r, "tglDaftar")
	if tglDaftar == "" {
		tglDaftar = r.URL.Query().Get("tglDaftar")
	}
	if tglDaftar == "" {
		errorJSON(w, 400, "tglDaftar required")
		return
	}
	offset := chi.URLParam(r, "offset")
	limit := chi.URLParam(r, "limit")
	if offset == "" {
		offset = "0"
	}
	if limit == "" {
		limit = "10"
	}
	endpoint := fmt.Sprintf("pendaftaran/tglDaftar/%s/%s/%s", url.PathEscape(tglDaftar), url.PathEscape(offset), url.PathEscape(limit))
	data, err := h.client.Get(endpoint)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PostPendaftaran creates a new registration.
func (h *Handler) PostPendaftaran(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("pendaftaran", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeletePendaftaran deletes a registration.
func (h *Handler) DeletePendaftaran(w http.ResponseWriter, r *http.Request) {
	noKartu := chi.URLParam(r, "noKartu")
	tglDaftar := chi.URLParam(r, "tglDaftar")
	noUrut := chi.URLParam(r, "noUrut")
	kdPoli := chi.URLParam(r, "kdPoli")
	endpoint := fmt.Sprintf("pendaftaran/peserta/%s/tglDaftar/%s/noUrut/%s/kdPoli/%s",
		url.PathEscape(noKartu), url.PathEscape(tglDaftar), url.PathEscape(noUrut), url.PathEscape(kdPoli))
	data, err := h.client.Delete(endpoint)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// === Kunjungan (Visit) CRUD ===

// GetKunjunganRujukan retrieves referral data for a visit.
func (h *Handler) GetKunjunganRujukan(w http.ResponseWriter, r *http.Request) {
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Get(fmt.Sprintf("kunjungan/rujukan/%s", url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// GetKunjunganRiwayat retrieves visit history by card number.
func (h *Handler) GetKunjunganRiwayat(w http.ResponseWriter, r *http.Request) {
	noKartu := chi.URLParam(r, "noKartu")
	data, err := h.client.Get(fmt.Sprintf("kunjungan/peserta/%s", url.PathEscape(noKartu)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// GetKunjunganRiwayatSemua returns visit history from ALL providers for a patient.
func (h *Handler) GetKunjunganRiwayatSemua(w http.ResponseWriter, r *http.Request) {
	noKartu := chi.URLParam(r, "noKartu")
	// Try all-provider endpoints
	endpoints := []string{
		fmt.Sprintf("kunjungan/peserta/semua/%s", url.PathEscape(noKartu)),
		fmt.Sprintf("kunjungan/peserta/%s", url.PathEscape(noKartu)),
	}
	for _, ep := range endpoints {
		data, err := h.client.Get(ep)
		if err == nil {
			pcareResponse(w, data)
			return
		}
	}
	errorJSON(w, 500, "Gagal mengambil riwayat kunjungan semua provider")
}

// PostKunjungan creates a new visit.
func (h *Handler) PostKunjungan(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("kunjungan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PutKunjungan updates a visit.
func (h *Handler) PutKunjungan(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Put("kunjungan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteKunjungan deletes a visit.
func (h *Handler) DeleteKunjungan(w http.ResponseWriter, r *http.Request) {
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Delete(fmt.Sprintf("kunjungan/%s", url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// === Obat (Medicine) CRUD ===

// GetObatKunjungan retrieves medicines for a visit.
func (h *Handler) GetObatKunjungan(w http.ResponseWriter, r *http.Request) {
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Get(fmt.Sprintf("obat/kunjungan/%s", url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PostObat adds a medicine to a visit.
func (h *Handler) PostObat(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("obat/kunjungan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteObat removes a medicine from a visit.
func (h *Handler) DeleteObat(w http.ResponseWriter, r *http.Request) {
	kdObatSK := chi.URLParam(r, "kdObatSK")
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Delete(fmt.Sprintf("obat/%s/kunjungan/%s",
		url.PathEscape(kdObatSK), url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// === Tindakan (Procedure) CRUD ===

// GetTindakanKunjungan retrieves procedures for a visit.
func (h *Handler) GetTindakanKunjungan(w http.ResponseWriter, r *http.Request) {
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Get(fmt.Sprintf("tindakan/kunjungan/%s", url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PostTindakan adds a procedure to a visit.
func (h *Handler) PostTindakan(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("tindakan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PutTindakan updates a procedure.
func (h *Handler) PutTindakan(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Put("tindakan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteTindakan removes a procedure from a visit.
func (h *Handler) DeleteTindakan(w http.ResponseWriter, r *http.Request) {
	kdTindakanSK := chi.URLParam(r, "kdTindakanSK")
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Delete(fmt.Sprintf("tindakan/%s/kunjungan/%s",
		url.PathEscape(kdTindakanSK), url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// === MCU (Medical Check-Up) CRUD ===

// GetMCU retrieves MCU data for a visit.
func (h *Handler) GetMCU(w http.ResponseWriter, r *http.Request) {
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Get(fmt.Sprintf("MCU/kunjungan/%s", url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PostMCU creates MCU data.
func (h *Handler) PostMCU(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("MCU", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// PutMCU updates MCU data.
func (h *Handler) PutMCU(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Put("MCU", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteMCU deletes MCU data.
func (h *Handler) DeleteMCU(w http.ResponseWriter, r *http.Request) {
	kdMCU := chi.URLParam(r, "kdMCU")
	noKunjungan := chi.URLParam(r, "noKunjungan")
	data, err := h.client.Delete(fmt.Sprintf("MCU/%s/kunjungan/%s",
		url.PathEscape(kdMCU), url.PathEscape(noKunjungan)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// === Kelompok (Prolanis Group) CRUD ===

// PostKelompokKegiatan creates a group activity.
func (h *Handler) PostKelompokKegiatan(w http.ResponseWriter, r *http.Request) {
	var req map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		errorJSON(w, 400, "Invalid JSON body")
		return
	}
	data, err := h.client.Post("kelompok/kegiatan", req)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteKelompokKegiatan deletes a group activity.
func (h *Handler) DeleteKelompokKegiatan(w http.ResponseWriter, r *http.Request) {
	eduId := chi.URLParam(r, "eduId")
	data, err := h.client.Delete(fmt.Sprintf("kelompok/kegiatan/%s", url.PathEscape(eduId)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}

// DeleteKelompokPeserta removes a participant from an activity.
func (h *Handler) DeleteKelompokPeserta(w http.ResponseWriter, r *http.Request) {
	eduId := chi.URLParam(r, "eduId")
	noKartu := chi.URLParam(r, "noKartu")
	data, err := h.client.Delete(fmt.Sprintf("kelompok/peserta/%s/%s",
		url.PathEscape(eduId), url.PathEscape(noKartu)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	pcareResponse(w, data)
}
