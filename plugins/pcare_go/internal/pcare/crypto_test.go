package pcare

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"fmt"
	"strings"
	"testing"
)

func TestCreateSignature(t *testing.T) {
	consumerID := "testconsumer123"
	consumerSecret := "secretxyz"
	var timestamp int64 = 1700000000

	sig := CreateSignature(consumerID, consumerSecret, timestamp)

	// Verify it's valid base64
	decoded, err := base64.StdEncoding.DecodeString(sig)
	if err != nil {
		t.Fatalf("Signature is not valid base64: %v", err)
	}

	// Verify HMAC-SHA256 manually
	message := fmt.Sprintf("%s&%d", consumerID, timestamp)
	mac := hmac.New(sha256.New, []byte(consumerSecret))
	mac.Write([]byte(message))
	expected := mac.Sum(nil)

	if string(decoded) != string(expected) {
		t.Errorf("Signature mismatch")
	}
}

func TestCreateAuthorization(t *testing.T) {
	username := "testuser"
	password := "testpass"
	kdAplikasi := "095"

	auth := CreateAuthorization(username, password, kdAplikasi)

	// Verify it's valid base64
	decoded, err := base64.StdEncoding.DecodeString(auth)
	if err != nil {
		t.Fatalf("Authorization is not valid base64: %v", err)
	}

	expected := username + ":" + password + ":" + kdAplikasi
	if string(decoded) != expected {
		t.Errorf("Expected decoded %q, got %q", expected, string(decoded))
	}
}

func TestCreateAuthorizationEmpty(t *testing.T) {
	auth := CreateAuthorization("", "", "")
	decoded, _ := base64.StdEncoding.DecodeString(auth)
	if string(decoded) != "::" {
		t.Errorf("Expected '::', got %q", string(decoded))
	}
}

func TestDecryptKey(t *testing.T) {
	consumerID := "cons123"
	consumerSecret := "sec456"
	var timestamp int64 = 1700000000

	key := DecryptKey(consumerID, consumerSecret, timestamp)

	// Key should be the concatenation: consumerID + consumerSecret + timestamp
	expected := fmt.Sprintf("%s%s%d", consumerID, consumerSecret, timestamp)
	if key != expected {
		t.Errorf("Expected key %q, got %q", expected, key)
	}
}

func TestPKCS7Unpad(t *testing.T) {
	tests := []struct {
		name  string
		input []byte
		want  []byte
	}{
		{
			name:  "valid padding 4",
			input: []byte("hello world\x04\x04\x04\x04"),
			want:  []byte("hello world"),
		},
		{
			name:  "valid padding 1",
			input: []byte("hello world12345\x01"),
			want:  []byte("hello world12345"),
		},
		{
			name:  "empty input returns empty",
			input: []byte{},
			want:  []byte{},
		},
		{
			name:  "invalid padding value zero returns original",
			input: []byte("hello\x00"),
			want:  []byte("hello\x00"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := pkcs7Unpad(tt.input)
			if string(got) != string(tt.want) {
				t.Errorf("pkcs7Unpad() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestLZStringDecompressEmpty(t *testing.T) {
	result := LZStringDecompress("")
	if result != "" {
		t.Errorf("Expected empty string, got %q", result)
	}
}

func TestLZStringDecompressNil(t *testing.T) {
	result := LZStringDecompress("A")
	// Single character shouldn't crash - just return empty or partial
	_ = result
}

func TestDecryptResponseInvalidBase64(t *testing.T) {
	key := "somekey"
	_, err := DecryptResponse(key, "not-valid-base64!!!")
	if err == nil {
		t.Error("Expected error for invalid base64")
	}
}

func TestDecryptResponseShortCiphertext(t *testing.T) {
	key := "somekey"
	// Base64 of 8 bytes (less than AES block size)
	short := base64.StdEncoding.EncodeToString([]byte("short"))
	_, err := DecryptResponse(key, short)
	if err == nil {
		t.Error("Expected error for short ciphertext")
	}
}

func TestSignatureFormat(t *testing.T) {
	var timestamp int64 = 12345
	sig := CreateSignature("id", "secret", timestamp)
	// Should not contain newlines or spaces
	if strings.ContainsAny(sig, "\n\r ") {
		t.Errorf("Signature contains whitespace: %q", sig)
	}
}
