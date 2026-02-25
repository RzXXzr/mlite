# Dokumentasi Fitur Alergi Pasien

## Deskripsi
Fitur form alergi pasien yang terintegrasi dengan nomor rekam medis (no_rkm_medis). Satu pasien hanya memiliki satu row data alergi, dapat diedit jika ditemukan alergi baru.

## Kode PCare Compliant
### Alergi Makanan
- 00 = Tidak Ada
- 01 = Seafood
- 02 = Gandum
- 03 = Susu Sapi
- 04 = Kacang-Kacangan
- 05 = Makanan Lain

### Alergi Udara
- 00 = Tidak Ada
- 01 = Udara Panas
- 02 = Udara Dingin
- 03 = Udara Kotor

### Alergi Obat
- 00 = Tidak Ada
- 01 = Antibiotik
- 02 = Antiinflamasi
- 03 = Non Steroid
- 04 = Aspirin
- 05 = Kortikosteroid
- 06 = Insulin
- 07 = Obat-Obatan Lain

---

## File yang Dibuat/Diubah

### 1. Database
**File SQL:** `plugins/pemeriksaan_ralan/sql/alergi_pasien.sql`
```sql
CREATE TABLE IF NOT EXISTS `alergi_pasien` (
  `no_rkm_medis` varchar(15) NOT NULL,
  `alergi_makanan` varchar(2) DEFAULT '00',
  `alergi_makanan_lainnya` varchar(100) DEFAULT NULL,
  `alergi_udara` varchar(2) DEFAULT '00',
  `alergi_udara_lainnya` varchar(100) DEFAULT NULL,
  `alergi_obat` varchar(2) DEFAULT '00',
  `alergi_obat_lainnya` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `nip_input` varchar(20) DEFAULT NULL,
  `tgl_update` datetime DEFAULT NULL,
  `nip_update` varchar(20) DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

---

## Modul: pemeriksaan_ralan

### 2. Form View
**File:** `plugins/pemeriksaan_ralan/view/admin/form.alergi.html`
- Form dropdown dengan 3 kategori (Makanan, Udara, Obat)
- Hidden text input untuk "Lainnya" yang muncul jika dipilih
- Tombol Simpan dengan AJAX
- Function `loadAlergiPasien(no_rkm_medis)` untuk load data

### 3. Backend AJAX Handler
**File:** `plugins/pemeriksaan_ralan/Admin.php`

**Method Ditambahkan:**
```php
public function postGetAlergi()
// GET data alergi berdasarkan no_rkm_medis
// Return: JSON {status, data/message}

public function postSaveAlergi()
// INSERT atau UPDATE data alergi
// Return: JSON {status, message}
```

### 4. Integrasi View
**File:** `plugins/pemeriksaan_ralan/view/admin/manage.html`
- Ditambahkan include form.alergi.html di dalam div `col-md-9 col-md-offset-3`

**File:** `plugins/pemeriksaan_ralan/view/admin/display.html`
- Ditambahkan pemanggilan `loadAlergiPasien(no_rkm_medis)` saat pasien dipilih
- Show `#form_alergi_container` saat form pemeriksaan ditampilkan

**File:** `plugins/pemeriksaan_ralan/js/admin/pemeriksaan_ralan.js`
- Ditambahkan hide `#form_alergi_container` saat kembali ke display

---

## Modul: dokter_ralan

### 5. Form View (Copy)
**File:** `plugins/dokter_ralan/view/admin/form.alergi.html`
- Sama dengan pemeriksaan_ralan, kecuali URL endpoint mengarah ke `dokter_ralan`

### 6. Backend AJAX Handler
**File:** `plugins/dokter_ralan/Admin.php`

**Method Ditambahkan:**
```php
public function postGetAlergi()
// GET data alergi berdasarkan no_rkm_medis

public function postSaveAlergi()
// INSERT atau UPDATE data alergi
```

### 7. Integrasi View
**File:** `plugins/dokter_ralan/view/admin/manage.html`
- Ditambahkan include form.alergi.html di dalam div `col-md-9 col-md-offset-3`

**File:** `plugins/dokter_ralan/view/admin/display.html`
- Ditambahkan pemanggilan `loadAlergiPasien(no_rkm_medis)` di click handler `#soap`
- Show `#form_alergi_container` saat form SOAP ditampilkan

