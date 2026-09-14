package handler

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"pcare-go/internal/config"
)

func TestHealthCheck(t *testing.T) {
	cfg := &config.Config{
		PCareAPIURL: "https://example.com",
		KodeFKTP:    "12345",
	}
	h := New(cfg, nil, nil)

	req := httptest.NewRequest("GET", "/health", nil)
	w := httptest.NewRecorder()

	h.HealthCheck(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("Expected status 200, got %d", w.Code)
	}
	if w.Header().Get("Content-Type") != "application/json" {
		t.Errorf("Expected Content-Type application/json, got %q", w.Header().Get("Content-Type"))
	}
	body := w.Body.String()
	if body == "" {
		t.Error("Expected non-empty body")
	}
}

func TestJsonResponse(t *testing.T) {
	w := httptest.NewRecorder()
	jsonResponse(w, 200, map[string]string{"test": "value"})

	if w.Code != 200 {
		t.Errorf("Expected 200, got %d", w.Code)
	}
	if w.Header().Get("Content-Type") != "application/json" {
		t.Errorf("Expected application/json content type")
	}
	body := w.Body.String()
	if body == "" {
		t.Error("Expected non-empty body")
	}
}

func TestErrorJSON(t *testing.T) {
	w := httptest.NewRecorder()
	errorJSON(w, 400, "bad request")

	if w.Code != 400 {
		t.Errorf("Expected 400, got %d", w.Code)
	}
	body := w.Body.String()
	if body == "" {
		t.Error("Expected non-empty error body")
	}
}

func TestConvertDateFormat(t *testing.T) {
	tests := []struct {
		input string
		want  string
	}{
		{"01-02-2025", "2025-02-01"},
		{"15-12-2024", "2024-12-15"},
		{"invalid", ""}, // will fallback to current date
	}

	for _, tt := range tests {
		got := convertDateFormat(tt.input)
		if tt.want != "" && got != tt.want {
			t.Errorf("convertDateFormat(%q) = %q, want %q", tt.input, got, tt.want)
		}
	}
}

func TestFormatDateDDMMYYYY(t *testing.T) {
	tests := []struct {
		input string
		want  string
	}{
		{"2025-02-01", "01-02-2025"},
		{"2024-12-15", "15-12-2024"},
	}
	for _, tt := range tests {
		got := formatDateDDMMYYYY(tt.input)
		if got != tt.want {
			t.Errorf("formatDateDDMMYYYY(%q) = %q, want %q", tt.input, got, tt.want)
		}
	}
}

func TestFirstNonEmpty(t *testing.T) {
	tests := []struct {
		vals []string
		want string
	}{
		{[]string{"", "", "hello"}, "hello"},
		{[]string{"first", "second"}, "first"},
		{[]string{"", "0", "value"}, "value"},
		{[]string{""}, ""},
	}
	for _, tt := range tests {
		got := firstNonEmpty(tt.vals...)
		if got != tt.want {
			t.Errorf("firstNonEmpty(%v) = %q, want %q", tt.vals, got, tt.want)
		}
	}
}

func TestNilIfEmpty(t *testing.T) {
	if nilIfEmpty("") != nil {
		t.Error("Expected nil for empty string")
	}
	if nilIfEmpty("hello") != "hello" {
		t.Error("Expected 'hello' for non-empty string")
	}
}

func TestIntValOr(t *testing.T) {
	if intValOr("120") != 120 {
		t.Errorf("Expected 120, got %d", intValOr("120"))
	}
	if intValOr("", "0", "80") != 80 {
		t.Errorf("Expected 80")
	}
	if intValOr("", "") != 0 {
		t.Errorf("Expected 0")
	}
}
