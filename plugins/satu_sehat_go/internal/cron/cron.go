package cron

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"satu-sehat-go/internal/auth"
	"satu-sehat-go/internal/config"
	"satu-sehat-go/internal/db"
)

// Settings holds cron configuration.
type Settings struct {
	JamMulai        string `json:"jam_mulai"`
	JamBerhenti     string `json:"jam_berhenti"`
	MaxErrors       int    `json:"max_errors"`
	TanggalDari     string `json:"tanggal_dari"`
	CrontabSchedule string `json:"crontab_schedule"`
	Enabled         bool   `json:"enabled"`
	Concurrency     int    `json:"concurrency"`
	RequestPerMenit int    `json:"request_per_menit"`
}

// Progress tracks cumulative cron state.
type Progress struct {
	LastCompletedDate string `json:"last_completed_date"`
	StartDate         string `json:"start_date"`
	EndDate           string `json:"end_date"`
	LastRun           string `json:"last_run"`
	TotalProcessed    int    `json:"total_processed"`
	TotalSuccess      int    `json:"total_success"`
	TotalFailed       int    `json:"total_failed"`
	TotalSkipped      int    `json:"total_skipped"`
	Sessions          int    `json:"sessions"`
}

// LogEntry is one log line.
type LogEntry struct {
	Time    string `json:"time"`
	Level   string `json:"level"`
	Message string `json:"message"`
}

// fhirStep defines one API call matching the paper-airplane flow on the Response page.
type fhirStep struct {
	Name string
	Path string
}

// Scheduler manages the cron lifecycle.
type Scheduler struct {
	mu       sync.Mutex
	running  bool
	stopCh   chan struct{}
	cfg      *config.Config
	db       *db.DB
	client   *auth.Client
	settings Settings
	progress Progress
	logs     []LogEntry
	maxLogs  int
}

// New creates a new Scheduler.
func New(cfg *config.Config, database *db.DB, client *auth.Client) *Scheduler {
	return &Scheduler{
		cfg:    cfg,
		db:     database,
		client: client,
		settings: Settings{
			JamMulai:        "23:00",
			JamBerhenti:     "06:00",
			MaxErrors:       10,
			CrontabSchedule: "0 23 * * *",
			Enabled:         false,
			Concurrency:     2,
			RequestPerMenit: 90,
		},
		maxLogs: 2000,
	}
}

// LoadSettings loads settings from DB.
func (s *Scheduler) LoadSettings() {
	s.mu.Lock()
	defer s.mu.Unlock()

	vals, err := s.db.GetSettings("satu_sehat_cron")
	if err != nil {
		return
	}
	if v, ok := vals["jam_mulai"]; ok && v != "" {
		s.settings.JamMulai = v
	}
	if v, ok := vals["jam_berhenti"]; ok && v != "" {
		s.settings.JamBerhenti = v
	}
	if v, ok := vals["max_errors"]; ok && v != "" {
		fmt.Sscanf(v, "%d", &s.settings.MaxErrors)
	}
	if v, ok := vals["tanggal_dari"]; ok && v != "" {
		s.settings.TanggalDari = v
	}
	if v, ok := vals["crontab_schedule"]; ok && v != "" {
		s.settings.CrontabSchedule = v
	}
	if v, ok := vals["enabled"]; ok {
		s.settings.Enabled = v == "1" || v == "true"
	}
	if v, ok := vals["concurrency"]; ok && v != "" {
		fmt.Sscanf(v, "%d", &s.settings.Concurrency)
	}
	if v, ok := vals["request_per_menit"]; ok && v != "" {
		fmt.Sscanf(v, "%d", &s.settings.RequestPerMenit)
	}

	// Load progress
	if v, ok := vals["progress_json"]; ok && v != "" {
		json.Unmarshal([]byte(v), &s.progress)
	}
}

