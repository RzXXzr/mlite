package web

import (
	"fmt"
	"log"
	"net/http"

	"satu-sehat-go/internal/config"
	"satu-sehat-go/internal/cron"
	"satu-sehat-go/internal/db"
)

// WebHandler serves the HTML frontend pages.
type WebHandler struct {
	cfg       *config.Config
	db        *db.DB
	engine    *TemplateEngine
	scheduler *cron.Scheduler
}

// NewWebHandler creates a new WebHandler.
func NewWebHandler(cfg *config.Config, database *db.DB) (*WebHandler, error) {
	engine, err := NewTemplateEngine()
	if err != nil {
		return nil, fmt.Errorf("failed to init templates: %w", err)
	}
	return &WebHandler{cfg: cfg, db: database, engine: engine}, nil
}

// SetScheduler injects the cron scheduler for use by CronPage.
func (wh *WebHandler) SetScheduler(s *cron.Scheduler) {
	wh.scheduler = s
}

func (wh *WebHandler) render(w http.ResponseWriter, name string, data interface{}) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := wh.engine.Render(w, name, data); err != nil {
		log.Printf("Template render error [%s]: %v", name, err)
		http.Error(w, "Internal Server Error", 500)
	}
}

// ManagePage renders the dashboard with navigation cards.
func (wh *WebHandler) ManagePage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "manage", PageData{Title: "Dashboard", Page: "manage"})
}

// SettingsPage renders the settings form.
func (wh *WebHandler) SettingsPage(w http.ResponseWriter, r *http.Request) {
	// Load settings from DB (mlite_settings where module='satu_sehat')
	dbSettings, _ := wh.db.GetSettings("satu_sehat")

	// Load mapping_lokasi for dropdowns
	lokasiRows, _ := wh.db.GetMappingLokasi()
	var mappingLokasi []LokasiItem
	for _, loc := range lokasiRows {
		mappingLokasi = append(mappingLokasi, LokasiItem{
			Kode:   loc.Kode,
			Lokasi: loc.Lokasi,
		})
	}

	// Load bidang (departemen names for practitioner mapping)
	bidangRows, _ := wh.db.GetBidang()
	var bidang []BidangItem
	for _, b := range bidangRows {
		bidang = append(bidang, BidangItem{Nama: b})
	}

	// Helper to get from DB settings with fallback to config
	getS := func(key, fallback string) string {
		if v, ok := dbSettings[key]; ok && v != "" {
			return v
		}
		return fallback
	}

	data := SettingsPageData{
		PageData: PageData{Title: "Pengaturan", Page: "settings"},
		Settings: SettingsData{
			OrganizationID: getS("organizationid", wh.cfg.OrganizationID),
			ClientID:       getS("clientid", wh.cfg.ClientID),
			SecretKey:      getS("secretkey", wh.cfg.SecretKey),
			AuthURL:        getS("authurl", wh.cfg.AuthURL),
			FhirURL:        getS("fhirurl", wh.cfg.FhirURL),
			Latitude:       getS("latitude", wh.cfg.Latitude),
			Longitude:      getS("longitude", wh.cfg.Longitude),
			Kelurahan:      getS("kelurahan", wh.cfg.Kelurahan),
			Kecamatan:      getS("kecamatan", wh.cfg.Kecamatan),
			Kabupaten:      getS("kabupaten", wh.cfg.Kabupaten),
			Propinsi:       getS("propinsi", wh.cfg.Propinsi),
			KodePos:        getS("kodepos", wh.cfg.KodePos),
			ZonaWaktu:      getS("zonawaktu", wh.cfg.ZonaWaktu),
			Farmasi:        getS("farmasi", wh.cfg.Farmasi),
			Laboratorium:   getS("laboratorium", wh.cfg.Laboratorium),
			Radiologi:      getS("radiologi", wh.cfg.Radiologi),
			PraktisiApotek: getS("praktisi_apotek", wh.cfg.PraktisiApotek),
			PraktisiLab:    getS("praktisi_lab", wh.cfg.PraktisiLab),
			PraktisiRad:    getS("praktisi_rad", wh.cfg.PraktisiRad),
			APIOpenAI:      getS("api_openai", wh.cfg.APIOpenAI),
		},
		Bidang:        bidang,
		MappingLokasi: mappingLokasi,
	}
	wh.render(w, "settings", data)
}

