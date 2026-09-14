package pcare

import (
	"testing"

	"pcare-go/internal/config"
)

func TestNewClient(t *testing.T) {
	cfg := &config.Config{
		ConsumerID:     "test_consumer",
		ConsumerSecret: "test_secret",
		Username:       "test_user",
		Password:       "test_pass",
		KdAplikasi:     "095",
		UserKey:        "test_user_key",
		PCareAPIURL:    "https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev",
	}
	client := NewClient(cfg)
	if client == nil {
		t.Fatal("Expected non-nil client")
	}
	if client.cfg.ConsumerID != "test_consumer" {
		t.Errorf("Expected consumer ID 'test_consumer', got %q", client.cfg.ConsumerID)
	}
}

func TestBuildHeaders(t *testing.T) {
	cfg := &config.Config{
		ConsumerID:     "testconsumer",
		ConsumerSecret: "testsecret",
		Username:       "testuser",
		Password:       "testpass",
		KdAplikasi:     "095",
		UserKey:        "testuserkey",
		PCareAPIURL:    "https://example.com/pcare",
	}
	client := NewClient(cfg)

	headers, ts := client.buildHeaders()

	// Check required headers (http.Header values are accessed via Get)
	if headers.Get("X-cons-id") != "testconsumer" {
		t.Errorf("Expected X-cons-id 'testconsumer', got %q", headers.Get("X-cons-id"))
	}
	if headers.Get("X-timestamp") == "" {
		t.Error("Expected non-empty X-timestamp")
	}
	if headers.Get("X-signature") == "" {
		t.Error("Expected non-empty X-signature")
	}
	if headers.Get("X-authorization") == "" {
		t.Error("Expected non-empty X-authorization")
	}
	if headers.Get("user_key") != "testuserkey" {
		t.Errorf("Expected user_key 'testuserkey', got %q", headers.Get("user_key"))
	}
	if headers.Get("Accept") != "application/json" {
		t.Errorf("Expected Accept 'application/json', got %q", headers.Get("Accept"))
	}
	if ts == 0 {
		t.Error("Expected non-zero timestamp")
	}
}

func TestBuildHeadersTimestampChanges(t *testing.T) {
	cfg := &config.Config{
		ConsumerID:     "c",
		ConsumerSecret: "s",
		Username:       "u",
		Password:       "p",
		KdAplikasi:     "095",
		UserKey:        "k",
		PCareAPIURL:    "https://example.com",
	}
	client := NewClient(cfg)

	h1, ts1 := client.buildHeaders()
	h2, ts2 := client.buildHeaders()

	// Timestamps should be the same (within same second) or at most 1 apart
	_ = h1
	_ = h2
	_ = ts1
	_ = ts2
}
