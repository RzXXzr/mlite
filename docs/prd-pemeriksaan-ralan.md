# PRD: Perapihan Modul Pemeriksaan Rawat Jalan Paramedis

| Atribut | Nilai |
| --- | --- |
| Status | Implementasi versi dev; perlu uji alur pengguna sebelum rilis |
| Modul implementasi | `plugins/pemeriksaan_ralan_dev` (Pemeriksaan Paramedis Dev) |
| Modul berjalan / referensi perilaku | `plugins/pemeriksaan_ralan` |
| Modul asal (referensi) | `plugins/dokter_ralan` |
| Pengguna utama | Paramedis/perawat rawat jalan |
| Tanggal | 26 September 2026 |

## 1. Ringkasan

`pemeriksaan_ralan` harus menjadi modul khusus untuk pencatatan pemeriksaan awal pasien rawat jalan oleh paramedis: tanda-tanda vital (TTV), anamnesa/keluhan awal, dan data alergi. Dokter kemudian menggunakan data tersebut sebagai masukan untuk pemeriksaan, diagnosis, terapi, resep, tindakan, dan dokumen medis lanjutan pada modul yang memang menjadi kewenangannya.

Modul saat ini berasal dari salinan `dokter_ralan`. Karena itu, ia masih memuat alur dan kode dokter yang tidak mendukung tujuan tersebut. Perapihan harus menyederhanakan modul tanpa mengubah atau menghapus rekam pemeriksaan yang sudah tersimpan pada tabel SIMRS.

## 2. Masalah dan bukti audit

Audit statis pada 26 September 2026 menemukan hal berikut.

- `Admin.php` memiliki 2.140 baris dan 52 handler publik; sebagian besar bukan alur pemeriksaan awal.
- JavaScript modul memiliki 1.731 baris, termasuk pencarian/simpan layanan, resep, racikan, laboratorium, radiologi, ICD-10/ICD-9, kontrol, odontogram, dan resume.
- Template `form.soap.html` masih membawa blok ICD-10/ICD-9, modal e-resep, modal odontogram, dan script pendukung walaupun komentar antarmuka menyebut fitur itu dinonaktifkan.
- Sisa identitas modul asal masih ada, misalnya judul pengaturan **"Pengaturan Modul Dokter Ralan"**, selector CSS `.dokter_ralan_view`, dan berkas CSS duplikat `css/admin/dokter_ralan.css`.
- Folder turunan masih berisi template resep, tindakan, lab/radiologi, kontrol BPJS, odontogram/OHIS, resume medis, serta surat rujukan/sehat/sakit.

Temuan ini adalah indikator ruang lingkup teknis, bukan dasar untuk langsung menghapus data atau fitur lintas modul.

## 3. Tujuan

1. Menyediakan alur cepat dan jelas bagi paramedis untuk memilih pasien rawat jalan, mengisi TTV dan anamnesa awal, lalu menyimpannya ke rekam medis.
2. Memastikan dokter dapat melihat data awal tersebut beserta waktu dan petugas pencatatnya.
3. Menghilangkan antarmuka, endpoint, aset, dan dependensi yang khusus untuk kewenangan dokter atau pelayanan lanjutan.
4. Menyeragamkan seluruh identitas teknis dan teks antarmuka menjadi `pemeriksaan_ralan` / **Pemeriksaan Awal Paramedis**.
5. Mempertahankan kompatibilitas data pemeriksaan ralan yang sudah ada.

## 4. Di luar ruang lingkup

Hal berikut tidak dibangun atau dioperasikan oleh modul ini:

- diagnosis dan prosedur ICD-10/ICD-9;
- resep reguler/racikan, e-resep, salin resep, stok obat, dan aturan pakai;
- tindakan medis, permintaan laboratorium, dan permintaan radiologi;
- kontrol BPJS, SEP, dan integrasi BPJS/PCare khusus layanan dokter;
- odontogram, OHIS, resume pasien, penilaian medis rawat jalan;
- surat rujukan, surat sehat, dan surat sakit;
Fitur-fitur tersebut tetap menjadi tanggung jawab modul dokter/layanan terkait. Modul ini tidak boleh menjadi jalur alternatif untuk membuat atau mengubah data tersebut.

Pembatasan di atas berlaku untuk pembuatan/perubahan data. Pembacaan diagnosis, prosedur, obat, SOAP lengkap, dan rawat inap dalam riwayat pasien diizinkan sebagai konteks pemeriksaan awal (FR-04). Rekam medis lengkap tetap menggunakan halaman milik modul `pasien`.

Pengecekan kepesertaan BPJS hanya-baca juga termasuk ruang lingkup (FR-08): provider/FKTP, nomor kartu, jenis/status peserta, PRB, dan Prolanis. Ini tidak membuka alur pendaftaran, kunjungan, rujukan, atau mutasi PCare.

## 5. Pengguna dan hak akses

| Peran | Kebutuhan |
| --- | --- |
| Paramedis/perawat | Melihat daftar pasien sesuai akses poli, mengisi dan menyimpan pemeriksaan awal, melihat riwayat yang relevan, serta mengubah/menghapus catatan miliknya sesuai kebijakan institusi. |
| Dokter | Membaca hasil pemeriksaan awal melalui rekam medis/modul dokter; tidak memakai modul ini untuk diagnosis, terapi, atau resep. |
| Administrator | Mengatur akses poli dan kebijakan status kunjungan; dapat melakukan koreksi sesuai hak admin dan jejak audit. |

Target otorisasi adalah akses berbasis akun pegawai/paramedis dan cakupan poli (*CAP*), bukan berdasarkan kecocokan `kd_dokter` dengan username pengguna. Administrator tetap memiliki akses lintas poli sesuai kebijakan sistem.

## 6. Alur pengguna target

```text
Daftar pasien kunjungan rawat jalan
        -> pilih pasien
        -> tampilkan alergi dan riwayat pemeriksaan yang relevan
        -> isi TTV + anamnesa awal
        -> validasi
        -> simpan ke pemeriksaan_ralan dengan NIP petugas dan waktu
        -> tandai kunjungan selesai (hanya bila pengaturan mengizinkan)
        -> dokter membaca hasil pada rekam medis/alur dokter
```

## 7. Kebutuhan fungsional

### FR-01 — Daftar pasien

- Tampilkan kunjungan rawat jalan pada rentang tanggal yang dipilih, tidak termasuk poli IGD seperti perilaku modul saat ini.
- Filter minimal: tanggal/rentang tanggal dan status kunjungan (belum/sudah diperiksa).
- Batasi daftar paramedis non-admin ke poli yang diizinkan untuk akunnya.
- Kolom daftar minimal: nomor RM, nama pasien, nomor rawat/kunjungan, antrean, poli, dokter tujuan, penjamin, tanggal kunjungan, dan status.
- Jangan tampilkan tombol atau aksi dokter dari daftar pasien.

### FR-02 — Form pemeriksaan awal