// SaveSettings persists settings to DB.
func (s *Scheduler) SaveSettings() {
	s.mu.Lock()
	sets := s.settings
	s.mu.Unlock()

	s.db.SaveSetting("satu_sehat_cron", "jam_mulai", sets.JamMulai)
	s.db.SaveSetting("satu_sehat_cron", "jam_berhenti", sets.JamBerhenti)
	s.db.SaveSetting("satu_sehat_cron", "max_errors", fmt.Sprintf("%d", sets.MaxErrors))
	s.db.SaveSetting("satu_sehat_cron", "tanggal_dari", sets.TanggalDari)
	s.db.SaveSetting("satu_sehat_cron", "crontab_schedule", sets.CrontabSchedule)
	s.db.SaveSetting("satu_sehat_cron", "concurrency", fmt.Sprintf("%d", sets.Concurrency))
	s.db.SaveSetting("satu_sehat_cron", "request_per_menit", fmt.Sprintf("%d", sets.RequestPerMenit))
	enabled := "0"
	if sets.Enabled {
		enabled = "1"
	}
	s.db.SaveSetting("satu_sehat_cron", "enabled", enabled)
}

// SaveProgress persists progress to DB.
func (s *Scheduler) SaveProgress() {
	s.mu.Lock()
	data, _ := json.Marshal(s.progress)
	s.mu.Unlock()
	s.db.SaveSetting("satu_sehat_cron", "progress_json", string(data))
}

// GetSettings returns a copy of current settings.
func (s *Scheduler) GetSettings() Settings {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.settings
}

// GetProgress returns a copy of current progress.
func (s *Scheduler) GetProgress() Progress {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.progress
}

// IsRunning returns whether the cron is actively running.
func (s *Scheduler) IsRunning() bool {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.running
}

// UpdateSettings updates settings from form values.
func (s *Scheduler) UpdateSettings(vals map[string]string) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if v, ok := vals["jam_mulai"]; ok {
		s.settings.JamMulai = v
	}
	if v, ok := vals["jam_berhenti"]; ok {
		s.settings.JamBerhenti = v
	}
	if v, ok := vals["max_errors"]; ok {
		fmt.Sscanf(v, "%d", &s.settings.MaxErrors)
	}
	if v, ok := vals["tanggal_dari"]; ok {
		s.settings.TanggalDari = v
	}
	if v, ok := vals["crontab_schedule"]; ok {
		s.settings.CrontabSchedule = v
	}
	if v, ok := vals["concurrency"]; ok {
		fmt.Sscanf(v, "%d", &s.settings.Concurrency)
	}
	if v, ok := vals["request_per_menit"]; ok {
		fmt.Sscanf(v, "%d", &s.settings.RequestPerMenit)
	}
	if v, ok := vals["enabled"]; ok {
		s.settings.Enabled = v == "1" || v == "true" || v == "on"
	} else {
		s.settings.Enabled = false
	}
}

// GetLogs returns the last N log lines.
func (s *Scheduler) GetLogs(n int) []LogEntry {
	s.mu.Lock()
	defer s.mu.Unlock()
	if n <= 0 || n > len(s.logs) {
		n = len(s.logs)
	}
	start := len(s.logs) - n
	if start < 0 {
		start = 0
	}
	result := make([]LogEntry, len(s.logs[start:]))
	copy(result, s.logs[start:])
	return result
}

// ClearLogs clears all logs.
func (s *Scheduler) ClearLogs() {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.logs = nil
}

// ResetProgress resets progress counters.
func (s *Scheduler) ResetProgress() {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.progress = Progress{}
}

func (s *Scheduler) addLog(level, msg string) {
	s.mu.Lock()
	defer s.mu.Unlock()
	entry := LogEntry{
		Time:    time.Now().Format("2006-01-02 15:04:05"),
		Level:   level,
		Message: msg,
	}
	s.logs = append(s.logs, entry)
	if len(s.logs) > s.maxLogs {
		s.logs = s.logs[len(s.logs)-s.maxLogs:]
	}
	log.Printf("[CRON][%s] %s", level, msg)
}

