package pcare

import (
	"encoding/json"
	"fmt"
)

// === Standard PCare API Response Wrapper ===

// APIResponse is the standard wrapper for all PCare API responses.
type APIResponse struct {
	Response interface{} `json:"response"`
	MetaData MetaData    `json:"metaData"`
}

// MetaData holds status information from the PCare API.
type MetaData struct {
	Code    FlexString `json:"code"`
	Message string     `json:"message"`
}

// FlexString can unmarshal from both JSON string ("200") and number (200).
type FlexString string

func (f *FlexString) UnmarshalJSON(data []byte) error {
	var s string
	if err := json.Unmarshal(data, &s); err == nil {
		*f = FlexString(s)
		return nil
	}
	var n json.Number
	if err := json.Unmarshal(data, &n); err == nil {
		*f = FlexString(n.String())
		return nil
	}
	*f = FlexString(fmt.Sprintf("%s", data))
	return nil
}

func (f FlexString) String() string { return string(f) }

// === Peserta (Patient/Participant) ===

type Peserta struct {
	NoKartu    string      `json:"noKartu"`
	Nama       string      `json:"nama"`
	HubKel     string      `json:"hubunganKeluarga"`
	Sex        string      `json:"sex"`
	TglLahir   string      `json:"tglLahir"`
	TglMulai   string      `json:"tglMulaiAktif"`
	TglAkhir   string      `json:"tglAkhirBerlaku"`
	KdProvider string      `json:"kdProviderPst"`
	NmProvider string      `json:"nmProviderPst"`
	KdProviderGigi string  `json:"kdProviderGigi"`
	NmProviderGigi string  `json:"nmProviderGigi"`
	JnsPeserta JnsPeserta  `json:"jnsPeserta"`
	JnsKelas   JnsKelas    `json:"jnsKelas"`
	GolDarah   string      `json:"golDarah"`
	NoHP       string      `json:"noHP"`
	NoKTP      string      `json:"noKTP"`
	PstnPst    string      `json:"pstProl"`
	Aktif      bool        `json:"aktif"`
	KetAktif   string      `json:"ketAktif"`
	AsuransiLN string      `json:"asuransi"`
	Tunggakan  int         `json:"tunggakan"`
}

type JnsPeserta struct {
	Kode string `json:"kode"`
	Nama string `json:"nama"`
}

type JnsKelas struct {
	Kode string `json:"kode"`
	Nama string `json:"nama"`
}

// === Pendaftaran (Registration) ===

type Pendaftaran struct {
	NoUrut           string `json:"noUrut,omitempty"`
	TglDaftar        string `json:"tglDaftar"`
	NoKartu          string `json:"noKartu"`
	KdPoli           string `json:"kdPoli"`
	NmPoli           string `json:"nmPoli,omitempty"`
	Keluhan          string `json:"keluhan,omitempty"`
	KunjSakit        bool   `json:"kunjSakit"`
	Sistole          int    `json:"sistole"`
	Diastole         int    `json:"diastole"`
	BeratBadan       int    `json:"beratBadan"`
	TinggiBadan      int    `json:"tinggiBadan"`
	RespRate         int    `json:"respRate"`
	HeartRate        int    `json:"heartRate"`
	LingkarPerut     int    `json:"lingkarPerut,omitempty"`
	KdTkp            string `json:"kdTkp"`
	KdProviderPeserta string `json:"kdProviderPeserta,omitempty"`
	Suhu             int    `json:"suhu,omitempty"`
	NmKdTkp          string `json:"nmKdTkp,omitempty"`
}

// === Kunjungan (Visit) ===

type Kunjungan struct {
	NoKunjungan      *string `json:"noKunjungan"`
	NoKartu          string  `json:"noKartu"`
	TglDaftar        string  `json:"tglDaftar"`
	KdPoli           *string `json:"kdPoli"`
	Keluhan          string  `json:"keluhan"`
	KdSadar          string  `json:"kdSadar"`
	Sistole          int     `json:"sistole"`
	Diastole         int     `json:"diastole"`
	BeratBadan       int     `json:"beratBadan"`
	TinggiBadan      int     `json:"tinggiBadan"`
	RespRate         int     `json:"respRate"`
	HeartRate        int     `json:"heartRate"`
	LingkarPerut     int     `json:"lingkarPerut,omitempty"`
	KdStatusPulang   string  `json:"kdStatusPulang"`
	TglPulang        string  `json:"tglPulang"`
	KdDokter         string  `json:"kdDokter"`
	KdDiag1          string  `json:"kdDiag1"`
	KdDiag2          *string `json:"kdDiag2"`
	KdDiag3          *string `json:"kdDiag3"`
	KdPoliRujukInternal *string `json:"kdPoliRujukInternal"`
	RujukLanjut      *string `json:"rujukLanjut"`
	KdTacc           int     `json:"kdTacc"`
	AlasanTacc       *string `json:"alasanTacc"`
	AlergiMakan      *string `json:"alergiMakan"`
	AlergiUdara      *string `json:"alergiUdara"`
	AlergiObat       *string `json:"alergiObat"`
	KdPrognosa       string  `json:"kdPrognosa"`
	Anamnesa         string  `json:"anamnesa,omitempty"`
	TerapiObat       string  `json:"terapiObat"`
	TerapiNonObat    string  `json:"terapiNonObat"`
	Bmhp             string  `json:"bmhp,omitempty"`
	Suhu             int     `json:"suhu,omitempty"`
}

