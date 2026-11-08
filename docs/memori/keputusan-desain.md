# Keputusan desain

Disusun per topik. Urutan kronologis per fase ada di bagian "Catatan kemajuan" pada `TODO-Pembangunan-SPK.md`. Rumus dan contoh hitung lengkap ada di PRD Bab 11. Bila PRD dan berkas ini berbeda, tanyakan ke pemilik proyek, jangan memilih sendiri.

## Latar

- Dosen pembimbing menilai sistem lama (ERP) bukan ERP dan meminta DSS saja. Pemilik proyek memilih membangun aplikasi baru (Plan B), bukan mengganti nama sistem lama.
- Cara isi nilai: gabungan (cara 3). Otomatis untuk tenggat, urgensi pelanggan, nilai order, dan lama menunggu. Manual atau impor Excel untuk bahan, sisa kerja, dan progres.
- Kuesioner pemetaan kebutuhan untuk client (PT Weba) ada di luar repo ini. Angka di PRD yang diberi catatan "mengikuti kuesioner" adalah usulan awal, bisa berubah.

## Metode SAW

1. Nilai asli dikonversi ke skala 1 sampai 5 lewat tabel skala per kriteria (5 paling diprioritaskan).
2. Normalisasi r = x dibagi nilai skala terbesar di kolom itu (semua kriteria diperlakukan sebagai benefit setelah dikonversi ke skala).
3. Skor V = jumlah bobot kali r. Simpan 6 desimal, tampilkan 4.
4. Nilai kosong diberi skala 1 dan dicatat sebagai peringatan.
5. SAW berjalan untuk jumlah kriteria berapa pun. Contoh PRD 11.6 dan 11.7 adalah uji unit wajib mesin SAW (Fase 6).

## Kriteria dan bobot

- Tujuh kriteria awal: C1 tenggat (sisa hari), C2 urgensi pelanggan, C3 bahan tersedia (persen), C4 sisa waktu kerja (jam), C5 progres (persen), C6 nilai order (rupiah), C7 lama menunggu (hari). Bobot awal 0,27; 0,18; 0,135; 0,135; 0,09; 0,09; 0,10.
- Bobot dihitung dalam basis poin (total 10000) di `CriterionWeights`, disimpan decimal(6,4). Dua mode, disimpan di `settings` kunci `weight_mode`: `importance` (dihitung dari tingkat kepentingan 1 sampai 5) dan `direct` (diatur langsung). Data demo memakai `direct` karena bobot usulan PRD tidak bisa dihasilkan dari tingkat kepentingan bilangan bulat.
- Siap dihitung bila ada sedikitnya 2 kriteria aktif, total bobot tepat 1, dan tiap kriteria aktif punya skala yang valid.
- Skala: batas bawah termasuk, batas atas tidak termasuk, ujung boleh terbuka, tidak boleh tumpang tindih atau bolong, arah harus konsisten (`ScaleRules`).
- Kriteria dan bobot diubah Manajer dan Pimpinan. Skala nilai hanya Manajer.

## Penilaian (Fase 4)

- Nilai otomatis (C1, C2, C6, C7) dihitung ulang setiap dibuka, tidak disimpan. Nilai manual (C3, C4, C5) disimpan dengan waktu pembaruan.
- Urgensi C2 bisa ditimpa Manajer dengan alasan wajib. C6 manual hanya bila order tidak punya harga satuan.
- Kedaluwarsa: hari kalender lebih dari `stale_value_days` (bawaan 3), hanya untuk nilai manual. Nilai otomatis dan urgensi yang ditimpa tidak pernah kedaluwarsa.
- Kelengkapan order: Belum lengkap lebih berat dari Kedaluwarsa, lebih berat dari Lengkap.
- Menyimpan ulang nilai yang sama menyegarkan waktunya tanpa menambah riwayat. Mengosongkan nilai tercatat di riwayat.
- Supervisor hanya mengubah C4 dan C5. Manajer mengubah semua nilai manual dan penimpaan. Admin dan Pimpinan tidak memakai halaman penilaian.

## Perlindungan order lama (PRD 7.7)

Enam lapis, karena satu cara saja terbukti tidak cukup (PRD 11.7): tenggat C1, lama menunggu C7, kunci otomatis untuk order Dikerjakan, kunci manual beralasan, eskalasi otomatis, dan pemantauan order tertahan.