// Start begins the cron batch processing in a goroutine.
// If manual is true, the time fence (Jam Mulai/Berhenti) is ignored.
func (s *Scheduler) Start(manual bool) error {
	s.mu.Lock()
	if s.running {
		s.mu.Unlock()
		return fmt.Errorf("cron sudah berjalan")
	}
	s.running = true
	s.stopCh = make(chan struct{})
	s.mu.Unlock()

	go s.run(manual)
	return nil
}

// Stop signals the cron to stop.
func (s *Scheduler) Stop() {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.running && s.stopCh != nil {
		close(s.stopCh)
	}
}

func (s *Scheduler) isStopped() bool {
	select {
	case <-s.stopCh:
		return true
	default:
		return false
	}
}

func (s *Scheduler) run(manual bool) {
	defer func() {
		s.client.ClearRateLimiter()
		s.mu.Lock()
		s.running = false
		s.mu.Unlock()
		s.addLog("INFO", "Cron selesai")
		s.SaveProgress()
	}()

	s.mu.Lock()
	s.progress.Sessions++
	settings := s.settings
	s.mu.Unlock()

	// Validate and clamp concurrency settings
	concurrency := settings.Concurrency
	if concurrency < 1 {
		concurrency = 1
	}
	if concurrency > 10 {
		concurrency = 10
	}
	reqPerMin := settings.RequestPerMenit
	if reqPerMin < 10 {
		reqPerMin = 10
	}
	if reqPerMin > 200 {
		reqPerMin = 200
	}
	burst := concurrency * 3
	if burst > reqPerMin/3 {
		burst = reqPerMin / 3
	}
	if burst < 1 {
		burst = 1
	}

	// Set rate limiter on the FHIR client (limits actual Satu Sehat API calls)
	s.client.SetRateLimiter(reqPerMin, burst, s.stopCh)

	s.addLog("INFO", fmt.Sprintf("=== Cron dimulai (sesi #%d) | Workers: %d | Rate: %d req/menit ===",
		s.progress.Sessions, concurrency, reqPerMin))

	// Determine date range
	startDate := settings.TanggalDari
	if startDate == "" {
		s.mu.Lock()
		if s.progress.LastCompletedDate != "" {
			t, err := time.Parse("2006-01-02", s.progress.LastCompletedDate)
			if err == nil {
				startDate = t.AddDate(0, 0, 1).Format("2006-01-02")
			}
		}
		s.mu.Unlock()
	}
	if startDate == "" {
		startDate = "2024-01-01"
	}

	endDate := time.Now().AddDate(0, 0, -1).Format("2006-01-02")

	s.addLog("INFO", fmt.Sprintf("Rentang tanggal: %s s/d %s", startDate, endDate))

	s.mu.Lock()
	s.progress.StartDate = startDate
	s.progress.EndDate = endDate
	s.progress.LastRun = time.Now().Format("2006-01-02 15:04:05")
	s.mu.Unlock()

	currentDate, err := time.Parse("2006-01-02", startDate)
	if err != nil {
		s.addLog("ERROR", "Format tanggal mulai tidak valid: "+startDate)
		return
	}
	endDateParsed, err := time.Parse("2006-01-02", endDate)
	if err != nil {
		s.addLog("ERROR", "Format tanggal akhir tidak valid: "+endDate)
		return
	}

	var totalErrors int32

	// Main date loop
	for !currentDate.After(endDateParsed) {
		if s.isStopped() {
			s.addLog("INFO", "Cron dihentikan oleh user")
			return
		}

		if !manual && !s.isWithinTimeFence(settings) {
			s.addLog("INFO", "Di luar jam operasi ("+settings.JamMulai+" - "+settings.JamBerhenti+"), berhenti")
			return
		}

		if int(atomic.LoadInt32(&totalErrors)) >= settings.MaxErrors {
			s.addLog("ERROR", fmt.Sprintf("Terlalu banyak error (%d), berhenti", atomic.LoadInt32(&totalErrors)))
			return
		}

		dateStr := currentDate.Format("2006-01-02")

		visits, err := s.db.GetRegPeriksaByDate(dateStr)
		if err != nil {
			s.addLog("ERROR", fmt.Sprintf("Error query tanggal %s: %v", dateStr, err))
			atomic.AddInt32(&totalErrors, 1)
			currentDate = currentDate.AddDate(0, 0, 1)
			continue
		}

		if len(visits) == 0 {
			currentDate = currentDate.AddDate(0, 0, 1)
			s.mu.Lock()
			s.progress.LastCompletedDate = dateStr
			s.mu.Unlock()
			continue
		}

		s.addLog("INFO", fmt.Sprintf("Tanggal %s: %d kunjungan", dateStr, len(visits)))

		// Process visits with concurrent worker pool
		sem := make(chan struct{}, concurrency)
		var wg sync.WaitGroup

		for _, visit := range visits {
			if s.isStopped() {
				break
			}
			if !manual && !s.isWithinTimeFence(settings) {
				s.addLog("INFO", "Di luar jam operasi, berhenti")
				break
			}
			if int(atomic.LoadInt32(&totalErrors)) >= settings.MaxErrors {
				break
			}

			sem <- struct{}{} // acquire worker slot
			wg.Add(1)
			go func(v db.RegPeriksa) {
				defer func() {
					if r := recover(); r != nil {
						s.addLog("ERROR", fmt.Sprintf("Panic saat proses %s: %v", v.NoRawat, r))
						atomic.AddInt32(&totalErrors, 1)
					}
					<-sem // release worker slot
					wg.Done()
				}()

				success, failed, skipped := s.processVisit(v)

				s.mu.Lock()
				s.progress.TotalProcessed++
				if failed == 0 && success > 0 {
					s.progress.TotalSuccess++
				} else if failed > 0 && success > 0 {
					// Partial success still counts
					s.progress.TotalSuccess++
				} else if failed > 0 {
					s.progress.TotalFailed++
					atomic.AddInt32(&totalErrors, 1)
				} else if skipped > 0 && success == 0 {
					// Semua di-skip (misal NIK tidak ditemukan)
					s.progress.TotalSkipped++
				}
				s.mu.Unlock()

			}(visit)
		}

		wg.Wait()

		// Date completed
		s.mu.Lock()
		s.progress.LastCompletedDate = dateStr
		s.mu.Unlock()
		s.SaveProgress()

		currentDate = currentDate.AddDate(0, 0, 1)
	}

	s.addLog("INFO", "Semua tanggal selesai diproses")
}