- Setelah paramedis memilih pasien, tampilkan identitas pasien yang hanya-baca dan waktu pencatatan.
- Form wajib mendukung: tensi, suhu, nadi, frekuensi napas, tinggi badan, berat badan, kesadaran, SpO2, GCS, lingkar perut, alergi, dan keluhan/anamnesa awal.
- Kolom anamnesa awal disimpan sebagai `keluhan`; temuan obyektif awal dapat disimpan sebagai `pemeriksaan` bila diperlukan institusi.
- Nilai wajib tidak boleh kosong, tanda `-`, atau format yang tidak valid. Validasi wajib dijalankan di server; validasi JavaScript hanya untuk umpan balik cepat.
- Kolom Assessment, Plan, Instruksi, dan Evaluasi tidak ditampilkan sebagai input paramedis pada versi target. Data historis pada kolom tersebut tidak dihapus.
- TTV harus tetap terlihat dalam mode antarmuka `simple`; mode tampilan tidak boleh membuat data wajib menjadi tersembunyi.

### FR-03 — Alergi pasien

- Tampilkan dan kelola data alergi makanan, udara, dan obat berdasarkan nomor RM.
- Pilihan "lainnya" wajib menyediakan catatan penjelas.
- Ringkasan alergi otomatis tersedia pada field alergi pemeriksaan awal, namun pengguna dapat memverifikasi isinya sebelum simpan.
- Simpan siapa dan kapan data alergi dibuat atau diperbarui.

### FR-04 — Riwayat kunjungan aktif dan semua kunjungan pasien

- Panel samping form menampilkan catatan pada kunjungan aktif, dengan tanggal/jam, petugas, TTV, dan anamnesa. Edit/hapus tetap mengikuti kepemilikan catatan dan pengecualian administrator.
- Form dan riwayat berada dalam satu halaman pemeriksaan. Pada desktop, form di kiri dan riwayat di kanan selalu terlihat bersamaan; membuka kunjungan, pagination, dan salin tidak menyembunyikan form atau mengganti tampilan halaman.
- Riwayat dimuat otomatis saat pasien dipilih. Panel kanan memuat catatan kunjungan aktif (dapat dilipat) serta semua kunjungan; kunjungan sebelumnya yang paling baru pada halaman tersebut otomatis dibuka. Panel riwayat memiliki scroll sendiri agar form tetap mudah diakses.
- Pada tablet/ponsel, kedua bagian tersusun vertikal dengan tautan jangkar Form/Riwayat pada halaman yang sama. Tidak ada tombol membuka halaman riwayat terpisah atau kembali ke form.
- Riwayat mencakup seluruh kunjungan pada nomor RM yang sama, termasuk lintas poli, IGD, rawat inap, dan kunjungan tanpa catatan. Urutan terbaru dahulu berdasarkan tanggal, jam, lalu nomor rawat. Tampilkan 10 kunjungan per halaman; seluruh halaman bisa diakses, bukan dibatasi pada 5/10 kunjungan terakhir.
- Identitas kunjungan: nomor rawat, tanggal/jam registrasi, jenis layanan, poli, dokter, penjamin, status kunjungan, serta penanda kunjungan aktif.
- Rincian dimuat saat kunjungan dibuka: semua catatan `pemeriksaan_ralan` dan `pemeriksaan_ranap`, TTV, alergi historis, SOAP lengkap, instruksi/evaluasi, petugas, diagnosis ICD-10, prosedur ICD-9, serta obat reguler dari seluruh resep kunjungan tersebut.
- Data diagnosis, prosedur, obat, dan rawat inap hanya-baca. Tidak ada aksi membuat/mengubah diagnosis, resep, atau layanan dari panel ini.
- Tautan **Rekam medis lengkap** membuka halaman `/pasien/riwayatperawatan/{no_rkm_medis}` di tab baru, sama seperti akses rekam medis versi lama. Rincian layanan, racikan, penunjang, dan dokumen lain mengikuti cakupan serta hak akses modul `pasien`; tidak diduplikasi pada panel ringkas dev.
- Endpoint riwayat mengotorisasi kunjungan aktif dengan CAP poli. Nomor RM diturunkan server dari kunjungan aktif, bukan dipercayai dari request. Akses lintas poli untuk pasien yang sama hanya berlaku pada pembacaan riwayat; tidak memperluas hak mutasi.
- Endpoint detail wajib memverifikasi bahwa nomor rawat sumber milik pasien yang sama. Kunjungan pasien lain ditolak walaupun nomor rawatnya diketahui.
- UI menyediakan loading, kondisi kosong, pagination, serta tombol coba lagi saat gagal. Respons lama diabaikan saat pengguna berganti pasien/halaman.

### FR-07 — Salin data pemeriksaan sebelumnya

- Setiap catatan rawat jalan pada rincian riwayat menyediakan tombol **Salin ke form**. Catatan rawat inap hanya ditampilkan untuk referensi.
- Pertahankan perilaku versi lama: hanya isi field tujuan yang kosong atau berisi `-`; nilai sumber kosong/`-` dilewati. Nilai yang sudah diisi pengguna tidak ditimpa.
- Daftar field yang boleh disalin: `tensi`, `suhu_tubuh`, `nadi`, `respirasi`, `spo2`, `gcs`, `kesadaran`, `tinggi`, `berat`, `lingkar_perut`, `keluhan`, `pemeriksaan`.
- Alergi tetap berasal dari profil pasien terbaru (`alergi_pasien`). Alergi historis tidak menimpa profil/ringkasan aktif. Assessment/Plan/Instruksi/Evaluasi, diagnosis, prosedur, dan resep tidak disalin ke form paramedis.
- Penyalinan menghasilkan draf catatan baru pada kunjungan aktif. Identitas edit (`original_tgl_perawatan`, `original_jam_rawat`) dikosongkan ketika ada field yang berhasil disalin. Nomor rawat sumber, NIP sumber, dan waktu sumber tidak menjadi identitas catatan tujuan.
- Tampilkan sumber nomor rawat, tanggal/jam catatan, jumlah kolom tersalin, penanda kolom, dan pesan untuk memverifikasi TTV sesuai pengukuran saat ini. Jika tidak ada field yang dapat disalin, beri informasi tanpa mengubah mode form.
- Salin memberi umpan balik langsung pada tombol dan status draf, tanpa menutup riwayat maupun memindahkan posisi scroll pengguna. Form dan riwayat tetap tersedia untuk dibandingkan.
- Salin tidak menyimpan otomatis. Pengguna memverifikasi/melengkapi isian lalu menekan **Simpan pemeriksaan awal**; server menerapkan validasi yang sama, dengan NIP sesi dan waktu server untuk catatan baru.
- Penanda sumber merupakan informasi draf di browser, bukan audit trail permanen. Tidak ada migrasi skema pada pekerjaan ini.

### FR-08 — Identitas pasien dan kepesertaan BPJS pada header