// ResponsePage renders the response data table page.
func (wh *WebHandler) ResponsePage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "response", ResponsePageData{
		PageData: PageData{Title: "Response", Page: "response"},
	})
}

// BulkPage renders the bulk sending page.
func (wh *WebHandler) BulkPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "bulk", BulkPageData{
		PageData: PageData{Title: "Kirim Massal", Page: "bulk"},
	})
}

// DepartemenPage renders the department mapping page.
func (wh *WebHandler) DepartemenPage(w http.ResponseWriter, r *http.Request) {
	// Get all existing KHANZA departments
	depts, _ := wh.db.GetDepartemen()
	var deptOptions []DepartemenOption
	for _, d := range depts {
		deptOptions = append(deptOptions, DepartemenOption{DepID: d.DepID, Nama: d.Nama})
	}

	// Get mapped departments
	mapped, _ := wh.db.GetMappingDepartemen()
	var mappedDepts []DepartemenMapping
	for _, m := range mapped {
		mappedDepts = append(mappedDepts, DepartemenMapping{
			DepID: m.DepID, Nama: m.Nama, IDOrganisasi: m.IDOrganisasi,
		})
	}

	data := DepartemenPageData{
		PageData:          PageData{Title: "Mapping Departemen", Page: "departemen"},
		Departemen:        deptOptions,
		MappingDepartemen: mappedDepts,
	}
	wh.render(w, "departemen", data)
}

// LokasiPage renders the location mapping page.
func (wh *WebHandler) LokasiPage(w http.ResponseWriter, r *http.Request) {
	// Poliklinik / Unit options
	polis, _ := wh.db.GetPoliklinik()
	var lokasiOpts []LokasiOption
	for _, p := range polis {
		lokasiOpts = append(lokasiOpts, LokasiOption{Kode: p.KdPoli, Nama: p.NmPoli})
	}

	// Bangsal options
	bangsals, _ := wh.db.GetBangsal()
	for _, b := range bangsals {
		lokasiOpts = append(lokasiOpts, LokasiOption{Kode: b.KdBangsal, Nama: b.NmBangsal})
	}

	// Mapped departments
	deptsMapped, _ := wh.db.GetMappingDepartemen()
	var deptMaps []DepartemenMapping
	for _, d := range deptsMapped {
		deptMaps = append(deptMaps, DepartemenMapping{DepID: d.DepID, Nama: d.Nama, IDOrganisasi: d.IDOrganisasi})
	}

	// Mapped locations
	locs, _ := wh.db.GetAllMappingLokasi()
	var lokasiMaps []LokasiMapping
	for _, l := range locs {
		lokasiMaps = append(lokasiMaps, LokasiMapping{
			Kode: l.Kode, Lokasi: l.Lokasi, DepID: l.DepID,
			IDOrganisasi: l.IDOrganisasi, IDLokasi: l.IDLokasi,
			Nama: l.Nama, Longitude: l.Longitude, Latitude: l.Latitude, Altitude: l.Altitude,
		})
	}

	data := LokasiPageData{
		PageData:            PageData{Title: "Mapping Lokasi", Page: "lokasi"},
		Lokasi:              lokasiOpts,
		SatuSehatDepartemen: deptMaps,
		SatuSehatLokasi:     lokasiMaps,
	}
	wh.render(w, "lokasi", data)
}

// MappingPraktisiPage renders the practitioner mapping page.
func (wh *WebHandler) MappingPraktisiPage(w http.ResponseWriter, r *http.Request) {
	// Doctors
	docs, _ := wh.db.GetDokter()
	var dokterOpts []DokterOption
	for _, d := range docs {
		dokterOpts = append(dokterOpts, DokterOption{KdDokter: d.KdDokter, NmDokter: d.NmDokter})
	}

	// Apoteker / medis
	apotekers, _ := wh.db.GetApoteker()
	var apoOpts []ApotekerOption
	for _, a := range apotekers {
		apoOpts = append(apoOpts, ApotekerOption{NIK: a.NIK, Nama: a.Nama})
	}

	// Mapped practitioners
	mapped, _ := wh.db.GetMappingPraktisiAll()
	var mappedPrs []PraktisiMapping
	for _, m := range mapped {
		mappedPrs = append(mappedPrs, PraktisiMapping{
			KdDokter: m.KdDokter, NmDokter: m.NmDokter, PractitionerID: m.PractitionerID,
		})
	}

	data := PraktisiPageData{
		PageData:        PageData{Title: "Mapping Praktisi", Page: "mapping_praktisi"},
		Dokter:          dokterOpts,
		Apoteker:        apoOpts,
		MappingPraktisi: mappedPrs,
	}
	wh.render(w, "mapping_praktisi", data)
}

