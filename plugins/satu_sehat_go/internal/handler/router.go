package handler

import (
	"github.com/go-chi/chi/v5"
	"satu-sehat-go/internal/middleware"
	"satu-sehat-go/internal/web"
)

// NewRouter creates the chi router with all routes.
func NewRouter(h *Handler, wh *web.WebHandler, ch *CronHandler) *chi.Mux {
	r := chi.NewRouter()

	// Middleware
	r.Use(middleware.Recovery)
	r.Use(middleware.Logger)
	r.Use(middleware.CORS)

	// Health check
	r.Get("/health", h.HealthCheck)

	// Auth
	r.Get("/api/token", h.GetToken)

	// Practitioner
	r.Get("/api/praktisi", h.GetPraktisi)
	r.Post("/api/praktisi", h.GetPraktisi)
	r.Get("/api/praktisi/id/{nik}", h.GetPraktisiID)
	r.Get("/api/praktisi/{id}", h.GetPraktisiByID)

	// Patient
	r.Get("/api/pasien", h.GetPasien)
	r.Post("/api/pasien", h.GetPasien)
	r.Get("/api/pasien/id/{nik}", h.GetPasienID)
	r.Get("/api/pasien/{id}", h.GetPasienByID)

	// Organization
	r.Post("/api/organization", h.PostOrganization)

	// Location
	r.Post("/api/location", h.PostLocation)

	// FHIR Resources by no_rawat
	r.Get("/api/encounter/*", h.GetEncounter)
	r.Get("/api/condition/*", h.GetCondition)
	r.Get("/api/observation/*", h.GetObservation)
	r.Get("/api/procedure/*", h.GetProcedure)
	r.Get("/api/medication/*", h.GetMedication)
	r.Get("/api/laboratory/*", h.GetLaboratory)
	r.Get("/api/radiology/*", h.GetRadiology)
	r.Get("/api/clinical-impression/*", h.GetClinicalImpression)
	r.Get("/api/care-plan/*", h.GetCarePlan)
	r.Get("/api/allergy/*", h.GetAllergy)

	// Batch / Forward
	r.Get("/api/forward-tanggal", h.ForwardByDate)
	r.Get("/api/forward-tanggal/{tanggal}", h.ForwardByDate)

	// Mapping
	r.Get("/api/mapping/praktisi", h.GetMappingPraktisi)
	r.Post("/api/mapping/praktisi", h.PostSaveMappingPraktisi)
	r.Get("/api/mapping/obat", h.GetMappingObat)
	r.Post("/api/mapping/obat", h.PostSaveMappingObat)
	r.Get("/api/mapping/lab", h.GetMappingLab)
	r.Post("/api/mapping/lab", h.PostSaveMappingLab)
	r.Get("/api/mapping/rad", h.GetMappingRad)
	r.Post("/api/mapping/rad", h.PostSaveMappingRad)
	r.Get("/api/mapping/lokasi", h.GetMappingLokasi)
	r.Post("/api/mapping/lokasi", h.PostSaveMappingLokasi)

	// Settings
	r.Get("/api/settings", h.GetSettings)
	r.Post("/api/settings", h.PostSaveSettings)

	// Response
	r.Get("/api/response", h.GetResponseList)

	// KFA
	r.Get("/api/kfa", h.SearchKFA)

	// Compatibility routes (matching PHP Site.php routes)
	r.Get("/satu-sehat/encounter/*", h.GetEncounter)
	r.Get("/satu-sehat/condition/*", h.GetCondition)
	r.Get("/satu-sehat/observation/*", h.GetObservation)
	r.Get("/satu-sehat/procedure/*", h.GetProcedure)
	r.Get("/satu-sehat/medication/*", h.GetMedication)
	r.Get("/satu-sehat/laboratory/*", h.GetLaboratory)
	r.Get("/satu-sehat/radiology/*", h.GetRadiology)
	r.Get("/satu-sehat/clinical-impression/*", h.GetClinicalImpression)
	r.Get("/satu-sehat/care-plan/*", h.GetCarePlan)
	r.Get("/satu-sehat/allergy/*", h.GetAllergy)
	r.Get("/satu-sehat/forward-tanggal", h.ForwardByDate)
	r.Get("/satu-sehat/forward-tanggal/{tanggal}", h.ForwardByDate)

	// === Cron API Routes ===
	if ch != nil {
		r.Post("/api/cron/action", ch.PostAction)
		r.Post("/api/cron/settings", ch.PostSettings)
		r.Get("/api/cron/log", ch.GetLog)
		r.Get("/api/cron/status", ch.GetStatus)
	}

	// === Web Frontend Routes ===
	if wh != nil {
		r.Get("/", wh.ManagePage)
		r.Get("/web", wh.ManagePage)
		r.Get("/web/settings", wh.SettingsPage)
		r.Get("/web/response", wh.ResponsePage)
		r.Get("/web/bulk", wh.BulkPage)
		r.Get("/web/departemen", wh.DepartemenPage)
		r.Get("/web/lokasi", wh.LokasiPage)
		r.Get("/web/mapping/praktisi", wh.MappingPraktisiPage)
		r.Get("/web/mapping/obat", wh.MappingObatPage)
		r.Get("/web/mapping/lab", wh.MappingLabPage)
		r.Get("/web/mapping/rad", wh.MappingRadPage)
		r.Get("/web/praktisi", wh.PraktisiRefPage)
		r.Get("/web/cron", wh.CronPage)
	}

	return r
}
