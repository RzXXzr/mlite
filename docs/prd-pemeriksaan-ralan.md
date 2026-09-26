# PRD: Perapihan Modul Pemeriksaan Rawat Jalan Paramedis

| Atribut | Nilai |
| --- | --- |
| Status | Implementasi inti versi dev telah diselaraskan; verifikasi staging WebSocket/PCare dan kebijakan hak khusus masih diperlukan |
| Modul implementasi | `plugins/pemeriksaan_ralan_dev` (Pemeriksaan Paramedis Dev) |
| Modul berjalan / referensi perilaku | `plugins/pemeriksaan_ralan` |
| Modul asal (referensi) | `plugins/dokter_ralan` |
| Integrasi antrean BPJS | Hanya `jkn_mobile_fktp`, bila modul aktif |
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
- Alur status perlu dikoreksi: versi dev masih memakai `pemeriksaan_ralan_dev.set_sudah` untuk menghasilkan `Sudah`, sedangkan alur TTV yang diminta menghasilkan `Berkas Dikirim`. Pada `rawat_jalan::postStatusRawat`, cabang `Berkas Dikirim` membuat `mutasi_berkas` tanpa memperbarui `reg_periksa.stts`; handler tersebut tidak dapat dipakai sebagai satu-satunya operasi transisi baru.
- Aksi tambah/panggil/batal antrean pada `rawat_jalan` memanggil handler `jkn_mobile_fktp` berbasis nomor RM dan tanggal hari ini. Integrasi yang dituju versi dev adalah FKTP saja; `jkn_mobile` untuk Antrol rumah sakit tidak menjadi jalur alternatif.

Temuan ini adalah indikator ruang lingkup teknis, bukan dasar untuk langsung menghapus data atau fitur lintas modul.

## 3. Tujuan

1. Menyediakan alur cepat dan jelas bagi paramedis untuk memilih pasien rawat jalan, mengisi TTV dan anamnesa awal, lalu menyimpannya ke rekam medis.
2. Memastikan dokter dapat melihat data awal tersebut beserta waktu dan petugas pencatatnya.
3. Menghilangkan antarmuka, endpoint, aset, dan dependensi yang khusus untuk kewenangan dokter atau pelayanan lanjutan.
4. Menyeragamkan seluruh identitas teknis dan teks antarmuka menjadi `pemeriksaan_ralan_dev` / **Pemeriksaan Awal Paramedis (Dev)** selama uji coba.
5. Mempertahankan kompatibilitas data pemeriksaan ralan yang sudah ada.
6. Mengantar pasien yang telah selesai TTV ke antrean poli melalui status `Berkas Dikirim` dan menyinkronkan tindakan antrean BPJS yang relevan.

## 4. Di luar ruang lingkup

Hal berikut tidak dibangun atau dioperasikan oleh modul ini:

- diagnosis dan prosedur ICD-10/ICD-9;
- resep reguler/racikan, e-resep, salin resep, stok obat, dan aturan pakai;
- tindakan medis, permintaan laboratorium, dan permintaan radiologi;
- kontrol BPJS, SEP, dan integrasi BPJS/PCare khusus layanan dokter di luar antrean dan pengecekan kepesertaan yang dijelaskan pada FR-08/FR-10;
- odontogram, OHIS, resume pasien, penilaian medis rawat jalan;
- surat rujukan, surat sehat, dan surat sakit;
Fitur-fitur tersebut tetap menjadi tanggung jawab modul dokter/layanan terkait. Modul ini tidak boleh menjadi jalur alternatif untuk membuat atau mengubah data tersebut.

Pembatasan di atas berlaku untuk pembuatan/perubahan data. Pembacaan diagnosis, prosedur, obat, SOAP lengkap, dan rawat inap dalam riwayat pasien diizinkan sebagai konteks pemeriksaan awal (FR-04). Rekam medis lengkap tetap menggunakan halaman milik modul `pasien`.

Pengecekan kepesertaan BPJS hanya-baca termasuk ruang lingkup FR-08. Operasi antrean FKTP (tambah, panggil, batal) merupakan ruang lingkup terpisah FR-10; operasi ini tidak membuat kunjungan, rujukan, resep, atau perubahan data kepesertaan PCare.

## 5. Pengguna dan hak akses

| Peran | Kebutuhan |
| --- | --- |
| Paramedis/perawat | Melihat daftar pasien sesuai akses poli, memanggil/menambah antrean sesuai hak, memilih lanjut SOAP atau batal pada status `Belum`, mengisi pemeriksaan awal, serta mengubah/menghapus catatan miliknya sesuai kebijakan institusi. |
| Dokter | Membaca hasil pemeriksaan awal melalui rekam medis/modul dokter; tidak memakai modul ini untuk diagnosis, terapi, atau resep. |
| Administrator | Mengatur akses poli, hak panggil/batal, mapping Antrol, dan rekonsiliasi kegagalan sinkronisasi; dapat melakukan koreksi sesuai hak admin dan jejak audit. |

Target otorisasi adalah akses berbasis akun pegawai/paramedis dan cakupan poli (*CAP*), bukan berdasarkan kecocokan `kd_dokter` dengan username pengguna. Administrator tetap memiliki akses lintas poli sesuai kebijakan sistem.

## 6. Alur pengguna target

```text
Daftar pasien rawat jalan (`reg_periksa.stts` ditampilkan apa adanya)
        -> panggil pasien (WebSocket/TTS; untuk antrean FKTP yang terdaftar, kirim panggil Antrol)
        -> pilih kunjungan
        -> jika `Belum`: pilih lanjut ke form SOAP atau Batal Periksa
        -> jika status lain: langsung lihat SOAP/riwayat, tanpa dialog awal
        -> lanjut form: isi TTV + anamnesa, lalu simpan secara atomik
        -> ubah `reg_periksa.stts` menjadi `Berkas Dikirim`, catat `mutasi_berkas.dikirim`
        -> dokter/poli menerima antrean; `Berkas Diterima` dan status lanjutan dikelola pemilik alurnya
```

## 7. Kebutuhan fungsional

### FR-01 — Daftar pasien

- Tampilkan kunjungan rawat jalan pada rentang tanggal yang dipilih, tidak termasuk poli IGD seperti perilaku modul saat ini.
- Filter minimal: tanggal/rentang tanggal dan status rawat sebenarnya; tersedia pintasan `Belum`, `Berkas Dikirim`, `Berkas Diterima`, `Sudah`, `Batal`, dan status lanjutan lain. Keberadaan SOAP ditampilkan sebagai indikator terpisah.
- Batasi daftar paramedis non-admin ke poli yang diizinkan untuk akunnya.
- Kolom daftar minimal: nomor RM, nama pasien, nomor rawat/kunjungan, antrean, poli, dokter tujuan, penjamin, tanggal kunjungan, dan status.
- Jangan tampilkan tombol atau aksi dokter dari daftar pasien.
- Tampilkan status antrean FKTP (belum didaftarkan, terdaftar, dipanggil, batal, gagal sinkron) hanya jika `jkn_mobile_fktp` aktif; status panggilan anjungan tetap terpisah. Panggilan/pendaftaran ulang dan pembatalan hanya tersedia sesuai matriks FR-05/FR-10.

