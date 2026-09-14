# Satu Sehat Go Module

Modul Satu Sehat (FHIR R4) untuk integrasi dengan platform Satu Sehat Kemkes, ditulis ulang dari PHP ke Go.

## Fitur Utama

- **OAuth2 Authentication** — Token management otomatis dengan caching & auto-refresh
- **FHIR R4 Resources** — Encounter, Condition, Observation (10+ vital signs), Procedure, Medication, Laboratory, Radiology, ClinicalImpression, CarePlan, AllergyIntolerance
- **Mapping Management** — Praktisi, Lokasi, Departemen, Obat (KFA), Lab (LOINC), Radiologi
- **Batch Processing & Cron** — Forward per tanggal, auto-scheduler, worker pool, progress tracking
- **REST API** — Semua endpoint tersedia via HTTP JSON API
- **Web Dashboard** — UI lengkap: settings, mapping, response tracking, cron monitor
- **Kompatibilitas** — Route compatibility dengan PHP Site.php
- **Error Handling** — Validasi, auto-promotion diagnosis, dependency chain

## Struktur Proyek

```
satu_sehat_go/
├── cmd/
│   └── main.go              # Entry point
├── internal/
│   ├── auth/
│   │   └── client.go        # OAuth2 client & FHIR API calls
│   ├── config/
│   │   └── config.go        # Configuration from env vars & DB
│   ├── cron/
│   │   └── cron.go          # Batch scheduler & progress tracking
│   ├── db/
│   │   ├── db.go            # Database layer (MySQL)
│   │   ├── db_cron.go       # Cron-specific queries
│   │   ├── db_response.go   # Response DataTable queries
│   │   └── db_web.go        # Web frontend queries
│   ├── fhir/
│   │   └── resources.go     # FHIR R4 resource builders
│   ├── handler/
│   │   ├── handler.go       # HTTP handlers
│   │   ├── handler_cron.go  # Cron endpoints
│   │   └── router.go        # Route definitions
│   ├── middleware/
│   │   └── middleware.go    # Logger, CORS, Recovery
│   ├── web/
│   │   ├── engine.go        # Template engine
│   │   ├── handlers.go      # Web page handlers
│   │   ├── types.go         # Data structures
│   │   └── templates/       # 14 HTML templates
│   └── kyc/                 # (Planned module)
├── .env.example
├── go.mod
└── README.md
```

## Instalasi & Menjalankan

### 1. Copy & konfigurasi environment

```bash
cp .env.example .env
# Edit .env sesuai konfigurasi (lihat bagian Cron, Web, KYC)
```

### 2. Download dependencies

```bash
go mod tidy
```

### 3. Build

```bash
go build -o satu-sehat-go ./cmd/main.go
```

### 4. Run

```bash
./satu-sehat-go
# atau langsung:
go run ./cmd/main.go
```

Server akan berjalan di `http://localhost:8085` (default).

## Konfigurasi Environment (.env)

Lihat `.env.example` untuk semua variabel:
- Database: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
- Satu Sehat API: SATUSEHAT_ORG_ID, SATUSEHAT_CLIENT_ID, SATUSEHAT_SECRET_KEY, SATUSEHAT_AUTH_URL, SATUSEHAT_FHIR_URL
- Lokasi: SATUSEHAT_KELURAHAN, SATUSEHAT_KECAMATAN, SATUSEHAT_KABUPATEN, SATUSEHAT_PROPINSI, SATUSEHAT_KODEPOS, SATUSEHAT_LONGITUDE, SATUSEHAT_LATITUDE, SATUSEHAT_ZONA_WAKTU
- Referensi Fasilitas: SATUSEHAT_FARMASI, SATUSEHAT_LABORATORIUM, SATUSEHAT_RADIOLOGI, SATUSEHAT_PRAKTISI_APOTEK, SATUSEHAT_PRAKTISI_LAB, SATUSEHAT_PRAKTISI_RAD
- Info RS: HOSPITAL_PHONE, HOSPITAL_EMAIL, HOSPITAL_ADDRESS, HOSPITAL_CITY
- Server: LISTEN_ADDR
- Cron: CRON_ENABLED, CRON_JAM_MULAI, CRON_JAM_BERHENTI, CRON_CONCURRENCY, CRON_REQUEST_PER_MENIT, CRON_MAX_ERRORS, CRON_TANGGAL_DARI
- Web: WEB_DASHBOARD, WEB_PORT
- KYC: KYC_ENABLED
- Optional: SATUSEHAT_API_OPENAI

> Semua setting bisa di-override dari halaman Settings Web (disimpan di tabel mlite_settings).

## Web Dashboard

- Dashboard utama: statistik, progress, hutang (pending visits)
- Settings: konfigurasi semua variabel
- Response: tracking & DataTable
- Bulk: kirim massal per tanggal
- Mapping: praktisi, obat, lab, radiologi, lokasi, departemen
- Cron: monitor, logs, control, progress
- Praktisi: pencarian referensi NIK

## API Endpoints

