package web

// PageData is the common data structure passed to all templates.
type PageData struct {
	Title string
	Page  string
	Flash string
}

// SettingsPageData holds data for the settings page.
type SettingsPageData struct {
	PageData
	Settings SettingsData
}

// SettingsData holds the current settings values.
type SettingsData struct {
	PCareAPIURL       string
	ConsumerID        string
	ConsumerSecret    string
	Username          string
	Password          string
	UserKey           string
	KdAplikasi        string
	KodeFKTP          string
	NamaFKTP          string
	KodeKabupatenKota string
	KabupatenKota     string
	Wilayah           string
	Cabang            string
	KdPjBpjs          string
}

// DashboardPageData holds data for the dashboard page.
type DashboardPageData struct {
	PageData
}

// MonitorPageData holds data for the monitoring page.
type MonitorPageData struct {
	PageData
}

// BridgingPageData holds data for the bridging page.
type BridgingPageData struct {
	PageData
}

// PesertaPageData holds data for the peserta lookup page.
type PesertaPageData struct {
	PageData
}

// PendaftaranPageData holds data for the pendaftaran page.
type PendaftaranPageData struct {
	PageData
}

// KunjunganPageData holds data for the kunjungan page.
type KunjunganPageData struct {
	PageData
}

// ObatPageData holds data for the obat DPHO page.
type ObatPageData struct {
	PageData
}

// TindakanPageData holds data for the tindakan page.
type TindakanPageData struct {
	PageData
}

// DiagnosaPageData holds data for the diagnosa page.
type DiagnosaPageData struct {
	PageData
}

// DokterPageData holds data for the dokter page.
type DokterPageData struct {
	PageData
}

// PoliPageData holds data for the poli page.
type PoliPageData struct {
	PageData
}

// AlergiPageData holds data for the alergi page.
type AlergiPageData struct {
	PageData
}

// KesadaranPageData holds data for the kesadaran page.
type KesadaranPageData struct {
	PageData
}

// PrognosaPageData holds data for the prognosa page.
type PrognosaPageData struct {
	PageData
}

// ProviderPageData holds data for the provider page.
type ProviderPageData struct {
	PageData
}

// SpesialisPageData holds data for the spesialis page.
type SpesialisPageData struct {
	PageData
}

// KelompokPageData holds data for the kelompok page.
type KelompokPageData struct {
	PageData
}

// StatusPulangPageData holds data for the status pulang page.
type StatusPulangPageData struct {
	PageData
}

// MCUPageData holds data for the MCU page.
type MCUPageData struct {
	PageData
}

// SkriningPageData holds data for the skrining page.
type SkriningPageData struct {
	PageData
}

// CekSinkronisasiPageData holds data for the cek sinkronisasi page.
type CekSinkronisasiPageData struct {
	PageData
}

// CekPendaftaranPageData holds data for the cek pendaftaran page.
type CekPendaftaranPageData struct {
	PageData
}

// DataKunjunganBpjsPageData holds data for the data kunjungan BPJS page.
type DataKunjunganBpjsPageData struct {
	PageData
}
