# SPK Prioritas Produksi

Sistem pendukung keputusan untuk menentukan prioritas penyelesaian produksi dengan metode SAW (Simple Additive Weighting). Studi kasus PT Weba International.

Spesifikasi lengkap ada di [PRD-SPK-Prioritas-Produksi-SAW.md](PRD-SPK-Prioritas-Produksi-SAW.md). Daftar pekerjaan dan kemajuannya ada di [TODO-Pembangunan-SPK.md](TODO-Pembangunan-SPK.md).

## Untuk kolaborator (developer dan AI)

Mulai dari [AGENTS.md](AGENTS.md). Berkas itu merangkum proyek, status, dan aturan kerja, dan menunjuk ke `docs/memori/` (peta kode, keputusan desain, jebakan teknis) sehingga tidak perlu memindai seluruh repo. Cara membangun ulang docx panduan dan PRD ada di [docs/alat-dokumen/README.md](docs/alat-dokumen/README.md).

## Teknologi

- PHP 8.3, Laravel 13, Filament 5, Livewire 4
- MariaDB 10.4 atau MySQL 8
- Pest 4 untuk pengujian, Pint untuk format kode

## Menjalankan di lokal

1. Salin `.env.example` menjadi `.env`, lalu `php artisan key:generate`.
2. Buat dua basis data kosong: `dss` dan `dss_test` (utf8mb4).
3. `composer install`, lalu `php artisan migrate`.
4. `php artisan db:seed` membuat empat akun demo (admin@spk.test, manajer@spk.test, supervisor@spk.test, pimpinan@spk.test, kata sandi `password`). Ganti kata sandi ini sebelum dipasang di server.
5. `php artisan serve --port=8001`, buka `http://localhost:8001/admin`.
6. Uji: `php artisan test --compact`. Format: `vendor/bin/pint --dirty`.

## Kebutuhan hosting

Hasil pemeriksaan untuk shared hosting cPanel (tanpa terminal).

- PHP 8.3 atau lebih baru.
- Ekstensi PHP: bcmath, ctype, curl, dom, fileinfo, gd, intl, json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip, exif.
- Basis data MariaDB 10.4 atau MySQL 8. Jangan memakai fungsi yang tidak ada di MariaDB 10.4, misalnya `JSON_ARRAYAGG`.
- Document root diarahkan ke folder `public`. Bila tidak bisa, pakai `.htaccess` di akar yang meneruskan ke `public`.
- Berkas `.env` disimpan di luar folder publik, dengan izin 600.
- Migrasi dijalankan lewat Cron Job atau skrip terkunci karena tidak ada terminal.
- Penjadwal evaluasi memakai Cron Job cPanel yang memanggil `php artisan schedule:run` tiap menit.
- Fungsi yang sering dimatikan di hosting seperti `exec` dan `symlink` tidak dipakai. Tautan storage dibuat dengan menyalin folder, bukan `storage:link`.
