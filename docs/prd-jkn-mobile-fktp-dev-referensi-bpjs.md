# PRD — Checklist penerimaan jadwal Mobile JKN dari referensi BPJS

Status: diimplementasikan untuk `jkn_mobile_fktp_dev`  
Tanggal: 27 September 2026  
Ruang lingkup: modul Dev; tidak mengubah jadwal BPJS, tabel master jadwal mLITE, atau modul produksi.

## 1. Masalah

BPJS dapat menampilkan lebih dari satu dokter pada poli dan shift yang sama, sedangkan klinik hanya menerima salah satunya. Mapping poli saja tidak cukup untuk menentukan dokter yang benar. Poli lokal `RUJ`/`RUJGG` juga dapat berbagi kode BPJS dengan poli rawat jalan, padahal keduanya khusus pembuatan rujukan dan tidak boleh menjadi tujuan pendaftaran.

Implementasi lama memiliki masalah berikut:

- batas `booking_open_days` pasien ikut memotong pemeriksaan referensi admin 7 hari;
- Generate 1 Minggu menyimpan per tanggal dan dapat menghasilkan minggu parsial;
- tombol Muat Referensi dapat membuka URL literal `%7B...` akibat ekspresi URL template tidak terkompilasi, lalu halaman inti menampilkan warning `$module`;
- pilihan dokter sebelumnya cenderung dicentang otomatis, bukan keputusan eksplisit admin;
- aktivasi berbasis file dan umur 24 jam tidak cocok sebagai konfigurasi operasional;
- status/sisa antrean masih dapat membaca sumber jadwal lama;
- jarak vertikal panel, alert, form, dan navigasi terlalu besar;
- saat dokter yang dipilih pasien tidak aktif, pesan tidak menyebut dokter pengganti yang benar.

## 2. Tujuan dan bukan tujuan

Tujuannya adalah menyediakan alur: admin membaca referensi BPJS, memilih poli rawat jalan lokal, mencentang dokter/jam yang benar-benar menerima pendaftaran, lalu menyimpan keputusan tersebut secara persisten untuk dipakai endpoint Dev.

Bukan tujuan fitur ini:

- mengubah daftar dokter atau jadwal pada aplikasi Mobile JKN/BPJS;
- mengubah tabel `jadwal`, `poliklinik`, `dokter`, atau tabel mapping bersama;
- mengaktifkan poli rujukan sebagai tujuan rawat jalan;
- memindahkan atau membatalkan booking yang sudah ada saat checklist berubah;
- mengalihkan booking diam-diam ke dokter lain.

## 3. Sumber referensi

Kredensial dibaca dari pengaturan PCare: Consumer ID, Consumer Secret, dan User Key Antrol. URL Antrol dapat dioverride pada modul Dev, tetapi hanya host HTTPS BPJS yang diizinkan.

Kontrak referensi:

1. `GET ref/poli/tanggal/{tanggal}`
2. `GET ref/dokter/kodepoli/{kodepoli}/tanggal/{tanggal}`