// isWithinTimeFence checks if current time is within allowed window.
func (s *Scheduler) isWithinTimeFence(settings Settings) bool {
	if settings.JamMulai == "" || settings.JamBerhenti == "" {
		return true // no fence
	}

	now := time.Now()
	nowMinutes := now.Hour()*60 + now.Minute()

	startH, startM := 23, 0
	fmt.Sscanf(settings.JamMulai, "%d:%d", &startH, &startM)
	startMinutes := startH*60 + startM

	endH, endM := 6, 0
	fmt.Sscanf(settings.JamBerhenti, "%d:%d", &endH, &endM)
	endMinutes := endH*60 + endM

	if startMinutes <= endMinutes {
		// Same day window: e.g. 08:00 - 17:00
		return nowMinutes >= startMinutes && nowMinutes < endMinutes
	}
	// Overnight window: e.g. 23:00 - 06:00
	return nowMinutes >= startMinutes || nowMinutes < endMinutes
}

// processVisit sends ALL FHIR resources for one visit using the exact same
// API endpoints as the paper-airplane button on the Response page.
// Step 0: Encounter (must succeed first)
// Steps 1-15: Condition, 10 observations, procedure, clinical-impression, care-plan, allergy
// Steps 16-18: Medication request → dispense → statement (sequential chain)
// Steps 19-22: Laboratory request → specimen → observation → diagnostic (sequential chain)
// Steps 23-26: Radiology request → specimen → observation → diagnostic (sequential chain)
func (s *Scheduler) processVisit(reg db.RegPeriksa) (success, failed, skipped int) {
	id := noRawatToURL(reg.NoRawat)

	// Exact same steps as processForwardNoRawat() in response.html
	steps := []fhirStep{
		{"Encounter", "/api/encounter/" + id},
		{"Condition", "/api/condition/" + id},
		{"Obs.Tensi", "/api/observation/" + id + "/tensi"},
		{"Obs.Nadi", "/api/observation/" + id + "/nadi"},
		{"Obs.Respirasi", "/api/observation/" + id + "/respirasi"},
		{"Obs.Suhu", "/api/observation/" + id + "/suhu"},
		{"Obs.SpO2", "/api/observation/" + id + "/spo2"},
		{"Obs.GCS", "/api/observation/" + id + "/gcs"},
		{"Obs.Kesadaran", "/api/observation/" + id + "/kesadaran"},
		{"Obs.Berat", "/api/observation/" + id + "/berat"},
		{"Obs.Tinggi", "/api/observation/" + id + "/tinggi"},
		{"Obs.Perut", "/api/observation/" + id + "/perut"},
		{"Procedure", "/api/procedure/" + id},
		{"ClinicalImpression", "/api/clinical-impression/" + id},
		{"CarePlan", "/api/care-plan/" + id},
		{"Allergy", "/api/allergy/" + id},
		{"Med.Request", "/api/medication/" + id + "/request"},
		{"Med.Dispense", "/api/medication/" + id + "/dispense"},
		{"Med.Statement", "/api/medication/" + id + "/statement"},
		{"Lab.Request", "/api/laboratory/" + id + "/request"},
		{"Lab.Specimen", "/api/laboratory/" + id + "/specimen"},
		{"Lab.Observation", "/api/laboratory/" + id + "/observation"},
		{"Lab.Diagnostic", "/api/laboratory/" + id + "/diagnostic"},
		{"Rad.Request", "/api/radiology/" + id + "/request"},
		{"Rad.Specimen", "/api/radiology/" + id + "/specimen"},
		{"Rad.Observation", "/api/radiology/" + id + "/observation"},
		{"Rad.Diagnostic", "/api/radiology/" + id + "/diagnostic"},
	}

	var details []string

	for i, step := range steps {
		if s.isStopped() {
			break
		}

		status, rid, msg := s.callEndpoint(step.Path)

		switch status {
		case "success":
			success++
			short := truncID(rid)
			details = append(details, fmt.Sprintf("%s:OK(%s)", step.Name, short))
		case "skipped":
			skipped++
			if i == 0 {
				if rid == "" {
					// NIK tidak ditemukan / data tidak lengkap → skip seluruh visit
					s.addLog("INFO", fmt.Sprintf("[%s] %s → Encounter dilewati, skip semua | %s",
						reg.TglRegistrasi, reg.NoRawat, msg))
					return
				}
				// Encounter sudah dikirim sebelumnya → lanjut proses step berikutnya
				details = append(details, fmt.Sprintf("%s:SUDAH(%s)", step.Name, truncID(rid)))
			}
		case "error":
			failed++
			details = append(details, fmt.Sprintf("%s:GAGAL", step.Name))
			// If encounter fails, skip everything (same as paper-airplane)
			if i == 0 {
				s.addLog("ERROR", fmt.Sprintf("[%s] %s → Encounter GAGAL, skip semua | %s",
					reg.TglRegistrasi, reg.NoRawat, msg))
				return
			}
		}
	}

	// Log summary
	level := "INFO"
	statusText := "selesai"
	if failed > 0 && success == 0 {
		level = "ERROR"
		statusText = "gagal"
	} else if failed > 0 {
		level = "WARN"
		statusText = "sebagian"
	}

	detail := ""
	if len(details) > 0 {
		detail = " | " + strings.Join(details, ", ")
	}

	s.addLog(level, fmt.Sprintf("[%s] %s → %s (%d sukses, %d gagal, %d dilewati)%s",
		reg.TglRegistrasi, reg.NoRawat, statusText, success, failed, skipped, detail))

	return
}

