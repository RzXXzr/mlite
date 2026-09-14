package main

import (
	"log"
	"net/http"
	"os"

	"github.com/joho/godotenv"

	"pcare-go/internal/config"
	"pcare-go/internal/db"
	"pcare-go/internal/handler"
	"pcare-go/internal/pcare"
	"pcare-go/internal/web"
)

func main() {
	if _, err := os.Stat(".env"); err == nil {
		godotenv.Load()
	}

	cfg := config.LoadFromEnv()

	log.Printf("PCare Go Module starting...")
	log.Printf("  API URL:    %s", cfg.PCareAPIURL)
	log.Printf("  Kode FKTP:  %s", cfg.KodeFKTP)
	log.Printf("  Listen:     %s", cfg.ListenAddr)

	database, err := db.New(cfg)
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}
	defer database.Close()
	log.Printf("  Database:   Connected to %s:%s/%s", cfg.DBHost, cfg.DBPort, cfg.DBName)

	// Load settings from database (overrides ENV values)
	dbSettings, err := database.GetSettings("pcare")
	if err != nil {
		log.Printf("Warning: could not load settings from DB: %v", err)
	} else {
		cfg.LoadFromDB(dbSettings)
		log.Printf("  Settings:   Loaded %d values from mlite_settings", len(dbSettings))
	}

	client := pcare.NewClient(cfg)

	h := handler.New(cfg, database, client)
	wh, err := web.NewWebHandler(cfg, database)
	if err != nil {
		log.Fatalf("Failed to init web handler: %v", err)
	}

	router := handler.NewRouter(h, wh)

	log.Printf("Server listening on %s", cfg.ListenAddr)
	if err := http.ListenAndServe(cfg.ListenAddr, router); err != nil {
		log.Fatalf("Server error: %v", err)
	}
}