### FR-02 — Form pemeriksaan awal

- Setelah paramedis memilih pasien, tampilkan identitas pasien yang hanya-baca dan waktu pencatatan.
- Saat membuka kunjungan berstatus `Belum`, tampilkan pilihan **Lanjut ke form SOAP** atau **Batal Periksa**. Membuka form tidak mengubah status; jika pasien hanya ingin dilihat catatannya, form/riwayat tetap dapat dibuka. Untuk status selain `Belum`, langsung buka form/riwayat tanpa dialog perubahan status.
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
- Untuk instalasi FKTP, gunakan `jkn_mobile_fktp.kd_pj` sebagai kode penjamin BPJS bila terisi; bila belum dikonfigurasi, pengenalan BPJS pada tampilan kepesertaan dapat memakai kode `BPJ` atau nama penjamin yang mengandung BPJS/JKN. Kelayakan mutasi antrean FKTP tetap memerlukan modul `jkn_mobile_fktp` aktif dan kode penjamin yang terkonfigurasi, bukan hanya hasil pengenalan dari nama penjamin.
- Setelah data lokal diterima, otomatis panggil endpoint PCare yang sudah digunakan modul Pasien/PCare: `GET /pcare/byjeniskartu/noka/{nomor}` untuk kartu 13 digit. Bila kartu belum valid tetapi NIK 16 digit tersedia, gunakan `GET /pcare/byjeniskartu/nik/{nomor}`. Nomor dan URL diturunkan dari data pasien di server, bukan dari input bebas pada halaman pemeriksaan.
- Route PCare yang ada tetap menangani token sesi, izin akses, konfigurasi kredensial, signature, dekripsi, dan dekompresi respons. Kredensial tidak dikirim ke browser; versi dev tidak menggandakan service/algoritme PCare.
- Aktivasi `pcare` untuk pengecekan kepesertaan hanya-baca dan aktivasi `jkn_mobile_fktp` untuk mutasi antrean diperiksa terpisah. Tidak aktifnya modul antrean FKTP tidak menyembunyikan identitas pasien atau menghentikan form SOAP.
- Data lokal dan form tetap tersedia ketika PCare lambat, gagal, tidak aktif, tidak berizin, atau nomor kartu/NIK belum lengkap. Beri status dan tombol **Cek ulang** bila endpoint pengecekan tersedia. Request PCare dibatasi 35 detik di browser, sedangkan service yang ada membatasi request eksternal 30 detik.
- Bedakan **Memeriksa**, **Data diterima**, dan **Belum terverifikasi**. **Data diterima** berarti respons peserta berhasil diterima, bukan otomatis status kepesertaan aktif.
- Nilai PRB/Prolanis `null`, string `null`, kosong, `-`, atau tidak tersedia ditampilkan **Tidak ada informasi**. Nilai boolean/kode eksplisit positif (`true`, `1`, `ya/y/yes`) menjadi **Terdaftar**; negatif (`false`, `0`, `tidak/n/no`) menjadi **Tidak terdaftar**. Deskripsi/kode lain ditampilkan sesuai respons tanpa menebak maknanya. Status `aktif` mengikuti pola yang sama dengan label **Aktif/Tidak aktif**.
- Tampilkan sumber PCare dan waktu pengecekan pada perangkat. Pada cek ulang, hasil lama dikosongkan hingga hasil baru diterima; kegagalan tidak boleh meninggalkan status lama yang tampak masih terverifikasi.
- Abaikan respons dari pasien atau request sebelumnya. Pengecekan kartu menolak respons dengan nomor kartu yang berbeda; nomor kartu respons harus valid. Cegah permintaan ganda selama pengecekan sedang berjalan.
- Tidak menyimpan hasil kepesertaan ke database, cache permanen, localStorage, atau console. Pengecekan tidak mengubah identitas pasien, profil alergi, draf pemeriksaan, maupun status kunjungan. Nomor kartu hasil pencarian NIK hanya memperbarui tampilan sesi ini.

### FR-05 — Simpan dan status kunjungan

- Simpan catatan pada tabel `pemeriksaan_ralan` dengan `nip` dari sesi pengguna, bukan nilai kiriman klien.
- Catatan yang sama (nomor rawat, tanggal, dan jam) diperbarui, bukan digandakan. Ini mengikuti kunci primer tabel saat ini; NIP adalah atribut pencatat, bukan bagian dari kunci.
- Saat membuat pemeriksaan awal dari status `Belum`, keberhasilan simpan mengubah `reg_periksa.stts` menjadi `Berkas Dikirim` serta mencatat waktu serah berkas pertama di `mutasi_berkas.dikirim` dengan `mutasi_berkas.status='Sudah Dikirim'`. Perubahan SOAP, status, dan mutasi berkas harus atomik dalam transaksi database. Saat membuat baris `mutasi_berkas`, isi kolom wajib lain sesuai skema aktif tanpa menimpa nilai `diterima` yang sudah ada. `Berkas Dikirim` berarti TTV selesai dan pasien dapat menunggu pelayanan poli, bukan pemeriksaan dokter selesai.
- Setting lama `pemeriksaan_ralan_dev.set_sudah` tidak lagi dibaca. Versi dev tidak menyediakan toggle status setelah simpan: hasil pertama yang sah selalu `Berkas Dikirim`. Konfigurasi modul berjalan `pemeriksaan_ralan` tetap terpisah.
- Modul dev ditetapkan untuk penggunaan FKTP sesuai keputusan ruang lingkup FR-10. FKTP/FKTL bukan pilihan pengguna dan tidak disediakan sebagai toggle. Konfigurasi kredensial, mapping, penjamin, PCare, dan WebSocket tetap dikelola oleh modul pemiliknya.
- Jika status berubah sejak form dibuka, server menolak transisi yang tidak valid dan meminta muat ulang; request klien tidak dapat menentukan nilai `stts` akhir. Simpan baru dari `Batal`, `Berkas Diterima`, `Sudah`, `Dirujuk`, `Meninggal`, `Dirawat`, atau `Pulang Paksa` tidak mengubah status dan tidak boleh membuat pasien kembali antre.
- Koreksi catatan yang sudah ada tetap tunduk pada hak pemilik/admin. Jika koreksi diizinkan pada `Berkas Dikirim`, hanya isi catatan yang berubah; waktu `mutasi_berkas.dikirim` tidak ditimpa dan transisi status tidak diulang. Pada status lanjutan, form dibuka sebagai baca saja sampai kebijakan koreksi klinis disetujui.
- **Batal Periksa** adalah aksi eksplisit dari status `Belum`, memerlukan alasan, hak petugas, dan konfirmasi. Server mengubah `reg_periksa.stts` menjadi `Batal` dengan pemeriksaan ulang status di dalam transaksi; tidak membuat SOAP kosong. Pembatalan Antrol dijalankan sesuai FR-10. Pasien batal tetap dapat membuka riwayat/ SOAP tanpa dialog awal.

Matriks status rawat (`reg_periksa.stts`; label UI mengikuti istilah pengguna):

