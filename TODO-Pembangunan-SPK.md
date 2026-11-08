# TODO: Pembangunan SPK Prioritas Produksi (DSS SAW)

Acuan: [PRD-SPK-Prioritas-Produksi-SAW.md](PRD-SPK-Prioritas-Produksi-SAW.md) versi 1.5, Bab 14. Nomor tugas, ukuran, dan kriteria selesai di sini sama persis dengan PRD. Bila PRD berubah, ubah berkas ini juga.

Status penandaan: `[ ]` belum, `[~]` sedang dikerjakan, `[x]` selesai. Satu tugas dianggap selesai hanya bila kriteria "Selesai bila" terpenuhi, bukan saat kodenya baru ditulis.

Ukuran: **S** kurang dari 2 jam, **M** sekitar setengah hari, **L** sekitar satu hari atau lebih.

## Aturan kerja

- Aplikasi baru di folder `dss`, repo git terpisah, seluruhnya kode sendiri. Tidak memakai kode ERP lama.
- Laravel 13, Filament 5, Pest untuk uji, Pint untuk format. Teks tampilan berbahasa Indonesia penuh.
- Setiap fitur langsung diuji dengan data demo. Halaman tidak boleh kosong saat dibuka.
- Setiap halaman punya tampilan keadaan kosong, hak akses per peran lewat Policy, dan validasi di server.
- Database harus jalan di MySQL 8 dan MariaDB 10.4. Hindari fungsi yang tidak ada di MariaDB 10.4 (misalnya JSON_ARRAYAGG).
- Commit hanya atas persetujuan pengguna. Tanpa Claude sebagai co-author.

## Pintu keluar tiap fase

Fase dinyatakan selesai bila semua butir ini terpenuhi:

- [ ] Semua tugas di fase itu bertanda `[x]`.
- [ ] `php artisan test --compact` hijau.
- [ ] `vendor/bin/pint --dirty` bersih.
- [ ] Halaman fase itu dibuka di peramban dengan data demo dan tidak ada yang kosong atau error.
- [ ] Peran yang tidak berhak sudah dicoba dan ditolak.

(Salin daftar ini ke catatan fase saat menutup fase.)

## Ringkasan kemajuan

| Fase | Isi | Tugas | Selesai |
|---|---|---|---|
| 0 | Persiapan | 4 | 4 |
| 1 | Fondasi, Autentikasi, Pengguna | 6 | 6 |
| 2 | Data Master dan Order | 8 | 8 |
| 3 | Kriteria, Bobot, Skala | 5 | 5 |
| 4 | Penilaian | 7 | 7 |
| 5 | Impor dan Ekspor Excel | 3 | 3 |
| 6 | Mesin SAW dan Evaluasi | 9 | 0 |
| 7 | Hasil, Persetujuan, Penerapan | 10 | 0 |
| 8 | Dasbor dan Laporan | 6 | 0 |
| 9 | Log, Pengaturan, Penjadwalan | 4 | 0 |
| 10 | Pengujian dan Validasi | 6 | 0 |
| 11 | Pemasangan dan Dokumentasi | 4 | 0 |
| Total | | 72 | 33 |

Urutan: Fase 0 sampai 11 dikerjakan berurutan. Fase 5 butuh Fase 2 dan 4, Fase 6 butuh Fase 3 dan 4, Fase 7 butuh Fase 6, Fase 8 butuh Fase 7.

---

## Fase 0: Persiapan

Tujuan: Proyek kosong berjalan di lokal dengan Filament, Pest, dan Pint siap.

Isi: 4 tugas (S 3, M 1, L 0).

- [x] **P0-01** Membuat project Laravel 13 baru di folder dss, repo git terpisah (S)
  - Selesai bila: Aplikasi kosong berjalan di lokal
- [x] **P0-02** Memasang Filament 5, panel admin, tema, logo teks, bahasa Indonesia, zona waktu Asia/Jakarta (M)
  - Selesai bila: Halaman masuk tampil dalam bahasa Indonesia
- [x] **P0-03** Memasang Pest, Pint, konfigurasi basis data uji (S)
  - Selesai bila: Perintah uji berjalan hijau
