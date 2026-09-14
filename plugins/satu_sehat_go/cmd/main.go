package main

import (
	"log"
	"net/http"
	"os"

	"github.com/joho/godotenv"

	"satu-sehat-go/internal/auth"
	"satu-sehat-go/internal/config"
	"satu-sehat-go/internal/cron"
	"satu-sehat-go/internal/db"
	"satu-sehat-go/internal/handler"
	"satu-sehat-go/internal/web"
)

func main() {
	// Load .env file if present
	if _, err := os.Stat(".env"); err == nil {
		godotenv.Load()
	}

	// Load configuration
	cfg := config.LoadFromEnv()

	log.Printf("Satu Sehat Go Module starting...")
	log.Printf("  Auth URL:        %s", cfg.AuthURL)
	log.Printf("  FHIR URL:        %s", cfg.FhirURL)
	log.Printf("  Organization ID: %s", cfg.OrganizationID)
	log.Printf("  Zona Waktu:      %s (%s)", cfg.ZonaWaktu, cfg.TimezoneOffset())
	log.Printf("  Listen:          %s", cfg.ListenAddr)

	// Connect to database
	database, err := db.New(cfg)
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}
	defer database.Close()
	log.Printf("  Database:        Connected to %s:%s/%s", cfg.DBHost, cfg.DBPort, cfg.DBName)

	// Load settings from database (overrides ENV values)
	dbSettings, err := database.GetSettings("satu_sehat")
	if err != nil {
		log.Printf("Warning: could not load settings from DB: %v", err)
	} else {
		cfg.LoadFromDB(dbSettings)
		log.Printf("  Settings:        Loaded %d values from mlite_settings", len(dbSettings))
		log.Printf("  Organization ID: %s", cfg.OrganizationID)
		log.Printf("  Auth URL:        %s", cfg.AuthURL)
		log.Printf("  FHIR URL:        %s", cfg.FhirURL)
	}

	// Create Satu Sehat API client
	client := auth.NewClient(cfg)

	// Create handler and router
	h := handler.New(cfg, database, client)
	wh, err := web.NewWebHandler(cfg, database)
	if err != nil {
		log.Fatalf("Failed to init web handler: %v", err)
	}

	// Create cron scheduler
	scheduler := cron.New(cfg, database, client)
	scheduler.LoadSettings()
	ch := handler.NewCronHandler(scheduler, database)

	// Inject scheduler into web handler for CronPage
	wh.SetScheduler(scheduler)

	router := handler.NewRouter(h, wh, ch)

	// Start auto-scheduler (checks crontab every minute)
	scheduler.StartAutoScheduler()
	log.Printf("  Cron:            Auto-scheduler started")

	// Start HTTP server
	log.Printf("Server listening on %s", cfg.ListenAddr)
	if err := http.ListenAndServe(cfg.ListenAddr, router); err != nil {
		log.Fatalf("Server error: %v", err)
	}
}