// callEndpoint calls an internal API endpoint via HTTP loopback and returns status + id.
// callEndpoint calls an internal API endpoint via HTTP loopback and returns status + id + message.
// Rate limiting is handled at the FHIR client level (per actual Satu Sehat API call).
// Retries once on error (transient failure).
func (s *Scheduler) callEndpoint(path string) (status, id, pesan string) {
	for attempt := 0; attempt < 2; attempt++ {
		if s.isStopped() {
			return "error", "", "stopped"
		}

		st, rid, msg := s.doHTTPCall(path)
		if st == "skipped" {
			return "skipped", rid, msg
		}
		if st == "success" {
			return "success", rid, ""
		}

		// Error on first attempt → wait and retry
		if attempt == 0 {
			select {
			case <-s.stopCh:
				return "error", "", msg
			case <-time.After(3 * time.Second):
			}
		} else {
			return "error", "", msg
		}
	}
	return "error", "", "max retries"
}

// doHTTPCall makes the actual HTTP GET and parses the response.
func (s *Scheduler) doHTTPCall(path string) (status, id, pesan string) {
	url := fmt.Sprintf("http://127.0.0.1%s%s", s.cfg.ListenAddr, path)
	resp, err := http.Get(url)
	if err != nil {
		return "error", "", fmt.Sprintf("HTTP error: %v", err)
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return "error", "", fmt.Sprintf("JSON decode error: %v", err)
	}

	// Extract pesan field for logging
	msg, _ := result["pesan"].(string)

	// Check explicit status field
	if st, ok := result["status"].(string); ok {
		switch st {
		case "skipped":
			rid, _ := result["id"].(string)
			return "skipped", rid, msg
		case "error":
			return "error", "", msg
		case "success":
			rid, _ := result["id"].(string)
			return "success", rid, ""
		}
	}

	// Fallback: check for error indicators
	if issue, ok := result["issue"]; ok {
		issueStr, _ := json.Marshal(issue)
		return "error", "", string(issueStr)
	}
	if msg != "" && strings.Contains(msg, "Gagal") {
		return "error", "", msg
	}

	// If we got an ID, consider it success
	if rid, ok := result["id"].(string); ok && rid != "" {
		return "success", rid, ""
	}

	return "skipped", "", msg
}

