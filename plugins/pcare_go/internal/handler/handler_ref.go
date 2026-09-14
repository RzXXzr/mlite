package handler

import (
	"fmt"
	"net/http"
	"net/url"

	"github.com/go-chi/chi/v5"
)

// === Reference Data Handlers ===
// These handlers proxy GET requests to BPJS PCare API reference endpoints.

// GetPeserta retrieves patient data by card number.
func (h *Handler) GetPeserta(w http.ResponseWriter, r *http.Request) {
	noKartu := chi.URLParam(r, "noKartu")
	if noKartu == "" {
		errorJSON(w, 400, "noKartu required")
		return
	}
	data, err := h.client.Get("peserta/" + url.PathEscape(noKartu))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetPesertaByJenis retrieves patient data by card type and number.
func (h *Handler) GetPesertaByJenis(w http.ResponseWriter, r *http.Request) {
	jenis := chi.URLParam(r, "jnsPeserta")
	nomor := chi.URLParam(r, "noKartu")
	if jenis == "" || nomor == "" {
		errorJSON(w, 400, "jnsPeserta and noKartu required")
		return
	}
	data, err := h.client.Get(fmt.Sprintf("peserta/%s/%s", url.PathEscape(jenis), url.PathEscape(nomor)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetDiagnosa searches diagnoses by keyword.
func (h *Handler) GetDiagnosa(w http.ResponseWriter, r *http.Request) {
	keyword := chi.URLParam(r, "keyword")
	if keyword == "" {
		keyword = r.URL.Query().Get("keyword")
	}
	if keyword == "" {
		errorJSON(w, 400, "keyword required")
		return
	}
	data, err := h.client.Get(fmt.Sprintf("diagnosa/%s/0/500", url.PathEscape(keyword)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetDokter retrieves list of doctors.
func (h *Handler) GetDokter(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("dokter/0/500")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetPoli retrieves list of clinics.
func (h *Handler) GetPoli(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("poli/fktp/0/500")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetKesadaran retrieves consciousness levels.
func (h *Handler) GetKesadaran(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("kesadaran")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetAlergi retrieves allergies by type.
func (h *Handler) GetAlergi(w http.ResponseWriter, r *http.Request) {
	jenis := chi.URLParam(r, "jenis")
	if jenis == "" {
		jenis = "01" // Default: makanan
	}
	data, err := h.client.Get(fmt.Sprintf("alergi/jenis/%s", url.PathEscape(jenis)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetPrognosa retrieves prognosis types.
func (h *Handler) GetPrognosa(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("prognosa")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetProvider retrieves healthcare providers.
func (h *Handler) GetProvider(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("provider/0/500")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSpesialis retrieves specialist types.
func (h *Handler) GetSpesialis(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("spesialis")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSubSpesialis retrieves sub-specialists for a specialist.
func (h *Handler) GetSubSpesialis(w http.ResponseWriter, r *http.Request) {
	kd := chi.URLParam(r, "kdSpesialis")
	data, err := h.client.Get(fmt.Sprintf("spesialis/%s/subspesialis", url.PathEscape(kd)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSarana retrieves medical facility types.
func (h *Handler) GetSarana(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("spesialis/sarana")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSpesialisKhusus retrieves special referral categories.
func (h *Handler) GetSpesialisKhusus(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("spesialis/khusus")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetFaskesRujukan retrieves referral facilities.
func (h *Handler) GetFaskesRujukan(w http.ResponseWriter, r *http.Request) {
	kdSubSpesialis := chi.URLParam(r, "kdSubSpesialis")
	kdSarana := chi.URLParam(r, "kdSarana")
	tglEstRujuk := chi.URLParam(r, "tglEstRujuk")
	endpoint := fmt.Sprintf("spesialis/rujuk/subspesialis/%s/sarana/%s/tglEstRujuk/%s",
		url.PathEscape(kdSubSpesialis), url.PathEscape(kdSarana), url.PathEscape(tglEstRujuk))
	data, err := h.client.Get(endpoint)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetFaskesKhusus retrieves special referral facilities.
func (h *Handler) GetFaskesKhusus(w http.ResponseWriter, r *http.Request) {
	kode := chi.URLParam(r, "kode")
	noKartu := chi.URLParam(r, "noKartu")
	tglEstRujuk := chi.URLParam(r, "tglEstRujuk")
	endpoint := fmt.Sprintf("spesialis/rujuk/khusus/%s/noKartu/%s/tglEstRujuk/%s",
		url.PathEscape(kode), url.PathEscape(noKartu), url.PathEscape(tglEstRujuk))
	data, err := h.client.Get(endpoint)
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetStatusPulang retrieves discharge statuses.
func (h *Handler) GetStatusPulang(w http.ResponseWriter, r *http.Request) {
	isRawatInap := chi.URLParam(r, "isRawatInap")
	if isRawatInap == "" {
		isRawatInap = "false"
	}
	data, err := h.client.Get(fmt.Sprintf("statuspulang/rawatInap/%s", url.PathEscape(isRawatInap)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetObatDPHO searches medicines by DPHO code/name.
func (h *Handler) GetObatDPHO(w http.ResponseWriter, r *http.Request) {
	keyword := chi.URLParam(r, "keyword")
	if keyword == "" {
		keyword = r.URL.Query().Get("keyword")
	}
	if keyword == "" {
		errorJSON(w, 400, "keyword required")
		return
	}
	data, err := h.client.Get(fmt.Sprintf("obat/dpho/%s/0/500", url.PathEscape(keyword)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetTindakanRef retrieves procedure reference by TKP code.
func (h *Handler) GetTindakanRef(w http.ResponseWriter, r *http.Request) {
	kdTkp := chi.URLParam(r, "kdTkp")
	if kdTkp == "" {
		kdTkp = "10" // Default: RJTP
	}
	data, err := h.client.Get(fmt.Sprintf("tindakan/kdTkp/%s/0/500", url.PathEscape(kdTkp)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetKelompokClub retrieves prolanis club list.
func (h *Handler) GetKelompokClub(w http.ResponseWriter, r *http.Request) {
	jenis := chi.URLParam(r, "jenis")
	if jenis == "" {
		jenis = "01" // Default: DM
	}
	data, err := h.client.Get(fmt.Sprintf("kelompok/club/%s", url.PathEscape(jenis)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetKelompokKegiatan retrieves group activities.
func (h *Handler) GetKelompokKegiatan(w http.ResponseWriter, r *http.Request) {
	kdClub := chi.URLParam(r, "kdClub")
	if kdClub == "" {
		errorJSON(w, 400, "kdClub required")
		return
	}
	data, err := h.client.Get(fmt.Sprintf("kelompok/kegiatan/%s", url.PathEscape(kdClub)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetKelompokPeserta retrieves activity participants.
func (h *Handler) GetKelompokPeserta(w http.ResponseWriter, r *http.Request) {
	eduId := chi.URLParam(r, "eduId")
	data, err := h.client.Get(fmt.Sprintf("kelompok/peserta/%s", url.PathEscape(eduId)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSkriningRekap retrieves screening summary.
func (h *Handler) GetSkriningRekap(w http.ResponseWriter, r *http.Request) {
	data, err := h.client.Get("skrinning/rekap")
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSkriningPeserta retrieves screening participant details.
func (h *Handler) GetSkriningPeserta(w http.ResponseWriter, r *http.Request) {
	keyword := chi.URLParam(r, "keyword")
	data, err := h.client.Get(fmt.Sprintf("skrinning/peserta/%s/0/500", url.PathEscape(keyword)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}

// GetSkriningProlanis retrieves prolanis screening data.
func (h *Handler) GetSkriningProlanis(w http.ResponseWriter, r *http.Request) {
	tipe := chi.URLParam(r, "tipe") // dm or ht
	keyword := chi.URLParam(r, "keyword")
	data, err := h.client.Get(fmt.Sprintf("skrinning/prolanis/%s/%s/0/500", url.PathEscape(tipe), url.PathEscape(keyword)))
	if err != nil {
		errorJSON(w, 500, err.Error())
		return
	}
	jsonRaw(w, 200, data)
}
