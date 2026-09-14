package config

import (
	"os"
	"strings"
)

// Config holds all application configuration for Satu Sehat module.
type Config struct {
	// Database
	DBHost     string
	DBPort     string
	DBUser     string
	DBPass     string
	DBName     string

	// Satu Sehat API
	OrganizationID string
	ClientID       string
	SecretKey      string
	AuthURL        string
	FhirURL        string

	// Location info
	Kelurahan  string
	Kecamatan  string
	Kabupaten  string
	Propinsi   string
	KodePos    string
	Longitude  string
	Latitude   string
	ZonaWaktu  string // WIB, WITA, WIT

	// Facility references
	Farmasi         string
	Laboratorium    string
	Radiologi       string
	PraktisiApotek  string
	PraktisiLab     string
	PraktisiRad     string

	// Hospital info
	NomorTelepon string
	Email        string
	Alamat       string
	Kota         string

	// Server
	ListenAddr string

	// OpenAI (optional)
	APIOpenAI string
}

// LoadFromEnv loads configuration from environment variables.
func LoadFromEnv() *Config {
	return &Config{
		DBHost:     getEnv("DB_HOST", "127.0.0.1"),
		DBPort:     getEnv("DB_PORT", "3306"),
		DBUser:     getEnv("DB_USER", "root"),
		DBPass:     getEnv("DB_PASS", ""),
		DBName:     getEnv("DB_NAME", "mlite"),

		OrganizationID: getEnv("SATUSEHAT_ORG_ID", ""),
		ClientID:       getEnv("SATUSEHAT_CLIENT_ID", ""),
		SecretKey:      getEnv("SATUSEHAT_SECRET_KEY", ""),
		AuthURL:        getEnv("SATUSEHAT_AUTH_URL", "https://api-satusehat-dev.dto.kemkes.go.id/oauth2/v1"),
		FhirURL:        getEnv("SATUSEHAT_FHIR_URL", "https://api-satusehat-dev.dto.kemkes.go.id/fhir-r4/v1"),

		Kelurahan:  getEnv("SATUSEHAT_KELURAHAN", ""),
		Kecamatan:  getEnv("SATUSEHAT_KECAMATAN", ""),
		Kabupaten:  getEnv("SATUSEHAT_KABUPATEN", ""),
		Propinsi:   getEnv("SATUSEHAT_PROPINSI", ""),
		KodePos:    getEnv("SATUSEHAT_KODEPOS", ""),
		Longitude:  getEnv("SATUSEHAT_LONGITUDE", ""),
		Latitude:   getEnv("SATUSEHAT_LATITUDE", ""),
		ZonaWaktu:  getEnv("SATUSEHAT_ZONA_WAKTU", "WIB"),

		Farmasi:        getEnv("SATUSEHAT_FARMASI", ""),
		Laboratorium:   getEnv("SATUSEHAT_LABORATORIUM", ""),
		Radiologi:      getEnv("SATUSEHAT_RADIOLOGI", ""),
		PraktisiApotek: getEnv("SATUSEHAT_PRAKTISI_APOTEK", ""),
		PraktisiLab:    getEnv("SATUSEHAT_PRAKTISI_LAB", ""),
		PraktisiRad:    getEnv("SATUSEHAT_PRAKTISI_RAD", ""),

		NomorTelepon: getEnv("HOSPITAL_PHONE", ""),
		Email:        getEnv("HOSPITAL_EMAIL", ""),
		Alamat:       getEnv("HOSPITAL_ADDRESS", ""),
		Kota:         getEnv("HOSPITAL_CITY", ""),

		ListenAddr: getEnv("LISTEN_ADDR", ":8085"),

		APIOpenAI: getEnv("SATUSEHAT_API_OPENAI", ""),
	}
}

// TimezoneOffset returns the ISO timezone offset string for the configured zone.
func (c *Config) TimezoneOffset() string {
	switch strings.ToUpper(c.ZonaWaktu) {
	case "WITA":
		return "+08:00"
	case "WIT":
		return "+09:00"
	default:
		return "+07:00"
	}
}

// TimezoneModifyHours returns the negative hour offset for UTC conversion.
func (c *Config) TimezoneModifyHours() int {
	switch strings.ToUpper(c.ZonaWaktu) {
	case "WITA":
		return -8
	case "WIT":
		return -9
	default:
		return -7
	}
}

// LoadFromDB overrides config values from mlite_settings in the database.
// It takes a function that returns settings map to avoid circular dependency on db package.
func (c *Config) LoadFromDB(settings map[string]string) {
	if v, ok := settings["organizationid"]; ok && v != "" {
		c.OrganizationID = v
	}
	if v, ok := settings["clientid"]; ok && v != "" {
		c.ClientID = v
	}
	if v, ok := settings["secretkey"]; ok && v != "" {
		c.SecretKey = v
	}
	if v, ok := settings["authurl"]; ok && v != "" {
		c.AuthURL = v
	}
	if v, ok := settings["fhirurl"]; ok && v != "" {
		c.FhirURL = v
	}
	if v, ok := settings["kelurahan"]; ok && v != "" {
		c.Kelurahan = v
	}
	if v, ok := settings["kecamatan"]; ok && v != "" {
		c.Kecamatan = v
	}
	if v, ok := settings["kabupaten"]; ok && v != "" {
		c.Kabupaten = v
	}
	if v, ok := settings["propinsi"]; ok && v != "" {
		c.Propinsi = v
	}
	if v, ok := settings["kodepos"]; ok && v != "" {
		c.KodePos = v
	}
	if v, ok := settings["longitude"]; ok && v != "" {
		c.Longitude = v
	}
	if v, ok := settings["latitude"]; ok && v != "" {
		c.Latitude = v
	}
	if v, ok := settings["zonawaktu"]; ok && v != "" {
		c.ZonaWaktu = v
	}
	if v, ok := settings["farmasi"]; ok && v != "" {
		c.Farmasi = v
	}
	if v, ok := settings["laboratorium"]; ok && v != "" {
		c.Laboratorium = v
	}
	if v, ok := settings["radiologi"]; ok && v != "" {
		c.Radiologi = v
	}
	if v, ok := settings["praktisi_apotek"]; ok && v != "" {
		c.PraktisiApotek = v
	}
	if v, ok := settings["praktisi_lab"]; ok && v != "" {
		c.PraktisiLab = v
	}
	if v, ok := settings["praktisi_rad"]; ok && v != "" {
		c.PraktisiRad = v
	}
	if v, ok := settings["api_openai"]; ok && v != "" {
		c.APIOpenAI = v
	}
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
