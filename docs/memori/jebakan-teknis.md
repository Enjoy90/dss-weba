# Jebakan teknis

Masalah yang sudah pernah terjadi selama pembangunan, supaya tidak diulang.

## Lingkungan

- PHP harus 8.3 atau lebih baru. Di mesin pemilik proyek ada dua PHP, 8.2 sebagai bawaan dan 8.3 di `D:\php83`. Bila perintah artisan atau composer gagal karena versi, pasang PATH ke PHP 8.3 lebih dulu.
- Basis data `dss` dan `dss_test` harus ada dan kosong sebelum `migrate`. Tes memakai `dss_test` lewat `phpunit.xml`.
- MariaDB 10.4: jangan pakai `JSON_ARRAYAGG` dan fungsi MySQL 8 lain yang tidak ada di sana.
- Kolom `evaluated_at` (Fase 6) tidak boleh memakai ON UPDATE.

## Eloquent dan model

- Riwayat aktivitas kosong karena tipe morph tidak cocok antara nama tabel dan nama kelas. Solusinya `Relation::morphMap` di `AppServiceProvider` dan `getMorphClass()`. Model baru yang masuk `activity_logs` wajib didaftarkan di morph map.
- Model baru bernilai null pada kolom berdefault (misalnya `lock_type`). Isi `$attributes` di model untuk nilai bawaan.
- `Collection::only()` memilih berdasarkan kunci, bukan kode kriteria. Untuk memilih per kode, pakai filter.
- `Criterion` yang disimpan otomatis menghitung ulang bobot. Seeder yang mengisi bobot harus menyetel `weight_mode` ke `direct` sebelum membuat kriteria, kalau tidak bobotnya tertimpa.

## Filament dan tampilan

- Tabel melebar dan tombol terpotong di layar 1366 piksel. Sembunyikan kolom sekunder secara bawaan dan pindahkan aksi baris ke `ActionGroup` di kiri.
- Status "siap dihitung" harus ikut memeriksa skala tiap kriteria aktif, bukan hanya total bobot.
- Hak lihat rupiah dicek di banyak tempat. Fitur baru yang menampilkan harga, nilai order, atau kriteria C6 wajib memakai `User::canSeeOrderValue()`.

## Tes

- Properti `paths[]` di proxy `test()` milik Pest tidak bisa dipakai untuk pembersihan. Pakai fungsi shutdown untuk menghapus berkas sementara.
- `Illuminate\Http\UploadedFile` palsu tidak punya `$name`. Pakai `UploadedFile::fake()->createWithContent(...)`.
- Penanggalan Excel: serial 46462 adalah 16 Maret 2027. Hitung ulang sebelum menulis ekspektasi tanggal.
- Pint bisa mengubah nama kelas pada type hint `Testable`. Jalankan Pint lalu jalankan tes lagi.
- Mutasi lewat regex di PHP rawan gagal diterapkan karena `$user` ikut diinterpolasi. Ubah berkas dengan skrip yang mengganti string tepat, dan pastikan perubahan benar-benar terjadi sebelum menjalankan tes.

## Tangkapan layar dan docx

- Batas masuk 5 kali per menit. Skrip tangkap layar yang dijalankan beruntun harus diberi jeda sekitar 70 detik, atau login akan ditolak.
- Mengambil tangkapan layar halaman penuh (`fullPage`) menaruh bilah atas yang menempel di tengah gambar. Untuk halaman panjang, pakai jendela yang cukup tinggi (lihat `tangkap-layar/ambil-ulang-halaman-panjang.js`).
- Nomor halaman daftar isi docx dibaca dari PDF hasil konversi. Jangan memakai PDF lama bila konversi gagal, hapus dulu PDF-nya. `bangun.sh` sudah melakukannya.
- Dua build docx yang berjalan bersamaan saling menimpa berkas. Jalankan satu per satu.
- Tangkapan layar harus diambil sesudah `php artisan migrate:fresh --seed`, supaya angka di gambar sama dengan angka di teks panduan.