- [x] **P0-04** Memeriksa kompatibilitas hosting (PHP 8.3, ekstensi, MariaDB) (S)
  - Selesai bila: Daftar kebutuhan hosting tercatat

## Fase 1: Fondasi, Autentikasi, Pengguna

Tujuan: Semua peran bisa masuk dan melihat menu yang sesuai.

Isi: 6 tugas (S 3, M 3, L 0).

- [x] **P1-01** Migrasi users dengan role dan is_active, enum peran (S)
  - Selesai bila: Migrasi berjalan, model punya helper peran
- [x] **P1-02** Login, keluar, pembatasan percobaan, tolak akun nonaktif (M)
  - Selesai bila: Kriteria penerimaan F01 terpenuhi
- [x] **P1-03** Halaman profil dan ganti kata sandi (S)
  - Selesai bila: Kata sandi baru berlaku
- [x] **P1-04** Halaman manajemen pengguna (F02) dengan aturan Admin terakhir (M)
  - Selesai bila: Kriteria penerimaan F02 terpenuhi
- [x] **P1-05** Policy dasar per peran dan menu yang menyesuaikan peran (M)
  - Selesai bila: Menu tiap peran sesuai Bab 9
- [x] **P1-06** Seeder akun demo empat peran (S)
  - Selesai bila: Empat akun dapat masuk

## Fase 2: Data Master dan Order

Tujuan: Data order lengkap ada di aplikasi, termasuk kasus order lama dan order Dikerjakan.

Catatan: Data demo wajib memuat order yang sudah lama menunggu dan order berstatus Dikerjakan, karena dipakai menguji Fase 6, 7, dan 10.

Isi: 8 tugas (S 1, M 7, L 0).

- [x] **P2-01** Pelanggan: migrasi, model, halaman, validasi (F03) (M)
  - Selesai bila: CRUD pelanggan berjalan
- [x] **P2-02** Produk: migrasi, model, halaman (F04) (M)
  - Selesai bila: CRUD produk berjalan
- [x] **P2-03** Order produksi: migrasi, model, status, nomor otomatis (M)
  - Selesai bila: Order tersimpan dengan nomor unik
- [x] **P2-04** Halaman daftar order dengan filter terlambat dan mendekati tenggat, badge status (M)
  - Selesai bila: Filter memberi hasil benar
- [x] **P2-05** Formulir order dengan validasi tanggal dan harga dari produk (M)
  - Selesai bila: Kriteria penerimaan F05 terpenuhi
- [x] **P2-06** Halaman detail order dan riwayat perubahan (M)
  - Selesai bila: Detail menampilkan data lengkap
- [x] **P2-07** Seeder data demo: 5 pelanggan, 4 produk, 25 order beragam status dan tenggat, termasuk beberapa order lama yang sudah lama menunggu dan order Dikerjakan (M)
  - Selesai bila: Semua halaman berisi data, termasuk kasus order lama
- [x] **P2-08** Kolom penguncian pada order (jenis, alasan, pemasang, waktu, tanggal berakhir) dan ikon kunci di daftar order (S)
  - Selesai bila: Kolom tersedia dan ikon tampil pada order terkunci

## Fase 3: Kriteria, Bobot, Skala

Tujuan: Tujuh kriteria (C1 sampai C7) siap dengan bobot dan skala yang bisa diubah dari halaman.

Catatan: Nilai bobot dan skala tidak boleh ditanam di kode. Semuanya dari basis data.

Isi: 5 tugas (S 3, M 1, L 1).

- [x] **P3-01** Migrasi criteria (termasuk sumber nilai lama menunggu) dan criterion_scales (S)
  - Selesai bila: Tabel tersedia
- [x] **P3-02** Halaman kriteria dengan tingkat kepentingan dan bobot otomatis (M)
  - Selesai bila: Contoh bobot F07 tepat
- [x] **P3-03** Mode atur bobot langsung dengan validasi total (S)
  - Selesai bila: Total selain 100% ditolak
