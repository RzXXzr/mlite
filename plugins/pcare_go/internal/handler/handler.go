package handler

import (
	"encoding/json"
	"fmt"
	"net/http"

	"pcare-go/internal/config"
	"pcare-go/internal/db"
	"pcare-go/internal/pcare"
)

// Handler holds dependencies for API handlers.
type Handler struct {
	cfg    *config.Config
	db     *db.DB
	client *pcare.Client
}

// New creates a new Handler.
func New(cfg *config.Config, database *db.DB, client *pcare.Client) *Handler {
	return &Handler{cfg: cfg, db: database, client: client}
}

// jsonResponse writes a JSON response.
func jsonResponse(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// jsonRaw writes raw JSON bytes as response.
func jsonRaw(w http.ResponseWriter, status int, data []byte) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	w.Write(data)
}

// errorJSON writes a JSON error response.
func errorJSON(w http.ResponseWriter, status int, message string) {
	jsonResponse(w, status, map[string]string{"status": "error", "message": message})
}

// pcareResponse writes a PCare API response, detecting error codes in metaData.
// If PCare returns a non-200 code, it maps to an appropriate HTTP status.
func pcareResponse(w http.ResponseWriter, data []byte) {
	var meta struct {
		MetaData struct {
			Code    json.RawMessage `json:"code"`
			Message string          `json:"message"`
		} `json:"metaData"`
	}
	httpStatus := 200
	if json.Unmarshal(data, &meta) == nil {
		code := ""
		// Handle code as string or number
		var s string
		if json.Unmarshal(meta.MetaData.Code, &s) == nil {
			code = s
		} else {
			var n float64
			if json.Unmarshal(meta.MetaData.Code, &n) == nil {
				code = fmt.Sprintf("%.0f", n)
			}
		}
		if code != "" && code != "200" && code != "0" {
			switch code {
			case "401":
				httpStatus = 401
			case "412":
				httpStatus = 412
			}
			// Other PCare error codes (e.g. 50000) stay HTTP 200
			// so the frontend .done() handler can process metaData.code
		}
	}
	jsonRaw(w, httpStatus, data)
}

// HealthCheck returns service status.
func (h *Handler) HealthCheck(w http.ResponseWriter, r *http.Request) {
	jsonResponse(w, 200, map[string]string{"status": "ok", "service": "pcare-go"})
}
