package handler

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"satu-sehat-go/internal/cron"
	"satu-sehat-go/internal/db"
)

// CronHandler provides HTTP handlers for cron operations.
type CronHandler struct {
	scheduler *cron.Scheduler
	db        *db.DB
}

// NewCronHandler creates a new CronHandler.
func NewCronHandler(scheduler *cron.Scheduler, database *db.DB) *CronHandler {
	return &CronHandler{scheduler: scheduler, db: database}
}

// PostAction handles cron action commands: start, stop, reset, clearlog.
func (ch *CronHandler) PostAction(w http.ResponseWriter, r *http.Request) {
	r.ParseForm()
	action := r.FormValue("action")
	if action == "" {
		// Try JSON body
		var body struct {
			Action string `json:"action"`
		}
		json.NewDecoder(r.Body).Decode(&body)
		action = body.Action
	}

	switch action {
	case "start":
		if err := ch.scheduler.Start(true); err != nil {
			jsonOK(w, map[string]string{"status": "error", "message": err.Error()})
			return
		}
		jsonOK(w, map[string]string{"status": "ok", "message": "Cron dimulai"})

	case "stop":
		ch.scheduler.Stop()
		jsonOK(w, map[string]string{"status": "ok", "message": "Cron dihentikan"})

	case "reset":
		ch.scheduler.ResetProgress()
		ch.scheduler.SaveProgress()
		jsonOK(w, map[string]string{"status": "ok", "message": "Progress direset"})

	case "clearlog":
		ch.scheduler.ClearLogs()
		jsonOK(w, map[string]string{"status": "ok", "message": "Log dihapus"})

	default:
		jsonError(w, 400, "Action tidak dikenali: "+action)
	}
}

// PostSettings saves cron settings.
func (ch *CronHandler) PostSettings(w http.ResponseWriter, r *http.Request) {
	r.ParseForm()
	vals := make(map[string]string)
	for key := range r.Form {
		vals[key] = r.FormValue(key)
	}

	if len(vals) == 0 {
		// Try JSON body
		json.NewDecoder(r.Body).Decode(&vals)
	}

	ch.scheduler.UpdateSettings(vals)
	ch.scheduler.SaveSettings()

	jsonOK(w, map[string]string{"status": "ok", "message": "Pengaturan disimpan"})
}

// GetLog returns cron log lines.
func (ch *CronHandler) GetLog(w http.ResponseWriter, r *http.Request) {
	linesStr := r.URL.Query().Get("lines")
	lines := 100
	if linesStr != "" {
		if n, err := strconv.Atoi(linesStr); err == nil && n > 0 {
			lines = n
		}
	}

	logs := ch.scheduler.GetLogs(lines)

	// Format as plain text
	var sb strings.Builder
	for _, entry := range logs {
		sb.WriteString(fmt.Sprintf("[%s] [%s] %s\n", entry.Time, entry.Level, entry.Message))
	}

	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	w.Write([]byte(sb.String()))
}

// GetStatus returns cron running status, progress, and hutang data as JSON.
func (ch *CronHandler) GetStatus(w http.ResponseWriter, r *http.Request) {
	progress := ch.scheduler.GetProgress()
	running := ch.scheduler.IsRunning()

	result := map[string]interface{}{
		"running":  running,
		"progress": progress,
	}

	// Include hutang data for live dashboard refresh
	if ch.db != nil {
		totalHutang, totalKunjungan, err := ch.db.GetTotalHutang()
		if err == nil {
			result["total_hutang"] = totalHutang
			result["total_kunjungan"] = totalKunjungan
		}
		hutangRows, err := ch.db.GetHutangPerBulan()
		if err == nil {
			result["hutang_per_bulan"] = hutangRows
		}
	}

	jsonOK(w, result)
}