| Nilai database | Label UI | Saat buka dari daftar | Panggil/ubah status oleh modul ini | Simpan SOAP |
| --- | --- | --- | --- | --- |
| `Belum` | Belum Periksa | Dialog pilihan **Lanjut ke form SOAP** / **Batal Periksa** | Boleh panggil; boleh batal setelah konfirmasi | Catatan baru berhasil → `Berkas Dikirim` |
| `Berkas Dikirim` | Berkas Dikirim | Langsung lihat form dan riwayat | Tidak panggil ulang sebagai pasien baru; tidak batal otomatis | Koreksi catatan berizin tanpa mengulang transisi |
| `Berkas Diterima` | Berkas Diterima | Langsung lihat form dan riwayat | Tidak berubah | Baca saja secara default |
| `Sudah` | Sudah Periksa | Langsung lihat form dan riwayat | Tidak berubah | Baca saja secara default |
| `Batal` | Batal Periksa | Langsung lihat form dan riwayat | Tidak berubah; pembukaan kembali melalui alur pendaftaran | Baca saja |
| `Dirujuk`, `Meninggal`, `Dirawat`, `Pulang Paksa` | Pasien Dirujuk, Meninggal, Dirawat, Pulang Paksa | Langsung lihat form dan riwayat | Tidak berubah | Baca saja |

Indikator **SOAP/TTV tercatat** dihitung dari keberadaan `pemeriksaan_ralan`, terpisah dari `stts`: catatan historis bisa ada pada status `Batal`/`Sudah`, dan `Berkas Dikirim` tidak boleh disimpulkan hanya dari ada/tidaknya SOAP.
Jika data lama sudah `Berkas Dikirim` tetapi SOAP atau waktu `dikirim` tidak ada, tampilkan **Perlu rekonsiliasi** untuk admin; jangan diam-diam menciptakan catatan klinis atau mengubah status dari layar baca.

### FR-09 — Kandidat Prolanis berbasis riwayat diagnosis

- Saat pasien rawat jalan dibuka, modul dapat menampilkan indikator kandidat Program Prolanis berdasarkan diagnosis pasien pada seluruh kunjungan: hipertensi (`I10`/`I11%`) dan diabetes (`E10%`–`E14%`).
- Pengecekan ini hanya memberi tanda kandidat dan kode diagnosis yang mendasari; tidak mendaftarkan pasien ke Prolanis dan tidak mengubah data PCare.
- Jika respons PCare menyatakan pasien sudah memiliki Prolanis (`pstProl` positif), indikator kandidat disembunyikan. Nilai PCare kosong/tidak diketahui tidak boleh dianggap sudah terdaftar.
- Endpoint `POST kandidatprolanis` harus memverifikasi kunjungan aktif dan CAP seperti endpoint pasien lainnya, mengambil `no_rkm_medis` dari server, menggunakan query terparameterisasi, serta mengembalikan `has_ht`, `ht_codes`, `has_dm`, dan `dm_codes`.
- Kegagalan pengecekan kandidat tidak boleh menghambat form pemeriksaan atau pengecekan kepesertaan BPJS.

### FR-06 — Panggilan antrean melalui WebSocket

- Tombol **Panggil** pada daftar pasien dipertahankan untuk memanggil pasien menuju **Pemeriksaan Awal**. Tombol hanya aktif pada status `Belum` bagi peran yang berhak memanggil dan ketika fitur WebSocket atau fallback TTS tersedia. ACK anjungan berarti pesan diterima anjungan; ACK tidak membuktikan pasien datang dan tidak mengubah `reg_periksa.stts`.
- Saat koneksi terbuka, klien mengirim pesan `panggil` dengan identitas korelasi unik `msgId`, modul `pemeriksaan_awal`, serta nomor antrean, nama pasien, poli, dan nama pemanggil.
- Anjungan menampilkan dan menyuarakan panggilan, lalu mengirim `panggil_ack` dengan `msgId` yang sama. Tombol menampilkan status berhasil hanya setelah ACK yang cocok diterima.
- Bila koneksi tidak tersedia atau ACK tidak tiba dalam batas waktu, UI menggunakan Text-to-Speech lokal sebagai fallback dan memberi tahu pengguna bahwa panggilan anjungan belum terkonfirmasi.
- Panggilan, ACK, timeout, kegagalan koneksi, dan fallback perlu dicatat pada log aplikasi/audit tanpa menyimpan data klinis berlebihan.
- Bila antrean FKTP berlaku, satu klik memulai panggilan lokal/anjungan dan sinkronisasi `antrean/panggil` melalui server; status kedua jalur ditampilkan secara terpisah. Kegagalan BPJS tidak membatalkan suara panggilan lokal. Jangan menyatakan panggilan BPJS berhasil hanya karena WebSocket menerima ACK, atau sebaliknya.

### FR-10 — Integrasi antrean JKN Mobile FKTP dan waktu milidetik

