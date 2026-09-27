# PRD — Penerimaan poli dan dokter jaga Mobile JKN Dev

> Dokumen riwayat: editor jadwal manual pada dokumen ini telah digantikan oleh [PRD checklist referensi BPJS](prd-jkn-mobile-fktp-dev-referensi-bpjs.md). Implementasi aktif menyimpan kandidat dan checklist per tanggal di tabel `mlite_jkn_mobile_fktp_dev_schedule`; file `tmp` bukan sumber runtime lagi.

## Tujuan dan batas perubahan

Admin menentukan poli rawat jalan yang menerima Mobile JKN dan satu dokter jaga untuk setiap shift pagi/sore pada setiap hari. Dashboard berada di **JKN Mobile FKTP (Dev) → Poli & Jadwal Online**. Modul produksi tidak diubah. Implementasi tidak menjalankan migrasi, mengubah master/mapping/jadwal, atau menulis pengaturan baru ke database. Penyimpanan transaksi antrean dan audit API yang sudah ada tetap berfungsi saat API dipakai.

Pengaturan penerimaan disimpan di `tmp/jkn_mobile_fktp_dev_schedule.php` sebagai dokumen JSON dengan pelindung PHP; berkas ini adalah konfigurasi persisten, wajib ikut backup dan tidak boleh dibersihkan sebagai cache. Penyimpanan atomik, kunci berkas, nomor revisi, dan validasi server mencegah konfigurasi setengah tertulis/tertimpa admin lain. Tidak ada kredensial/pasien di berkas ini.

## Masalah yang diselesaikan

- Satu kode BPJS 001 dapat dipetakan ke UMU, RUJ, dan USG. Join mapping saja tidak dapat menentukan tujuan pendaftaran yang benar.
- RUJ/RUJGG adalah layanan rujukan, tidak boleh menjadi tujuan pendaftaran rawat jalan Mobile JKN.
- Beberapa dokter BPJS tersedia bersamaan. Admin harus memilih dokter yang benar-benar bertugas tanpa mengubah jadwal master bersama.
- Waktu praktik sore dapat berbeda (16:30–21:00 vs 17:00–21:00). Pencocokan tidak boleh hanya memakai label sore.

## Aturan penerimaan

1. Admin memilih poli lokal dari mapping yang tersedia. Maksimal satu poli lokal aktif untuk satu kode BPJS. Contoh yang diinginkan: 001 → UMU, 002 → GIGI; USG tidak otomatis diaktifkan.
2. Kode lokal berawalan RUJ atau nama poli berawalan RUJ/rujukan selalu ditolak, termasuk bila payload konfigurasi dimanipulasi. Poli/dokter harus masih aktif dan memiliki mapping yang tidak ambigu.
3. Belum ada konfigurasi berarti pendaftaran/status jadwal ditutup hingga admin menyimpan; tidak ada pemilihan poli atau dokter pertama secara otomatis.
4. Untuk setiap poli dan hari SENIN–AKHAD, admin dapat menambahkan pagi dan/atau sore. Satu baris memuat poli, hari, shift, dokter lokal yang memiliki kode BPJS, waktu mulai/selesai, kuota online. Jam harus HH:MM, selesai setelah mulai, tanpa lintas tengah malam; kuota 1–999.
5. Satu dokter per shift per poli/hari. Interval yang beririsan pada poli sama, atau dokter sama pada poli berbeda, ditolak; jam yang bersambung boleh. Pagi/sore adalah label admin, bukan sumber tebakan rentang jam.
6. Pengecualian tanggal opsional menggantikan SELURUH jadwal mingguan poli pada tanggal itu. Admin dapat memilih dokter pengganti atau menutup satu hari melalui baris Tutup. Jadwal pagi perlu ikut diisi bila pengecualian tanggal hanya akan mengganti sore.
7. Dokter dan jam pada request harus persis sama dengan pilihan admin serta kode mapping BPJS. Dokter lain yang terlihat pada aplikasi Mobile JKN ditolak dengan HTTP 422; tidak dialihkan diam-diam ke dokter jaga.
8. Konfigurasi lokal tidak mengubah daftar di aplikasi BPJS. Admin menyalin kode/jam/kuota berdasarkan referensi BPJS yang sudah tersedia; tampilan screenshot bukan sumber sinkronisasi otomatis. Penyesuaian jadwal di sisi BPJS tetap melalui mekanisme BPJS yang terpisah.

## Perilaku API