// MappingObatPage renders the medication mapping page.
func (wh *WebHandler) MappingObatPage(w http.ResponseWriter, r *http.Request) {
	obat, _ := wh.db.GetAllObatWithMapping()
	var rows []ObatRow
	totalMapped, totalPushed, totalRepush := 0, 0, 0
	for _, o := range obat {
		row := ObatRow{
			KodeBrng:         o.KodeBrng,
			NamaBrng:         o.NamaBrng,
			Mapped:           o.KodeKFA != "",
			KodeKFA:          o.KodeKFA,
			NamaKFA:          o.NamaKFA,
			Type:             o.Type,
			IDMedication:     o.IDMedication,
			UpdatedAfterPush: o.UpdatedAfterPush == 1,
		}
		if row.Mapped {
			totalMapped++
		}
		if row.IDMedication != "" {
			totalPushed++
			if row.UpdatedAfterPush {
				totalRepush++
			}
		}
		rows = append(rows, row)
	}

	// DataBarang dropdown
	barangs, _ := wh.db.GetDataBarang()
	var barangOpts []BarangOption
	for _, b := range barangs {
		barangOpts = append(barangOpts, BarangOption{KodeBrng: b.KodeBrng, NamaBrng: b.NamaBrng})
	}

	data := ObatPageData{
		PageData:      PageData{Title: "Mapping Obat", Page: "mapping_obat"},
		TotalObat:     len(rows),
		TotalMapped:   totalMapped,
		TotalUnmapped: len(rows) - totalMapped,
		TotalPushed:   totalPushed,
		TotalRepush:   totalRepush,
		AllObat:       rows,
		DataBarang:    barangOpts,
	}
	wh.render(w, "mapping_obat", data)
}

// MappingLabPage renders the laboratory mapping page.
func (wh *WebHandler) MappingLabPage(w http.ResponseWriter, r *http.Request) {
	templates, _ := wh.db.GetTemplateLab()
	var tmplItems []LabTemplate
	for _, t := range templates {
		tmplItems = append(tmplItems, LabTemplate{
			IDTemplate: t.IDTemplate, KdJenisPerawatan: t.KdJenisPerawatan, Pemeriksaan: t.Pemeriksaan,
		})
	}

	mapped, _ := wh.db.GetMappingLabAll()
	var mappedItems []LabMapping
	for _, m := range mapped {
		mappedItems = append(mappedItems, LabMapping{
			IDTemplate: m.IDTemplate, KdJenisPerawatan: m.KdJenisPerawatan, Pemeriksaan: m.Pemeriksaan,
			Code: m.Code, Display: m.Display,
			SampelCode: m.SampelCode, SampelSystem: m.SampelSystem, SampelDisplay: m.SampelDisplay,
		})
	}

	data := LabPageData{
		PageData:    PageData{Title: "Mapping Laboratorium", Page: "mapping_lab"},
		TemplateLab: tmplItems,
		MappingLab:  mappedItems,
	}
	wh.render(w, "mapping_lab", data)
}

