package pcare

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"fmt"

	lzstring "github.com/daku10/go-lz-string"
)

// CreateSignature generates the HMAC-SHA256 signature for PCare API authentication.
// data = consumerID + "&" + timestamp
func CreateSignature(consumerID, consumerSecret string, timestamp int64) string {
	data := fmt.Sprintf("%s&%d", consumerID, timestamp)
	mac := hmac.New(sha256.New, []byte(consumerSecret))
	mac.Write([]byte(data))
	return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}

// CreateAuthorization generates the Base64-encoded authorization string.
// format: base64(username:password:kdAplikasi)
func CreateAuthorization(username, password, kdAplikasi string) string {
	raw := fmt.Sprintf("%s:%s:%s", username, password, kdAplikasi)
	return base64.StdEncoding.EncodeToString([]byte(raw))
}

// DecryptResponse decrypts the encrypted response from BPJS PCare API.
// Uses AES-256-CBC with key derived from consumerID + consumerSecret + timestamp.
func DecryptResponse(key, encrypted string) (string, error) {
	if encrypted == "" {
		return "", nil
	}

	// Derive AES key and IV from key using SHA-256
	h := sha256.Sum256([]byte(key))
	keyHash := h[:]
	iv := keyHash[:16]

	ciphertext, err := base64.StdEncoding.DecodeString(encrypted)
	if err != nil {
		return "", fmt.Errorf("base64 decode: %w", err)
	}

	block, err := aes.NewCipher(keyHash)
	if err != nil {
		return "", fmt.Errorf("aes cipher: %w", err)
	}

	if len(ciphertext) < aes.BlockSize || len(ciphertext)%aes.BlockSize != 0 {
		return "", fmt.Errorf("invalid ciphertext length")
	}

	mode := cipher.NewCBCDecrypter(block, iv)
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)

	// Remove PKCS7 padding
	plaintext = pkcs7Unpad(plaintext)

	return string(plaintext), nil
}

// pkcs7Unpad removes PKCS7 padding from decrypted data.
func pkcs7Unpad(data []byte) []byte {
	if len(data) == 0 {
		return data
	}
	padding := int(data[len(data)-1])
	if padding > len(data) || padding > aes.BlockSize || padding == 0 {
		return data
	}
	for i := len(data) - padding; i < len(data); i++ {
		if data[i] != byte(padding) {
			return data
		}
	}
	return data[:len(data)-padding]
}

// DecryptKey builds the decryption key from consumerID, consumerSecret, and timestamp.
func DecryptKey(consumerID, consumerSecret string, timestamp int64) string {
	return fmt.Sprintf("%s%s%d", consumerID, consumerSecret, timestamp)
}

// KeyHashHex returns the hex-encoded SHA-256 hash of a key (for debugging).
func KeyHashHex(key string) string {
	h := sha256.Sum256([]byte(key))
	return hex.EncodeToString(h[:])
}

// LZStringDecompress decompresses an LZ-string encoded URI component.
func LZStringDecompress(input string) string {
	if input == "" {
		return ""
	}
	result, err := lzstring.DecompressFromEncodedURIComponent(input)
	if err != nil {
		return ""
	}
	return result
}