- Header bersama form/riwayat menampilkan nama, nomor RM, poli, umur saat ini, tanggal lahir, golongan darah, penjamin kunjungan, dan nomor kartu peserta.
- Identitas dibaca dari tabel `pasien`; penjamin berasal dari `reg_periksa.kd_pj` kunjungan aktif → `penjab`, bukan penjamin default pasien. Umur dihitung dari tanggal lahir valid sampai tanggal server hari ini (tahun/bulan/hari). Tanggal kosong, tidak valid, atau di masa depan tidak menghasilkan umur; golongan darah kosong/`-` ditampilkan **Belum diketahui**.
- Untuk kunjungan BPJS, tampilkan provider/FKTP terdaftar (nama dan kode), jenis peserta, status kepesertaan, peserta PRB, dan peserta Prolanis secara inline pada header yang sama, tanpa modal atau pergantian halaman.
- Deteksi BPJS mengikuti pola modul PCare: gunakan `jkn_mobile.kd_pj_bpjs` bila tersedia; bila belum dikonfigurasi gunakan kode `BPJ` atau nama penjamin mengandung BPJS/JKN.
- Setelah data lokal diterima, otomatis panggil endpoint PCare yang sudah digunakan modul Pasien/PCare: `GET /pcare/byjeniskartu/noka/{nomor}` untuk kartu 13 digit. Bila kartu belum valid tetapi NIK 16 digit tersedia, gunakan `GET /pcare/byjeniskartu/nik/{nomor}`. Nomor dan URL diturunkan dari data pasien di server, bukan dari input bebas pada halaman pemeriksaan.
- Route PCare yang ada tetap menangani token sesi, izin akses, konfigurasi kredensial, signature, dekripsi, dan dekompresi respons. Kredensial tidak dikirim ke browser; versi dev tidak menggandakan service/algoritme PCare.
- Data lokal dan form tetap tersedia ketika PCare lambat, gagal, tidak aktif, tidak berizin, atau nomor kartu/NIK belum lengkap. Beri status dan tombol **Cek ulang** bila endpoint pengecekan tersedia. Request PCare dibatasi 35 detik di browser, sedangkan service yang ada membatasi request eksternal 30 detik.
- Bedakan **Memeriksa**, **Data diterima**, dan **Belum terverifikasi**. **Data diterima** berarti respons peserta berhasil diterima, bukan otomatis status kepesertaan aktif.
- Nilai PRB/Prolanis `null`, string `null`, kosong, `-`, atau tidak tersedia ditampilkan **Tidak ada informasi**. Nilai boolean/kode eksplisit positif (`true`, `1`, `ya/y/yes`) menjadi **Terdaftar**; negatif (`false`, `0`, `tidak/n/no`) menjadi **Tidak terdaftar**. Deskripsi/kode lain ditampilkan sesuai respons tanpa menebak maknanya. Status `aktif` mengikuti pola yang sama dengan label **Aktif/Tidak aktif**.
- Tampilkan sumber PCare dan waktu pengecekan pada perangkat. Pada cek ulang, hasil lama dikosongkan hingga hasil baru diterima; kegagalan tidak boleh meninggalkan status lama yang tampak masih terverifikasi.
- Abaikan respons dari pasien atau request sebelumnya. Pengecekan kartu menolak respons dengan nomor kartu yang berbeda; nomor kartu respons harus valid. Cegah permintaan ganda selama pengecekan sedang berjalan.
- Tidak menyimpan hasil kepesertaan ke database, cache permanen, localStorage, atau console. Pengecekan tidak mengubah identitas pasien, profil alergi, draf pemeriksaan, maupun status kunjungan. Nomor kartu hasil pencarian NIK hanya memperbarui tampilan sesi ini.

### FR-05 — Simpan dan status kunjungan

- Simpan catatan pada tabel `pemeriksaan_ralan` dengan `nip` dari sesi pengguna, bukan nilai kiriman klien.
- Catatan yang sama (nomor rawat, tanggal, dan jam) diperbarui, bukan digandakan. Ini mengikuti kunci primer tabel saat ini; NIP adalah atribut pencatat, bukan bagian dari kunci.
- Pengaturan `pemeriksaan_ralan.set_sudah` menentukan apakah penyimpanan mengubah `reg_periksa.stts` menjadi `Sudah`.
- Pengaturan dan judulnya menggunakan istilah **Pemeriksaan Awal Paramedis**, bukan Dokter Ralan.
- Modul tidak boleh mengubah status menjadi `Berkas Dikirim`; status itu bukan keluaran pemeriksaan awal.

### FR-06 — Panggilan antrean melalui WebSocket

- Tombol **Panggil** pada daftar pasien dipertahankan untuk memanggil pasien menuju **Pemeriksaan Awal**. Tombol hanya tampil/aktif bagi peran yang berhak memanggil dan ketika fitur WebSocket atau fallback TTS tersedia.
- Saat koneksi terbuka, klien mengirim pesan `panggil` dengan identitas korelasi unik `msgId`, modul `pemeriksaan_awal`, serta nomor antrean, nama pasien, poli, dan nama pemanggil.
- Anjungan menampilkan dan menyuarakan panggilan, lalu mengirim `panggil_ack` dengan `msgId` yang sama. Tombol menampilkan status berhasil hanya setelah ACK yang cocok diterima.
- Bila koneksi tidak tersedia atau ACK tidak tiba dalam batas waktu, UI menggunakan Text-to-Speech lokal sebagai fallback dan memberi tahu pengguna bahwa panggilan anjungan belum terkonfirmasi.
- Panggilan, ACK, timeout, kegagalan koneksi, dan fallback perlu dicatat pada log aplikasi/audit tanpa menyimpan data klinis berlebihan.

## 8. Kebutuhan nonfungsional dan keamanan

- Semua query harus terparameterisasi atau memakai query builder. Nilai tanggal, status, poli, dan identitas pengguna tidak boleh dirangkai langsung ke SQL.
- Semua endpoint mutasi harus memeriksa token sesi, autentikasi, otorisasi poli, dan kepemilikan catatan bila berlaku.
- Validasi server mencakup pasien/kunjungan valid, hak akses pengguna, field wajib, serta tipe/rentang TTV yang disepakati pemilik klinis.
- Respons API yang dipakai UI berbentuk konsisten (`status`, `message`, dan `data` bila ada); kesalahan tidak membocorkan detail SQL.
- Jalur simpan pemeriksaan tidak bergantung pada layanan BPJS/farmasi. Pengecekan kepesertaan hanya-baca memakai route PCare yang sudah ada sesuai FR-08; gangguan layanan tidak menghambat input pemeriksaan.
- Hasil pemeriksaan, alergi, serta riwayat harus dapat dimuat tanpa JavaScript error di halaman baru maupun setelah filter daftar pasien.

### 8.1 Kontrak integrasi WebSocket panggilan antrean

Implementasi saat ini menggunakan pengaturan global `settings.websocket` dan `settings.websocket_proxy`. Bila proxy kosong, browser memakai `ws://<host>:3892`; bila proxy diisi, URL `http(s)` dikonversi ke `ws(s)`. Berkas `workerman.php` yang ada saat ini bertindak sebagai *broadcast broker*: setiap pesan diteruskan ke seluruh koneksi. Server tersebut tidak membuat ACK sendiri; anjungan yang menerima panggilan mengirim ACK, lalu broker meneruskannya kembali ke pemanggil.

```text
Paramedis                   Broker WebSocket                 Anjungan
    |--- panggil(msgId) ---------->|--- broadcast ------------->|
    |                               |                            | tampilkan popup + TTS
    |                               |<--- panggil_ack(msgId) ----|
    |<-- broadcast ACK -------------|                            |
    |--- status "Dipanggil"         |                            |
```

