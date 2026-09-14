package auth

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"time"

	"satu-sehat-go/internal/config"
)

// TokenResponse from Satu Sehat OAuth2 endpoint.
// Note: Production API returns expires_in as string, dev as int — use json.Number.
type TokenResponse struct {
	AccessToken string      `json:"access_token"`
	TokenType   string      `json:"token_type"`
	ExpiresIn   json.Number `json:"expires_in"`
	Scope       string      `json:"scope"`
}

// Client handles Satu Sehat OAuth2 authentication and FHIR API calls.
type Client struct {
	cfg          *config.Config
	httpClient   *http.Client
	cachedToken  string
	tokenExpiry  time.Time
	mu           sync.Mutex
	limiter      *TokenBucket
	limiterStopCh <-chan struct{}
	limiterMu    sync.RWMutex
}

// TokenBucket implements a rate limiter using the token bucket algorithm.
type TokenBucket struct {
	mu         sync.Mutex
	tokens     float64
	maxTokens  float64
	refillRate float64 // tokens per second
	lastRefill time.Time
}

// NewTokenBucket creates a new rate limiter.
func NewTokenBucket(perMinute, burst int) *TokenBucket {
	return &TokenBucket{
		tokens:     float64(burst),
		maxTokens:  float64(burst),
		refillRate: float64(perMinute) / 60.0,
		lastRefill: time.Now(),
	}
}

func (tb *TokenBucket) refill() {
	now := time.Now()
	elapsed := now.Sub(tb.lastRefill).Seconds()
	tb.tokens += elapsed * tb.refillRate
	if tb.tokens > tb.maxTokens {
		tb.tokens = tb.maxTokens
	}
	tb.lastRefill = now
}

// Acquire blocks until a token is available or stop signal received.
func (tb *TokenBucket) Acquire(stopCh <-chan struct{}) bool {
	for {
		tb.mu.Lock()
		tb.refill()
		if tb.tokens >= 1 {
			tb.tokens--
			tb.mu.Unlock()
			return true
		}
		deficit := 1 - tb.tokens
		waitSec := deficit / tb.refillRate
		tb.mu.Unlock()

		wait := time.Duration(waitSec * float64(time.Second))
		if wait < time.Millisecond {
			wait = time.Millisecond
		}
		select {
		case <-stopCh:
			return false
		case <-time.After(wait):
		}
	}
}

// SetRateLimiter configures a rate limiter for FHIR API calls. Pass stopCh for cancellation.
func (c *Client) SetRateLimiter(perMinute, burst int, stopCh <-chan struct{}) {
	c.limiterMu.Lock()
	defer c.limiterMu.Unlock()
	c.limiter = NewTokenBucket(perMinute, burst)
	c.limiterStopCh = stopCh
}

// ClearRateLimiter removes the rate limiter (for manual/non-cron usage).
func (c *Client) ClearRateLimiter() {
	c.limiterMu.Lock()
	defer c.limiterMu.Unlock()
	c.limiter = nil
	c.limiterStopCh = nil
}

// acquireToken blocks until a rate limiter token is available. Returns false if stopped.
func (c *Client) acquireToken() bool {
	c.limiterMu.RLock()
	lim := c.limiter
	stopCh := c.limiterStopCh
	c.limiterMu.RUnlock()
	if lim == nil {
		return true // no limiter, proceed immediately
	}
	return lim.Acquire(stopCh)
}

// NewClient creates a new Satu Sehat API client.
func NewClient(cfg *config.Config) *Client {
	return &Client{
		cfg: cfg,
		httpClient: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// GetAccessToken retrieves or refreshes the access token.
func (c *Client) GetAccessToken() (string, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.cachedToken != "" && time.Now().Before(c.tokenExpiry) {
		return c.cachedToken, nil
	}

	data := url.Values{}
	data.Set("client_id", c.cfg.ClientID)
	data.Set("client_secret", c.cfg.SecretKey)

	req, err := http.NewRequest("POST",
		c.cfg.AuthURL+"/accesstoken?grant_type=client_credentials",
		strings.NewReader(data.Encode()))
	if err != nil {
		return "", fmt.Errorf("creating token request: %w", err)
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return "", fmt.Errorf("requesting token: %w", err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", fmt.Errorf("reading token response: %w", err)
	}

	var tokenResp TokenResponse
	if err := json.Unmarshal(body, &tokenResp); err != nil {
		return "", fmt.Errorf("parsing token response: %w, body: %s", err, string(body))
	}

	if tokenResp.AccessToken == "" {
		return "", fmt.Errorf("empty access token, response: %s", string(body))
	}

	c.cachedToken = tokenResp.AccessToken
	// Expire 60 seconds early to be safe
	expiresIn, _ := strconv.Atoi(tokenResp.ExpiresIn.String())
	if expiresIn > 0 {
		c.tokenExpiry = time.Now().Add(time.Duration(expiresIn-60) * time.Second)
	} else {
		c.tokenExpiry = time.Now().Add(50 * time.Minute)
	}

	return c.cachedToken, nil
}

// FHIRGet performs a GET request to the FHIR API with automatic 429 retry.
func (c *Client) FHIRGet(path string) ([]byte, error) {
	if !c.acquireToken() {
		return nil, fmt.Errorf("rate limiter stopped")
	}
	for attempt := 0; attempt < 3; attempt++ {
		token, err := c.GetAccessToken()
		if err != nil {
			return nil, err
		}

		reqURL := c.cfg.FhirURL + path
		req, err := http.NewRequest("GET", reqURL, nil)
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Authorization", "Bearer "+token)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			return nil, err
		}

		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			return nil, err
		}

		if resp.StatusCode == 429 {
			wait := time.Duration(3*(attempt+1)) * time.Second
			time.Sleep(wait)
			continue
		}
		return body, nil
	}
	return nil, fmt.Errorf("rate limit exceeded after retries (429)")
}

// FHIRPost performs a POST request to the FHIR API with automatic 429 retry.
func (c *Client) FHIRPost(path string, jsonBody []byte) ([]byte, error) {
	if !c.acquireToken() {
		return nil, fmt.Errorf("rate limiter stopped")
	}
	for attempt := 0; attempt < 3; attempt++ {
		token, err := c.GetAccessToken()
		if err != nil {
			return nil, err
		}

		reqURL := c.cfg.FhirURL + path
		req, err := http.NewRequest("POST", reqURL, strings.NewReader(string(jsonBody)))
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Authorization", "Bearer "+token)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			return nil, err
		}

		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			return nil, err
		}

		if resp.StatusCode == 429 {
			wait := time.Duration(3*(attempt+1)) * time.Second
			time.Sleep(wait)
			continue
		}
		return body, nil
	}
	return nil, fmt.Errorf("rate limit exceeded after retries (429)")
}