- [x] **P3-04** Halaman skala per kriteria dengan validasi rentang dan uji rentang (L)
  - Selesai bila: Kriteria penerimaan F08 terpenuhi
- [x] **P3-05** Seeder tujuh kriteria (C1 sampai C7) dan skala contoh Bab 11.5 (S)
  - Selesai bila: Kriteria siap pakai, total bobot 1

## Fase 4: Penilaian

Tujuan: Setiap order punya nilai per kriteria, sebagian otomatis, sebagian manual.

Isi: 7 tugas (S 4, M 2, L 1).

- [x] **P4-01** Migrasi assessments dan assessment_histories (S)
  - Selesai bila: Tabel tersedia
- [x] **P4-02** Layanan nilai otomatis: sisa hari, urgensi pelanggan, nilai order, lama menunggu (M)
  - Selesai bila: Nilai otomatis benar di uji unit
- [x] **P4-03** Layanan konversi nilai asli ke skala (M)
  - Selesai bila: Contoh F08 lolos uji
- [x] **P4-04** Halaman matriks penilaian dengan edit sel, penanda sumber, kelengkapan, kedaluwarsa (L)
  - Selesai bila: Kriteria penerimaan F09 terpenuhi
- [x] **P4-05** Penimpaan urgensi per order dengan alasan (S)
  - Selesai bila: Alasan wajib
- [x] **P4-06** Riwayat perubahan nilai (S)
  - Selesai bila: Setiap ubah tercatat
- [x] **P4-07** Seeder nilai penilaian demo, termasuk beberapa yang kosong dan kedaluwarsa (S)
  - Selesai bila: Penanda tampil di demo

## Fase 5: Impor dan Ekspor Excel

Tujuan: Order dan nilai bisa dimasukkan massal lewat Excel dan diekspor.

Isi: 3 tugas (S 2, M 0, L 1).

- [x] **P5-01** Templat Excel order dan penilaian (S)
  - Selesai bila: Templat dapat diunduh
- [x] **P5-02** Impor dengan pratinjau, validasi per baris, mode tambah atau perbarui, transaksi (L)
  - Selesai bila: Kriteria penerimaan F06 terpenuhi
- [x] **P5-03** Ekspor daftar order (S)
  - Selesai bila: File sesuai filter

## Fase 6: Mesin SAW dan Evaluasi

Tujuan: Mesin SAW menghitung benar, peringkat akhir berkelompok, hasil tersimpan sebagai evaluasi.

Catatan: Peringkat sistem murni berdasarkan skor. Peringkat akhir menambahkan kelompok (terkunci, eskalasi, biasa) dan penyesuaian manual. Kolom evaluated_at tidak boleh memakai ON UPDATE.

Isi: 9 tugas (S 3, M 6, L 0).

- [ ] **P6-01** Kelas SawCalculator murni (tanpa basis data) sesuai Bab 11 (M)
  - Selesai bila: Contoh 11.6 lolos uji
- [ ] **P6-02** Uji unit kasus khusus (satu order, nilai sama, nilai kosong, skor sama) (M)
  - Selesai bila: Semua lolos
- [ ] **P6-03** Migrasi evaluations dan evaluation_items (tanpa ON UPDATE pada evaluated_at) (S)
  - Selesai bila: Tabel tersedia
- [ ] **P6-04** Layanan evaluasi: pemeriksaan awal, hitung, simpan salinan (M)
  - Selesai bila: Evaluasi tersimpan lengkap
- [ ] **P6-05** Aksi Jalankan Perhitungan dengan konfirmasi data belum lengkap (M)
  - Selesai bila: Kriteria penerimaan F10 terpenuhi
- [ ] **P6-06** Uji kinerja 200 dan 1.000 order (S)
  - Selesai bila: Waktu sesuai NF01
- [ ] **P6-07** Penyusunan peringkat akhir berkelompok: terkunci, eskalasi, biasa (Bab 7.7), dengan peringkat sistem tetap murni (M)
  - Selesai bila: Urutan kelompok dan aturan seri sesuai spesifikasi
