package handler

import (
	"github.com/go-chi/chi/v5"
	"pcare-go/internal/middleware"
	"pcare-go/internal/web"
)

// NewRouter creates the chi router with all routes.
func NewRouter(h *Handler, wh *web.WebHandler) *chi.Mux {
	r := chi.NewRouter()

	// Middleware
	r.Use(middleware.Recovery)
	r.Use(middleware.Logger)
	r.Use(middleware.CORS)
	r.Use(middleware.Gzip)

	// Health check
	r.Get("/health", h.HealthCheck)

	// === API Routes ===

	// Settings
	r.Get("/api/settings", h.GetSettings)
	r.Post("/api/settings", h.PostSaveSettings)

	// Dashboard & Monitor
	r.Get("/api/dashboard", h.GetDashboardData)
	r.Get("/api/monitor", h.GetMonitorData)
	r.Get("/api/datakunjunganbpjs", h.GetDataKunjunganBpjs)

	// Bridging
	r.Get("/api/bridging", h.GetBridging)
	r.Post("/api/bridging/save", h.PostBridgingSave)
	r.Post("/api/bridging/kirim-kunjungan", h.PostKirimKunjungan)
	r.Post("/api/bridging/kirim-pendaftaran", h.PostKirimPendaftaran)
	r.Post("/api/bridging/kirim-semua-pendaftaran", h.PostKirimSemuaPendaftaran)
	r.Post("/api/bridging/kirim-semua-kunjungan", h.PostKirimSemuaKunjungan)
	r.Post("/api/bridging/sync-kunjungan", h.PostSyncKunjungan)
	r.Post("/api/bridging/sync-pendaftaran", h.PostSyncPendaftaran)
	r.Post("/api/bridging/batch-riwayat", h.PostBatchRiwayat)
	r.Get("/api/bridging/prefill-kunjungan", h.GetPrefillKunjungan)
	r.Get("/api/bridging/riwayat-pasien", h.GetRiwayatPasien)

	// Peserta
	r.Get("/api/peserta/{noKartu}", h.GetPeserta)
	r.Get("/api/peserta/jenis/{jnsPeserta}/{noKartu}", h.GetPesertaByJenis)

	// Pendaftaran
	r.Get("/api/pendaftaran/nourut/{noKartu}/{tglDaftar}/{kdPoli}/{noUrut}", h.GetPendaftaranNoUrut)
	r.Get("/api/pendaftaran/provider/{tglDaftar}/{offset}/{limit}", h.GetPendaftaranProvider)
	r.Post("/api/pendaftaran", h.PostPendaftaran)
	r.Delete("/api/pendaftaran/{noUrut}/{noKartu}/{tglDaftar}/{kdPoli}", h.DeletePendaftaran)

	// Kunjungan
	r.Get("/api/kunjungan/rujukan/{noKunjungan}", h.GetKunjunganRujukan)
	r.Get("/api/kunjungan/riwayat/{noKartu}", h.GetKunjunganRiwayat)
	r.Post("/api/kunjungan", h.PostKunjungan)
	r.Put("/api/kunjungan", h.PutKunjungan)
	r.Delete("/api/kunjungan/{noKunjungan}", h.DeleteKunjungan)

	// Obat
	r.Get("/api/obat/{noKunjungan}", h.GetObatKunjungan)
	r.Post("/api/obat", h.PostObat)
	r.Delete("/api/obat/{noObat}/{noKunjungan}", h.DeleteObat)

	// Tindakan
	r.Get("/api/tindakan/{noKunjungan}", h.GetTindakanKunjungan)
	r.Post("/api/tindakan", h.PostTindakan)
	r.Put("/api/tindakan", h.PutTindakan)
	r.Delete("/api/tindakan/{noKunjungan}/{kdTindakan}", h.DeleteTindakan)

	// MCU
	r.Get("/api/mcu/{noKunjungan}", h.GetMCU)
	r.Post("/api/mcu", h.PostMCU)
	r.Put("/api/mcu", h.PutMCU)
	r.Delete("/api/mcu/{noKunjungan}", h.DeleteMCU)

	// Diagnosa
	r.Get("/api/diagnosa/{keyword}/{offset}/{limit}", h.GetDiagnosa)

	// Dokter
	r.Get("/api/dokter/{offset}/{limit}", h.GetDokter)

	// Poli
	r.Get("/api/poli/{offset}/{limit}", h.GetPoli)

	// Kesadaran
	r.Get("/api/kesadaran", h.GetKesadaran)

	// Alergi
	r.Get("/api/alergi/{jenis}", h.GetAlergi)

	// Prognosa
	r.Get("/api/prognosa", h.GetPrognosa)

	// Provider
	r.Get("/api/provider/{offset}/{limit}", h.GetProvider)

	// Spesialis
	r.Get("/api/spesialis", h.GetSpesialis)
	r.Get("/api/subspesialis/{kdSpesialis}", h.GetSubSpesialis)

	// Sarana
	r.Get("/api/sarana", h.GetSarana)
	r.Get("/api/spesialis-khusus/{kdSarana}", h.GetSpesialisKhusus)
	r.Get("/api/faskes-rujukan/{kdSpesialis}/{kdSubSpesialis}/{kdSarana}/{offset}/{limit}", h.GetFaskesRujukan)
	r.Get("/api/faskes-khusus/{kdSpesialis}/{kdSubSpesialis}/{kdSarana}/{offset}/{limit}", h.GetFaskesKhusus)

	// Status Pulang
	r.Get("/api/statuspulang", h.GetStatusPulang)

	// Obat DPHO
	r.Get("/api/obat-dpho/{keyword}/{offset}/{limit}", h.GetObatDPHO)

	// Tindakan Ref
	r.Get("/api/tindakan-ref/{keyword}/{offset}/{limit}", h.GetTindakanRef)

	// Kelompok
	r.Get("/api/kelompok/club/{jenis}", h.GetKelompokClub)
	r.Get("/api/kelompok/kegiatan/{kdClub}", h.GetKelompokKegiatan)
	r.Post("/api/kelompok/kegiatan", h.PostKelompokKegiatan)
	r.Delete("/api/kelompok/kegiatan/{kdClub}/{tglKegiatan}", h.DeleteKelompokKegiatan)
	r.Get("/api/kelompok/peserta/{eduId}", h.GetKelompokPeserta)
	r.Delete("/api/kelompok/peserta/{kdClub}/{tglKegiatan}/{noKartu}", h.DeleteKelompokPeserta)

	// Skrining
	r.Get("/api/skrining/rekap/{tglMulai}/{tglAkhir}", h.GetSkriningRekap)
	r.Get("/api/skrining/peserta/{tglMulai}/{tglAkhir}", h.GetSkriningPeserta)
	r.Get("/api/skrining/prolanis/{tglMulai}/{tglAkhir}", h.GetSkriningProlanis)

	// === Web Frontend Routes ===
	if wh != nil {
		r.Get("/", wh.ManagePage)
		r.Get("/web", wh.ManagePage)
		r.Get("/web/settings", wh.SettingsPage)
		r.Get("/web/dashboard", wh.DashboardPage)
		r.Get("/web/monitorpcare", wh.MonitorPage)
		r.Get("/web/bridging", wh.BridgingPage)
		r.Get("/web/peserta", wh.PesertaPage)
		r.Get("/web/pendaftaran", wh.PendaftaranPage)
		r.Get("/web/kunjungan", wh.KunjunganPage)
		r.Get("/web/obat", wh.ObatPage)
		r.Get("/web/tindakan", wh.TindakanPage)
		r.Get("/web/diagnosa", wh.DiagnosaPage)
		r.Get("/web/dokter", wh.DokterPage)
		r.Get("/web/poli", wh.PoliPage)
		r.Get("/web/alergi", wh.AlergiPage)
		r.Get("/web/kesadaran", wh.KesadaranPage)
		r.Get("/web/prognosa", wh.PrognosaPage)
		r.Get("/web/provider", wh.ProviderPage)
		r.Get("/web/spesialis", wh.SpesialisPage)
		r.Get("/web/kelompok", wh.KelompokPage)
		r.Get("/web/statuspulang", wh.StatusPulangPage)
		r.Get("/web/mcu", wh.MCUPage)
		r.Get("/web/skrining", wh.SkriningPage)
		r.Get("/web/ceksinkronisasi", wh.CekSinkronisasiPage)
		r.Get("/web/cekpendaftaran", wh.CekPendaftaranPage)
		r.Get("/web/datakunjunganbpjs", wh.DataKunjunganBpjsPage)
	}

	return r
}
