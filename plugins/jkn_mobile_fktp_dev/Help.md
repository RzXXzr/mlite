# JKN Mobile FKTP Dev

Base path: /jknmobilefktpdev.

Sebelum UAT, buka admin **JKN Mobile FKTP Dev**, lalu isi username, password, dan kode penjamin. Pastikan mapping poli dan dokter sudah tersedia. Buka tab **Poli & Jadwal Online** untuk mengambil referensi BPJS melalui kredensial PCare lalu mencentang jadwal yang diterima.

## Poli & Jadwal Online

1. Pastikan pengaturan PCare berisi URL PCare, Cons ID, Secret dan **User Key Antrol**. Jangan menyalin token Postman ke sini. Sumber dev/produksi mengikuti PCare, bukan nama modul Dev.
2. Pilih tanggal hari ini sampai 90 hari ke depan, klik **Ambil / Perbarui Referensi BPJS**. Operasi ini hanya GET referensi poli dan dokter. Tidak membuat/membatalkan antrean atau mengubah jadwal BPJS.
3. Centang poli tujuan rawat jalan (misalnya UMU untuk 001 dan GIGI untuk 002). Satu kode BPJS hanya boleh diarahkan ke satu poli lokal. RUJ/RUJGG tidak dapat dipilih.
4. Centang dokter dan jam yang diterima pada tabel. Jam dan kapasitas berasal dari BPJS, tidak perlu diketik ulang. Mapping dokter yang kosong/ambigu atau kapasitas nol tidak dapat dipilih. Dua dokter satu shift atau jam bertumpuk ditolak. Label pagi berlaku untuk mulai sebelum 12:00; sore mulai 12:00 atau sesudahnya.
5. Klik **Simpan checklist tanggal ini**. Jadwal disimpan ke tabel khusus Dev; menyimpan satu tanggal tidak menghapus tanggal lainnya.
6. Gunakan kode poli, dokter dan jam pada tabel **Jadwal aktif tersimpan untuk API** di Postman. Untuk libur, hapus semua centang lalu simpan. Untuk penggantian dokter, ambil ulang referensi dan centang dokter pengganti yang tersedia.

Referensi berlaku **per tanggal**. Snapshot formulir harus disimpan dalam 30 menit sejak pengambilan, tetapi checklist yang sudah tersimpan tidak kedaluwarsa 24 jam. Pengambilan baru tidak mengubah aktivasi sampai disimpan. Bila BPJS gagal diakses, konfigurasi lama tidak ditimpa.

Tab **Generate 1 Minggu** selalu membaca tujuh hari referensi dan tidak dibatasi nilai Hari Booking di Depan. Nilai tersebut hanya membatasi request pasien. Satu minggu disimpan atomik; bila satu hari gagal, tidak ada tanggal yang berubah.

Jika BPJS mengembalikan pesan khusus seperti **Jadwal Dokter tidak ditemukan/tidak tersedia/libur**, tanggal/poli ditampilkan sebagai **Tutup/libur** dan tetap dianggap response referensi yang valid. Error signature, Consumer ID, User Key, koneksi, dekripsi, atau format response tetap menghentikan penyimpanan.

Pengaturan ini tidak memperbarui daftar dokter pada aplikasi BPJS. Jadwal yang sudah terlanjur dibooking tidak otomatis dibatalkan saat konfigurasi diubah. Kuota hanya menghitung booking Dev, bukan semua antrean manual.

Konfigurasi tersimpan di tabel `mlite_jkn_mobile_fktp_dev_schedule`. Kandidat yang tidak dicentang ikut disimpan dengan status nonaktif sehingga API dapat memberi alasan dan nama dokter pengganti yang aktif. File jadwal lama bukan sumber runtime lagi.

PRD terbaru: `docs/prd-jkn-mobile-fktp-dev-referensi-bpjs.md`. Uji offline: `php plugins/jkn_mobile_fktp_dev/tests/schedule_repository_test.php` dan `php plugins/jkn_mobile_fktp_dev/tests/bpjs_reference_test.php`. Pengujian memakai response simulasi dan SQLite memory, tidak menghubungi BPJS atau database fasilitas.

## Urutan Postman

1. GET {{baseUrl}}/jknmobilefktpdev/auth

   Header: x-username: {{username}}, x-password: {{password}}.

   Simpan response.token sebagai variabel token.

2. Untuk endpoint selain auth gunakan header x-username: {{username}} dan x-token: {{token}}.

3. Jika pasien belum terdaftar, POST /peserta dengan JSON katalog FKTP. Ambil response.norm.

4. POST /antrean dengan JSON:

{
  "nomorkartu": "0000012345678",
  "nik": "3212345678987654",
  "nohp": "081234567890",
  "kodepoli": "001",
  "tanggalperiksa": "2026-10-01",
  "keluhan": "sakit kepala",
  "kodedokter": "123456",
  "jampraktek": "08:00-12:00",
  "norm": "000001"
}

Kode dokter dan jam harus sama dengan **pratinjau Poli & Jadwal Online**; ubah tanggal/contoh sesuai data UAT lokal dan batas hari booking.

5. GET /antrean/status/{{kodepoli}}/{{tanggal}} dan GET /antrean/sisapeserta/{{nomorkartu}}/{{kodepoli}}/{{tanggal}}.

6. Untuk batal gunakan PUT /antrean/batal, bukan POST:

{
  "nomorkartu": "0000012345678",
  "kodepoli": "001",
  "tanggalperiksa": "2026-10-01",
  "keterangan": "peserta batal hadir"
}

Respons mematuhi amplop BPJS metadata.code: 200 sukses, 201 ditolak, 202 pasien belum terdaftar. Endpoint dev tidak memanggil server BPJS keluar.
# Perilaku booking ulang

Jika booking untuk pasien, poli, dan tanggal yang sama sebelumnya berstatus `Batal`, panggil kembali `POST /antrean` dengan payload yang valid. Modul mengaktifkan kembali ID kunjungan (`no_rawat`) lama dan menerbitkan nomor antrean/`no_reg` baru. Bandingkan kolom **No. Rawat** pada menu Rawat Jalan atau **Log transaksi** untuk verifikasi; tidak boleh muncul ID kunjungan baru.