| Pesan | Pengirim | Payload saat ini | Perlakuan target |
| --- | --- | --- | --- |
| Panggilan | `pemeriksaan_ralan` | `action: "panggil"`, `modul: "pemeriksaan_awal"`, `msgId`, `data.nm_pasien`, `data.nm_poli`, `data.no_reg`, `data.nm_pemanggil` | Dipertahankan sebagai kontrak minimal. Tambahkan `schemaVersion`, waktu kirim, dan tujuan/ruang anjungan bila broker tidak lagi broadcast global. |
| Konfirmasi | Anjungan | `action: "panggil_ack"`, `modul`, `msgId`, `source: "anjungan"` | Wajib dicocokkan dengan `msgId`; ACK terlambat tidak boleh mengubah hasil panggilan baru. |
| Pendaftaran baru | Modul rawat jalan/IGD | `action: "simpan"`, `modul: "rawat_jalan"` atau `"igd"` | Klien pemeriksaan memuat ulang daftar hanya jika form pemeriksaan tidak sedang terbuka. Pertahankan perilaku ini setelah diuji. |
| Perubahan status lama | `pemeriksaan_ralan` | `action: "update_status"`, `modul: "pemeriksaan_ralan"`, `data.no_rawat`, `data.new_status` | Saat ini dipakai untuk me-refresh anjungan setelah status `Berkas Dikirim`. Karena status itu di luar target modul, event ini tidak dipancarkan oleh alur pemeriksaan awal yang telah dirapikan. Jika anjungan perlu sinkronisasi, tetapkan event baru yang tidak mengubah status klinis. |

Kebijakan ketahanan dan keamanan yang diperlukan:

- Gunakan `wss://` pada lingkungan HTTPS; endpoint broker harus hanya dapat diakses dari jaringan/asal yang disetujui.
- Implementasi saat ini membroadcast ke semua klien dan tidak mengautentikasi payload. Sebelum dipakai lintas jaringan, broker perlu autentikasi sesi atau token singkat, validasi skema, pembatasan ukuran/rate, dan kanal/ruang per poli atau anjungan.
- `msgId` dibuat per klik. Satu pemanggilan hanya boleh memiliki satu timer ACK; timer harus dibersihkan saat ACK, koneksi tutup, atau pengguna meninggalkan halaman.
- Reconnect saat ini mencoba kembali tiap lima detik. Target perlu *backoff*, satu pengelola koneksi (bukan listener ganda), indikator status koneksi, dan batas retry yang aman.
- Nama pasien dan nomor antrean adalah data pribadi. Payload hanya memuat minimum yang perlu ditampilkan, tidak dicatat ke console produksi, dan tidak boleh diteruskan ke klien yang bukan anjungan/petugas terkait.

## 9. Ruang lingkup teknis perapihan

### Dipertahankan atau ditulis ulang sebagai inti

- navigasi/index, halaman kelola, daftar pasien, filter, stylesheet dan JavaScript yang minimal;
- endpoint daftar/kelola, tampilan riwayat pemeriksaan, simpan/hapus pemeriksaan, baca/simpan alergi, pengaturan, CSS, JavaScript, serta integrasi panggilan antrean WebSocket yang minimal;
- integrasi ke `reg_periksa`, `pasien`, `pemeriksaan_ralan`, `pegawai`, dan `alergi_pasien` yang diperlukan oleh alur di atas.

### Dihapus dari modul setelah verifikasi referensi lintas modul

- handler dan template rincian layanan, resep/e-resep/racikan/salin resep, lab, radiologi, tindakan, serta pencarian obat;
- handler/template ICD, kontrol dan kontrol BPJS, odontogram/OHIS, resume, penilaian medis ralan, serta surat;
- JavaScript, modal, selector, dan aset yang hanya digunakan oleh fitur-fitur tersebut;
- `css/admin/dokter_ralan.css` yang duplikat serta selector bernama `dokter_ralan` yang tidak lagi dipakai;
- teks, URL, namespace, konfigurasi, dan dokumentasi sisa `dokter_ralan`.

Sebelum setiap berkas dihapus, implementor wajib mencari pemanggilan dari plugin, tema, dan konfigurasi lain. Bila masih dirujuk pihak lain, rujukan harus dipindahkan ke pemilik fitur yang benar atau dicatat sebagai keputusan migrasi tersendiri.

## 10. Struktur data saat ini

Bagian ini mendokumentasikan struktur yang dipakai modul saat audit. Dokumen ini tidak menyatakan bahwa semua tabel tersebut dimiliki oleh plugin; sebagian besar adalah tabel SIMRS bersama.

```text
pasien (1) ---< reg_periksa (1) ---< pemeriksaan_ralan
   |                 |                    |
   |                 +--- poliklinik       +--- pegawai (NIP pencatat)
   |                 +--- dokter (dokter tujuan)
   |
   +--- alergi_pasien (0..1)
```

### 10.1 Entitas inti

| Entitas | Kunci dan relasi | Kolom yang dipakai modul | Peran saat ini |
| --- | --- | --- | --- |
| `reg_periksa` | PK `no_rawat`; mengacu ke pasien, poli, dokter, penjamin | `no_rawat`, `tgl_registrasi`, `jam_reg`, `no_rkm_medis`, `kd_poli`, `kd_dokter`, `stts`, `status_lanjut`, `status_bayar` | Sumber daftar kunjungan rawat jalan dan status kunjungan. |
| `pasien` | PK `no_rkm_medis` | `no_rkm_medis`, `nm_pasien`, `jk`, `tgl_lahir`, `gol_darah`, `no_peserta`, `no_ktp` | Identitas pasien, umur terhitung, golongan darah, kartu peserta; NIK hanya untuk fallback pencarian PCare. |
| `pemeriksaan_ralan` | PK gabungan `no_rawat`, `tgl_perawatan`, `jam_rawat`; mengacu ke `reg_periksa` dan NIP pegawai pada skema referensi | TTV, SOAP, alergi ringkas, `nip` | Penyimpanan pemeriksaan per kunjungan. Ini adalah tabel utama modul. |
| `pegawai` | `nik` unik | `nik`, `nama`, `departemen`, status pegawai | Identitas petugas pencatat dan dasar validasi akun paramedis. |
| `poliklinik` | PK `kd_poli` | `kd_poli`, `nm_poli`, `status` | Pembatasan akses dan tampilan poli kunjungan. |
| `dokter` | PK `kd_dokter`; terhubung ke pegawai | `kd_dokter`, `nm_dokter`, `status` | Dokter tujuan kunjungan; hanya informasi baca dalam modul target. |
| `alergi_pasien` | PK `no_rkm_medis` | Kode dan catatan alergi makanan/udara/obat, waktu dan NIP input/pembaruan | Profil alergi pasien lintas kunjungan. |
| `mlite_settings` | konfigurasi per modul | `pemeriksaan_ralan.set_sudah` | Menentukan apakah simpan pemeriksaan dapat menandai kunjungan `Sudah`. |