- Satu-satunya sumber integrasi antrean versi dev adalah `jkn_mobile_fktp`. Server mengecek `$this->core->ActiveModule('jkn_mobile_fktp')` sebagaimana mekanisme modul aktif mLite. Jika tidak aktif, seluruh aksi dan request tambah/panggil/batal Antrol FKTP tidak dijalankan; pemeriksaan, status rawat lokal, WebSocket, dan TTS tetap bekerja. Tidak ada fallback ke `jkn_mobile` atau layanan antrean lain.
- Jika `jkn_mobile_fktp` aktif, versi dev menggunakan aturan/kredensial dan kontrak FKTP yang sudah ada untuk `antrean/add`, `antrean/panggil`, dan `antrean/batal`. Browser mengirim `no_rawat` kunjungan aktif ke endpoint dev ber-token; server meneruskan ke jalur FKTP setelah validasi, bukan meminta browser memanggil handler GET berbasis nomor RM yang memutasi BPJS.
- Setelah gerbang aktivasi lolos, kelayakan pasien ditentukan dari `reg_periksa.kd_pj = jkn_mobile_fktp.kd_pj`, kredensial Antrol FKTP, nomor kartu/NIK yang dibutuhkan, serta mapping `maping_poliklinik_pcare` dan `maping_dokter_pcare`. Penjamin non-JKN atau konfigurasi tidak lengkap tidak memanggil Antrol dan menampilkan alasannya. Keberhasilan membaca kepesertaan PCare pada FR-08 tidak menggantikan validasi ini.
- **Tambah antrean** hanya untuk kunjungan terpilih yang belum dibatalkan dan belum tercatat sukses di Antrol. Gunakan `no_rawat` sebagai identitas lokal; turunkan tanggal, RM, `no_reg`, poli, dokter, dan penjamin dari `reg_periksa` di server. Tampilkan status remote dan aksi **Tambahkan antrean** setelah dipastikan booking FKTP belum ada. Karena handler `rawat_jalan` lama tidak menyimpan hasilnya secara lokal, status tanpa jejak harus ditandai **Belum diketahui** dan direkonsiliasi dengan BPJS/proses pendaftaran sebelum mengirim tambah baru. Jangan otomatis menambah antrean setiap form dibuka, dipanggil, atau disimpan. Nomor antrean mengikuti pemetaan poli yang dikonfigurasi; kode `A/B/C` berbasis nama poli pada handler lama tidak dijadikan aturan bisnis baru.
- **Panggil antrean** FKTP menggunakan tanggal, kode poli terpetakan, nomor kartu, `status=1`, dan `waktu` epoch Unix dalam milidetik dari server. Hanya kirim bila tambah antrean telah terkonfirmasi atau keberadaannya telah direkonsiliasi. Panggilan ulang harus menjadi aksi eksplisit dengan waktu kejadian baru; klik ganda tidak mengirim dua request.
- **Batal antrean** FKTP mengirim tanggal, kode poli, nomor kartu, dan alasan aktual dari petugas. Saat **Batal Periksa** dikonfirmasi, status lokal `Batal` dicatat terlebih dahulu. Untuk kunjungan yang memenuhi gerbang FKTP, pembatalan tetap dicoba walaupun jejak tambah tidak ada di outbox dev karena booking mungkin dibuat oleh kanal FKTP lain. Jika BPJS gagal/menolak, kunjungan tetap batal lokal dan UI menampilkan **Batal lokal; sinkronisasi BPJS gagal** beserta aksi coba lagi/rujuk ke petugas berwenang. Jangan mengganti alasan dengan alasan statis “perubahan jadwal dokter”.
- Operasi tambah/panggil/batal bersifat idempoten menurut `no_rawat + operasi + identitas kejadian`, punya kunci korelasi dan status `pending/success/failed/needs_review`; simpan kode/pesan respons, waktu kejadian, waktu kirim, jumlah percobaan, dan petugas. Tanggapan `200` atau kode “sudah ada” yang terdokumentasi dapat direkonsiliasi; timeout tidak boleh langsung dianggap gagal definitif atau memicu penambahan duplikat. Atur timeout dan retry terbatas, dengan pengecekan status remote bila API mendukungnya.
- Handler FKTP lama memakai GET untuk efek samping, mencari kunjungan dari nomor RM + tanggal hari ini, belum menyimpan status add/panggil/batal lokal, dan handler batal menulis payload sebelum respons JSON. Adapter dev harus memvalidasi kunjungan `no_rawat`, CAP poli, hak aksi, tanggal layanan, nomor kartu/mapping, kredensial Antrol yang tepat, serta respons `metadata`/`metaData`; pisahkan hasil lokal, WebSocket, dan BPJS. Hindari pemanggilan endpoint lama langsung dari browser dev sampai kontrak ini diperbaiki.

Waktu panggilan BPJS dibuat pada server dalam timezone aplikasi yang benar lalu dikonversi menjadi epoch Unix milidetik, misalnya `round(microtime(true) * 1000)`; simpan sebagai integer 13 digit (`BIGINT`) bersama waktu lokal untuk audit. `strtotime(...)*1000` pada handler lama hanya memberi ketelitian detik. Timestamp header autentikasi BPJS adalah **detik UTC**, berbeda dari `waktu` kejadian dalam **milidetik**. Timestamp kejadian tidak berasal dari jam browser, tidak dibuat ulang saat retry, dan tidak boleh lebih awal dari pendaftaran antrean atau lebih akhir dari pembatalan yang sudah tercatat.

| Operasi di modul dev | Jalur yang ada di repositori | Waktu yang dibutuhkan |
| --- | --- | --- |
| Tambah antrean FKTP | `jkn_mobile_fktp` → `antrean/add` | Payload handler yang ada belum memakai `waktu` milidetik; tanggal/jadwal berasal dari kunjungan/mapping. |
| Panggil antrean FKTP | `jkn_mobile_fktp` → `antrean/panggil` | `waktu` kejadian Unix milidetik; `status=1` sesuai handler yang ada. |
| Batal antrean FKTP | `jkn_mobile_fktp` → `antrean/batal` | Payload handler yang ada memakai alasan; simpan waktu kejadian lokal untuk audit/retry. |

Kontrak endpoint internal yang perlu dibuat pada versi dev:

| Endpoint POST | Input klien | Pemeriksaan server dan hasil |
| --- | --- | --- |
| `statuskunjungan` | `no_rawat` | Mengembalikan `stts`, indikator SOAP, dan hak aksi setelah verifikasi kunjungan/CAP; dipanggil ulang sebelum membuka dialog dan sebelum mutasi. |
| `batalperiksa` | `no_rawat`, `alasan` | Mengubah `Belum` → `Batal` sekali, mencatat petugas/waktu/alasan, lalu mencoba pembatalan remote untuk kunjungan yang memenuhi gerbang FKTP. Kunci idempotensi diturunkan server dari `no_rawat`, bukan dipercaya dari klien. |
| `antreanstatus` | `no_rawat` | Mengembalikan `module_active=false` tanpa panggilan BPJS bila FKTP tidak aktif; bila aktif, mengembalikan kelayakan pasien, identitas antrean remote yang diketahui, dan status tambah/panggil/batal tanpa mutasi. |
| `antreantambah` | `no_rawat` | Menurunkan payload dari data server, menahan duplikasi dengan kunci tetap per kunjungan, mengirim tambah antrean melalui adapter FKTP, dan menyimpan hasil/retry. |
| `antreanpanggil` | `no_rawat`, `msgId` panggilan | Memastikan antrean terdaftar, mengirim `status=1` dan `waktu` milidetik sekali per panggilan, lalu mengembalikan status remote terpisah dari ACK WebSocket. |
| `antreanbatalulang` | `no_rawat` | Hanya mengulang sinkronisasi pembatalan yang belum terkonfirmasi; menggunakan alasan dan waktu kejadian awal, tidak membatalkan kunjungan kedua kali. |

Semua endpoint mutasi memerlukan token sesi, akun pegawai, CAP poli, hak aksi spesifik, metode POST, validasi status terkini, dan respons JSON yang konsisten. Browser tidak boleh mengirim kartu, kode poli, waktu BPJS, atau status rawat tujuan sebagai nilai yang dipercaya server. Setiap endpoint Antrol memeriksa kembali apakah `jkn_mobile_fktp` masih aktif pada saat request diterima. Pada transaksi simpan/batal, buat catatan outbox lokal bila antrean FKTP perlu disinkronkan; panggil BPJS setelah commit agar kegagalan jaringan tidak menahan transaksi database.

## 8. Kebutuhan nonfungsional dan keamanan