// KunjunganRiwayat is used for visit history response.
type KunjunganRiwayat struct {
	NoKunjungan    string `json:"noKunjungan"`
	NoKartu        string `json:"noKartu"`
	TglDaftar      string `json:"tglDaftar"`
	KdPoli         string `json:"kdPoli"`
	NmPoli         string `json:"nmPoli"`
	Keluhan        string `json:"keluhan"`
	KdSadar        string `json:"kdSadar"`
	NmSadar        string `json:"nmSadar"`
	KdStatusPulang string `json:"kdStatusPulang"`
	NmStatusPulang string `json:"nmStatusPulang"`
	TglPulang      string `json:"tglPulang"`
	KdDokter       string `json:"kdDokter"`
	NmDokter       string `json:"nmDokter"`
	KdDiag1        string `json:"kdDiag1"`
	NmDiag1        string `json:"nmDiag1"`
}

// === Diagnosa ===

type Diagnosa struct {
	KdDiag       string `json:"kdDiag"`
	NmDiag       string `json:"nmDiag"`
	NonSpesialis bool   `json:"nonSpesialis"`
}

// === Obat (Medicine) ===

type Obat struct {
	KdObatSK      int     `json:"kdObatSK"`
	NoKunjungan   string  `json:"noKunjungan"`
	Racikan       bool    `json:"racikan"`
	KdObat        string  `json:"kdObat"`
	NmObat        string  `json:"nmObat,omitempty"`
	Signa1        int     `json:"signa1"`
	Signa2        int     `json:"signa2"`
	JmlObat       float64 `json:"jmlObat"`
	JmlPermintaan int     `json:"jmlPermintaan"`
	NmObatNonDPHO string  `json:"nmObatNonDPHO,omitempty"`
}

type ObatDPHO struct {
	KdObat string `json:"kdObat"`
	NmObat string `json:"nmObat"`
}

// === Tindakan (Procedure) ===

type Tindakan struct {
	KdTindakanSK int     `json:"kdTindakanSK"`
	NoKunjungan  string  `json:"noKunjungan"`
	KdTindakan   string  `json:"kdTindakan"`
	NmTindakan   string  `json:"nmTindakan,omitempty"`
	Biaya        float64 `json:"biaya,omitempty"`
	Keterangan   *string `json:"keterangan"`
	Hasil        int     `json:"hasil,omitempty"`
}

type TindakanRef struct {
	KdTindakan string `json:"kdTindakan"`
	NmTindakan string `json:"nmTindakan"`
}

// === Dokter ===

type Dokter struct {
	KdDokter string `json:"kdDokter"`
	NmDokter string `json:"nmDokter"`
}

// === Poli ===

type Poli struct {
	KdPoli   string `json:"kdPoli"`
	NmPoli   string `json:"nmPoli"`
	PoliSakit bool  `json:"poliSakit"`
}

// === Kesadaran ===

type Kesadaran struct {
	KdSadar string `json:"kdSadar"`
	NmSadar string `json:"nmSadar"`
}

// === Alergi ===

type Alergi struct {
	KdAlergi string `json:"kdAlergi"`
	NmAlergi string `json:"nmAlergi"`
}

// === Prognosa ===

type Prognosa struct {
	KdPrognosa string `json:"kdPrognosa"`
	NmPrognosa string `json:"nmPrognosa"`
}

// === Provider ===

type Provider struct {
	KdProvider string `json:"kdProvider"`
	NmProvider string `json:"nmProvider"`
}

// === Spesialis ===

type Spesialis struct {
	KdSpesialis string `json:"kdSpesialis"`
	NmSpesialis string `json:"nmSpesialis"`
}

type SubSpesialis struct {
	KdSubSpesialis string `json:"kdSubSpesialis"`
	NmSubSpesialis string `json:"nmSubSpesialis"`
}

type Sarana struct {
	KdSarana string `json:"kdSarana"`
	NmSarana string `json:"nmSarana"`
}

type FaskesRujukan struct {
	KdPPK  string `json:"kdPPK"`
	NmPPK  string `json:"nmPPK"`
}

// === Status Pulang ===

type StatusPulang struct {
	KdStatusPulang string `json:"kdStatusPulang"`
	NmStatusPulang string `json:"nmStatusPulang"`
}

// === Kelompok / Prolanis ===