### 10.2 Struktur `pemeriksaan_ralan`

| Kelompok | Kolom saat ini | Perlakuan pada target |
| --- | --- | --- |
| Identitas catatan | `no_rawat`, `tgl_perawatan`, `jam_rawat`, `nip` | Dipertahankan. Nilai `nip` dan waktu harus ditetapkan server dari sesi/waktu server; `no_rawat` harus diverifikasi milik poli yang boleh diakses. |
| TTV | `tensi`, `suhu_tubuh`, `nadi`, `respirasi`, `tinggi`, `berat`, `spo2`, `gcs`, `kesadaran`, `lingkar_perut` | Dipertahankan dan ditampilkan pada form paramedis. Saat ini mayoritas bertipe `varchar`; format dan rentang dikontrol di aplikasi pada fase perapihan. |
| Pemeriksaan awal | `keluhan`, `pemeriksaan`, `alergi` | Dipertahankan. `keluhan` adalah anamnesa awal; `pemeriksaan` opsional sesuai SOP; `alergi` adalah ringkasan dari profil alergi. |
| Kewenangan dokter/historis | `rtl`, `penilaian`, `instruksi`, `evaluasi` | Tidak ditampilkan sebagai input paramedis. Tidak dihapus dari tabel maupun dari catatan lama. Nilai default penyimpanan harus tetap memenuhi constraint skema yang aktif. |

Kamus data `pemeriksaan_ralan` pada dump referensi adalah sebagai berikut. Tipe ini menjadi acuan audit, bukan izin untuk mengubah skema produksi tanpa verifikasi.

| Kolom | Tipe saat ini | Null | Keterangan |
| --- | --- | --- | --- |
| `no_rawat` | `varchar(17)` | Tidak | Nomor kunjungan; bagian kunci primer. |
| `tgl_perawatan` | `date` | Tidak | Tanggal catatan; bagian kunci primer. |
| `jam_rawat` | `time` | Tidak | Jam catatan; bagian kunci primer. |
| `nip` | `varchar(20)` | Tidak | NIP/nik petugas pencatat. |
| `suhu_tubuh` | `varchar(5)` | Ya | Suhu tubuh. |
| `tensi` | `varchar(8)` | Tidak | Tekanan darah dalam format teks. |
| `nadi`, `respirasi`, `spo2` | `varchar(3)` | Nadi/RR ya; SpO2 tidak | Nadi, frekuensi napas, dan saturasi oksigen. |
| `tinggi`, `berat`, `lingkar_perut` | `varchar(5)` | Ya | Antropometri. |
| `gcs` | `varchar(10)` | Ya | Glasgow Coma Scale. |
| `kesadaran` | `enum('Compos Mentis','Somnolence','Sopor','Coma')` | Tidak | Kesadaran; nilai UI harus sama dengan skema aktif. |
| `keluhan`, `pemeriksaan` | `varchar(2000)` | Ya | Anamnesa awal dan temuan obyektif. |
| `alergi` | `varchar(50)` | Ya | Ringkasan alergi pada saat pemeriksaan. |
| `rtl`, `penilaian`, `instruksi`, `evaluasi` | `varchar(2000)` | Tidak | Field dokter/historis yang masih diwajibkan oleh skema referensi. |

Catatan integritas penting:

- Kunci primer tidak mengandung `nip`; karena itu hanya boleh ada satu catatan untuk kombinasi nomor rawat, tanggal, dan jam. Logika *upsert* harus memakai tiga kolom tersebut secara konsisten.
- Data dump dan skrip plugin menunjukkan variasi definisi `alergi_pasien` (panjang kolom dan tipe waktu). Sebelum membuat migrasi apa pun, implementor wajib menjalankan `SHOW CREATE TABLE alergi_pasien` dan `SHOW CREATE TABLE pemeriksaan_ralan` pada database target, lalu menjadikan skema produksi sebagai sumber kebenaran.
- Kolom TTV bertipe teks memungkinkan nilai seperti `36,5`, `-`, atau format tensi yang tidak konsisten. Fase ini tidak mengubah tipe data agar kompatibel; normalisasi tipe/rentang adalah kandidat fase lanjutan setelah audit data.
- Tabel pemeriksaan belum memiliki `created_at`, `updated_at`, alasan perubahan, atau jejak versi yang eksplisit. Nilai waktu pemeriksaan dan NIP bukan pengganti audit trail perubahan.

### 10.3 Struktur `alergi_pasien`

Satu baris mewakili satu pasien (`no_rkm_medis`). Kelompok atributnya adalah:

- makanan: `alergi_makanan`, `alergi_makanan_lainnya`;
- udara: `alergi_udara`, `alergi_udara_lainnya`;
- obat: `alergi_obat`, `alergi_obat_lainnya`;
- audit: `tgl_input`, `nip_input`, `tgl_update`, `nip_update`.

Kode yang tersedia pada UI saat ini adalah `00` (tidak ada) serta pilihan kategori spesifik; `05` pada makanan dan `07` pada obat membuka catatan "lainnya". Target harus memvalidasi konsistensi kode dan isi catatan "lainnya" di server, bukan hanya menyembunyikan/menampilkan input di browser.

Skrip instalasi plugin mendefinisikan `no_rkm_medis varchar(15)` sebagai kunci primer, tiga kode alergi sebagai `varchar(5)`, tiga catatan "lainnya" sebagai `varchar(255)`, dua waktu sebagai `datetime`, dan dua NIP audit sebagai `varchar(20)`. Definisi dump yang ada lebih longgar untuk sejumlah kolom; perbedaan ini harus ditutup pada fase standardisasi skema, bukan ditimpa secara diam-diam saat deploy.

### 10.4 Perbaikan struktur data yang direkomendasikan

| Prioritas | Perbaikan | Alasan dan ketergantungan |
| --- | --- | --- |
| P0 | Validasi server dan normalisasi tampilan TTV | Melindungi kualitas data sekarang tanpa mengubah skema bersama. Definisikan format tensi `sistolik/diastolik`, angka desimal suhu, dan rentang per umur melalui SOP klinis. |
| P0 | Tetapkan server sebagai sumber `nip`, waktu, dan hak akses | Mencegah pemalsuan pencatat, pasien, serta poli melalui request manual. |
| P1 | Tambahkan audit aplikasi untuk perubahan/hapus | Catat pengguna, waktu, nilai sebelum/sesudah, dan alasan. Desain tabel audit baru harus disetujui karena menyentuh data klinis. |
| P1 | Standarkan skema `alergi_pasien` | Selaraskan skrip instalasi dengan skema produksi, tipe `datetime`, panjang kolom, kode yang valid, serta foreign key ke pasien/pegawai bila data produksi memungkinkan. |
| P2 | Migrasi TTV ke tipe terstruktur | Hanya setelah profiling data dan rencana kompatibilitas laporan: angka untuk suhu/nadi/RR/TB/BB/SpO2, dan dua angka atau format tervalidasi untuk tensi. Jangan dilakukan dalam refaktor penghapusan kode warisan. |
| P2 | Pisahkan catatan awal dari catatan dokter secara eksplisit | Evaluasi tabel/jenis entri baru bila sistem perlu membedakan penilaian paramedis dari SOAP dokter tanpa bergantung pada NIP atau field kosong. |