- Semua query harus terparameterisasi atau memakai query builder. Nilai tanggal, status, poli, dan identitas pengguna tidak boleh dirangkai langsung ke SQL.
- Semua endpoint mutasi harus memeriksa token sesi, autentikasi, otorisasi poli, dan kepemilikan catatan bila berlaku.
- Validasi server mencakup pasien/kunjungan valid, hak akses pengguna, field wajib, serta tipe/rentang TTV yang disepakati pemilik klinis.
- Respons API yang dipakai UI berbentuk konsisten (`status`, `message`, dan `data` bila ada); kesalahan tidak membocorkan detail SQL.
- Jalur simpan pemeriksaan tidak bergantung pada layanan BPJS/farmasi. Pengecekan kepesertaan hanya-baca memakai route PCare yang sudah ada sesuai FR-08; gangguan layanan tidak menghambat input pemeriksaan.
- Transisi `Belum` → `Berkas Dikirim` dan `Belum` → `Batal` memakai pemeriksaan ulang status pada server, transaksi dan pembatasan kunjungan/poli. Perubahan ke `Berkas Diterima`, `Sudah`, atau status lanjutan milik alur terkait tidak boleh ditimpa oleh request terlambat dari form paramedis.
- Operasi Antrol dilakukan oleh server dengan kredensial tersimpan, hak aksi terpisah, log minimal, dan retry terkontrol; kegagalan jaringan eksternal tidak membatalkan transaksi klinis yang telah berhasil.
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
| Panggilan | `pemeriksaan_ralan_dev` (browser) | `action: "panggil"`, `modul: "pemeriksaan_awal"`, `msgId`, `data.nm_pasien`, `data.nm_poli`, `data.no_reg`, `data.nm_pemanggil` | Dipertahankan sebagai kontrak minimal. Tambahkan `schemaVersion`, waktu kirim, dan tujuan/ruang anjungan bila broker tidak lagi broadcast global. |
| Konfirmasi | Anjungan | `action: "panggil_ack"`, `modul`, `msgId`, `source: "anjungan"` | Wajib dicocokkan dengan `msgId`; ACK terlambat tidak boleh mengubah hasil panggilan baru. |
| Pendaftaran baru | Modul rawat jalan/IGD | `action: "simpan"`, `modul: "rawat_jalan"` atau `"igd"` | Klien pemeriksaan memuat ulang daftar hanya jika form pemeriksaan tidak sedang terbuka. Pertahankan perilaku ini setelah diuji. |
| Perubahan status | `pemeriksaan_ralan_dev` setelah commit server | `action: "update_status"`, `modul: "pemeriksaan_ralan_dev"`, `data.no_rawat`, `data.new_status` | Anjungan saat ini me-refresh pada setiap `action: "update_status"` tanpa memeriksa `modul`; kirim hanya setelah database sukses. Anjungan memuat ulang status dari server. |

Kebijakan ketahanan dan keamanan yang diperlukan:

- Gunakan `wss://` pada lingkungan HTTPS; endpoint broker harus hanya dapat diakses dari jaringan/asal yang disetujui.
- Implementasi saat ini membroadcast ke semua klien dan tidak mengautentikasi payload. Sebelum dipakai lintas jaringan, broker perlu autentikasi sesi atau token singkat, validasi skema, pembatasan ukuran/rate, dan kanal/ruang per poli atau anjungan.
- `msgId` dibuat per klik. Satu pemanggilan hanya boleh memiliki satu timer ACK; timer harus dibersihkan saat ACK, koneksi tutup, atau pengguna meninggalkan halaman.
- Versi dev sudah memakai *backoff* 1–30 detik dan satu pengelola koneksi. Saat koneksi tutup, timer panggilan yang tertunda perlu diselesaikan/dibersihkan tanpa menciptakan ACK palsu; status koneksi, retry, dan fallback tetap terlihat.
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
| `mutasi_berkas` | PK/FK `no_rawat` → `reg_periksa` | `status`, `dikirim`, `diterima` | Waktu serah berkas ke antrean poli; `dikirim` diisi pertama kali saat TTV selesai. Tidak menjadi alasan untuk menghubungi Antrol rumah sakit dari modul dev. |
| `mlite_modules` | direktori modul aktif | `dir='jkn_mobile_fktp'` | Sumber gerbang aktivasi melalui `ActiveModule('jkn_mobile_fktp')`. |
| `mlite_settings` | konfigurasi lintas modul | `pemeriksaan_ralan_dev.set_sudah` (warisan, diabaikan), `jkn_mobile_fktp.kd_pj`, kredensial PCare/Antrol FKTP, dan WebSocket umum | Modul dev tidak memiliki setting lokal. `kd_pj` dan konfigurasi integrasi dibaca dari modul pemilik; `set_sudah` tidak memengaruhi workflow. |
| Data sinkronisasi antrean dev (tabel baru, desain migrasi) | Unik `no_rawat + jenis_operasi + kunci_kejadian` | `no_rawat`, `operation`, identitas remote FKTP, `event_at_ms BIGINT`, `requested_at`, `status`, `attempt_count`, `response_code`, `response_message`, `actor_nip` | Outbox/idempotensi, audit, dan retry antrean FKTP hanya saat `jkn_mobile_fktp` aktif. Struktur final diselaraskan dengan data antrean FKTP yang sudah tersedia sebelum migrasi. |

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
Beranda Pemeriksaan Paramedis (`index.html`)
└── Antrean Pemeriksaan (`manage.html`)
    ├── daftar pasien (`display.html`)
    ├── pemeriksaan dan editor alergi (`form.pemeriksaan.html`)
    ├── riwayat kunjungan aktif (`riwayat.html`)
    └── seluruh riwayat pasien (`riwayat.pasien.html`)

