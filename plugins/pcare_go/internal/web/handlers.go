package web

import (
	"fmt"
	"log"
	"net/http"

	"pcare-go/internal/config"
	"pcare-go/internal/db"
)

// WebHandler serves the HTML frontend pages.
type WebHandler struct {
	cfg    *config.Config
	db     *db.DB
	engine *TemplateEngine
}

// NewWebHandler creates a new WebHandler.
func NewWebHandler(cfg *config.Config, database *db.DB) (*WebHandler, error) {
	engine, err := NewTemplateEngine()
	if err != nil {
		return nil, fmt.Errorf("failed to init templates: %w", err)
	}
	return &WebHandler{cfg: cfg, db: database, engine: engine}, nil
}

func (wh *WebHandler) render(w http.ResponseWriter, name string, data interface{}) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := wh.engine.Render(w, name, data); err != nil {
		log.Printf("Template render error [%s]: %v", name, err)
		http.Error(w, "Internal Server Error", 500)
	}
}

// ManagePage renders the main dashboard with navigation cards.
func (wh *WebHandler) ManagePage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "manage", PageData{Title: "PCare BPJS", Page: "manage"})
}

// SettingsPage renders the settings form.
func (wh *WebHandler) SettingsPage(w http.ResponseWriter, r *http.Request) {
	dbSettings, _ := wh.db.GetSettings("pcare")

	getS := func(key, fallback string) string {
		if v, ok := dbSettings[key]; ok && v != "" {
			return v
		}
		return fallback
	}

	data := SettingsPageData{
		PageData: PageData{Title: "Pengaturan PCare", Page: "settings"},
		Settings: SettingsData{
			PCareAPIURL:       getS("pcare_api_url", wh.cfg.PCareAPIURL),
			ConsumerID:        getS("pcare_consumer_id", wh.cfg.ConsumerID),
			ConsumerSecret:    getS("pcare_consumer_secret", wh.cfg.ConsumerSecret),
			Username:          getS("pcare_username", wh.cfg.Username),
			Password:          getS("pcare_password", wh.cfg.Password),
			UserKey:           getS("pcare_user_key", wh.cfg.UserKey),
			KdAplikasi:        getS("pcare_kd_aplikasi", wh.cfg.KdAplikasi),
			KodeFKTP:          getS("pcare_kode_fktp", wh.cfg.KodeFKTP),
			NamaFKTP:          getS("pcare_nama_fktp", wh.cfg.NamaFKTP),
			KodeKabupatenKota: getS("pcare_kode_kabupaten_kota", wh.cfg.KodeKabupatenKota),
			KabupatenKota:     getS("pcare_kabupaten_kota", wh.cfg.KabupatenKota),
			Wilayah:           getS("pcare_wilayah", wh.cfg.Wilayah),
			Cabang:            getS("pcare_cabang", wh.cfg.Cabang),
			KdPjBpjs:          getS("kd_pj_bpjs", ""),
		},
	}
	wh.render(w, "settings", data)
}

// DashboardPage renders the dashboard page.
func (wh *WebHandler) DashboardPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "dashboard", DashboardPageData{
		PageData: PageData{Title: "Dashboard PCare", Page: "dashboard"},
	})
}

// MonitorPage renders the monitoring page.
func (wh *WebHandler) MonitorPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "monitorpcare", MonitorPageData{
		PageData: PageData{Title: "Monitor PCare", Page: "monitorpcare"},
	})
}

// BridgingPage renders the bridging data page.
func (wh *WebHandler) BridgingPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "bridging", BridgingPageData{
		PageData: PageData{Title: "Data Bridging", Page: "bridging"},
	})
}

// PesertaPage renders the peserta lookup page.
func (wh *WebHandler) PesertaPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "peserta", PesertaPageData{
		PageData: PageData{Title: "Peserta BPJS", Page: "peserta"},
	})
}

// PendaftaranPage renders the pendaftaran page.
func (wh *WebHandler) PendaftaranPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "pendaftaran", PendaftaranPageData{
		PageData: PageData{Title: "Pendaftaran PCare", Page: "pendaftaran"},
	})
}