- [ ] **P6-08** Uji unit pengelompokan dan eskalasi: contoh Bab 11.7, batas 7 dan 8 hari, order terkunci, kunci kedaluwarsa (M)
  - Selesai bila: Semua kasus lolos
- [ ] **P6-09** Simpan lama menunggu, kelompok, dan peringkat sebelumnya pada setiap item evaluasi (S)
  - Selesai bila: Kolom terisi dan tren peringkat bisa dibaca

## Fase 7: Hasil, Persetujuan, Penerapan

Tujuan: Hasil bisa disesuaikan, disetujui, diterapkan, dan order lama terlindungi.

Catatan: Batas tunggu maksimum (bawaan 7 hari) dan kunci otomatis dibaca dari pengaturan. Bila halaman pengaturan (P9-02) belum ada, pakai nilai bawaan dari config lalu sambungkan ke pengaturan saat P9-02 selesai.

Isi: 10 tugas (S 3, M 5, L 2).

- [ ] **P7-01** Daftar evaluasi dengan status dan filter (M)
  - Selesai bila: Daftar tampil
- [ ] **P7-02** Detail evaluasi tujuh tab (L)
  - Selesai bila: Semua tab berisi data
- [ ] **P7-03** Penyesuaian peringkat dengan alasan (M)
  - Selesai bila: Peringkat sistem dan akhir tersimpan
- [ ] **P7-04** Alur ajukan, setujui, tolak, terapkan sesuai pengaturan (L)
  - Selesai bila: Kriteria penerimaan F11 terpenuhi
- [ ] **P7-05** Penanda Prioritas Utama di order (S)
  - Selesai bila: Label tampil di daftar order
- [ ] **P7-06** Bandingkan dua evaluasi (M)
  - Selesai bila: Naik, turun, tetap tampil benar
- [ ] **P7-07** Aksi pasang dan lepas kunci manual dengan alasan wajib dan tanggal berakhir opsional (M)
  - Selesai bila: Kriteria penerimaan F18 nomor 3 terpenuhi
- [ ] **P7-08** Kunci otomatis saat status Dikerjakan dan pelepasan saat Selesai atau Dibatalkan, mengikuti pengaturan (S)
  - Selesai bila: Ikon kunci muncul dan hilang sesuai status
- [ ] **P7-09** Grafik tren peringkat per order di halaman detail (M)
  - Selesai bila: Grafik sesuai riwayat evaluasi
- [ ] **P7-10** Peringatan bila slot Prioritas Utama penuh oleh kunci manual dan eskalasi; order Dikerjakan tidak dihitung (S)
  - Selesai bila: Peringatan tampil di hasil evaluasi

## Fase 8: Dasbor dan Laporan

Tujuan: Dasbor dan laporan menampilkan angka yang sama dengan layar perhitungan.

Isi: 6 tugas (S 2, M 2, L 2).

- [ ] **P8-01** Widget kartu angka, prioritas teratas, order terlambat, grafik status (L)
  - Selesai bila: Kriteria penerimaan F12 terpenuhi
- [ ] **P8-02** Dasbor menyesuaikan peran (Pimpinan melihat persetujuan) (S)
  - Selesai bila: Isi sesuai peran
- [ ] **P8-03** Kop laporan dari pengaturan perusahaan (S)
  - Selesai bila: Kop tampil
- [ ] **P8-04** Laporan peringkat dan rincian perhitungan PDF dan Excel (L)
  - Selesai bila: Angka sama dengan layar
- [ ] **P8-05** Laporan order terlambat, rekap order, riwayat evaluasi (M)
  - Selesai bila: Filter periode berjalan
- [ ] **P8-06** Widget order tertahan di dasbor dan filter Tertahan di daftar order (M)
  - Selesai bila: Kriteria penerimaan F18 nomor 4 terpenuhi

## Fase 9: Log, Pengaturan, Penjadwalan

Tujuan: Aktivitas tercatat, pengaturan bisa diubah, evaluasi bisa dijadwalkan, notifikasi jalan.

Catatan: Nilai bawaan perlindungan order lama: kunci otomatis aktif, batas tunggu 7 hari, tertahan setelah 5 evaluasi dan 4 hari, N sama dengan 5.