// noRawatToURL converts "2024/01/02/000001" → "2024--01--02--000001" (same format as JS).
func noRawatToURL(noRawat string) string {
	return strings.ReplaceAll(noRawat, "/", "--")
}

// truncID shortens a UUID for log display.
func truncID(id string) string {
	if len(id) > 12 {
		return id[:12] + "…"
	}
	return id
}

// StartAutoScheduler launches a background goroutine that triggers the cron
// according to the configured crontab schedule.
func (s *Scheduler) StartAutoScheduler() {
	go func() {
		for {
			time.Sleep(60 * time.Second)

			s.mu.Lock()
			enabled := s.settings.Enabled
			running := s.running
			schedule := s.settings.CrontabSchedule
			s.mu.Unlock()

			if !enabled || running {
				continue
			}

			if s.matchesCrontab(schedule) {
				s.addLog("INFO", "Auto-scheduler triggered (crontab: "+schedule+")")
				s.Start(false)
			}
		}
	}()
}

// matchesCrontab checks if current time matches a simple crontab spec "M H * * *".
func (s *Scheduler) matchesCrontab(spec string) bool {
	parts := strings.Fields(spec)
	if len(parts) < 2 {
		return false
	}

	now := time.Now()
	minute := fmt.Sprintf("%d", now.Minute())
	hour := fmt.Sprintf("%d", now.Hour())

	minMatch := parts[0] == "*" || parts[0] == minute
	hourMatch := parts[1] == "*" || parts[1] == hour

	return minMatch && hourMatch
}