// KunjunganPage renders the kunjungan page.
func (wh *WebHandler) KunjunganPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "kunjungan", KunjunganPageData{
		PageData: PageData{Title: "Kunjungan", Page: "kunjungan"},
	})
}

// ObatPage renders the obat DPHO page.
func (wh *WebHandler) ObatPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "obat", ObatPageData{
		PageData: PageData{Title: "Obat DPHO", Page: "obat"},
	})
}

// TindakanPage renders the tindakan page.
func (wh *WebHandler) TindakanPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "tindakan", TindakanPageData{
		PageData: PageData{Title: "Tindakan", Page: "tindakan"},
	})
}

// DiagnosaPage renders the diagnosa page.
func (wh *WebHandler) DiagnosaPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "diagnosa", DiagnosaPageData{
		PageData: PageData{Title: "Diagnosa", Page: "diagnosa"},
	})
}

// DokterPage renders the dokter page.
func (wh *WebHandler) DokterPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "dokter", DokterPageData{
		PageData: PageData{Title: "Dokter", Page: "dokter"},
	})
}

// PoliPage renders the poli page.
func (wh *WebHandler) PoliPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "poli", PoliPageData{
		PageData: PageData{Title: "Poliklinik", Page: "poli"},
	})
}

// AlergiPage renders the alergi page.
func (wh *WebHandler) AlergiPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "alergi", AlergiPageData{
		PageData: PageData{Title: "Alergi", Page: "alergi"},
	})
}

// KesadaranPage renders the kesadaran page.
func (wh *WebHandler) KesadaranPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "kesadaran", KesadaranPageData{
		PageData: PageData{Title: "Kesadaran", Page: "kesadaran"},
	})
}

// PrognosaPage renders the prognosa page.
func (wh *WebHandler) PrognosaPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "prognosa", PrognosaPageData{
		PageData: PageData{Title: "Prognosa", Page: "prognosa"},
	})
}

// ProviderPage renders the provider page.
func (wh *WebHandler) ProviderPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "provider", ProviderPageData{
		PageData: PageData{Title: "Provider", Page: "provider"},
	})
}

// SpesialisPage renders the spesialis page.
func (wh *WebHandler) SpesialisPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "spesialis", SpesialisPageData{
		PageData: PageData{Title: "Spesialis", Page: "spesialis"},
	})
}

// KelompokPage renders the kelompok page.
func (wh *WebHandler) KelompokPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "kelompok", KelompokPageData{
		PageData: PageData{Title: "Kelompok / Club", Page: "kelompok"},
	})
}

// StatusPulangPage renders the status pulang page.
func (wh *WebHandler) StatusPulangPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "statuspulang", StatusPulangPageData{
		PageData: PageData{Title: "Status Pulang", Page: "statuspulang"},
	})
}

// MCUPage renders the MCU page.
func (wh *WebHandler) MCUPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "mcu", MCUPageData{
		PageData: PageData{Title: "MCU", Page: "mcu"},
	})
}

// SkriningPage renders the skrining page.
func (wh *WebHandler) SkriningPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "skrining", SkriningPageData{
		PageData: PageData{Title: "Skrining", Page: "skrining"},
	})
}

// CekSinkronisasiPage renders the cek sinkronisasi page.
func (wh *WebHandler) CekSinkronisasiPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "ceksinkronisasi", CekSinkronisasiPageData{
		PageData: PageData{Title: "Cek Sinkronisasi", Page: "ceksinkronisasi"},
	})
}

// CekPendaftaranPage renders the cek pendaftaran page.
func (wh *WebHandler) CekPendaftaranPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "cekpendaftaran", CekPendaftaranPageData{
		PageData: PageData{Title: "Cek Pendaftaran Provider", Page: "cekpendaftaran"},
	})
}

// DataKunjunganBpjsPage renders the data kunjungan BPJS page.
func (wh *WebHandler) DataKunjunganBpjsPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "datakunjunganbpjs", DataKunjunganBpjsPageData{
		PageData: PageData{Title: "Data Kunjungan BPJS", Page: "datakunjunganbpjs"},
	})
}