Isi: 4 tugas (S 1, M 3, L 0).

- [ ] **P9-01** Pencatat log aktivitas dan halaman log (M)
  - Selesai bila: Kriteria penerimaan F14 terpenuhi
- [ ] **P9-02** Halaman pengaturan (F15), termasuk kunci otomatis, batas tunggu maksimum, dan ambang order tertahan (M)
  - Selesai bila: Semua pengaturan tersimpan dan dipakai
- [ ] **P9-03** Perintah evaluasi terjadwal dan dokumentasi Cron cPanel (S)
  - Selesai bila: Evaluasi terjadwal tercipta pada jam yang diatur
- [ ] **P9-04** Notifikasi dalam aplikasi (F17) untuk pengajuan, persetujuan, penerapan, order terlambat, nilai kedaluwarsa, order tertahan, dan order dieskalasi (M)
  - Selesai bila: Kriteria penerimaan F17 terpenuhi

## Fase 10: Pengujian dan Validasi

Tujuan: Ketepatan hitung, hak akses, dan perlindungan order lama terbukti dengan data.

Catatan: Simulasi P10-06 membandingkan batas 5, 7, dan 10 hari. Hasilnya dipakai menguji apakah 7 hari wajar.

Isi: 6 tugas (S 1, M 5, L 0).

- [ ] **P10-01** Uji hak akses semua peran untuk setiap halaman dan aksi (M)
  - Selesai bila: Tidak ada akses tanpa izin
- [ ] **P10-02** Skenario uji black box per fitur (M)
  - Selesai bila: Tabel skenario lengkap dan lolos
- [ ] **P10-03** File Excel perhitungan manual untuk data uji Bagian J (M)
  - Selesai bila: Selisih skor di bawah 0,0001
- [ ] **P10-04** Hitung korelasi Spearman dengan urutan perusahaan (S)
  - Selesai bila: Nilai dan analisis tercatat
- [ ] **P10-05** UAT dan kuesioner SUS (M)
  - Selesai bila: Hasil tercatat
- [ ] **P10-06** Simulasi order tertahan 30 hari, tanpa dan dengan perlindungan (batas 5, 7, dan 10 hari), lalu bandingkan hasilnya (M)
  - Selesai bila: Tabel perbandingan tercatat dan sasaran T7 tercapai

## Fase 11: Pemasangan dan Dokumentasi

Tujuan: Sistem terpasang di staging, dokumentasi dan repositori siap.

Catatan: .env disimpan di luar folder publik. Dokumen, mockup, dan tes tidak ikut diunggah ke server.

Isi: 4 tugas (S 1, M 1, L 2).

- [ ] **P11-01** Paket rilis dan pemasangan di staging (.env di luar folder publik) (M)
  - Selesai bila: Staging dapat dipakai semua peran
- [ ] **P11-02** Buku panduan pengguna per peran (docx) (L)
  - Selesai bila: Panduan lengkap dengan tangkapan layar
- [ ] **P11-03** Dokumen teknis: arsitektur, ERD, use case, rumus, pengujian (L)
  - Selesai bila: Siap dipakai untuk Bab 3 dan 4
- [ ] **P11-04** README dan repositori GitHub (S)
  - Selesai bila: Repo terunggah

---

## Catatan kemajuan

Isi tiap kali fase ditutup: tanggal, hasil uji (jumlah lulus), temuan, dan keputusan baru.