// MappingRadPage renders the radiology mapping page.
func (wh *WebHandler) MappingRadPage(w http.ResponseWriter, r *http.Request) {
	prws, _ := wh.db.GetJnsPerawatanRad()
	var prwItems []RadPerawatan
	for _, p := range prws {
		prwItems = append(prwItems, RadPerawatan{
			KdJenisPerawatan: p.KdJenisPerawatan, NmPerawatan: p.NmPerawatan,
		})
	}

	mapped, _ := wh.db.GetMappingRadAll()
	var mappedItems []RadMapping
	for _, m := range mapped {
		mappedItems = append(mappedItems, RadMapping{
			KdJenisPerawatan: m.KdJenisPerawatan, NmPerawatan: m.NmPerawatan,
			Code: m.Code, Display: m.Display,
			SampelCode: m.SampelCode, SampelSystem: m.SampelSystem, SampelDisplay: m.SampelDisplay,
		})
	}

	data := RadPageData{
		PageData:        PageData{Title: "Mapping Radiologi", Page: "mapping_rad"},
		JnsPerawatanRad: prwItems,
		MappingRad:      mappedItems,
	}
	wh.render(w, "mapping_rad", data)
}

// PraktisiRefPage renders the referensi praktisi search page.
func (wh *WebHandler) PraktisiRefPage(w http.ResponseWriter, r *http.Request) {
	wh.render(w, "praktisi", PraktisiRefPageData{
		PageData: PageData{Title: "Referensi Praktisi", Page: "praktisi"},
	})
}

// CronPage renders the cron monitoring page.
func (wh *WebHandler) CronPage(w http.ResponseWriter, r *http.Request) {
	// Generate jam options
	var jamOpts []JamOption
	for h := 0; h < 24; h++ {
		val := fmt.Sprintf("%02d:00", h)
		jamOpts = append(jamOpts, JamOption{Val: val, Label: val + " WIB"})
	}

	// Load hutang per bulan from DB
	hutangRows, _ := wh.db.GetHutangPerBulan()
	var hutangPerBulan []HutangBulan
	for _, h := range hutangRows {
		hutangPerBulan = append(hutangPerBulan, HutangBulan{
			Bulan:      h.Bulan,
			Total:      h.Total,
			BelumKirim: h.BelumKirim,
			SudahKirim: h.SudahKirim,
			Persen:     h.Persen,
		})
	}

	// Load totals
	totalHutang, totalKunjungan, _ := wh.db.GetTotalHutang()

	// Cron state from scheduler
	cronRunning := false
	var progress cron.Progress
	var settings cron.Settings
	logContent := "Cron belum pernah dijalankan."

	if wh.scheduler != nil {
		cronRunning = wh.scheduler.IsRunning()
		progress = wh.scheduler.GetProgress()
		settings = wh.scheduler.GetSettings()

		logs := wh.scheduler.GetLogs(100)
		if len(logs) > 0 {
			logContent = ""
			for _, entry := range logs {
				logContent += fmt.Sprintf("[%s] [%s] %s\n", entry.Time, entry.Level, entry.Message)
			}
		}
	}

	progressLastDate := "-"
	if progress.LastCompletedDate != "" {
		progressLastDate = progress.LastCompletedDate
	}
	progressLastRun := "-"
	if progress.LastRun != "" {
		progressLastRun = progress.LastRun
	}
	progressStartDate := "-"
	if progress.StartDate != "" {
		progressStartDate = progress.StartDate
	}

	data := CronPageData{
		PageData:               PageData{Title: "Cron Monitor", Page: "cron"},
		CronRunning:            cronRunning,
		TotalHutang:            totalHutang,
		TotalKunjungan:         totalKunjungan,
		ProgressTotalSuccess:   progress.TotalSuccess,
		ProgressSessions:       progress.Sessions,
		ProgressLastDate:       progressLastDate,
		ProgressLastRun:        progressLastRun,
		ProgressStartDate:      progressStartDate,
		ProgressTotalProcessed: progress.TotalProcessed,
		ProgressTotalFailed:    progress.TotalFailed,		ProgressTotalSkipped:  progress.TotalSkipped,		HutangPerBulan:         hutangPerBulan,
		JamOptions:             jamOpts,
		CronSettings: CronSettingsData{
			JamMulai:        settings.JamMulai,
			JamBerhenti:     settings.JamBerhenti,
			MaxErrors:       settings.MaxErrors,
			TanggalDari:     settings.TanggalDari,
			CrontabSchedule: settings.CrontabSchedule,
			Enabled:         settings.Enabled,
			Concurrency:     settings.Concurrency,
			RequestPerMenit: settings.RequestPerMenit,
		},
		LogContent: logContent,
	}
	wh.render(w, "cron", data)
}
