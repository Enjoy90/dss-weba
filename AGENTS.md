# SPK Prioritas Produksi: konteks untuk AI dan developer

Baca berkas ini lebih dulu. Isinya cukup untuk mulai bekerja tanpa memindai seluruh repo. Rincian ada di `docs/memori/`, buka hanya yang dibutuhkan.

## Apa ini

Aplikasi web sistem pendukung keputusan (SPK) untuk menentukan urutan prioritas penyelesaian order produksi dengan metode SAW (Simple Additive Weighting). Studi kasus PT Weba International, dibuat untuk skripsi. Dosen pembimbing meminta DSS saja, bukan ERP, sehingga ini aplikasi baru yang seluruhnya kode sendiri. Jangan menyalin kode dari proyek ERP lama.

Cara isi nilai kriteria adalah gabungan: tenggat, urgensi pelanggan, nilai order, dan lama menunggu dihitung otomatis. Bahan, sisa waktu kerja, dan progres diisi manual atau lewat impor Excel.

## Teknologi

PHP 8.3, Laravel 13, Filament 5, Livewire 4, Pest 4, Pint. Basis data MariaDB 10.4 atau MySQL 8 (kode harus jalan di keduanya). Impor dan ekspor Excel memakai OpenSpout yang sudah ikut Filament. Tidak ada paket tambahan, jangan menambah dependensi tanpa persetujuan.

## Menjalankan

```
cp .env.example .env && php artisan key:generate
composer install
php artisan migrate --seed
php artisan serve --port=8001      # http://localhost:8001/admin
php artisan test --compact         # semua tes harus hijau
vendor/bin/pint --dirty            # format kode
```

Butuh dua basis data kosong: `dss` dan `dss_test`. Akun demo (kata sandi `password`): admin@spk.test, manajer@spk.test, supervisor@spk.test, pimpinan@spk.test. Data demo lengkap (25 order, 7 kriteria, 57 penilaian) dibuat oleh `php artisan migrate:fresh --seed`.

## Status

Fase 0 sampai 5 dari 12 selesai, 33 dari 72 tugas, 282 tes lulus. Fase 6 (mesin SAW dan evaluasi) belum dimulai. Daftar tugas dan catatan kemajuan ada di `TODO-Pembangunan-SPK.md`, itu sumber kebenaran status. Perbarui bagian ini setiap fase selesai.

Yang sudah ada: masuk dan profil, pengguna, pelanggan, produk, order produksi, kriteria dengan bobot dan skala nilai, matriks penilaian, impor dan ekspor Excel. Perhitungan SAW belum ada, jadi jangan menganggap ada peringkat atau evaluasi.

## Dokumen yang jadi acuan

1. `PRD-SPK-Prioritas-Produksi-SAW.md` (v1.5): spesifikasi lengkap. Bab 7.7 perlindungan order lama, Bab 8 fitur F01 sampai F18, Bab 9 hak akses, Bab 10 model data, Bab 11 rumus dan contoh hitung, Bab 14 tugas.
2. `TODO-Pembangunan-SPK.md`: 72 tugas per fase, dengan kriteria selesai dan catatan keputusan per fase.
3. `Panduan-Penggunaan-dan-Pengujian-SPK.md` dan `.docx`: panduan uji langkah demi langkah dengan tangkapan layar, bertambah satu bab per fase.

## Peta berkas memori

- `docs/memori/aturan-kerja.md`: cara bekerja yang diminta pemilik proyek (gaya tulisan, validasi, alur tiap fase). Wajib dibaca.
- `docs/memori/keputusan-desain.md`: keputusan dan alasannya, termasuk hal yang sengaja belum dibuat. Wajib dibaca sebelum mengubah perilaku.
- `docs/memori/peta-kode.md`: letak kelas, tabel, halaman, dan pola yang dipakai. Baca ini sebagai ganti memindai folder `app`.
- `docs/memori/jebakan-teknis.md`: masalah yang pernah terjadi dan cara mengatasinya.
- `docs/alat-dokumen/README.md`: cara membangun ulang docx panduan dan PRD, serta mengambil tangkapan layar.

## Aturan yang paling sering terlupa

1. Jangan menambah fitur, kolom, atau perilaku yang belum disepakati. Bila ragu, tanyakan dengan memberi satu rekomendasi.
2. Pikirkan UX di setiap halaman: keadaan kosong, pesan galat yang jelas, tidak terpotong di layar 1366 piksel.
3. Semua validasi di server, hak akses lewat Policy. Supervisor tidak boleh melihat nilai rupiah.
4. Setiap fitur diuji dengan data demo di peramban, halaman tidak boleh kosong. Tes otomatis wajib hijau, dan aturan penting diuji dengan mutasi (rusakkan aturan sebentar, pastikan tes gagal, lalu pulihkan).
5. Teks tampilan dan dokumen berbahasa Indonesia. Hindari em dash, en dash, titik tengah, dan gaya tulisan yang terdengar buatan AI.
6. Badge berwarna penuh (solid), bukan transparan. Font bawaan Filament, jangan diganti.
7. Commit dan push hanya atas persetujuan pemilik proyek. Pesan commit tanpa co-author AI.
8. Selesai satu fase: perbarui `TODO-Pembangunan-SPK.md`, tambah satu bab di panduan (dengan tangkapan layar nyata), perbarui bagian Status di atas, lalu berhenti dan lapor. Jangan langsung masuk fase berikutnya sebelum pemilik proyek bilang lanjut.