Keputusan perancang (disebut K-12 sampai K-14 di PRD, ditolak untuk ditanyakan ulang ke client):

1. K-12: order Dikerjakan tidak boleh disalip pesanan baru. Kunci otomatis tetap, bisa dimatikan lewat pengaturan.
2. K-13: jumlah Prioritas Utama N hanya menghitung antrean berstatus Antri. Order Dikerjakan tidak memakai slot. Kunci manual dan eskalasi memakai slot. Bila keduanya saja sudah memenuhi N, tampilkan peringatan.
3. K-14: batas tunggu maksimum bawaan 7 hari (bukan 14), bisa diatur. Ambang order tertahan 4 hari. Nilai final divalidasi lewat simulasi 5, 7, dan 10 hari serta kuesioner.

Peringkat sistem murni dari skor. Peringkat akhir disusun tiga kelompok berurutan: terkunci (urut skor), eskalasi (urut paling lama menunggu, lalu skor), biasa (urut skor). Skala C7 dirancang agar skala 5 tercapai tepat di batas tunggu maksimum. Bila batas diubah, skala C7 perlu disesuaikan. Kolom `evaluated_at` tidak boleh memakai ON UPDATE.

## Hak akses

Matriks lengkap di PRD Bab 9, ringkas di panduan Bab 10. Peran: Admin, Manajer Produksi, Supervisor, Pimpinan (enum `UserRole`: admin, manager, supervisor, leader). Semua lewat Policy. Aturan khusus:

- Supervisor tidak boleh melihat rupiah. Disembunyikan di detail order, kolom harga produk, kolom C6 di matriks penilaian, riwayat perubahan order, dan riwayat nilai C6.
- Pengguna tidak dihapus, hanya dinonaktifkan. Admin terakhir tidak boleh dinonaktifkan atau diubah perannya. Tidak boleh menonaktifkan diri sendiri.
- Impor dan ekspor hanya Admin dan Manajer.
- Lima kali gagal masuk dalam satu menit memicu jeda 60 detik.

## Impor dan ekspor Excel (Fase 5)

- Memakai OpenSpout bawaan Filament, tanpa paket baru. Maksimal 1.000 baris.
- Baris bermasalah dilewati, baris benar tetap diimpor, semuanya dalam satu transaksi. Baris yang ditolak tetap tampil setelah impor.
- Berkas dibaca ulang saat konfirmasi, bukan memakai data pratinjau.
- Mode tambah saja atau tambah dan perbarui. Saat memperbarui, kolom wajib menimpa dan kolom opsional kosong membiarkan data lama.
- Ekspor mengikuti filter dan pencarian di daftar. Sebelas kolom pertama sama dengan templat sehingga hasil ekspor bisa diimpor lagi tanpa mengubah data.

## Sengaja belum dibuat atau ditunda

- Kunci otomatis saat status berubah ke Dikerjakan, dan pelepasan saat selesai atau batal (P7-08). Order demo sudah terkunci lewat data awal, tetapi order yang diimpor atau diubah statusnya dengan tangan belum otomatis terkunci.
- Tabel `activity_logs` dan `settings` dimajukan dari Fase 9 dengan isi minimal. Halaman log (P9-01) dan halaman pengaturan (P9-02) belum ada. `stale_value_days` dibaca dari tabel `settings`, bawaan 3.
- Kolom peringkat terakhir, bagian hasil di detail order, dan grafik tren menyusul di fase yang menghasilkan datanya.
- Angka demo "4 belum lengkap, 4 kedaluwarsa" hanya benar pada hari data di-seed, karena tanggal di seeder relatif terhadap hari ini.
- Belum ada commit di riwayat git saat berkas ini ditulis.

## Batasan teknis yang disepakati

- Harus jalan di MySQL 8 dan MariaDB 10.4. Jangan pakai fungsi yang tidak ada di MariaDB 10.4, misalnya `JSON_ARRAYAGG`.
- Target hosting shared hosting cPanel tanpa terminal: tanpa `exec` dan `symlink`, tautan storage lewat salin folder, penjadwal lewat Cron Job yang memanggil `schedule:run`. Detail di `README.md`.