- 5 Nov 2026, Fase 0 selesai. Laravel 13.40, Filament 5.11, Livewire 4.4, Pest 4.7, PHP 8.3.35, MariaDB 10.4.32. Basis data `dss` dan `dss_test`. Tes: 3 lulus (halaman masuk berbahasa Indonesia, locale id dan zona Asia/Jakarta, tamu diarahkan ke login). Kebutuhan hosting dicatat di README.
- 6 Nov 2026, Fase 1 selesai. Peran lewat enum UserRole di kolom users.role, Policy per peran, halaman masuk khusus (pesan "Akun Anda dinonaktifkan", jeda setelah 5 kali gagal), profil dengan email hanya dibaca, kelola pengguna dengan aturan Admin terakhir dan larangan menonaktifkan diri sendiri. Tes: 33 lulus (138 assertion). Uji tiruan (mutasi) pada aturan Admin terakhir membuktikan tesnya benar-benar menangkap kesalahan. Menu per peran baru mencakup Dasbor dan Pengguna; menu lain mengikuti saat halamannya dibuat di fase berikutnya, dan Policy-nya wajib dibuat bersamaan dengan resource-nya. Akun demo: admin@spk.test, manajer@spk.test, supervisor@spk.test, pimpinan@spk.test, kata sandi `password`.
- 6 Nov 2026, Fase 2 selesai. Pelanggan, produk, dan order (daftar, filter, formulir, detail) berjalan dengan hak akses per peran. Tes: 79 lulus (432 assertion). Data demo: 5 pelanggan, 4 produk, 25 order dengan tanggal relatif hari ini, berisi 3 order terlambat, 2 mendekati tenggat, 3 order lama yang menunggu lebih dari 7 hari, 6 order Dikerjakan (kunci otomatis), dan 1 kunci manual. Catatan keputusan: (1) tabel `activity_logs` dimajukan dari Fase 9 hanya untuk riwayat perubahan order di halaman detail (P2-06); halaman log dan pencatatan kegiatan lain tetap di P9-01. (2) Kunci otomatis saat status berubah ke Dikerjakan belum dibuat, itu P7-08. (3) Kolom peringkat terakhir, bagian penilaian terakhir, dan grafik tren di halaman order menyusul di fase yang menghasilkan datanya.
- 6 Nov 2026, perbaikan UX dari pemeriksaan di peramban: kolom Masuk, Menunggu, dan Prioritas di daftar order disembunyikan bawaannya (bisa dinyalakan lewat ikon kolom), menu aksi baris dipindah ke kiri dalam satu menu tiga titik, tombol Lihat dihapus karena seluruh baris bisa diklik, dan menu kiri bisa dipersempit. Tes tetap 79 lulus. Dokumentasi langkah uji Fase 0 sampai 2 dibuat di `Panduan-Penggunaan-dan-Pengujian-SPK` (md dan docx) dan bertambah satu bab tiap fase.
- 6 Nov 2026, Fase 3 selesai. Kriteria dan bobot (halaman Kriteria dan Bobot), skala nilai (halaman Skala Nilai dengan uji rentang), tujuh kriteria awal dengan skala contoh Bab 11.5. Tes: 133 lulus (645 assertion). Uji tiruan (mutasi) pada pembulatan bobot dan deteksi tumpang tindih membuktikan tesnya menangkap kesalahan. Keputusan perancangan: (1) tabel `settings` dimajukan dari Fase 9 hanya untuk menyimpan mode bobot (`weight_mode`: dihitung dari tingkat kepentingan atau diatur langsung); halaman pengaturan tetap di P9-02. (2) Mode bobot awal data demo adalah diatur langsung, karena bobot usulan PRD (0,27; 0,18; 0,135; ...) tidak bisa dihasilkan dari tingkat kepentingan bilangan bulat. (3) Skala nilai hanya diubah Manajer sesuai F08, kriteria dan bobot diubah Manajer dan Pimpinan sesuai Bab 9. (4) Jenis nilai kosong, riwayat bobot di log sudah tercatat tetapi halaman lognya baru di P9-01.
- 6 Nov 2026, perbaikan setelah pemeriksaan menyeluruh (tes 135 lulus, 652 assertion): (1) tangkapan layar halaman panjang di panduan salah karena bilah atas tertempel di tengah gambar, diambil ulang memakai jendela yang cukup tinggi; (2) daftar pengguna, pelanggan, dan produk melebar di layar 1366 piksel sehingga tombol terpotong, aksi baris dipindah ke menu tiga titik di kiri seperti daftar order dan kriteria; (3) status "Siap dihitung" kini juga memeriksa bahwa setiap kriteria aktif punya skala yang lengkap dan benar; (4) docx PRD dibangun ulang supaya nilai bawaan batas tunggu 7 hari dan ambang tertahan 4 hari di kamus data sama dengan md. Penyapuan semua halaman untuk keempat peran tidak menemukan error JavaScript atau respons gagal selain 403 yang memang disengaja.
- 6 Nov 2026, revisi tampilan atas permintaan pemilik: semua badge (sisa hari, status, prioritas, urgensi, peran) kini berwarna penuh dengan teks putih, bukan latar pucat transparan. Diatur satu kali di panel (`AdminPanelProvider`, gaya di head), berlaku di seluruh halaman. Tangkapan layar panduan diambil ulang. Tes tetap 135 lulus.
- 6 Nov 2026, Fase 4 selesai. Halaman Penilaian Order (matriks order dan kriteria dengan nilai asli, skala, dan sumber), nilai otomatis C1, C2, C6, C7 yang dihitung ulang setiap dibuka, nilai manual C3, C4, C5 (dan C6 bila order tanpa harga satuan), penimpaan urgensi dengan alasan wajib, batas persen, jam, dan rupiah, riwayat nilai, penanda kelengkapan dan filternya, serta bagian Penilaian dan tab Riwayat nilai di detail order. Tes: 211 lulus (1.005 assertion). Uji tiruan pada batas kedaluwarsa dan hak akses Supervisor membuktikan tesnya menangkap kesalahan.
  - Keputusan perancangan: (1) kedaluwarsa dihitung dalam hari kalender dan hanya untuk nilai manual; urgensi yang ditimpa dan nilai otomatis tidak pernah kedaluwarsa. Batas dibaca dari `stale_value_days` (bawaan 3), halaman pengaturannya tetap di P9-02. (2) Menyimpan ulang nilai yang sama menyegarkan waktunya tanpa menambah riwayat. (3) Nilai manual yang dikosongkan tercatat di riwayat dengan nilai baru kosong. (4) Impor nilai dari Excel (F09 butir 5) dikerjakan di Fase 5.
  - Koreksi dari Fase 2: nilai order dalam rupiah tidak boleh dilihat Supervisor (Bab 9, K-06) tetapi sebelumnya masih tampil. Sekarang disembunyikan dari Supervisor di detail order (harga satuan dan nilai order), kolom harga di daftar Produk, kolom C6 di matriks penilaian, baris harga di riwayat perubahan order, dan riwayat nilai C6.