## 11. Halaman dan antarmuka yang ada sekarang

### 11.1 Peta halaman saat ini

```text
Index Pemeriksaan Paramedis
├── Kelola (`manage.html`)
│   ├── daftar pasien (`display.html`)
│   ├── data alergi (`form.alergi.html`)
│   ├── form pemeriksaan (`form.soap.html`)
│   └── riwayat pemeriksaan (`soap.html`)
└── Pengaturan (`settings.html`)

Kode warisan yang masih dapat dirute:
├── rincian layanan, obat, resep, racikan, lab, radiologi
├── ICD-10/ICD-9, kontrol/BPJS, odontogram/OHIS
└── resume, penilaian medis, dan surat
```

### 11.2 Halaman inti yang dipertahankan dan ditingkatkan

| Halaman saat ini | Fungsi saat ini | Masalah/ruang perbaikan | Keputusan target |
| --- | --- | --- | --- |
| `index.html` | Dua kartu menuju Kelola dan Pengaturan. | Sudah sederhana; perlu penamaan konsisten. | Pertahankan; gunakan label **Pemeriksaan Awal Paramedis**. |
| `manage.html` | Kontainer yang menggabungkan daftar, alergi, form, dan hasil riwayat. | Terlalu banyak state/elemen tersembunyi di satu halaman. | Pertahankan sebagai *shell*, tetapi jadikan state eksplisit: `daftar`, `pemeriksaan pasien`, dan `riwayat`. |
| `display.html` | Daftar kunjungan, filter periode/status, pemilihan pasien, dan tombol **Panggil** yang mengirim panggilan WebSocket. | Tabel lebar, aksi warisan, daftar dimuat penuh, dan status panggilan belum menjadi komponen tersendiri. | Tampilkan kolom inti saja, aksi **Mulai pemeriksaan** dan **Panggil** yang dikendalikan hak akses, *empty state*, indikator data sudah diisi, status koneksi/panggilan, pencarian, serta paging/filter server-side bila volume besar. |
| `form.soap.html` | Identitas pasien, TTV, SOAP, blok ICD tersembunyi, modal e-resep/odontogram, dan banyak script. | 718 baris; campuran input paramedis dan fungsi dokter. TTV tersembunyi pada mode `simple`. | Tulis ulang sebagai form pemeriksaan awal yang kecil; kelompokkan TTV, alergi, dan anamnesa; hilangkan blok/modal/script warisan; tampilkan TTV pada semua mode. |
| `soap.html` | Menampilkan riwayat pemeriksaan ralan dan, pada kondisi tertentu, data ranap/ICD. | Riwayat bercampur dengan data yang bukan kebutuhan pemeriksaan awal. | Jadikan riwayat ringkas dan dapat diperluas: waktu, petugas, TTV, alergi, keluhan, temuan awal. Pertahankan aksi edit/hapus hanya jika berhak. |
| `form.alergi.html` | Baca dan simpan alergi makanan, udara, dan obat; membuat ringkasan untuk form. | JavaScript inline dan state global dapat berbenturan saat halaman dimuat ulang. | Pertahankan sebagai komponen mandiri dengan API terkontrak, validasi server, notifikasi yang konsisten, serta penanda alergi penting. |
| `settings.html` | Pengaturan `set_sudah`. | Judul backend masih Dokter Ralan; makna efek status belum dijelaskan. | Pertahankan satu pengaturan ini, ubah judul/teks, dan tambahkan bantuan: `Ya` menandai kunjungan selesai setelah simpan. |

### 11.3 Halaman warisan yang tidak menjadi target modul

| Kelompok | Berkas yang ada saat ini | Tindakan |
| --- | --- | --- |
| Layanan dan farmasi | `form.rincian.html`, `rincian.html`, `layanan.html`, `obat.html`, `obat.racikan.html`, `racikan.html`, `eresep.html`, `rincian.eresep.html`, `copyresep.display.html` | Lepaskan dari modul setelah pemanggilan lintas modul diverifikasi. |
| Penunjang | `laboratorium.html`, `radiologi.html`, `details.html` | Lepaskan; permintaan penunjang adalah alur dokter/layanan. |
| Dokumen dan kontrol | `form.kontrol.html`, `kontrol.html`, `surat.rujukan.html`, `surat.sehat.html`, `surat.sakit.html` | Lepaskan; tidak relevan untuk input awal paramedis. |
| Penilaian dokter | `medis.ralan.html`, `medis.ralan.tampil.html`, `resume.html`, `resume.tampil.html` | Lepaskan; dokter tetap mengaksesnya dari modul dokter. |
| Dental/khusus | `odontogram.html`, `odontogram.tampil.html`, `ohis.tampil.html` | Lepaskan, kecuali pemilik klinis menyetujui kebutuhan triase dental yang terpisah. |
| Tidak terhubung dari alur inti | `pasien.html` | Telusuri pemanggilan; hapus bila tidak ada rute/rujukan aktif. |

### 11.4 Peningkatan pengalaman pengguna

| Prioritas | Peningkatan | Hasil yang diharapkan |
| --- | --- | --- |
| P0 | Satu tombol tindakan utama pada form: **Simpan pemeriksaan awal**; jangan tampilkan tombol diagnosis/resep/tindakan. Tombol **Panggil** hanya berada pada daftar pasien. | Paramedis tidak masuk ke alur dokter secara tidak sengaja. |
| P0 | Validasi per-field yang jelas, fokus ke field pertama bermasalah, dan validasi ulang di server. | Pengisian lebih cepat dan data wajib lengkap. |
| P0 | Banner alergi yang selalu terlihat saat pasien terpilih, dengan ringkasan dan sumber pembaruannya. | Risiko alergi terlewat berkurang. |
| P1 | Gunakan satu komponen tanggal/waktu dari server dan cegah pengeditan waktu tanpa hak. | Urutan catatan dan kunci primer lebih andal. |
| P1 | Bedakan status **belum diperiksa**, **tersimpan**, dan **selesai**; jangan menyamakan status administrasi dengan bukti bahwa semua tindakan dokter selesai. | Status kunjungan lebih mudah dipahami. |
| P1 | Tambahkan indikator riwayat terbaru dan nilai TTV abnormal berdasarkan ambang yang disetujui klinis. | Dokter menerima konteks cepat tanpa modul mengambil alih penilaian dokter. |
| P1 | Tampilkan status koneksi WebSocket, status "menunggu anjungan", ACK berhasil, dan fallback TTS lokal secara jelas. | Petugas tahu apakah panggilan benar-benar diterima anjungan dan dapat mengulang panggilan bila perlu. |
| P2 | Responsif untuk tablet, dukungan navigasi keyboard, ukuran kontrol yang cukup, dan kontras yang baik. | Lebih layak dipakai di meja triase. |

## 12. Data dan kompatibilitas

