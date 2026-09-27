# UAT Postman — JKN Mobile FKTP Dev

Import dua berkas berikut ke Postman:

- \`jkn_mobile_fktp_dev.postman_collection.json\`
- \`jkn_mobile_fktp_dev.postman_environment.json\`

Base endpoint sudah diisi: \`https://mlite.klinikarrohman.com/jknmobilefktpdev\`.

Isi password pada environment/collection variables; password tidak disertakan dalam berkas. Sebelum alur sukses, ganti \`kodepoli\`, \`kodedokter\`, dan \`jampraktek\` dengan mapping poli-dokter serta jadwal yang aktif pada tanggal uji. Isi juga pasangan kode/nama \`kodeprop\`, \`namaprop\`, \`kodedati2\`, \`namadati2\`, \`kodekec\`, \`namakec\`, \`kodekel\`, dan \`namakel\` sesuai payload BPJS serta master wilayah mLITE. Pre-request script menerapkan variabel tersebut ke seluruh body pasien.

Wilayah pasien tidak lagi diambil dari default pengaturan. Kode+nama yang dikirim harus menunjuk ke satu master lokal yang tepat; data yang tidak dikenal atau ambigu sengaja ditolak agar pasien tidak tersimpan pada wilayah yang salah.

Jalankan folder berurutan dari 01 sampai 08. Folder 04, 06, dan pembatalan pada folder 08 memiliki pengaman: set \`allowWrite\` menjadi persis \`YES_DEV_ONLY\` hanya ketika siap menulis data uji. Pembatalan akan mengubah status antrean yang dibuat.

Setiap request memeriksa HTTP status dan \`metadata.code\`. Setelah mengirim request, buka menu admin **JKN Mobile FKTP Dev → Log transaksi** untuk melihat ringkasan audit. Audit sengaja tidak menyimpan password, token, NIK, nomor kartu, atau body request.

Folder 09 hanya untuk slot jadwal dev khusus kuota 1; jangan gunakan jadwal pelayanan nyata.

## Pengaturan poli dan shift (baru)

Sebelum menjalankan suite, buka **JKN Mobile FKTP Dev → Poli & Jadwal Online**. Pilih tanggal, ambil referensi BPJS dengan kredensial PCare, pilih satu tujuan lokal untuk masing-masing kode BPJS (contoh 001 → UMU, 002 → GIGI), lalu **centang dokter/jam dan simpan checklist tanggal tersebut**. Jam dan kapasitas berasal dari BPJS. RUJ/RUJGG tidak boleh dipilih. Tanggal tanpa checklist aktif ditutup. Snapshot form harus disimpan dalam 30 menit; checklist tabel tidak kedaluwarsa 24 jam.

Pilih tanggal dalam batas hari booking, lihat pratinjau, lalu samakan `kodepoli`, `kodedokter`, `jampraktek` dan `tanggal` di Postman. Pastikan tanggal hasil pre-request script juga sesuai. Token harus berasal dari respons `/auth`, bukan token secret pengaturan. Jangan ubah assertion menjadi 401 jika pengujian membutuhkan autentikasi sukses; ambil token baru bila kedaluwarsa.

Tambahan UAT jadwal (ubah konfigurasi hanya pada instance/data uji):

| Skenario | Hasil yang diharapkan |
| --- | --- |
| Status pada tanggal yang dibuka | HTTP 200; hanya dokter/jam yang ditetapkan admin |
| Booking dokter pagi dengan jam pagi yang tepat | HTTP 200; tujuan lokal UMU, bukan RUJ/USG |
| Dokter BPJS lain yang tidak ditugaskan pada shift tersebut | HTTP 422, metadata 201; pesan menyebut nama/jam dokter aktif pengganti; tidak ada kunjungan baru |
| Jam sore 17:00–21:00 tetapi konfigurasi 16:30–21:00 | HTTP 422; tidak mengganti jam/dokter otomatis |
| Poli tidak dipilih atau tanggal ditutup | Status HTTP 404; booking HTTP 422 |
| Dua dokter pada shift yang sama melalui dashboard | Penyimpanan ditolak; konfigurasi sebelumnya tetap berlaku |
| Dua interval beririsan di poli yang sama | Penyimpanan ditolak |
| Dokter sama bersamaan pada UMU dan GIGI | Penyimpanan ditolak |
| Hanya shift sore dicentang | Pada tanggal itu pagi tertutup; tanggal lain tidak otomatis ikut aktif |
| Kuota 1, dua pasien berbeda meminta slot yang sama | Hanya satu booking aktif; lainnya HTTP 422 |
| Booking sudah ada, lalu hari ditutup | Sisa peserta tetap dapat dibaca dan booking dapat dibatalkan |
| Booking batal, lalu dibuka ulang pada slot yang diizinkan | ID kunjungan tetap, nomor antrean baru |
| Generate 7 hari gagal pada salah satu tanggal | Semua tanggal tidak berubah; tidak ada penyimpanan parsial |
| BPJS menjawab jadwal dokter tidak ditemukan/libur | Dashboard menandai Tutup/libur; minggu tetap dapat disimpan; booking tanggal tersebut ditolak dengan alasan hari tutup |
| Refresh BPJS gagal pada salah satu poli | Konfigurasi lama tidak ditimpa; respons error terlihat di dashboard |
| Dokter tanpa mapping atau kapasitas nol | Checkbox tidak dapat diaktifkan; manipulasi POST juga ditolak |
| Snapshot formulir lewat 30 menit | Simpan ditolak; ambil ulang referensi |
| Checklist tersimpan lewat 24 jam | Tetap aktif sampai admin mengubah checklist atau mapping menjadi tidak valid |
| Jadwal diperbarui BPJS | Refresh tidak mengubah aktivasi sebelum Simpan; setelah Simpan hanya ID jadwal terbaru yang dipilih diterima |

Kasus negatif tetap perlu data pasien dan payload lain yang valid agar benar-benar menguji penolakan jadwal. Jangan jalankan mutasi jika belum siap membuat data UAT. Perubahan pengaturan tidak mengubah daftar yang terlihat di aplikasi BPJS.
