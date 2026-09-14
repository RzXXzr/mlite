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
	Settings     SettingsData
	Bidang       []BidangItem
	MappingLokasi []LokasiItem
}

// SettingsData holds the current settings values.
type SettingsData struct {
	OrganizationID string
	ClientID       string
	SecretKey      string
	AuthURL        string
	FhirURL        string
	Latitude       string
	Longitude      string
	Kelurahan      string
	Kecamatan      string
	Kabupaten      string
	Propinsi       string
	KodePos        string
	ZonaWaktu      string
	Farmasi        string
	Laboratorium   string
	Radiologi      string
	PraktisiApotek string
	PraktisiLab    string
	PraktisiRad    string
	APIOpenAI      string
}

// BidangItem is a department/section for practitioner mapping.
type BidangItem struct {
	Nama string
}

// LokasiItem represents a mapped location for settings dropdowns.
type LokasiItem struct {
	Kode  string
	Lokasi string
}

// DepartemenPageData holds data for the department mapping page.
type DepartemenPageData struct {
	PageData
	Departemen        []DepartemenOption
	MappingDepartemen []DepartemenMapping
}

type DepartemenOption struct {
	DepID string
	Nama  string
}

type DepartemenMapping struct {
	DepID        string
	Nama         string
	IDOrganisasi string
}

// LokasiPageData holds data for the location mapping page.
type LokasiPageData struct {
	PageData
	Lokasi              []LokasiOption
	SatuSehatDepartemen []DepartemenMapping
	SatuSehatLokasi     []LokasiMapping
}

type LokasiOption struct {
	Kode string
	Nama string
}

type LokasiMapping struct {
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

// PraktisiPageData holds data for the practitioner mapping page.
type PraktisiPageData struct {
	PageData
	Dokter          []DokterOption
	Apoteker        []ApotekerOption
	MappingPraktisi []PraktisiMapping
}

type DokterOption struct {
	KdDokter string
	NmDokter string
}

type ApotekerOption struct {
	NIK  string
	Nama string
}

type PraktisiMapping struct {
	KdDokter       string
	NmDokter       string
	PractitionerID string
}

// ObatPageData holds data for the medication mapping page.
type ObatPageData struct {
	PageData
	TotalObat     int
	TotalMapped   int
	TotalUnmapped int
	TotalPushed   int
	TotalRepush   int
	AllObat       []ObatRow
	DataBarang    []BarangOption
}

type ObatRow struct {
	KodeBrng          string
	NamaBrng          string
	Mapped            bool
	KodeKFA           string
	NamaKFA           string
	Type              string
	IDMedication      string
	UpdatedAfterPush  bool
}

type BarangOption struct {
	KodeBrng string
	NamaBrng string
}

// LabPageData holds data for the lab mapping page.
type LabPageData struct {
	PageData
	TemplateLab []LabTemplate
	MappingLab  []LabMapping
}

type LabTemplate struct {
	IDTemplate       string
	KdJenisPerawatan string
	Pemeriksaan      string
}

type LabMapping struct {
	IDTemplate       string
	KdJenisPerawatan string
	Pemeriksaan      string
	Code             string
	Display          string
	SampelCode       string
	SampelSystem     string
	SampelDisplay    string
}

// RadPageData holds data for the radiology mapping page.
type RadPageData struct {
	PageData
	JnsPerawatanRad []RadPerawatan
	MappingRad      []RadMapping
}

type RadPerawatan struct {
	KdJenisPerawatan string
	NmPerawatan      string
}

type RadMapping struct {
	KdJenisPerawatan string
	NmPerawatan      string
	Code             string
	Display          string
	SampelCode       string
	SampelSystem     string
	SampelDisplay    string
}

// BulkPageData holds data for the bulk page (empty, data loaded via AJAX).
type BulkPageData struct {
	PageData
}

// CronPageData holds data for the cron monitoring page.
type CronPageData struct {
	PageData
	CronRunning           bool
	TotalHutang           int
	TotalKunjungan        int
	ProgressTotalSuccess  int
	ProgressSessions      int
	ProgressLastDate      string
	ProgressLastRun       string
	ProgressStartDate     string
	ProgressTotalProcessed int
	ProgressTotalFailed   int
	ProgressTotalSkipped  int
	HutangPerBulan        []HutangBulan
	JamOptions            []JamOption
	CronSettings          CronSettingsData
	LogContent            string
}

type HutangBulan struct {
	Bulan       string
	Total       int
	BelumKirim  int
	SudahKirim  int
	Persen      int
}

type JamOption struct {
	Val   string
	Label string
}

type CronSettingsData struct {
	JamMulai        string
	JamBerhenti     string
	MaxErrors       int
	TanggalDari     string
	CrontabSchedule string
	Enabled         bool
	Concurrency     int
	RequestPerMenit int
}

// ResponsePageData holds data for the response page (empty, data loaded via AJAX).
type ResponsePageData struct {
	PageData
}

// PraktisiRefPageData holds data for the praktisi reference page.
type PraktisiRefPageData struct {
	PageData
}