- `POST /antrean`: validasi penerimaan sebelum pendaftaran, lalu lock baris poli lokal yang stabil untuk serialisasi pemeriksaan kuota dan alokasi antrean. Slot manual tidak harus ada di tabel jadwal. Kuota dihitung dari booking Dev aktif pada poli/dokter/tanggal/jam tepat. Pasien memilih dokter; server tidak menggantinya.
- `GET /antrean/status/{kodepoli}/{tanggal}`: hanya slot yang dipilih admin pada tanggal tersebut. Poli tidak dibuka/libur: 404 metadata 201. Konfigurasi rusak/tidak dapat dibaca: 503 metadata 201.
- Sisa peserta dan pembatalan: tetap dapat mengakses booking Dev yang sudah ada walaupun jadwal kemudian ditutup/diubah; tidak boleh salah memilih RUJ dari join kode BPJS ganda. Pengambilan slot lama memakai data booking, bukan keharusan cocok ke master jadwal.
- Booking ulang setelah Batal tetap memakai ID kunjungan pasien/poli/tanggal yang sama dengan nomor antrean baru. Bila slot kini ditutup, booking ulang ditolak.
- Perubahan konfigurasi berlaku bagi request berikutnya; tidak memindahkan atau membatalkan booking yang sudah tersimpan. Penurunan kuota di bawah jumlah booking menutup tambahan booking, tidak menghapus kunjungan.
- Kuota ini khusus booking Dev, tidak menggabungkan kunjungan manual yang tidak memiliki informasi shift. Hal ini ditampilkan pada dashboard.

## Dashboard

Navigasi konsisten di semua halaman, pilihan poli dengan kode lokal/BPJS dan penanda poli rujukan terkunci, editor baris jadwal mingguan/tanggal khusus, tambah/hapus baris sebelum simpan, pesan kesalahan yang mempertahankan form, revisi konfigurasi, pratinjau tanggal berupa kartu/tabel dokter dan jam yang diterima API. Daftar referensi master jadwal ditampilkan baca-saja. Tidak ada panggilan otomatis ke BPJS.

Maksimal 80 baris jadwal untuk membatasi ukuran formulir. CSRF diverifikasi dan penanda akhir form wajib diterima; jika PHP memotong POST akibat batas ukuran/jumlah variabel, konfigurasi lama tidak boleh ditimpa sebagian. Akses tetap melalui autentikasi dan izin modul admin mLITE yang ada. Membuka halaman admin/API tidak lagi menjalankan DDL otomatis; tabel audit instalasi yang sudah ada tetap digunakan.

Admin wajib mem-backup berkas konfigurasi. Jika file hilang, sistem kembali tertutup; jika file rusak, API memberi 503. Pada deployment multi-node, konfigurasi harus memakai storage persisten bersama (termasuk kunci file), bukan salinan independen per node. Pengaturan dan perubahan jam tidak menjadi sinkronisasi ke BPJS.

## Penerimaan dan pengujian

Uji otomatis terisolasi tanpa koneksi database produksi:

- Kosong: tidak membuka poli apa pun; hanya whitelist yang diterima.
- UMU dan RUJ punya kode 001: hanya UMU; RUJ/RUJGG manipulasi ditolak.
- Dua tujuan lokal aktif untuk kode 001 ditolak.
- Dokter A pagi dan B sore diterima tepat; B pagi/A sore/jam meleset ditolak.
- Dua dokter shift sama dan interval 16:30–21:00 vs 17:00–21:00 ditolak.
- Libur, hari lain, pengecualian tanggal dan penggantian seluruh hari berfungsi.
- Mapping hilang/inaktif menutup slot; kode BPJS berubah tidak diam-diam mengalihkan pasien.
- Status dan booking memakai resolver yang sama; pembatalan/sisa hanya menelusuri booking Dev, termasuk setelah konfigurasi tutup.
- Penyimpanan konfigurasi atomic, revisi lama ditolak, file rusak ditolak; tidak ada SQL tulis dalam pengelolaan jadwal.

UAT Postman: pilih tanggal di pratinjau dalam rentang booking, salin kode poli/dokter dan jam dari pratinjau. Uji sukses, dokter tidak ditugaskan, poli tidak dibuka, tanggal libur, jam tidak tepat, kuota penuh, pembatalan dan booking ulang. Panggilan mutasi API hanya dilakukan oleh penguji pada data UAT. Hasil tercatat di audit Dev yang sudah ada.