- 7 Nov 2026, Fase 5 selesai. Templat Excel (lembar Order dan lembar Petunjuk), halaman Impor Order (unggah, pilih mode, periksa, konfirmasi, ringkasan, daftar baris yang ditolak tetap terlihat), pengimpor dengan validasi per baris dan satu transaksi, ekspor daftar order sesuai filter dan pencarian, serta perintah `php artisan orders:sample-import` untuk membuat berkas uji. Tes: 282 lulus (1.332 assertion). Uji tiruan pada batas 1.000 baris, mode tambah saja, kolom kosong tidak menimpa, dan hak akses impor membuktikan tesnya menangkap kesalahan. Diuji juga di peramban sungguhan: berkas 20 order benar dan 2 rusak diunggah, diperiksa, diimpor, lalu ekspor dan templat yang diunduh terbaca kembali tanpa kesalahan.
  - Keputusan perancangan: (1) tanpa paket baru, memakai OpenSpout yang sudah ada bersama Filament. (2) Impor dan ekspor hanya untuk Admin dan Manajer (F06 dan Bab 9). (3) Baris bermasalah dilewati dan baris benar tetap diimpor, sesuai kriteria penerimaan F06 nomor 2. (4) Berkas dibaca ulang saat konfirmasi, tidak memakai data pratinjau di halaman, supaya yang tersimpan selalu berasal dari berkas. (5) Saat memperbarui, kolom wajib menimpa dan kolom opsional yang kosong membiarkan data lama. (6) Kolom bahan, sisa waktu, dan progres dipetakan ke kriteria C3, C4, C5 berdasarkan kode dan lewat batas yang sama dengan halaman Penilaian Order. (7) Order yang diimpor sebagai Dikerjakan belum bergembok otomatis, itu P7-08.