- Tidak ada penghapusan atau perubahan skema tabel pada fase ini.
- Catatan lama di `pemeriksaan_ralan` tetap dapat dibaca, termasuk nilai lama pada kolom yang tidak lagi diinputkan.
- `alergi_pasien` dan skrip `sql/alergi_pasien.sql` dipertahankan; skrip instalasi harus idempoten atau didokumentasikan sebagai prasyarat.
- Tidak ada migrasi data dari `dokter_ralan` karena kedua modul membaca tabel klinis bersama. Perubahan hanya membatasi kemampuan modul `pemeriksaan_ralan`.
- Salinan/backup berkas modul sebelum penghapusan kode disimpan melalui proses rilis/Git, bukan dengan menyalin data produksi.

## 13. Kriteria penerimaan

1. Akun paramedis hanya melihat pasien pada poli yang menjadi haknya; akun tanpa hubungan pegawai tidak dapat menyimpan pemeriksaan.
2. Paramedis dapat menyimpan TTV lengkap, alergi, dan keluhan awal; catatan menunjukkan NIP serta waktu pencatat yang benar.
3. Percobaan simpan dengan salah satu field wajib kosong/`-` atau pengguna tanpa hak akses ditolak oleh server dan tidak membuat data.
4. Data TTV tetap tampak dan dapat diisi pada mode `simple` dan `complex`.
5. Riwayat semua kunjungan dapat dibaca sesuai FR-04, termasuk SOAP, diagnosis, prosedur, dan obat; aksi penulisan layanan dokter tidak tersedia. Salin hanya memindahkan field pemeriksaan awal sesuai FR-07.
6. Pengguna biasa tidak dapat mengubah atau menghapus catatan milik petugas lain dengan memanggil endpoint secara langsung.
7. Bila `set_sudah=ya`, simpan pemeriksaan mengubah status kunjungan menjadi `Sudah`; bila `tidak`, status tidak berubah. Modul tidak pernah menetapkan `Berkas Dikirim`.
8. Implementasi baru memakai namespace, endpoint, aset, dan konfigurasi `pemeriksaan_ralan_dev` serta label Paramedis (Dev). Tabel klinis tetap tabel bersama. Versi berjalan `pemeriksaan_ralan` tetap terpisah.
9. Endpoint dan berkas yang dikeluarkan dari daftar inti tidak lagi dapat dipanggil melalui `pemeriksaan_ralan`; alur setara tetap tersedia pada modul dokter/layanan pemiliknya.
10. Pemeriksaan statis dan uji manual tidak menemukan error JavaScript/PHP pada alur pilih pasien, buka alergi, simpan, edit/hapus sesuai hak, filter, dan muat ulang riwayat.
11. Saat WebSocket aktif dan anjungan tersambung, klik **Panggil** mengirim satu payload `panggil` dengan `msgId` unik; popup/TTS anjungan berjalan dan ACK dengan `msgId` yang sama mengubah tombol menjadi **Dipanggil via anjungan**.
12. ACK dengan `msgId` lain, JSON rusak, atau ACK yang datang setelah timeout tidak boleh mengonfirmasi panggilan yang sedang aktif.
13. Saat broker/anjungan tidak tersedia, setelah tiga detik panggilan memakai TTS lokal dan UI menyatakan bahwa anjungan tidak terkonfirmasi; halaman tidak mengalami error JavaScript.
14. Saat form pemeriksaan terbuka, event pendaftaran baru tidak memuat ulang daftar hingga form ditutup; setelah form ditutup, daftar dapat disegarkan tanpa duplikasi DataTable atau listener WebSocket.
15. Pasien dengan lebih dari 10 kunjungan dapat ditelusuri sampai halaman terakhir, termasuk kunjungan lintas poli/rawat inap serta kunjungan tanpa SOAP.
16. Request detail dengan nomor rawat pasien lain ditolak; pengguna tanpa CAP pada kunjungan aktif tidak bisa mengambil daftar maupun detail riwayat.
17. Salin dari kunjungan lama mengisi hanya kolom kosong/`-`, mempertahankan nilai `0` yang sudah diisi, dan tidak mengubah alergi terbaru atau identitas pasien aktif. Tidak ada penulisan database sebelum tombol simpan ditekan.
18. Setelah salin, penyimpanan membuat catatan pada kunjungan aktif dengan petugas/waktu baru; catatan sumber tidak berubah. Respons terlambat dari pasien sebelumnya tidak boleh tampil pada pasien baru.
19. Teks riwayat yang berisi kutip, baris baru, atau markup ditampilkan sebagai teks, tidak dieksekusi sebagai HTML/JavaScript.
20. Header menampilkan seluruh identitas FR-08 dari pasien kunjungan aktif. Request memakai nomor RM kiriman lain tidak boleh mengubah pasien yang diambil; kunjungan di luar CAP ditolak.
21. Kunjungan non-BPJS tidak memanggil PCare walaupun pasien memiliki nomor kartu BPJS. BPJS memakai kartu valid, fallback NIK valid, atau pesan bahwa identitas belum lengkap.
22. Provider dan program peserta berasal dari respons PCare. PRB/Prolanis kosong tidak dianggap tidak terdaftar. Gangguan jaringan, respons tidak valid, dan izin ditolak tidak menghapus data lokal maupun menghalangi form.
23. Cek ulang dan perpindahan pasien tidak menampilkan hasil kedaluwarsa; respons nomor kartu berbeda ditolak. Semua pembacaan identitas/kepesertaan tidak menulis database.

## 14. Keputusan yang perlu disetujui sebelum implementasi

1. Apakah paramedis wajib mengisi seluruh 11 TTV/anamnesa yang sekarang divalidasi, atau ada field yang bersifat opsional menurut SOP klinis?
2. Apakah `pemeriksaan` (temuan obyektif) tetap diisi paramedis, dan apakah Assessment/Plan/Instruksi/Evaluasi hanya disembunyikan atau perlu dialihkan ke modul dokter?
3. Apakah paramedis boleh mengubah/menghapus catatan sendiri tanpa batas waktu, atau diperlukan batas waktu dan alasan perubahan?
4. Apa sumber hak akses paramedis yang resmi (role, `cap` poli, atau pemetaan pegawai khusus) agar pengganti logika `per_dokter` tepat?
5. Siapa yang berhak menjalankan panggilan antrean, anjungan mana yang menjadi tujuan tiap poli, dan apakah fallback TTS lokal boleh digunakan di area layanan?

Kelima keputusan ini tidak menghalangi audit dan pemisahan kode warisan, tetapi diperlukan untuk finalisasi validasi, otorisasi, dan SOP rilis.

## 15. Rencana rilis dan verifikasi

1. Inventarisasi pemanggilan lintas modul dan tandai endpoint/berkas yang akan dipertahankan atau dikeluarkan.
2. Bangun ulang halaman dan handler inti dengan test/cek otorisasi server terlebih dahulu.
3. Hapus kode warisan secara bertahap setelah tidak memiliki pemanggilan yang tersisa.
4. Uji di staging memakai akun admin, paramedis berizin, paramedis tanpa izin, dokter, broker WebSocket, dan anjungan.
5. Cadangkan basis data sesuai SOP sebelum rilis; lakukan smoke test pada data catatan lama setelah deploy.

## 16. Metrik keberhasilan