**File:** `plugins/dokter_ralan/js/admin/dokter_ralan.js`
- Ditambahkan hide `#form_alergi_container` di tombol `#selesai` dan `#selesai_kontrol`

---

## Modul: pasien (Display Riwayat Perawatan)

### 8. Backend Query
**File:** `plugins/pasien/Admin.php`
- Method `_getRiwayatData()` ditambahkan query untuk ambil data alergi
- Mapping kode ke nama (array `$mapMakanan`, `$mapUdara`, `$mapObat`)
- Output field: `nm_alergi_makanan`, `nm_alergi_udara`, `nm_alergi_obat`

### 9. View Riwayat
**File:** `plugins/pasien/view/admin/riwayat.perawatan.html`
- Ditambahkan 3 row tabel untuk display alergi (background kuning `#fff3cd`)

---

## Cara Kerja
1. Saat pasien dipilih di pemeriksaan_ralan atau dokter_ralan
2. Form alergi ditampilkan di atas form SOAP
3. Data alergi existing akan di-load via AJAX
4. User bisa ubah dropdown dan simpan
5. Jika pasien belum ada data, akan INSERT baru
6. Jika sudah ada, akan UPDATE data existing
7. Data alergi ditampilkan di Riwayat Perawatan Pasien



-- SQL untuk membuat tabel alergi_pasien
-- Tabel ini menyimpan data alergi per pasien berdasarkan referensi PCare
-- Satu pasien satu row, bisa diedit jika ditemukan alergi baru

CREATE TABLE IF NOT EXISTS `alergi_pasien` (
  `no_rkm_medis` varchar(15) NOT NULL COMMENT 'Nomor Rekam Medis Pasien',
  `alergi_makanan` varchar(5) DEFAULT '00' COMMENT 'Kode alergi makanan (00=Tidak Ada, 01=Seafood, 02=Gandum, 03=Susu Sapi, 04=Kacang-Kacangan, 05=Makanan Lain)',
  `alergi_makanan_lainnya` varchar(255) DEFAULT NULL COMMENT 'Alergi makanan lain jika dipilih 05',
  `alergi_udara` varchar(5) DEFAULT '00' COMMENT 'Kode alergi udara (00=Tidak Ada, 01=Udara Panas, 02=Udara Dingin, 03=Udara Kotor)',
  `alergi_udara_lainnya` varchar(255) DEFAULT NULL COMMENT 'Alergi udara lain jika ada',
  `alergi_obat` varchar(5) DEFAULT '00' COMMENT 'Kode alergi obat (00=Tidak Ada, 01=Antibiotik, 02=Antiinflamasi, 03=Non Steroid, 04=Aspirin, 05=Kortikosteroid, 06=Insulin, 07=Obat-Obatan Lain)',
  `alergi_obat_lainnya` varchar(255) DEFAULT NULL COMMENT 'Alergi obat lain jika dipilih 07',
  `tgl_input` datetime DEFAULT NULL COMMENT 'Tanggal input alergi',
  `tgl_update` datetime DEFAULT NULL COMMENT 'Tanggal update alergi',
  `nip_input` varchar(20) DEFAULT NULL COMMENT 'NIP petugas yang input',
  `nip_update` varchar(20) DEFAULT NULL COMMENT 'NIP petugas yang update',
  PRIMARY KEY (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Data alergi pasien berdasarkan referensi PCare';

-- Reference data untuk dropdown alergi (diambil dari PCare):
-- 
-- ALERGI MAKANAN:
-- 00 : Tidak Ada
-- 01 : Seafood
-- 02 : Gandum
-- 03 : Susu Sapi
-- 04 : Kacang-Kacangan
-- 05 : Makanan Lain
--
-- ALERGI UDARA:
-- 00 : Tidak Ada
-- 01 : Udara Panas
-- 02 : Udara Dingin
-- 03 : Udara Kotor
--
-- ALERGI OBAT:
-- 00 : Tidak Ada
-- 01 : Antibiotik
-- 02 : Antiinflamasi
-- 03 : Non Steroid
-- 04 : Aspirin
-- 05 : Kortikosteroid
-- 06 : Insulin
-- 07 : Obat-Obatan Lain