// FHIRPut performs a PUT request to the FHIR API with automatic 429 retry.
func (c *Client) FHIRPut(path string, jsonBody []byte) ([]byte, error) {
	if !c.acquireToken() {
		return nil, fmt.Errorf("rate limiter stopped")
	}
	for attempt := 0; attempt < 3; attempt++ {
		token, err := c.GetAccessToken()
		if err != nil {
			return nil, err
		}

		reqURL := c.cfg.FhirURL + path
		req, err := http.NewRequest("PUT", reqURL, strings.NewReader(string(jsonBody)))
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Authorization", "Bearer "+token)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			return nil, err
		}

		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			return nil, err
		}

		if resp.StatusCode == 429 {
			wait := time.Duration(3*(attempt+1)) * time.Second
			time.Sleep(wait)
			continue
		}
		return body, nil
	}
	return nil, fmt.Errorf("rate limit exceeded after retries (429)")
}

// GetPatientByNIK searches for a patient by NIK.
func (c *Client) GetPatientByNIK(nik string) ([]byte, error) {
	return c.FHIRGet("/Patient?identifier=https://fhir.kemkes.go.id/id/nik|" + url.QueryEscape(nik))
}

// GetPatientByID retrieves a patient by FHIR ID.
func (c *Client) GetPatientByID(id string) ([]byte, error) {
	return c.FHIRGet("/Patient/" + id)
}

// GetPractitionerByNIK searches for a practitioner by NIK.
func (c *Client) GetPractitionerByNIK(nik string) ([]byte, error) {
	return c.FHIRGet("/Practitioner?identifier=https://fhir.kemkes.go.id/id/nik|" + url.QueryEscape(nik))
}

// GetPractitionerByID retrieves a practitioner by FHIR ID.
func (c *Client) GetPractitionerByID(id string) ([]byte, error) {
	return c.FHIRGet("/Practitioner/" + id)
}

// ExtractPatientIHS extracts IHS Patient ID from the patient search response.
func ExtractPatientIHS(respBody []byte) string {
	var result struct {
		Entry []struct {
			Resource struct {
				ID string `json:"id"`
			} `json:"resource"`
		} `json:"entry"`
	}
	if err := json.Unmarshal(respBody, &result); err != nil || len(result.Entry) == 0 {
		return ""
	}
	return result.Entry[0].Resource.ID
}

// ExtractPractitionerIHS extracts IHS Practitioner ID from the practitioner search response.
func ExtractPractitionerIHS(respBody []byte) string {
	return ExtractPatientIHS(respBody) // same structure
}

// ExtractResourceID extracts the "id" field from a FHIR resource response.
func ExtractResourceID(respBody []byte) string {
	var result struct {
		ID string `json:"id"`
	}
	if err := json.Unmarshal(respBody, &result); err != nil {
		return ""
	}
	return result.ID
}

// SearchKFA searches drug products by KFA code.
func (c *Client) SearchKFA(code string) ([]byte, error) {
	token, err := c.GetAccessToken()
	if err != nil {
		return nil, err
	}

	parsed, err := url.Parse(c.cfg.AuthURL)
	if err != nil {
		return nil, err
	}
	baseURL := parsed.Scheme + "://" + parsed.Host

	reqURL := baseURL + "/kfa-v2/products?identifier=kfa&code=" + url.QueryEscape(code)
	req, err := http.NewRequest("GET", reqURL, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	return io.ReadAll(resp.Body)
}

// SearchKFAByKeyword searches drug products by name/keyword.
func (c *Client) SearchKFAByKeyword(keyword, productType string) ([]byte, error) {
	token, err := c.GetAccessToken()
	if err != nil {
		return nil, err
	}

	parsed, err := url.Parse(c.cfg.AuthURL)
	if err != nil {
		return nil, err
	}
	baseURL := parsed.Scheme + "://" + parsed.Host

	reqURL := baseURL + "/kfa-v2/products/all?page=1&size=10&product_type=" + url.QueryEscape(productType) + "&keyword=" + url.QueryEscape(keyword)
	req, err := http.NewRequest("GET", reqURL, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	return io.ReadAll(resp.Body)
}
