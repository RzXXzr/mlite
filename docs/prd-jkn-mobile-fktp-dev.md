# PRD — JKN Mobile FKTP Dev

## 1. Ringkasan

`jkn_mobile_fktp_dev` adalah pengganti terisolasi untuk endpoint Antrean FKTP yang diakses Mobile JKN. Modul ini **tidak mengubah** route atau pengaturan `jkn_mobile_fktp` lama. Tujuan rilis ini adalah menyediakan implementasi yang dapat diuji melalui Postman, selaras dengan katalog BPJS, aman terhadap pemesanan ganda, dan dapat dioperasikan menggunakan data mLITE yang sudah ada.

Rute dasar dev adalah:

```
{BASE_URL}/jknmobilefktpdev
```

## 2. Sumber acuan dan keputusan kontrak

Sumber utama adalah [`docs/antreanfktp.txt` pada bpjs_webservice_catalog](https://github.com/bastomiadi/bpjs_webservice_catalog/blob/main/docs/antreanfktp.txt), commit `d5702ce`. Nama yang disebut pada permintaan sebagai `docs/antranfktp` tidak ada pada branch `main`; berkas yang relevan adalah `docs/antreanfktp.txt`.

Katalog tersebut memisahkan dua arah integrasi:

| Arah | Pemanggil | Kontrak | Keputusan dev |
| --- | --- | --- | --- |
| Masuk | Mobile JKN → FKTP | `/auth`, `/antrean`, status, sisa, peserta, batal | Diimplementasikan penuh untuk Postman/UAT. |
| Keluar | FKTP → BPJS | `ref/*`, `antrean/add`, `antrean/panggil`, `antrean/batal` | Referensi dipakai admin untuk menyusun jadwal Dev. Jika `antrol_enabled=1`, penerimaan HTTP dan metadata BPJS wajib sebelum booking/pembatalan lokal disahkan. `panggil` tersedia sebagai aksi manual dari Rawat Jalan untuk kunjungan asal Dev. |

Versi V2 dipakai untuk implementasi baru karena mencantumkan dokter, jam praktik, nomor RM, nomor HP, dan respons status berbentuk daftar. Pembatalan mengikuti metode **PUT** sesuai katalog V2. `POST /antrean/batal` tidak disediakan sebagai alias, supaya metode yang salah terlihat saat pengujian Postman.

**Penting**: kredensial outbound Antrol dibaca langsung dari namespace PCare: `pcare.consumerID`, `pcare.consumerSecret`, dan `pcare.consumerUserKeyAntrol`. URL berasal dari override `jkn_mobile_fktp_dev.antrol_url`, atau diturunkan dari `pcare.PCareApiUrl` bila override kosong. Kredensial incoming Mobile JKN tetap terpisah di namespace modul Dev.

## 3. Temuan audit modul lama

| Prioritas | Temuan | Dampak | Perbaikan dev |
| --- | --- | --- | --- |
| Kritis | Secret JWT `abC123!` tertanam dan token memuat username serta password. | Token dapat dipalsukan; kredensial terekspos di token. | Secret acak disimpan di settings; JWT tidak memuat password, memiliki `iat`, `exp`, dan tanda tangan HMAC. |
| Kritis | Header dibaca langsung dengan indeks case-sensitive dan `apache_request_headers()`. | Notice/fatal pada Nginx/FPM atau header dengan kapitalisasi lain; otentikasi tidak andal. | Pembacaan header case-insensitive dengan fallback `$_SERVER`. |
| Kritis | Query kuota memilih dokter mana pun di poli, mengabaikan `kodedokter` dan `jampraktek` request. | Pasien dapat terdaftar ke dokter/jam yang tidak dipilih. | Kode dokter dan jam harus cocok dengan mapping dan jadwal hari layanan. |
| Kritis | Pemeriksaan duplikasi memakai tanggal hari ini, bukan `tanggalperiksa`. | Booking masa depan dapat diduplikasi/ditolak secara keliru. | Seluruh cek memakai tanggal layanan request. |
| Kritis | Kuota dihitung per dokter tetapi pendaftaran/antrean dan display beberapa kali dihitung lintas poli. | Sisa kuota, nomor antrean, dan panggilan salah. | Semua hitungan dibatasi tanggal + poli + dokter; status V2 menampilkan per dokter/jam. |
| Tinggi | Prefix antrean di-hardcode hanya untuk kode `001`, `002`, `003`; variabel tidak terinisialisasi untuk poli lain. | Notice dan nomor antrean salah. | Format configurable `{kodepoli}-{nomor}`, tanpa pemetaan A/B/C terselubung. |
| Tinggi | `getAntrolAddAntrian()` menghitung semua `reg_periksa` lalu menambah satu, sehingga off-by-one dan lintas poli. | Nomor antrean remote tidak sama dengan registrasi lokal. | Nomor outbound berasal dari `no_reg` yang dialokasikan atomik pada poli/dokter/shift aktif. |
| Tinggi | Endpoint batal didaftarkan sebagai GET walau katalog menetapkan PUT; body tidak tervalidasi menyeluruh. | Efek samping melalui GET dan kontrak tidak sesuai. | PUT wajib, JSON wajib, alasan V2 opsional namun diaudit. |
| Tinggi | Validasi `empty(isset($decode['rw']))`/`rt` selalu salah secara logika. | RW/RT kosong dapat lolos. | Nilai dipastikan string tidak kosong dan berformat aman. |
| Tinggi | `setNoRM()` dan update counter pasien bukan transaksi aman. | Nomor RM ganda pada pendaftaran paralel. | Pendaftaran pasien baru mengunci `set_no_rkm_medis`, mengalokasikan dan memperbarui counter di transaksi yang sama. |
| Sedang | Kesalahan internal/SQL dikirim ke klien. | Membocorkan detail implementasi. | Respons publik selalu metadata BPJS; detail dicatat audit minimal server-side. |
| Sedang | Pengecekan kartu, NIK, pasien, poli, dan mapping mengakses indeks array yang mungkin kosong. | Notice/fatal dan respons kosong untuk data tidak ditemukan. | Semua dependensi diperiksa sebelum dipakai. |
| Sedang | Tidak ada log pembatalan maupun jejak request publik. | Sulit rekonsiliasi dan audit. | Log request minimal tanpa password/token disediakan. |
| Sedang | Konfigurasi `hari` dan display tidak benar-benar menjadi gerbang booking. | Kebijakan hari booking tidak konsisten. | `booking_open_days` diterapkan secara eksplisit; display bukan parameter API. |
| Sedang | Endpoint `getApi` memakai token statis `rahasia`, CORS `*`, dan menerima efek samping melalui GET/parameter request. | Siapa pun yang mengetahui token dapat membuat registrasi; browser asal mana pun dapat memanggilnya. | Tidak dibawa ke dev; seluruh mutasi kontrak memakai JSON + metode eksplisit + token berttl. |
| Sedang | Pemilihan dokter fallback ke baris dokter pertama ketika jadwal tidak ditemukan. | Booking dapat tercatat pada dokter yang tidak dijadwalkan. | Tidak ada fallback dokter; jadwal/mapping harus cocok persis. |
| Kritis | Kredensial outbound dibaca melalui helper namespace modul Dev walau kunci yang diminta adalah `pcare.*`. | Nilai kosong/salah membuat signature Antrol tidak sah. | Semua klien admin dan endpoint membaca kunci PCare langsung; URL Dev tetap dapat dioverride secara eksplisit. |
| Kritis | Respons HTTP 200 dari outbound dianggap sukses tanpa memeriksa metadata BPJS. | Booking/pembatalan lokal sukses walau BPJS menolak. | HTTP harus 200, JSON/metadata harus valid, dan kode BPJS harus `1`/`200`; selain itu transaksi lokal di-rollback dan klien menerima HTTP 502 dengan metadata 201. |
| Rendah | Pagination pencarian mapping menghitung hanya hasil halaman pertama; JavaScript reset pencarian memakai `batflat.token` yang tidak ada; notifikasi poli menyebut pasien. | Halaman/admin tidak konsisten dan dapat gagal memuat ulang. | Dev tidak menggandakan UI mapping; memakai mapping yang sudah ada sebagai prasyarat backend. |
| Rendah | Parsing mapping `kode: nama` tidak memvalidasi delimiter, nilai kosong, atau keberadaan kode referensi. | Mapping rusak dapat tersimpan lalu dipakai API. | Endpoint dev menolak mapping/jadwal yang tidak dapat ditemukan saat request. |

## 4. Ruang lingkup

### Termasuk

- Halaman admin konfigurasi kredensial incoming, penjamin, rentang hari booking, format nomor, TTL token, URL override Antrol, serta status kredensial Antrol dari PCare.
- Endpoint incoming Mobile JKN sesuai katalog FKTP V2.
- Mapping tabel yang sudah tersedia: `maping_poliklinik_pcare` dan `maping_dokter_pcare`.
- Validasi pasien, jadwal, dokter, kuota, duplikasi, dan transaksi registrasi.
- Log audit minimal untuk booking/pembatalan/pendaftaran baru.
- **Sinkronisasi keluar BPJS**: `antrean/add` dan `antrean/batal` menjadi syarat commit lokal ketika `antrol_enabled=1`; penolakan BPJS tidak boleh dilaporkan sebagai sukses lokal.
- Aksi manual **Antrol Dev** (`add`, `panggil`, `batal`) pada Rawat Jalan, tanpa mengganti tombol Antrol produksi.
- Rekonsiliasi marker booking Dev yang yatim setelah induk `reg_periksa` dihapus, tanpa mengubah alur penghapusan modul Rawat Jalan.
- **Generate jadwal 1 minggu**: satu klik mengambil referensi BPJS dan mengaktifkan semua slot eligible untuk 7 hari berturut-turut.
- Dokumentasi koleksi Postman dan contoh request/response.

### Tidak termasuk

- Mengubah atau menghapus `jkn_mobile_fktp` lama.
- Enkripsi/dekripsi respons BPJS, retry outbox idempoten, atau rekonsiliasi batch.
- Bridging pendaftaran PCare saat booking Dev; alur bisnis ini sengaja ditunda untuk penulisan ulang terpisah.
- Menebak prefix fisik loket A/B/C dari nama atau kode poli.

## 5. Aktor dan alur

1. Admin memasang modul dev, menyimpan konfigurasi, dan memastikan mapping poli/dokter serta jadwal tersedia.
2. Mobile JKN meminta token menggunakan `GET /auth`.
3. Untuk pasien belum ada, Mobile JKN memanggil `POST /peserta`; modul membuat RM secara atomik.
4. Mobile JKN meminta `POST /antrean` dengan dokter dan jam praktik spesifik.
5. Modul mengunci jadwal, memeriksa pasien/mapping/kuota/duplikasi, menyiapkan satu `reg_periksa`, lalu—jika outbound aktif—meminta penerimaan `antrean/add` BPJS sebelum commit lokal.
6. Mobile JKN membaca status atau sisa antrean dan dapat membatalkan menggunakan PUT selama status kunjungan masih `Belum`; jika outbound aktif, status lokal baru menjadi `Batal` setelah BPJS menerima `antrean/batal`.

## 6. Kontrak API

Semua respons memakai `Content-Type: application/json; charset=utf-8` dan amplop:

```json
{"metadata":{"message":"Ok","code":200},"response":{}}
```

Kode metadata: `200` sukses, `201` penolakan/validasi, `202` pasien belum terdaftar. Status HTTP mengikuti hasil transport: `200` sukses, `202` pasien belum terdaftar, `400` JSON rusak, `401` autentikasi gagal, `404` resource tidak ditemukan, `405` metode salah, `409` duplikasi pasien, `422` validasi/aturan bisnis, dan `500`/`503` kegagalan server/konfigurasi. Klien tetap harus membaca `metadata.code`.

| Metode | Path | Header | Fungsi |
| --- | --- | --- | --- |
| GET | `/auth` | `x-username`, `x-password` | Menerbitkan token berttl. |
| POST | `/antrean` | `x-token`, `x-username` | Mengambil antrean V2. |
| GET | `/antrean/status/{kodepoli}/{tanggal}` | `x-token`, `x-username` | Status V2 per poli; respons array per dokter/jam. |
| GET | `/antrean/sisapeserta/{nomorkartu}/{kodepoli}/{tanggal}` | `x-token`, `x-username` | Posisi antrean pasien yang sudah booking. |
| PUT | `/antrean/batal` | `x-token`, `x-username` | Membatalkan booking berstatus `Belum`. |
| POST | `/peserta` | `x-token`, `x-username` | Mendaftarkan pasien baru dan mengembalikan RM. |

### 6.1 Aturan autentikasi

- Nama header baku selalu `x-username`, `x-password`, dan `x-token`, tidak dikonfigurasi per instalasi.
- Nilai username/password dibandingkan konstan-waktu (`hash_equals`). Password tidak pernah ada dalam respons, token, atau log.
- Token adalah JWT HS256 dengan `sub`, `iat`, `exp`, dan `aud=jkn-mobile-fktp-dev`. Masa berlaku default 300 detik, configurable 60–3600 detik.
- Token hanya valid untuk username konfigurasi saat ini; perubahan secret langsung mencabut token lama.

### 6.2 `POST /antrean`

Field wajib V2: `nomorkartu` (13 digit), `nik` (16 digit), `nohp`, `kodepoli`, `tanggalperiksa`, `keluhan`, `kodedokter`, `jampraktek`, `norm`. `tanggalperiksa` harus kalender valid dan tidak lampau; maksimalnya ditentukan `booking_open_days` (default 30 hari). `jampraktek` harus `HH:MM-HH:MM` dan tepat sama dengan jadwal dokter setelah normalisasi menit.

Server memastikan satu `pasien` memiliki nomor kartu, NIK, dan RM yang konsisten. Jika pasien belum ada, respons adalah metadata `202` dan klien harus memakai `/peserta`; endpoint booking tidak membuat pasien diam-diam. Satu pasien hanya dapat memiliki satu booking aktif pada kombinasi tanggal dan poli. Kuota dan nomor registrasi dibuat di transaksi yang mengunci baris jadwal. Baris `reg_periksa` baru selalu berstatus `Belum`, `status_lanjut=Ralan`, dan `status_bayar=Belum Bayar`.

Respons sukses menyediakan `nomorantrean`, `angkaantrean`, `namapoli`, `sisaantrean`, `antreanpanggil`, `keterangan`, `kodedokter`, `namadokter`, dan `jampraktek`. Nomor tampilan memakai template admin default `{kodepoli}-{nomor}`; `angkaantrean` tetap angka urut lokal.

### 6.3 Status dan sisa

- Status tidak mencampur dokter. Setiap elemen daftar mewakili satu jadwal dokter pada poli/tanggal yang diminta.
- `totalantrean` menghitung booking yang tidak `Batal`; `sisaantrean` adalah `max(kuota-total, 0)`.
- `antreanpanggil` adalah nomor aktif paling awal berstatus `Berkas Diterima`; bila belum ada, `0` dalam format template.
- Sisa peserta hanya sukses jika nomor kartu tersebut memiliki booking aktif pada poli/tanggal yang sama. `nomorantrean` adalah milik pasien itu sendiri, bukan total antrean poli.

### 6.4 Pembatalan

Payload: `nomorkartu`, `kodepoli`, `tanggalperiksa`, dan `keterangan` opsional. Tanggal harus tidak lampau. Hanya kunjungan `Belum` yang dapat dipindahkan ke `Batal`; yang sudah diproses tidak diubah. Pembatalan idempoten secara fungsional: request berulang setelah sukses mengembalikan metadata 201 dengan pesan keadaan aktual, tanpa mutasi kedua. Booking ulang sesudah pembatalan pada pasien, poli, dan tanggal yang sama mengaktifkan kembali `reg_periksa`/`no_rawat` yang berstatus `Batal`; nomor registrasi dan nomor antrean dihitung ulang. Audit mencatat hasil pembatalan tanpa menyimpan alasan bebas dari payload.

### 6.5 Pasien baru

Semua field katalog wajib: nomor kartu, NIK, KK (masing-masing digit valid), nama, L/P, tanggal lahir valid tidak masa depan, HP, alamat, kode/nama wilayah, RW, dan RT. Modul memeriksa duplikasi nomor kartu dan NIK; nomor KK hanya divalidasi formatnya karena skema `pasien` mLITE tidak menyediakan kolom nomor KK yang dapat dijadikan kunci unik. Nomor RM diambil dengan lock `FOR UPDATE` dari `set_no_rkm_medis`; pembaruan counter dan insert pasien berada dalam satu transaksi. Propinsi, kabupaten/kota, kecamatan, dan kelurahan berasal dari pasangan kode+nama pada payload BPJS. Resolver memilih master lokal dengan kecocokan kode sekaligus nama; bila ID mLITE berbeda dari kode BPJS, resolver hanya boleh memakai satu kecocokan nama yang tepat. Wilayah yang tidak ditemukan atau ambigu ditolak. Tidak ada fallback ke nilai konfigurasi, `0`, atau baris master pertama. Form pasien juga menangani referensi wilayah lama yang sudah hilang sebagai nilai kosong, bukan mencetak warning PHP; perilaku toleran saat membaca data lama ini tidak memperlonggar validasi pasien baru.

## 7. Data dan ketergantungan

| Entitas | Penggunaan |
| --- | --- |
| `pasien` | Identitas, kartu JKN, NIK, nomor RM. |
| `jadwal` | Referensi master lokal saja; bukan sumber keputusan penerimaan Mobile JKN Dev. |
| `poliklinik`, `dokter` | Nama tampilan dan biaya registrasi. |
| `maping_poliklinik_pcare`, `maping_dokter_pcare` | Translasi kode BPJS ke kode lokal. |
| `reg_periksa` | Booking/antrean lokal. |
| `mlite_jkn_mobile_fktp_dev_booking` | Mengikat `no_rawat` dengan dokter/jam praktik yang dipilih agar kuota shift tidak tercampur. |
| `mlite_jkn_mobile_fktp_dev_schedule` | Snapshot kandidat BPJS dan checklist aktif per tanggal khusus modul Dev. |
| `set_no_rkm_medis` | Counter RM pasien baru. |
| `mlite_jkn_mobile_fktp_dev_log` | Audit minimal mutasi API dev. |

### 7.1 Integrasi Rawat Jalan tanpa mengganti perilaku produksi

- `mlite_jkn_mobile_fktp_dev_booking` adalah penanda asal booking Dev dan memiliki relasi logis ke `reg_periksa.no_rawat`.
- Modul Dev memasang extension point generik pada tampilan Rawat Jalan. Untuk baris yang mempunyai marker Dev, tombol langsung **Panggil JKN Dev** mengirim `antrean/panggil`; dropdown di sebelahnya menyediakan `Add Dev` dan `Batal Dev`. Tombol Antrol produksi yang sudah ada tetap utuh dan tetap menuju modul produksi. Baris non-Dev tidak diberi tombol Dev agar antrean produksi tidak salah sasaran.
- Aksi manual Dev memakai kode poli, kode dokter, dan jam praktik dari marker/snapshot Dev, bukan menebak dari jadwal produksi.
- Karena penghapusan kunjungan tetap menjadi tanggung jawab Rawat Jalan, modul Dev melakukan rekonsiliasi defensif saat modul diinisialisasi: marker yang induk `reg_periksa`-nya benar-benar tidak ada dihapus secara transaksional dan dicatat sebagai `integrity_cleanup`. Pembersihan idempoten dan memeriksa ulang `NOT EXISTS` sebelum delete.
- Rekonsiliasi tidak mengubah, membatalkan, atau membuat kunjungan Rawat Jalan, dan tidak menyentuh booking yang induknya masih ada.

Prasyarat UAT: kode penjamin BPJS tersedia dan aktif; setiap poli/dokter yang diuji memiliki mapping; jadwal untuk hari request ada; dan tabel inti mengikuti skema mLITE 6.x.

Antarmuka admin dev menyediakan lima akses dalam satu navigasi: **Pengaturan**, **Poli & Jadwal Online**, **Generate 1 Minggu**, **Mapping Poli**, **Mapping Dokter**, dan **Log Transaksi**. Kedua mapping dikelola secara manual dari data lokal mLITE agar tidak memicu request keluar ke BPJS saat UAT; perubahan mapping langsung dipakai endpoint dev.

Fitur **Generate 1 Minggu** mengambil referensi BPJS untuk tepat 7 hari berturut-turut mulai tanggal yang dipilih. Admin mencentang dokter/jam secara eksplisit; kandidat baru tidak aktif otomatis. Tujuh tanggal disimpan atomik ke tabel khusus Dev. `booking_open_days` hanya membatasi request pasien dan tidak memotong pemeriksaan referensi admin.

Halaman **Poli & Jadwal Online** menggunakan snapshot tersebut sebagai editor mingguan lokal. Setiap sel tanggal/poli/shift mempunyai dropdown dokter berisi kandidat BPJS yang sudah pernah tersimpan, beserta kapasitas yang tetap dapat diedit manual. Contoh pergantian 28 September, Poli Umum, shift pagi dari Yooshy ke Asna cukup memilih Asna pada sel tersebut lalu menekan **Simpan Dokter & Kapasitas**; tidak ada request baru ke BPJS. Penyimpanan mengaktifkan kandidat pengganti dan menonaktifkan kandidat lama secara atomik, menolak perpindahan lintas tanggal/poli/shift, mapping dokter yang tidak lagi unik, jadwal beririsan, atau dokter yang bertugas bersamaan pada poli lain. Booking yang sudah dibuat tetap terikat pada dokter dan jam lama; perubahan hanya berlaku untuk booking berikutnya.

Kunjungan lama yang tidak memiliki jejak pada tabel slot dev tidak dipetakan paksa ke salah satu shift; modul menampilkan dan menghitung kuota per shift untuk booking yang dibuat jalur dev. Rekonsiliasi antrean manual/legacy ke shift tertentu membutuhkan aturan operasional tersendiri, karena skema `reg_periksa` tidak menyimpan jam praktik.

## 8. Kebutuhan keamanan dan operasional

- Tidak ada CORS wildcard secara default. `allowed_origin` kosong berarti tidak menambahkan header CORS; isi satu origin eksplisit bila diperlukan.
- Endpoint yang salah metode menghasilkan metadata 201 dan HTTP 405/`Allow` yang sesuai.
- JSON invalid, field hilang, dan tanggal tidak valid ditolak deterministik tanpa notice PHP.
- Seluruh SQL baru melalui prepared statement. Exception database tidak diteruskan ke klien.
- Log tidak menyimpan password, token JWT, nomor kartu, NIK, atau payload identitas lengkap.
- Saat outbound aktif, hanya HTTP 200 dengan JSON ber-metadata sukses (`1` atau `200`) yang boleh dianggap diterima BPJS. Kegagalan koneksi/TLS, HTTP non-200, JSON rusak, metadata hilang, atau kode penolakan menggagalkan commit lokal dan masuk Log Transaksi.
- Kredensial signature Antrol wajib berasal dari namespace `pcare.*`; pengaturan Dev hanya menyimpan sakelar aktivasi dan URL override.

## 8a. Kontrak keluar BPJS Antrian FKTP

Diakses modul ini sebagai klien ke BPJS menggunakan header standar Antrol (tanpa X-authorization PCare):

```
GET  {antrol_url}/ref/poli/tanggal/{tanggal}
GET  {antrol_url}/ref/dokter/kodepoli/{kodepoli}/tanggal/{tanggal}
POST {antrol_url}/antrean/add
POST {antrol_url}/antrean/panggil   ← aksi manual Antrol Dev dari Rawat Jalan
POST {antrol_url}/antrean/batal
```

Semua request keluar memakai header:
```
X-cons-id: {pcare.consumerID}
X-timestamp: {unix_timestamp}
X-signature: base64(HMAC-SHA256("{consumer_id}&{timestamp}", pcare.consumerSecret))
user_key: {pcare.consumerUserKeyAntrol}
```

### antrean/add (POST) — harus diterima sebelum commit booking lokal saat outbound aktif
```json
{
  "nomorkartu":   "{13 digit}",
  "nik":          "{16 digit}",
  "nohp":         "{nomor HP pasien}",
  "kodepoli":     "{kode BPJS poli}",
  "namapoli":     "{nama poli}",
  "norm":         "{nomor RM}",
  "tanggalperiksa": "{yyyy-mm-dd}",
  "kodedokter":   12345,
  "namadokter":   "{nama dokter BPJS}",
  "jampraktek":   "{HH:MM-HH:MM}",
  "nomorantrean": "{format template, e.g. UMU-5}",
  "angkaantrean": 5,
  "keterangan":   ""
}
```

### antrean/batal (POST) — harus diterima sebelum commit pembatalan lokal saat outbound aktif
```json
{
  "tanggalperiksa": "{yyyy-mm-dd}",
  "kodepoli":       "{kode BPJS poli}",
  "nomorkartu":     "{13 digit}",
  "alasan":         "Pembatalan melalui Mobile JKN"
}
```

### antrean/panggil (POST) — aksi manual pada menu tambahan Antrol Dev di Rawat Jalan
```json
{
  "tanggalperiksa": "{yyyy-mm-dd}",
  "kodepoli":       "{kode BPJS poli}",
  "nomorkartu":     "{13 digit}",
  "status":         1,
  "waktu":          1616559330000
}
```
`status 1 = Hadir; status 2 = Tidak Hadir`. Waktu dalam timestamp milidetik.

## 9. Kriteria penerimaan dan UAT Postman

1. `GET /auth` dengan header benar menghasilkan token tanpa password; header salah ditolak.
2. Semua endpoint non-auth menolak token hilang, token kedaluwarsa, atau username tidak cocok.
3. Booking pasien belum terdaftar mengembalikan `metadata.code=202`, tidak membuat `reg_periksa`.
4. `POST /peserta` valid menghasilkan satu RM; pengiriman paralel tidak menghasilkan RM duplikat.
5. Booking valid menghasilkan tepat satu `reg_periksa`, sesuai dokter/jam request, dengan kuota berkurang satu.
6. Dua request booking paralel pasien yang sama hanya dapat membuat satu baris aktif; booking pada tanggal masa depan memeriksa duplikasi pada tanggal tersebut, bukan hari ini.
7. Booking dokter/jam tidak cocok, mapping hilang, kuota habis, atau tanggal lampau menghasilkan metadata 201 dan tidak ada mutasi.
8. Status menampilkan daftar per dokter/jam; sisa peserta mengembalikan nomor antrean milik peserta.
9. PUT batal untuk booking `Belum` mengubahnya menjadi `Batal`; PUT ulang dan booking yang sudah dilayani tidak mengubah data. Booking ulang sesudah Batal memakai `no_rawat` sebelumnya dan mendapatkan `no_reg` baru, bukan menambah ID kunjungan.
10. `POST /antrean/batal` menghasilkan 405; modul lama `jkn_mobile_fktp` tetap dapat diakses tanpa perubahan.
11. Koleksi `postman/jkn_mobile_fktp_dev.postman_collection.json` menyertakan uji sukses, autentikasi, method/JSON invalid, validasi pasien, booking ganda, pembatalan berulang, serta skenario kuota/race manual. Endpoint bawaannya adalah `https://mlite.klinikarrohman.com/jknmobilefktpdev`; request yang menulis data dipagari variabel `allowWrite=YES_DEV_ONLY`.
12. Bila outbound aktif, HTTP/metadata penolakan BPJS pada add atau batal menghasilkan kegagalan lokal; status booking tidak boleh terlihat sukses dan audit memuat `antrol_add`/`antrol_batal` dengan outcome error.
13. Pendaftaran pasien memakai wilayah yang dikirim BPJS; wilayah tidak dikenal/ambigu ditolak dan tidak boleh berubah menjadi wilayah default.
14. Penghapusan induk Rawat Jalan tidak menyisakan marker booking Dev setelah rekonsiliasi berikutnya; marker yang masih memiliki induk tidak boleh terhapus.
15. Kunjungan asal Dev menampilkan tombol langsung **Panggil JKN Dev** dan menu aksi Dev tambahan pada Rawat Jalan, sedangkan tombol dan URL Antrol produksi tetap tersedia dan tidak berubah.
16. Booking Dev belum menjalankan bridging pendaftaran PCare. Tidak boleh ada perubahan terselubung pada alur PCare dalam rilis ini.
17. Admin dapat mengganti dokter pada satu tanggal/poli/shift dari grid mingguan menggunakan kandidat snapshot BPJS tanpa fetch ulang; pergantian atomik, kapasitas tetap editable, konflik ditolak, dan booking lama tidak dipindahkan.

## 10. Risiko dan tindak lanjut

Katalog referensi bersifat contoh dan tidak menggantikan dokumen onboarding BPJS yang berlaku pada FKTP. Sebelum produksi, konfirmasi URL/versi, rentang hari booking, definisi status `Berkas Diterima`, format prefix, serta kebutuhan integrasi outbound dengan PIC BPJS. Fitur outbound sudah fail-closed terhadap penolakan BPJS, tetapi tetap memerlukan environment non-produksi, outbox/idempotency key, rekonsiliasi dua arah, audit operasional, dekripsi respons bila diwajibkan kontrak aktual, dan persetujuan eksplisit sebelum diaktifkan. Bridging pendaftaran PCare tetap menjadi pekerjaan terpisah dan tidak termasuk rilis ini.
