package pcare

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strings"
	"time"

	"pcare-go/internal/config"
)

// Client is the PCare BPJS API client.
type Client struct {
	cfg        *config.Config
	httpClient *http.Client
}

// NewClient creates a new PCare API client.
func NewClient(cfg *config.Config) *Client {
	transport := &http.Transport{
		MaxIdleConns:        10,
		MaxIdleConnsPerHost: 5,
		IdleConnTimeout:     90 * time.Second,
	}
	return &Client{
		cfg: cfg,
		httpClient: &http.Client{
			Timeout:   15 * time.Second,
			Transport: transport,
		},
	}
}

// buildHeaders creates the authentication headers for a PCare API request.
func (c *Client) buildHeaders() (http.Header, int64) {
	timestamp := time.Now().Unix()

	h := http.Header{}
	h.Set("Accept", "application/json")
	h.Set("X-cons-id", c.cfg.ConsumerID)
	h.Set("X-timestamp", fmt.Sprintf("%d", timestamp))
	h.Set("X-signature", CreateSignature(c.cfg.ConsumerID, c.cfg.ConsumerSecret, timestamp))
	h.Set("X-authorization", "Basic "+CreateAuthorization(c.cfg.Username, c.cfg.Password, c.cfg.KdAplikasi))
	h.Set("user_key", c.cfg.UserKey)

	return h, timestamp
}

// decryptResponseBody decrypts and decompresses the BPJS API encrypted response.
func (c *Client) decryptResponseBody(timestamp int64, encrypted string) (string, error) {
	key := DecryptKey(c.cfg.ConsumerID, c.cfg.ConsumerSecret, timestamp)
	decrypted, err := DecryptResponse(key, encrypted)
	if err != nil {
		return "", fmt.Errorf("decrypt: %w", err)
	}
	if decrypted == "" {
		return "", nil
	}
	decompressed := LZStringDecompress(decrypted)
	return decompressed, nil
}

// processResponse handles the standard PCare API response pattern:
// 1. Parse JSON wrapper (metaData + response)
// 2. Decrypt response field
// 3. Decompress LZ-string
// 4. Return clean JSON with decrypted response
func (c *Client) processResponse(timestamp int64, body []byte, statusCode int) ([]byte, error) {
	// Strip UTF-8 BOM if present
	body = bytes.TrimPrefix(body, []byte("\xef\xbb\xbf"))

	// Detect non-JSON (HTML) responses from API gateway or proxy
	trimmed := bytes.TrimSpace(body)
	if len(trimmed) > 0 && trimmed[0] == '<' {
		log.Printf("PCare API returned HTML (HTTP %d): %s", statusCode, string(trimmed[:min(len(trimmed), 500)]))
		return nil, fmt.Errorf("PCare API mengembalikan error (HTTP %d). Silakan coba lagi atau hubungi BPJS.", statusCode)
	}

	var raw struct {
		Response interface{} `json:"response"`
		MetaData MetaData    `json:"metaData"`
	}
	if err := json.Unmarshal(body, &raw); err != nil {
		log.Printf("PCare API JSON parse error (HTTP %d): %v, body: %s", statusCode, err, string(body[:min(len(body), 500)]))
		return nil, fmt.Errorf("json parse: %w", err)
	}

	code := raw.MetaData.Code.String()
	message := raw.MetaData.Message

	// If response is a string, try to decrypt it
	responseStr, ok := raw.Response.(string)
	if ok && responseStr != "" {
		decrypted, err := c.decryptResponseBody(timestamp, responseStr)
		if err != nil {
			log.Printf("Warning: decrypt failed: %v", err)
			// Return raw response on decrypt failure
			result := map[string]interface{}{
				"metaData": map[string]string{"code": code, "message": message},
				"response": responseStr,
			}
			return json.Marshal(result)
		}
		// Build clean response with decrypted data
		result := fmt.Sprintf(`{"metaData":{"code":"%s","message":"%s"},"response":%s}`, code, message, decrypted)
		return []byte(result), nil
	}

	// If not encrypted, return as-is
	return body, nil
}

// Get performs a GET request to the PCare API.
func (c *Client) Get(endpoint string) ([]byte, error) {
	url := strings.TrimRight(c.cfg.PCareAPIURL, "/") + "/" + strings.TrimLeft(endpoint, "/")

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}

	headers, timestamp := c.buildHeaders()
	req.Header = headers

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("http get: %w", err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read body: %w", err)
	}

	return c.processResponse(timestamp, body, resp.StatusCode)
}

// Post performs a POST request to the PCare API.
func (c *Client) Post(endpoint string, data interface{}) ([]byte, error) {
	return c.doMutation("POST", endpoint, data)
}

// Put performs a PUT request to the PCare API.
func (c *Client) Put(endpoint string, data interface{}) ([]byte, error) {
	return c.doMutation("PUT", endpoint, data)
}

// Delete performs a DELETE request to the PCare API.
func (c *Client) Delete(endpoint string) ([]byte, error) {
	url := strings.TrimRight(c.cfg.PCareAPIURL, "/") + "/" + strings.TrimLeft(endpoint, "/")

	req, err := http.NewRequest("DELETE", url, nil)
	if err != nil {
		return nil, err
	}

	headers, timestamp := c.buildHeaders()
	req.Header = headers

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("http delete: %w", err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read body: %w", err)
	}

	return c.processResponse(timestamp, body, resp.StatusCode)
}

// doMutation performs POST/PUT requests with JSON body.
func (c *Client) doMutation(method, endpoint string, data interface{}) ([]byte, error) {
	url := strings.TrimRight(c.cfg.PCareAPIURL, "/") + "/" + strings.TrimLeft(endpoint, "/")

	var bodyReader io.Reader
	if data != nil {
		jsonData, err := json.Marshal(data)
		if err != nil {
			return nil, fmt.Errorf("json marshal: %w", err)
		}
		bodyReader = strings.NewReader(string(jsonData))
	}

	req, err := http.NewRequest(method, url, bodyReader)
	if err != nil {
		return nil, err
	}

	headers, timestamp := c.buildHeaders()
	req.Header = headers
	if data != nil {
		req.Header.Set("Content-Type", "text/plain")
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("http %s: %w", method, err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read body: %w", err)
	}

	return c.processResponse(timestamp, body, resp.StatusCode)
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// GetRaw performs a GET request and returns raw bytes (no decryption).
func (c *Client) GetRaw(endpoint string) ([]byte, error) {
	url := strings.TrimRight(c.cfg.PCareAPIURL, "/") + "/" + strings.TrimLeft(endpoint, "/")

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}

	headers, _ := c.buildHeaders()
	req.Header = headers

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("http get: %w", err)
	}
	defer resp.Body.Close()

	return io.ReadAll(resp.Body)
}
