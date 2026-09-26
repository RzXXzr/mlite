# Pemeriksaan Paramedis (Dev)

Modul ini adalah versi pengembangan terpisah dari `pemeriksaan_ralan`.

Fungsi pencatatan tersedia untuk pemeriksaan awal rawat jalan: daftar pasien, TTV, anamnesa awal, alergi, riwayat pemeriksaan, dan panggilan antrean.

Form dan riwayat tampil bersamaan pada satu halaman: isi form di kiri sambil menelusuri panel **Riwayat kunjungan** di kanan (10 kunjungan per halaman). Riwayat dimuat otomatis dan kunjungan sebelumnya yang paling baru langsung terbuka. Klik kunjungan lain untuk membaca TTV, SOAP, diagnosis, prosedur, dan obat reguler. Data rawat inap hanya-baca. Gunakan **Rekam medis** untuk rincian layanan lainnya melalui modul Pasien; akses mengikuti izin modul tersebut. Pada ponsel, form dan riwayat tersusun vertikal dengan tautan untuk berpindah posisi dalam halaman.

Tombol **Salin ke form** pada catatan rawat jalan hanya mengisi kolom kosong atau `-`. Isian yang sudah ada dan alergi terbaru dipertahankan. Hasil salinan menjadi draf baru pada kunjungan aktif; periksa ulang TTV lalu simpan. Salin tidak menyimpan otomatis dan tidak mengubah catatan sumber. Diagnosis, resep, dan layanan dokter tidak dapat ditulis melalui modul ini.

Ringkasan alergi tersedia di atas form; klik **Perbarui** untuk membuka profil makanan/udara/obat. Tombol simpan dan status draf berada di bagian bawah form dan tetap mudah dijangkau saat menggulir. Penyalinan tidak menutup panel riwayat atau menggeser posisi halaman.

Header pasien menampilkan nama, RM, umur saat ini, tanggal lahir, golongan darah, penjamin kunjungan, dan nomor kartu. Untuk BPJS, provider/FKTP, jenis/status kepesertaan, PRB, dan Prolanis diperiksa otomatis melalui PCare. Gunakan **Cek ulang** untuk memperbarui hasil. Status **Tidak ada informasi** berarti PCare tidak memberikan nilai, bukan berarti pasien tidak terdaftar pada program tersebut. Jika koneksi/izin PCare bermasalah, form tetap dapat digunakan. Hasil pengecekan tidak memperbarui data master pasien.

Sebelum mengaktifkan di produksi, pastikan akun paramedis memiliki relasi pegawai dan cakupan poli (*CAP*) yang benar. Pengaturan WebSocket berada di Pengaturan Umum mLITE.