Kode warisan yang masih dapat dirute:
├── rincian layanan, obat, resep, racikan, lab, radiologi
├── ICD-10/ICD-9, kontrol/BPJS, odontogram/OHIS
└── resume, penilaian medis, dan surat
```

### 11.2 Halaman inti yang dipertahankan dan ditingkatkan

| Halaman saat ini | Fungsi saat ini | Masalah/ruang perbaikan | Keputusan target |
| --- | --- | --- | --- |
| `index.html` | Sebelumnya hanya dua kartu generik menuju Kelola dan Pengaturan. | Tidak menjelaskan tujuan modul, workflow, kesiapan integrasi, atau tindakan utama. | Jadikan beranda operasional dengan satu CTA ke antrean, tiga langkah kerja, serta status baca-saja FKTP, JKN, PCare, dan WebSocket. Gunakan sistem visual yang sama dengan halaman pemeriksaan. |
| `manage.html` | Kontainer yang menggabungkan daftar, alergi, form, dan hasil riwayat. | Alur status baru belum didukung versi dev. | Pertahankan satu halaman; untuk `Belum` tampilkan dialog **Lanjut ke form SOAP / Batal Periksa**, untuk status lain langsung lihat form/riwayat. Status remote Antrol dan status lokal ditampilkan terpisah. |
| `display.html` | Daftar kunjungan, filter periode/status, pemilihan pasien, dan tombol **Panggil** yang mengirim panggilan WebSocket. | Status saat ini disimpulkan dari ada/tidaknya SOAP; belum ada aksi antrean FKTP. | Tampilkan nilai `reg_periksa.stts` sebenarnya, indikator SOAP terpisah, penjamin, status antrean FKTP, serta aksi **Tambah/Panggil/Batal** sesuai matriks status dan hak akses. Pencarian/paging server-side bila volume besar. |
| `form.pemeriksaan.html` | Form ringkas TTV, kesadaran, antropometri, anamnesa, temuan awal, dan editor alergi yang dapat dilipat. | Kepadatan vertikal dan state edit/baca harus tetap konsisten di berbagai ukuran layar. | Gunakan grid responsif, satuan menyatu pada input, status draf yang jelas, validasi per-field, dan action bar simpan yang konsisten. |
| `riwayat.html` / `riwayat.pasien.html` | Riwayat aktif dan seluruh kunjungan tampil berdampingan dengan form. | Informasi panjang berisiko sulit dipindai dan aksi dapat tercampur dengan data baca-saja. | Gunakan kartu/accordion seragam, pill TTV, hirarki metadata yang konsisten, serta aksi edit/hapus/salin hanya pada kondisi yang diizinkan server. |
| `settings.html` | Sebelumnya memuat `set_sudah`, lalu hanya menjadi halaman informasi. | Tidak ada nilai lokal yang sah untuk diubah; halaman menambah navigasi dan berpotensi menyiratkan FKTP/FKTL atau status akhir dapat dipilih. | Hapus halaman dan rute navigasinya. Tampilkan ringkasan aturan tetap pada beranda; arahkan admin ke modul JKN Mobile FKTP hanya untuk konfigurasi integrasi miliknya. |

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

- Fase implementasi sebelumnya tidak menghapus atau mengubah skema tabel. Revisi FR-05/FR-10 memerlukan pemeriksaan skema aktif: `mlite_dump.sql` mencantumkan `Berkas Dikirim` pada enum `reg_periksa.stts`, sedangkan `mlite_db.sql` belum. Bila skema aktif belum memuat nilai itu, gunakan contoh migrasi `plugins/pemeriksaan_ralan_dev/sql/status_berkas_dikirim.sql.example` setelah backup dan uji rollback sebelum fitur simpan baru diaktifkan. Tabel outbox/audit dibuat idempoten oleh instalasi/runtime modul.
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
7. Simpan pertama yang valid pada kunjungan `Belum` menulis SOAP, mengubah `reg_periksa.stts` menjadi `Berkas Dikirim`, dan mencatat `mutasi_berkas.dikirim` sekali dalam satu transaksi. Kegagalan salah satu operasi me-rollback semuanya; setting `set_sudah` tidak menghasilkan status `Sudah`.
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
24. Bila riwayat diagnosis memiliki kode hipertensi/diabetes dan pasien belum terindikasi Prolanis oleh PCare, panel menampilkan kandidat beserta kode diagnosis; panel tidak tampil untuk pasien tanpa kode tersebut atau yang sudah terdaftar.
25. Versi dev tidak memiliki halaman pengaturan lokal: `set_sudah` diabaikan, jenis fasilitas tetap FKTP, dan simpan pertama selalu menuju `Berkas Dikirim`. Kredensial/mapping JKN, PCare, serta WebSocket dikelola pada modul pemilik.
26. Saat membuka kunjungan `Belum`, dialog menampilkan pilihan lanjut form atau **Batal Periksa**; memilih lanjut hanya membuka form. Status `Batal`/selain `Belum` langsung membuka SOAP/riwayat tanpa dialog dan tanpa perubahan status.
27. Pembatalan dari `Belum` memerlukan alasan dan konfirmasi, menghasilkan `Batal` tepat sekali, tidak membuat SOAP, dan menolak request terlambat/klik ganda. Status terminal tidak dapat dipanggil atau disimpan kembali dari modul ini.
28. Koreksi catatan pada `Berkas Dikirim` tidak mengulang pengiriman status dan tidak mengubah waktu `mutasi_berkas.dikirim`; perubahan status oleh poli setelah form dibuka tidak ditimpa.
29. Bila `jkn_mobile_fktp` tidak aktif, aksi tambah/panggil/batal Antrol tidak ditampilkan dan tidak ada request BPJS; alur klinis, status lokal, serta panggilan WebSocket/TTS tetap tersedia. Modul dev tidak mencoba `jkn_mobile` sebagai pengganti.
30. Panggilan anjungan, panggilan Antrol FKTP, dan status kunjungan tampil sebagai tiga hasil terpisah. Panggil FKTP mengirim `status=1` dan `waktu` Unix milidetik 13 digit dari server; ACK WebSocket tidak mengubah status kedatangan pasien.
31. Bila `jkn_mobile_fktp` aktif, pasien dengan `kd_pj` yang cocok dan mapping/kredensial valid dapat ditambah ke antrean dari kunjungan terpilih; klik ganda/timeout tidak membuat booking duplikat. Penjamin lain atau konfigurasi kurang memberi alasan jelas tanpa memanggil BPJS.
32. Saat batal, status lokal tetap `Batal` walau API FKTP gagal; operasi remote tercatat untuk retry/rekonsiliasi dengan alasan aktual. Bila modul FKTP tidak aktif, hanya pembatalan lokal yang berlangsung. Kode/pesan BPJS dan waktu kejadian dapat diaudit tanpa menyimpan data klinis berlebihan.

## 14. Keputusan yang perlu disetujui sebelum implementasi

1. Apakah paramedis wajib mengisi seluruh 11 TTV/anamnesa yang sekarang divalidasi, atau ada field yang bersifat opsional menurut SOP klinis?
2. Apakah `pemeriksaan` (temuan obyektif) tetap diisi paramedis, dan apakah Assessment/Plan/Instruksi/Evaluasi hanya disembunyikan atau perlu dialihkan ke modul dokter?
3. Apakah paramedis boleh mengubah/menghapus catatan sendiri tanpa batas waktu, atau diperlukan batas waktu dan alasan perubahan?
4. Apa sumber hak akses paramedis yang resmi (role, `cap` poli, atau pemetaan pegawai khusus) agar pengganti logika `per_dokter` tepat?
5. Siapa yang berhak menjalankan panggilan antrean, anjungan mana yang menjadi tujuan tiap poli, dan apakah fallback TTS lokal boleh digunakan di area layanan?
6. Untuk setiap poli/penjamin FKTP, siapa pemilik proses tambah antrean dan bagaimana status booking yang sudah dibuat dari modul pendaftaran dibaca tanpa membuat booking ganda?
7. Saat pembatalan lokal sudah sah tetapi BPJS menolak (misalnya pasien telah check-in/dilayani), siapa yang menyelesaikan konflik dan apakah koreksi status dilakukan di modul pendaftaran?
8. Apakah koreksi SOAP setelah `Berkas Diterima`/`Sudah` perlu dibuka untuk peran tertentu? Secara default versi dev hanya memberi akses baca pada status tersebut.

Keputusan di atas diperlukan untuk finalisasi otorisasi, mapping antrean, dan SOP rilis. Alur dasar yang sudah ditetapkan pada FR-05/FR-10 tetap menjadi acuan implementasi.

## 15. Rencana rilis dan verifikasi

1. Inventarisasi pemanggilan lintas modul dan tandai endpoint/berkas yang akan dipertahankan atau dikeluarkan.
2. Bangun ulang halaman dan handler inti dengan test/cek otorisasi server terlebih dahulu.
3. Hapus kode warisan secara bertahap setelah tidak memiliki pemanggilan yang tersisa.
4. Uji di staging memakai akun admin, paramedis berizin, paramedis tanpa izin, dokter, broker WebSocket, dan anjungan.
5. Periksa enum `reg_periksa.stts` pada basis data aktif, struktur wajib `mutasi_berkas`, status aktif `jkn_mobile_fktp`, serta mapping/kredensial FKTP. Jalankan migrasi dan uji rollback yang diperlukan sebelum mengaktifkan alur status baru.
6. Uji status `Belum` hingga `Berkas Dikirim`/`Batal`, konflik perubahan status, klik ganda, kegagalan BPJS, retry, dan tiga jalur panggilan (anjungan, TTS lokal, Antrol FKTP) pada staging.
7. Cadangkan basis data sesuai SOP sebelum rilis; lakukan smoke test pada data catatan lama setelah deploy.

## 16. Metrik keberhasilan

- Tidak ada aksi penulisan layanan dokter dari menu/endpoint `pemeriksaan_ralan_dev`; pembacaan konteks klinis di riwayat mengikuti FR-04.
- Alur pilih pasien sampai simpan pemeriksaan awal dapat diselesaikan paramedis tanpa berpindah modul.
- Tidak ada catatan pemeriksaan awal baru dengan NIP kosong atau berasal dari pengguna di luar poli berhak.
- Tidak ada regresi pembacaan catatan `pemeriksaan_ralan` dan alergi yang sudah ada setelah rilis.
- Panggilan WebSocket yang menerima ACK terkonfirmasi di UI; kegagalan broker/anjungan beralih ke fallback tanpa membuat panggilan ganda.
- Tidak ada kunjungan yang kembali dari status lanjutan ke `Belum`/`Berkas Dikirim` karena request form terlambat; status `Batal` tidak membuat SOAP baru.
- Semua simpan TTV pertama yang berhasil dari `Belum` memiliki `reg_periksa.stts='Berkas Dikirim'` dan `mutasi_berkas.dikirim` yang konsisten.
- Operasi Antrol FKTP memiliki jejak sukses/gagal yang dapat direkonsiliasi per `no_rawat`, tanpa booking ganda atau waktu kejadian yang berubah saat retry.

## 17. Implementasi riwayat dan salin pada versi dev — 26 September 2026

Bagian 2, 10, dan 11.1–11.3 mencatat audit modul lama. Target riwayat diperluas sesuai permintaan pengguna; FR-04/FR-07 menggantikan pembatasan lama terhadap pembacaan data lintas kunjungan.

| Komponen | Implementasi |
| --- | --- |
| Halaman form | `view/admin/manage.html` menampilkan identitas pasien bersama, kepesertaan BPJS, kandidat Prolanis bila relevan, form kiri, dan riwayat kanan dalam satu halaman. `form.pemeriksaan.html` memuat status draf/edit/tersimpan dan sumber salinan. |
| Riwayat kunjungan aktif | `view/admin/riwayat.html`; edit/hapus tetap pada kunjungan aktif dan mengikuti hak pengguna. |
| Riwayat semua kunjungan | `view/admin/riwayat.pasien.html`; panel kanan otomatis dimuat, catatan aktif, daftar berhalaman, detail expandable, dan tautan ERM. Form tidak disembunyikan. |
| `POST riwayatpasien` | Input `no_rawat` aktif, `page`; output JSON `status`, `data.visits`, `page`, `pages`, `total`, `erm_url`. Halaman minimal 1 dan maksimal halaman terakhir. |
| `POST detailriwayat` | Input `no_rawat` aktif dan `source_no_rawat`; output `data.no_rawat`, `records`, `diagnoses`, `procedures`, `medicines`. Setiap record memiliki `jenis`, `nama_petugas`, `can_copy`. |
| Penyalinan | JavaScript memakai data JSON record yang diterima; field whitelist dan pemeriksaan sesi pasien di browser. Tidak ada endpoint mutasi khusus copy. |
| Struktur data baca | `reg_periksa` → pasien melalui `no_rkm_medis`; catatan ralan/ranap, diagnosis/prosedur, dan resep melalui `no_rawat`; nama petugas melalui `nip=pegawai.nik`; seluruh resep reguler melalui `resep_obat.no_resep=resep_dokter.no_resep`. |
| Data tambahan | `diagnosa_pasien` + `penyakit`; `prosedur_pasien` + `icd9`; `resep_obat` + `resep_dokter` + `databarang`; `pemeriksaan_ranap` hanya-baca. Tidak ada tabel baru. |
| Kandidat Prolanis | `diagnosa_pasien` bergabung ke `reg_periksa` berdasarkan RM; kode `I10`/`I11%` dan `E10%`–`E14%` dikembalikan sebagai kandidat. Tidak ada penulisan peserta Prolanis. |

Verifikasi rilis mencakup membaca riwayat dan mengisi form secara bersamaan tanpa kehilangan isian, paging tanpa menyembunyikan form, kegagalan jaringan dan retry, catatan dokter/paramedis pada hari yang sama, salin saat isian sebagian terisi, alergi berubah sejak kunjungan lama, dan pergantian pasien ketika request masih berjalan. Pengujian otomatis menggunakan data sintetis; uji browser dengan akun berizin serta integrasi rekam medis lengkap tetap diperlukan sebelum rilis.

### 17.1 Revisi UI/UX single-page

- Identitas pasien dan poli menjadi header bersama untuk form dan riwayat.
- TTV disusun adaptif dengan satuan menyatu di input: enam kolom pada workspace desktop lebar sehingga TTV dan antropometri selesai dalam dua baris, empat/tiga kolom pada desktop/tablet menengah, lalu dua/satu kolom pada layar kecil. Label bidang tetap menjadi pengelompokan utama tanpa baris judul antropometri tambahan.
- Anamnesa dan temuan obyektif berdampingan pada desktop, label terhubung ke input, dan textarea mendukung perubahan tinggi.
- Ringkasan alergi ditampilkan sekali di atas form. Status belum dimuat berwarna netral, tidak ada alergi berwarna hijau lembut, dan alergi tercatat berwarna amber. Editor profil dapat dilipat dan dibuka melalui Perbarui.
- Tombol simpan berada pada action bar sticky dengan ruang untuk footer aplikasi. Status membedakan draf baru, salinan, edit, menyimpan, gagal, tersimpan, dan baca-saja.
- Ringkasan daftar menyatu pada header tabel, filter tidak memakai header kartu tambahan, penjamin terlihat bersama identitas pasien, dan status administratif ditampilkan terpisah dari indikator ada/tidaknya TTV.
- Semua fakta pasien tetap tersedia pada tablet; antarmuka tidak menyembunyikan penjamin atau nomor kartu untuk menghemat tinggi. Ukuran input ponsel minimal 16px, pesan validasi terhubung ke field, dan fokus dipindahkan ke judul form saat workspace dibuka lalu dikembalikan ke tombol pasien saat ditutup.
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

### 17.3 Status audit kesesuaian terhadap PRD — 26 September 2026

| Area | Status | Catatan |
| --- | --- | --- |
| Single-page form, riwayat, salin, validasi, CAP, identitas pasien, dan BPJS read-only | Diimplementasikan | Form/riwayat tetap satu workspace; escaping output template dan umpan balik validasi per-field ditambahkan. |
| Kandidat Prolanis dari diagnosis | Sesuai secara kode | Hanya indikator baca; tidak ada mutasi PCare. Skenario endpoint ini belum masuk regresi PHP otomatis. |
| Dialog awal hanya untuk `Belum`; batal periksa; simpan → `Berkas Dikirim` dan waktu mutasi berkas | Diimplementasikan | Simpan, status, audit transisi, dan mutasi berkas dijalankan dalam transaksi dengan pemeriksaan ulang status. |
| Tambah/panggil/batal antrean FKTP, timestamp milidetik, dan status sinkronisasi | Diimplementasikan, perlu uji live | Adapter server memakai event key idempoten, timestamp server milidetik, kode/pesan respons, status `pending/success/failed/needs_review`, dan retry pembatalan. |
| Gerbang aktivasi khusus FKTP | Diimplementasikan | Hanya `ActiveModule('jkn_mobile_fktp')`; tidak ada fallback ke `jkn_mobile`. |
| Kode penjamin BPJS di header | Diimplementasikan | Memakai `jkn_mobile_fktp.kd_pj` bila modul FKTP aktif; fallback nama/kode hanya untuk tampilan kepesertaan baca-saja. |
| Daftar pasien menampilkan penjamin | Diimplementasikan | Penjamin ditampilkan bersama identitas pasien tanpa menambah lebar kolom tabel. |
| Beranda, navigasi, dan konsistensi visual | Diimplementasikan | Beranda memiliki satu CTA utama, ringkasan kesiapan, tiga langkah workflow, serta token warna/border/spacing yang sama dengan antrean, form, dan riwayat. |
| Pengaturan lokal versi dev | Dihapus sesuai keputusan | FKTP dan `Berkas Dikirim` adalah aturan tetap, bukan preferensi. Konfigurasi JKN/PCare/WebSocket tetap berada pada modul pemilik. |
| Hak panggil berbasis peran | Belum lengkap | Tombol panggil mengikuti akses poli, belum ada capability/role khusus untuk membatasi hak memanggil. |
| Audit log panggilan/ACK/timeout/fallback | Sebagian | Operasi FKTP tersimpan di outbox/audit. ACK dan fallback WebSocket lokal masih hanya berstatus di browser dan tetap menjadi pekerjaan lanjutan bila audit institusi mewajibkan. |
| Uji integrasi WebSocket/PCare live | Belum dilakukan | Uji otomatis memakai data/respons sintetis; perlu staging dengan broker, anjungan, dan akun PCare. |

### 17.4 Implementasi alur status dan Antrean FKTP

Implementasi versi dev menambahkan kontrak berikut.

| Endpoint | Fungsi dan aturan utama |
| --- | --- |
| `POST statuskunjungan` | Mengembalikan status aktual serta hak create/edit/cancel; digunakan sebelum workspace dibuka agar keputusan tidak hanya bergantung pada atribut DOM. |
| `POST batalperiksa` | Alasan 5–255 karakter; mengunci baris kunjungan, hanya menerima `Belum`, menulis `Batal` dan audit dalam transaksi, lalu mencoba pembatalan FKTP setelah commit untuk kunjungan yang memenuhi gerbang FKTP. Percobaan tetap dilakukan walau booking dibuat oleh kanal FKTP lain dan belum tercatat di outbox dev. |
| `POST antreantambah` | Khusus kunjungan `Belum` yang memenuhi penjamin, identitas, kredensial, mapping poli/dokter, dan jadwal FKTP. Nomor antrean berasal dari mapping poli dan `no_reg`, bukan pencocokan nama Umum/Gigi/KIA. |
| `POST antreanpanggil` | Memerlukan hasil tambah sukses dan `message_id` panggilan. Payload memakai `status=1` dan waktu Unix milidetik dari server yang dipertahankan untuk event yang sama. |
| `POST antreanbatalulang` | Mengulang hanya operasi batal berstatus `failed`/`needs_review` dengan alasan dan event key yang sama. |

Tabel `mlite_pemeriksaan_ralan_dev_status_log` menyimpan transisi lokal (`from_status`, `to_status`, alasan, aktor, waktu, dan event key unik). Tabel `mlite_pemeriksaan_ralan_dev_antrean` menjadi outbox/audit operasi FKTP: `no_rawat`, jenis operasi, event key unik, waktu milidetik, alasan, waktu request/selesai, status, jumlah percobaan, kode/pesan respons, aktor, dan hash payload. Kedua tabel dibuat idempoten saat instalasi serta diperiksa saat runtime untuk instalasi modul yang sudah ada.

Penyimpanan pemeriksaan baru mengunci `reg_periksa`, menolak status selain `Belum`, lalu menulis SOAP, `Berkas Dikirim`, waktu `mutasi_berkas.dikirim` pertama, dan audit status dalam satu transaksi. Koreksi hanya diterima pada `Belum`/`Berkas Dikirim` untuk pemilik catatan atau admin dan tidak mengulang waktu kirim. Penghapusan dibatasi pada status `Belum`.

UI daftar menyembunyikan status selain `Belum` secara default namun dapat menampilkan semuanya, hanya menampilkan tombol panggil pada `Belum`, dan memisahkan status kunjungan, keberadaan TTV, status anjungan, serta status FKTP. Saat `jkn_mobile_fktp` tidak aktif atau penjamin tidak cocok, tombol sinkronisasi tidak dirender dan alur lokal tetap bekerja.

### 17.5 Beranda, konfigurasi, dan bahasa desain

- Navigasi modul hanya memuat **Beranda** dan **Antrean Pemeriksaan**. Tidak ada halaman pengaturan lokal atau menu kosong.
- Beranda menjadi orientasi singkat, bukan dashboard klinis kedua: hanya satu CTA utama menuju antrean, status baca-saja jenis fasilitas, JKN, PCare, dan WebSocket, tiga langkah workflow, serta aturan modul.
- Jenis fasilitas ditetapkan **FKTP**. Modul tidak menawarkan pilihan FKTL karena Antrean FKTP, mapping PCare, payload, dan gerbang modulnya berbeda dari Antrol rumah sakit.
- Simpan pertama selalu menghasilkan **Berkas Dikirim**. Tidak ada toggle menuju `Sudah` atau status lain karena status tersebut dimiliki tahap pelayanan berikutnya.
- Admin dapat menuju pengaturan `jkn_mobile_fktp` dari beranda saat modul tersebut aktif; tautan ini tidak menjadikan konfigurasi JKN sebagai setting milik modul pemeriksaan.
- Semua halaman memakai namespace `.pemeriksaan-dev`, token warna dan border yang sama, radius kartu 8–10 px, bayangan ringan, status badge semantik, fokus keyboard terlihat, CTA biru tunggal, serta pola header–card–state yang konsisten.
- Kepadatan tetap menjadi prioritas di layar kerja: daftar, form, riwayat, dan beranda menghindari panel dekoratif berulang. Pada tablet/ponsel, grid berubah menjadi satu kolom tanpa menyembunyikan informasi pasien penting.