Field yang dipercaya dari response BPJS adalah `kodepoli`, `namapoli`, `kodedokter`, `namadokter`, `jampraktek`, dan `kapasitas`. Response plain maupun AES/LZString didukung. Metadata sukses `1` dan `200` didukung. Referensi kontrak: [BPJS webservice catalog — Antrean FKTP](https://github.com/bastomiadi/bpjs_webservice_catalog/blob/main/docs/antreanfktp.txt).

## 4. Alur admin

### 4.0 Poli & Jadwal Online (editor lokal mingguan)

1. Halaman utama jadwal membaca tabel `mlite_jkn_mobile_fktp_dev_schedule` untuk tujuh tanggal mulai dari tanggal yang dipilih. Membuka atau berpindah minggu tidak memanggil API BPJS.
2. Grid hanya menampilkan jadwal yang sudah diaktifkan melalui penyimpanan referensi sebelumnya. Baris dipisahkan berdasarkan kombinasi poli dan shift **Pagi/Sore**, sedangkan kolom berisi tujuh tanggal.
3. Admin dapat mengubah kapasitas lokal setiap slot aktif dengan nilai 1–999. Perubahan hanya memperbarui kolom `quota`; identitas referensi, dokter, poli, tanggal, shift, dan jam praktik tidak dapat dikirim ulang dari browser.
4. Seluruh perubahan kapasitas dalam satu form divalidasi dan disimpan atomik. ID palsu, jadwal nonaktif, atau ID di luar periode tujuh hari membatalkan semua perubahan.
5. Akses BPJS hanya dilakukan ketika admin secara eksplisit membuka **Generate 1 Minggu** dan menekan Muat Referensi.

### 4.1 Per tanggal

1. Admin membuka **Poli & Jadwal Online** dan memilih tanggal hari ini sampai 90 hari ke depan.
2. Admin menekan **Ambil / Perbarui Referensi BPJS**. Ini hanya membaca data BPJS dan tidak mengubah konfigurasi aktif.
3. Admin memilih tepat satu tujuan poli lokal rawat jalan untuk setiap kode BPJS yang dipakai. `RUJ`, `RUJGG`, poli nonaktif, dan mapping ambigu tidak dapat dipilih.
4. Sistem menampilkan semua kandidat dokter/jam/kapasitas dari BPJS. Kandidat tanpa mapping dokter lokal unik, kuota nol, atau poli yang tidak dipilih dinonaktifkan.
5. Admin mencentang dokter/jam yang diterima. Tidak ada auto-check untuk kandidat baru.
6. Admin menekan **Simpan checklist tanggal ini**. Kandidat yang tidak dicentang tetap disimpan sebagai snapshot dengan `is_active=0` agar alasan penolakan dan dokter pengganti dapat ditentukan.

### 4.2 Generate 1 Minggu

1. Admin memilih tanggal mulai dan poli rawat jalan.
2. Tanggal mulai dibatasi hari ini sampai +84 hari, sehingga seluruh tujuh tanggal tetap berada dalam horizon administrasi 90 hari.
3. Sistem mengambil tepat tujuh tanggal, tanpa memperhatikan `booking_open_days` pasien.
4. Jika satu tanggal mengalami error teknis/autentikasi, tombol Simpan dinonaktifkan. Khusus endpoint referensi jadwal, metadata BPJS `201` diperlakukan sebagai respons lengkap tanpa jadwal (Minggu, tanggal merah, atau hari libur)—bukan kegagalan minggu. Pesan autentikasi/security yang eksplisit tetap fatal.
5. Grid mengurutkan kandidat per sel menjadi kelompok **Pagi** dan **Sore**, serta menyediakan filter **Semua / Pagi / Sore**. Checklist tersimpan tetap dipertahankan saat filter berubah; kandidat baru tidak dicentang otomatis. Memilih dokter lain pada poli/tanggal/shift yang sama otomatis melepas pilihan sebelumnya, sementara aksi **Hapus semua pilihan** tetap tersedia.
6. Ketujuh tanggal disimpan dalam satu transaksi database. Satu pilihan palsu, konflik, snapshot kedaluwarsa, atau perubahan sumber membatalkan seluruh batch.

## 5. Pemisahan horizon

`booking_open_days` membatasi tanggal untuk daftar status dan pembuatan booking baru. Sisa antrean dan pembatalan booking yang sudah ada tetap dapat diakses walaupun admin kemudian memperkecil horizon. Nilai ini tidak membatasi admin ketika mengambil/generate referensi.

- Horizon pasien: hari ini sampai `booking_open_days` (0–90).
- Horizon admin per tanggal: hari ini sampai +90 hari.
- Horizon admin satu minggu: tanggal mulai hari ini sampai +84 hari.

Dengan konfigurasi booking 2 hari, admin tetap dapat memuat dan menyimpan referensi tujuh hari; pasien tetap ditolak jika mencoba booking melewati dua hari.

## 6. Aturan bisnis

- Pagi adalah slot dengan jam mulai `< 12:00`; sore adalah jam mulai `>= 12:00`.
- Hanya satu dokter aktif per poli lokal, tanggal, dan shift.
- Interval aktif yang bertumpang tindih ditolak; interval berdampingan diperbolehkan.
- Dokter yang sama tidak boleh aktif pada dua poli dengan waktu bertumpang tindih.
- Dokter/jam/kuota yang dikirim browser tidak dipercaya; server memakai kandidat dalam snapshot session.
- Kode dokter dan `jampraktek` pada booking harus sama persis dengan checklist aktif.
- Tanggal tanpa checklist aktif tertutup untuk pendaftaran baru.
- Response referensi dokter “jadwal tidak ditemukan/tidak tersedia/libur” disimpan sebagai penanda tutup per tanggal/poli. Booking pada tanggal itu mendapat alasan hari libur/tanggal merah atau jadwal belum tersedia.
- Menghapus semua centang menutup tanggal tersebut, tetapi tidak membatalkan booking lama.
- Booking ulang setelah status `Batal` memakai kembali `no_rawat`/ID kunjungan lama dan menghasilkan `no_reg`/nomor antrean baru.

Jika pasien memilih kandidat BPJS yang ada tetapi tidak diaktifkan lokal, booking ditolak—tidak dialihkan otomatis—dengan pesan:

> Jadwal dokter yang dipilih tidak diaktifkan. Pendaftaran online pada shift ini dilayani oleh {nama dokter aktif} ({jam aktif}). Silakan pilih dokter tersebut di Mobile JKN.

Jika tidak ada dokter aktif pada shift itu, pesan menyatakan jadwal tidak menerima pendaftaran online.

## 7. Penyimpanan

Tabel baru: `mlite_jkn_mobile_fktp_dev_schedule`.

| Kelompok | Kolom utama |
| --- | --- |
| Identitas | `service_date`, `candidate_id` |
| Referensi BPJS | `kodepoli`, `namapoli`, `kodedokter`, `namadokter`, `jam_mulai`, `jam_selesai`, `quota` |
| Tujuan lokal | `kd_poli`, `kd_dokter`, `shift_name` |
| Keputusan | `is_active` |
| Audit sumber | `source_url`, `source_fingerprint`, `source_fetched_at`, `saved_at` |

Unik: `(service_date, candidate_id)`. Tabel dibuat saat instalasi baru dan secara idempoten ketika admin modul lama dibuka. File `tmp/jkn_mobile_fktp_dev_schedule.php` bukan lagi sumber runtime penerimaan; dipertahankan hanya untuk kompatibilitas/rollback manual dan tidak dimigrasikan diam-diam.

Checklist tabel tidak memiliki kedaluwarsa 24 jam. Snapshot session untuk menyimpan formulir berlaku 30 menit. Perubahan mapping setelah penyimpanan diverifikasi ulang saat runtime dan gagal tertutup jika tidak lagi unik/aktif.

## 8. Keamanan dan kegagalan

- CSRF dan sentinel `form_complete` wajib pada fetch/save.
- Snapshot session terikat fingerprint URL dan kredensial tanpa menampilkan secret.
- Maksimum 20 poli dan 300 kandidat per tanggal; TLS diverifikasi; redirect tidak diikuti.
- ID kandidat palsu, response cacat, jadwal duplikat kontradiktif, mapping ambigu, kuota nol, dan konflik shift ditolak.
- Klasifikasi hari tutup hanya berlaku pada endpoint referensi poli/dokter. Metadata `201`, termasuk pesan `No Content`, adalah respons lengkap tanpa jadwal. Pesan yang menyebut consumer, secret, signature, user key, otorisasi, akses, atau kredensial tidak pernah diklasifikasikan sebagai libur; HTTP, dekripsi, dan format response juga tetap fatal.
- Semua SQL data memakai prepared statement; penyimpanan minggu memakai transaksi.
- Error database/BPJS tidak membocorkan kredensial atau raw response ke pengguna.
- URL aksi dihitung controller, bukan ekspresi `url()` inline setelah conditional template, untuk mencegah route `%7B` dan warning `$module`.
- Log transaksi tetap tidak menyimpan token, password, NIK, nomor kartu, atau body identitas.

## 9. UI/UX

- Enam tab tetap tersedia: Pengaturan, Poli & Jadwal Online, Generate 1 Minggu, Mapping Poli, Mapping Dokter, Log Transaksi.
- Margin panel/alert/form dipadatkan menjadi 8–12 px dan panel body 14–16 px.
- Checklist poli memakai grid responsif; jadwal minggu memakai tabel horizontal responsif.
- Tombol Simpan selalu disabled bila referensi belum lengkap atau kedaluwarsa.
- Baris tersimpan dan kandidat yang tidak bisa dipilih tetap terlihat beserta alasan.
- Link Log Transaksi diarahkan ke route `logs` yang benar.

## 10. Penerimaan/UAT

1. Dengan `booking_open_days=2`, Generate 1 Minggu memuat tujuh hari; API pasien hari ke-3 tetap ditolak oleh batas booking.
2. Muat Referensi tidak pernah membuka `%7B`, tidak menghasilkan warning `$module`, dan kembali ke route modul yang benar.
3. Admin memilih Dokter A pagi dan Dokter B sore; status hanya menampilkan keduanya.
4. Booking Dokter C pagi ditolak dan pesan menyebut Dokter A beserta jamnya.
5. RUJ/RUJGG tidak dapat dicentang melalui UI maupun POST manipulasi.
6. Dua dokter pada poli/shift yang sama atau interval bertumpang tindih membuat penyimpanan gagal tanpa mengubah konfigurasi sebelumnya.
7. Kegagalan tanggal ke-7 saat generate tidak menyimpan tanggal 1–6.
8. Hari Minggu/tanggal merah dengan metadata `201`, termasuk `No Content`, tampil sebagai **Tutup/libur**, dihitung lengkap, tetap memungkinkan penyimpanan tujuh hari, dan tidak menyuruh admin mengganti kredensial.
9. Refresh referensi tidak mengubah checklist sebelum admin menekan Simpan.
10. Checklist tersimpan tetap berlaku lewat 24 jam; snapshot form berumur 30 menit ditolak.
11. Mapping dokter/poli yang berubah atau ambigu menutup slot terkait sampai admin memperbaiki mapping dan menyimpan ulang.
12. Booking batal lalu booking ulang mempertahankan `no_rawat` dan menghasilkan nomor antrean baru.
13. Semua request sukses/ditolak/error terlihat di Log Transaksi tanpa data sensitif.
14. Filter Pagi hanya menampilkan slot mulai sebelum 12:00 dan filter Sore hanya menampilkan slot mulai 12:00 atau sesudahnya; perubahan filter tidak mengubah checkbox yang sudah dipilih.
15. Membuka **Poli & Jadwal Online**, mengganti periode, dan menyimpan kapasitas tidak menghasilkan request ke API BPJS. Jadwal yang pernah disimpan tampil sebagai grid tujuh hari dengan baris poli-pagi/poli-sore.
16. Kapasitas aktif dapat diubah 1–999; manipulasi ID jadwal, kapasitas nol, kandidat nonaktif, atau jadwal di luar periode ditolak tanpa perubahan parsial.

Pengujian offline:

```bash
php plugins/jkn_mobile_fktp_dev/tests/schedule_repository_test.php
php plugins/jkn_mobile_fktp_dev/tests/bpjs_reference_test.php
```

Pengujian live tetap dilakukan admin dengan kredensial fasilitas dan koleksi Postman Dev.