- Tidak ada aksi penulisan layanan dokter dari menu/endpoint `pemeriksaan_ralan_dev`; pembacaan konteks klinis di riwayat mengikuti FR-04.
- Alur pilih pasien sampai simpan pemeriksaan awal dapat diselesaikan paramedis tanpa berpindah modul.
- Tidak ada catatan pemeriksaan awal baru dengan NIP kosong atau berasal dari pengguna di luar poli berhak.
- Tidak ada regresi pembacaan catatan `pemeriksaan_ralan` dan alergi yang sudah ada setelah rilis.
- Panggilan WebSocket yang menerima ACK terkonfirmasi di UI; kegagalan broker/anjungan beralih ke fallback tanpa membuat panggilan ganda.

## 17. Implementasi riwayat dan salin pada versi dev — 26 September 2026

Bagian 2, 10, dan 11.1–11.3 mencatat audit modul lama. Target riwayat diperluas sesuai permintaan pengguna; FR-04/FR-07 menggantikan pembatasan lama terhadap pembacaan data lintas kunjungan.

| Komponen | Implementasi |
| --- | --- |
| Halaman form | `view/admin/manage.html` menampilkan identitas pasien bersama, form kiri, dan riwayat kanan dalam satu halaman. `form.pemeriksaan.html` memuat status draf/edit/tersimpan dan sumber salinan. |
| Riwayat kunjungan aktif | `view/admin/riwayat.html`; edit/hapus tetap pada kunjungan aktif dan mengikuti hak pengguna. |
| Riwayat semua kunjungan | `view/admin/riwayat.pasien.html`; panel kanan otomatis dimuat, catatan aktif, daftar berhalaman, detail expandable, dan tautan ERM. Form tidak disembunyikan. |
| `POST riwayatpasien` | Input `no_rawat` aktif, `page`; output JSON `status`, `data.visits`, `page`, `pages`, `total`, `erm_url`. Halaman minimal 1 dan maksimal halaman terakhir. |
| `POST detailriwayat` | Input `no_rawat` aktif dan `source_no_rawat`; output `data.no_rawat`, `records`, `diagnoses`, `procedures`, `medicines`. Setiap record memiliki `jenis`, `nama_petugas`, `can_copy`. |
| Penyalinan | JavaScript memakai data JSON record yang diterima; field whitelist dan pemeriksaan sesi pasien di browser. Tidak ada endpoint mutasi khusus copy. |
| Struktur data baca | `reg_periksa` → pasien melalui `no_rkm_medis`; catatan ralan/ranap, diagnosis/prosedur, dan resep melalui `no_rawat`; nama petugas melalui `nip=pegawai.nik`; seluruh resep reguler melalui `resep_obat.no_resep=resep_dokter.no_resep`. |
| Data tambahan | `diagnosa_pasien` + `penyakit`; `prosedur_pasien` + `icd9`; `resep_obat` + `resep_dokter` + `databarang`; `pemeriksaan_ranap` hanya-baca. Tidak ada tabel baru. |

Verifikasi rilis mencakup membaca riwayat dan mengisi form secara bersamaan tanpa kehilangan isian, paging tanpa menyembunyikan form, kegagalan jaringan dan retry, catatan dokter/paramedis pada hari yang sama, salin saat isian sebagian terisi, alergi berubah sejak kunjungan lama, dan pergantian pasien ketika request masih berjalan. Pengujian otomatis menggunakan data sintetis; uji browser dengan akun berizin serta integrasi rekam medis lengkap tetap diperlukan sebelum rilis.

### 17.1 Revisi UI/UX single-page

- Identitas pasien dan poli menjadi header bersama untuk form dan riwayat.
- TTV disusun dalam grid tiga kolom dengan satuan menyatu di input; kesadaran memiliki satu baris penuh, kemudian antropometri menjadi kelompok tersendiri. Pada layar kecil grid menyesuaikan menjadi dua/satu kolom.
- Anamnesa dan temuan obyektif berdampingan pada desktop, label terhubung ke input, dan textarea mendukung perubahan tinggi.
- Ringkasan alergi ditampilkan sekali di atas form. Status belum dimuat berwarna netral, tidak ada alergi berwarna hijau lembut, dan alergi tercatat berwarna amber. Editor profil dapat dilipat dan dibuka melalui Perbarui.
- Tombol simpan berada pada action bar sticky dengan ruang untuk footer aplikasi. Status membedakan draf baru, salinan, edit, menyimpan, gagal, dan tersimpan.
- Detail riwayat menampilkan nilai TTV yang tersedia, anamnesa/temuan, serta data dokter yang terisi agar panel tetap mudah dipindai. Tidak mengubah catatan sumber.

Regresi otomatis: `php tests/PemeriksaanRalanDevHistoryTest.php` menggunakan SQLite in-memory dan query builder aplikasi tanpa memuat konfigurasi/database produksi. `tests/PemeriksaanRalanDevHistoryTest.js` menguji fungsi JavaScript aktual untuk whitelist salin, isian yang dipertahankan, identitas draf, dan respons kedaluwarsa (dapat dijalankan dengan Node). Validasi range klinis tetap mengikuti handler penyimpanan yang ada.

### 17.2 Struktur data dan integrasi header kepesertaan

| Informasi | Sumber |
| --- | --- |
| Nama / RM / lahir / golongan darah | `pasien.nm_pasien`, `no_rkm_medis`, `tgl_lahir`, `gol_darah` |
| Umur | Selisih tanggal lahir valid dengan tanggal server saat ini; tidak menggunakan kolom umur lama |
| Penjamin kunjungan | `reg_periksa.kd_pj` → `penjab.png_jawab` |
| Nomor kartu | `pasien.no_peserta`; `response.noKartu` setelah pengecekan berhasil |
| Provider/FKTP terdaftar | `response.kdProviderPst.nmProvider`, `kdProvider` (provider umum sesuai PCare) |
| Jenis peserta | `response.jnsPeserta.nama` |
| Status kepesertaan | `response.aktif` bila disediakan |
| PRB | `response.pstPrb` |
| Prolanis | `response.pstProl` |

Endpoint baru **POST `informasipasien`** menerima `no_rawat` aktif, melakukan validasi akses kunjungan, lalu menghasilkan `data.name`, `no_rkm_medis`, `birth_date`, `age`, `blood_group`, `payer`, `payer_code`, `card_number`, `is_bpjs`, serta `pcare.{url,type,message}`. Respons tidak boleh di-cache (`Cache-Control: no-store`). URL pengecekan hanya disediakan saat modul PCare aktif dan identitas yang dibutuhkan valid. Header kemudian mengakses endpoint PCare existing sesuai izin akun. Data peserta dimasukkan ke DOM sebagai teks.

Referensi implementasi: `plugins/pcare/Admin.php::getByJenisKartu`, `plugins/pasien/view/admin/pcare.bynokartu.html`, dan `pcare.bynik.html`. Tidak ada migrasi skema atau perubahan pada modul PCare. Pengujian menggunakan data sintetis dan respons PCare tiruan untuk memeriksa pemetaan, status kosong/negatif, kegagalan, dan pergantian pasien; koneksi PCare live perlu diverifikasi pada akun dengan akses yang sesuai.
