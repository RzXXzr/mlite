package config

import "os"

// Config holds all application configuration for PCare module.
type Config struct {
	// Database
	DBHost string
	DBPort string
	DBUser string
	DBPass string
	DBName string

	// PCare API
	Username       string
	Password       string
	ConsumerID     string
	ConsumerSecret string
	UserKey        string
	KdAplikasi     string
	PCareAPIURL    string

	// Facility Info
	KodeFKTP         string
	NamaFKTP         string
	KodeKabupatenKota string
	KabupatenKota    string
	Wilayah          string
	Cabang           string

	// Server
	ListenAddr string
}

// LoadFromEnv loads configuration from environment variables.
func LoadFromEnv() *Config {
	return &Config{
		DBHost: getEnv("DB_HOST", "127.0.0.1"),
		DBPort: getEnv("DB_PORT", "3306"),
		DBUser: getEnv("DB_USER", "root"),
		DBPass: getEnv("DB_PASS", ""),
		DBName: getEnv("DB_NAME", "mlite"),

		Username:       getEnv("PCARE_USERNAME", ""),
		Password:       getEnv("PCARE_PASSWORD", ""),
		ConsumerID:     getEnv("PCARE_CONSUMER_ID", ""),
		ConsumerSecret: getEnv("PCARE_CONSUMER_SECRET", ""),
		UserKey:        getEnv("PCARE_USER_KEY", ""),
		KdAplikasi:     getEnv("PCARE_KD_APLIKASI", "095"),
		PCareAPIURL:    getEnv("PCARE_API_URL", "https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev/"),

		KodeFKTP:         getEnv("KODE_FKTP", ""),
		NamaFKTP:         getEnv("NAMA_FKTP", ""),
		KodeKabupatenKota: getEnv("KODE_KABUPATEN_KOTA", ""),
		KabupatenKota:    getEnv("KABUPATEN_KOTA", ""),
		Wilayah:          getEnv("WILAYAH", ""),
		Cabang:           getEnv("CABANG", ""),

		ListenAddr: getEnv("LISTEN_ADDR", ":8091"),
	}
}

// LoadFromDB overrides config values from mlite_settings in the database.
func (c *Config) LoadFromDB(settings map[string]string) {
	if v, ok := settings["usernamePcare"]; ok && v != "" {
		c.Username = v
	}
	if v, ok := settings["passwordPcare"]; ok && v != "" {
		c.Password = v
	}
	if v, ok := settings["consumerID"]; ok && v != "" {
		c.ConsumerID = v
	}
	if v, ok := settings["consumerSecret"]; ok && v != "" {
		c.ConsumerSecret = v
	}
	if v, ok := settings["consumerUserKey"]; ok && v != "" {
		c.UserKey = v
	}
	if v, ok := settings["PCareApiUrl"]; ok && v != "" {
		c.PCareAPIURL = v
	}
	if v, ok := settings["kode_fktp"]; ok && v != "" {
		c.KodeFKTP = v
	}
	if v, ok := settings["nama_fktp"]; ok && v != "" {
		c.NamaFKTP = v
	}
	if v, ok := settings["kode_kabupatenkota"]; ok && v != "" {
		c.KodeKabupatenKota = v
	}
	if v, ok := settings["kabupatenkota"]; ok && v != "" {
		c.KabupatenKota = v
	}
	if v, ok := settings["wilayah"]; ok && v != "" {
		c.Wilayah = v
	}
	if v, ok := settings["cabang"]; ok && v != "" {
		c.Cabang = v
	}
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