### Auth
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/api/token` | Get access token |

### FHIR Resources
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/api/encounter/{no_rawat}` | Kirim Encounter |
| GET | `/api/condition/{no_rawat}` | Kirim Condition |
| GET | `/api/observation/{no_rawat}/{ttv}` | Kirim Observation (tensi/nadi/respirasi/suhu/spo2/gcs/kesadaran/berat/tinggi/perut) |
| GET | `/api/procedure/{no_rawat}` | Kirim Procedure |
| GET | `/api/medication/{no_rawat}/{tipe}` | Kirim Medication (request/dispense/statement) |
| GET | `/api/laboratory/{no_rawat}/{tipe}` | Kirim Laboratory (request/specimen/observation/diagnostic) |
| GET | `/api/radiology/{no_rawat}/{tipe}` | Kirim Radiology |
| GET | `/api/clinical-impression/{no_rawat}` | Kirim ClinicalImpression |
| GET | `/api/care-plan/{no_rawat}` | Kirim CarePlan |
| GET | `/api/allergy/{no_rawat}` | Kirim AllergyIntolerance |

### Referensi
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET/POST | `/api/praktisi?nik=...` | Cari Praktisi by NIK |
| GET/POST | `/api/pasien?nik=...` | Cari Pasien by NIK |
| GET | `/api/kfa?code=...` | Cari obat di KFA |

### Mapping
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/api/mapping/praktisi` | List mapping praktisi |
| POST | `/api/mapping/praktisi` | Simpan mapping praktisi |
| GET | `/api/mapping/obat` | List mapping obat |
| POST | `/api/mapping/obat` | Simpan mapping obat |
| GET | `/api/mapping/lab` | List mapping lab |
| POST | `/api/mapping/lab` | Simpan mapping lab |
| GET | `/api/mapping/rad` | List mapping radiologi |
| POST | `/api/mapping/rad` | Simpan mapping radiologi |
| GET | `/api/mapping/lokasi` | List mapping lokasi |
| POST | `/api/mapping/lokasi` | Simpan mapping lokasi |

### Lainnya
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/api/settings` | Get settings |
| POST | `/api/settings` | Save settings |
| GET | `/api/response?limit=100` | List encounter response |
| GET | `/api/forward-tanggal/{tanggal}` | List registrasi per tanggal |
| GET | `/health` | Health check |
| POST | `/api/cron/action` | Control cron (start/stop/reset/clearlog) |
| POST | `/api/cron/settings` | Update cron config |
| GET | `/api/cron/log?lines=100` | Get log |
| GET | `/api/cron/status` | Cron status & progress |

## Kompatibilitas Route PHP

Semua route dari PHP `Site.php` tersedia:
- `/satu-sehat/encounter/{no_rawat}`
- `/satu-sehat/condition/{no_rawat}`
- `/satu-sehat/observation/{no_rawat}/{ttv}`
- dll.

## Migrasi dari PHP

Module Go ini menggunakan database yang sama (`mlite`) dan tabel yang sama:
- `mlite_settings`
- `mlite_satu_sehat_response`
- `mlite_satu_sehat_lokasi`
- `mlite_satu_sehat_departemen`
- `mlite_satu_sehat_mapping_praktisi`
- `mlite_satu_sehat_mapping_obat`
- `mlite_satu_sehat_mapping_lab`
- `mlite_satu_sehat_mapping_rad`
- `reg_periksa`, `pasien`, `pegawai`, `poliklinik`, `bangsal`, `kamar`, `kamar_inap`
- `diagnosa_pasien`, `penyakit`, `prosedur_pasien`, `icd9`
- `pemeriksaan_ralan`, `pemeriksaan_ranap`

## Cron Scheduler & Progress

- Otomatis kirim data per tanggal (batch)
- Worker pool (concurrency), rate limiting, error handling
- Progress tracking: hutang, logs, statistik, last run
- Control via Web UI atau API

## Web Frontend

- 14 halaman HTML: dashboard, settings, mapping, response, bulk, cron, praktisi
- Template engine Go, AJAX DataTable, real-time monitoring

## Database Schema (Satu Sehat Tables)

Lihat tabel:
- `mlite_settings` (key-value config)
- `mlite_satu_sehat_response` (resource IDs per visit)
- `mlite_satu_sehat_mapping_praktisi` (dokter → practitioner)
- `mlite_satu_sehat_mapping_obat` (obat → KFA)
- `mlite_satu_sehat_mapping_lab` (lab → LOINC)
- `mlite_satu_sehat_mapping_rad` (rad → code)
- `mlite_satu_sehat_lokasi` (lokasi → UUID)
- `mlite_satu_sehat_departemen` (departemen → UUID)

## FHIR Resource Builders

- Encounter, Condition, Observation (LOINC/SNOMED-CT), Procedure (ICD-9), Medication (KFA), Lab (LOINC), Radiology, ClinicalImpression, CarePlan, AllergyIntolerance, DiagnosticReport, ServiceRequest, Specimen

## Rate Limiting & Error Handling

- Token bucket algorithm, configurable request/minute
- Validasi NIK, dependency chain, auto-promotion diagnosis

## Deployment

- Build: `go build -o satu-sehat-go ./cmd/main.go`
- Run: `./satu-sehat-go` atau `go run ./cmd/main.go`
- Web UI: `http://localhost:8085`
- Semua setting bisa diubah dari Web Dashboard