type KelompokClub struct {
	ClubID      string `json:"clubId"`
	NmClub      string `json:"nmClub"`
	NoKartuKetua string `json:"noKartu"`
	NmKetua     string `json:"nmPeserta"`
	Alamat      string `json:"alamat"`
}

type KelompokKegiatan struct {
	EduID       string `json:"eduId"`
	ClubID      string `json:"clubId"`
	TglPelayanan string `json:"tglPelayanan"`
	Materi      string `json:"materi"`
	Pembicara   string `json:"pembicara"`
	Lokasi      string `json:"lokasi"`
	Keterangan  string `json:"keterangan"`
	Biaya       int    `json:"biaya"`
}

type KelompokAddKegiatan struct {
	ClubID       string `json:"clubId"`
	TglPelayanan string `json:"tglPelayanan"`
	KdKegiatan   string `json:"kdKegiatan"`
	KdKelompok   string `json:"kdKelompok"`
	Materi       string `json:"materi"`
	Pembicara    string `json:"pembicara"`
	Lokasi       string `json:"lokasi"`
	Keterangan   string `json:"keterangan"`
	Biaya        int    `json:"biaya"`
}

type KelompokPeserta struct {
	EduID    string `json:"eduId"`
	NoKartu  string `json:"noKartu"`
	NmPeserta string `json:"nmPeserta"`
}

// === MCU ===

type MCU struct {
	KdMCU                 int     `json:"kdMCU"`
	NoKunjungan           string  `json:"noKunjungan"`
	KdProvider            string  `json:"kdProvider,omitempty"`
	TglPelayanan          string  `json:"tglPelayanan,omitempty"`
	TekananDarahSistole   int     `json:"tekananDarahSistole"`
	TekananDarahDiastole  int     `json:"tekananDarahDiastole"`
	DarahRutinHemo        float64 `json:"darahRutinHemo"`
	DarahRutinLeu         float64 `json:"darahRutinLeu"`
	DarahRutinErit        float64 `json:"darahRutinErit"`
	DarahRutinTromb       float64 `json:"darahRutinTromb,omitempty"`
	DarahRutinHt          float64 `json:"darahRutinHt,omitempty"`
	LemakDarahHDL         float64 `json:"lemakDarahHDL"`
	LemakDarahLDL         float64 `json:"lemakDarahLDL"`
	LemakDarahChol        float64 `json:"lemakDarahChol,omitempty"`
	LemakDarahTrigl       float64 `json:"lemakDarahTrigl,omitempty"`
	GulaDarahSewaktu      float64 `json:"gulaDarahSewaktu"`
	GulaDarahPuasa        float64 `json:"gulaDarahPuasa"`
	GulaDarahPostPrandial float64 `json:"gulaDarahPostPrandial,omitempty"`
	GulaDarahHbA1c        float64 `json:"gulaDarahHbA1c,omitempty"`
	FungsiHatiSGOT        float64 `json:"fungsiHatiSGOT"`
	FungsiHatiSGPT        float64 `json:"fungsiHatiSGPT"`
	FungsiHatiGamma       float64 `json:"fungsiHatiGamma,omitempty"`
	FungsiHatiProtein     float64 `json:"fungsiHatiProtein,omitempty"`
	FungsiHatiAlbumin     float64 `json:"fungsiHatiAlbumin,omitempty"`
	FungsiHatiGlobulin    float64 `json:"fungsiHatiGlobulin,omitempty"`
	FungsiGinjalCrea      float64 `json:"fungsiGinjalCrea"`
	FungsiGinjalUreum     float64 `json:"fungsiGinjalUreum"`
	FungsiGinjalAsam      float64 `json:"fungsiGinjalAsam,omitempty"`
	FungsiJantungABI      float64 `json:"fungsiJantungABI,omitempty"`
	FungsiJantungEKG      float64 `json:"fungsiJantungEKG,omitempty"`
	FungsiJantungEcho     float64 `json:"fungsiJantungEcho,omitempty"`
	FundusKopi            float64 `json:"fundusKopi,omitempty"`
	Pemeriksaan           float64 `json:"pemeriksaan,omitempty"`
	Keterangan            string  `json:"keterangan,omitempty"`
}

// === Skrining ===

type SkriningRekap struct {
	KdPenyakit  string `json:"kdPenyakit"`
	NmPenyakit  string `json:"nmPenyakit"`
	JmlSkrining int    `json:"jmlSkrining"`
	JmlPositif  int    `json:"jmlPositif"`
}

type SkriningPeserta struct {
	NoKartu    string `json:"noKartu"`
	NmPeserta  string `json:"nmPeserta"`
	TglSkrining string `json:"tglSkrining"`
	HasilSkrining string `json:"hasilSkrining"`
}

// === Paginated List Response ===

type ListResponse struct {
	Count int         `json:"count"`
	List  interface{} `json:"list"`
}
