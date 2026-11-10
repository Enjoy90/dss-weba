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
5. SAW berjalan untuk jumlah kriteria berapa pun. Contoh PRD 11.6 dan 11.7 adalah uji unit mesin SAW dan pengurut peringkat (`tests/Unit`).

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

## Evaluasi dan peringkat (Fase 6)

- `SawCalculator` dan `PriorityRanker` murni, tanpa basis data dan tanpa jam. Kunci yang masih berlaku dan lama menunggu dihitung oleh pemanggil (`EvaluationService`), jadi pengurut mudah diuji.
- Kunci dibaca dari kolom `lock_type` lewat `ProductionOrder::lockIsActive()`, bukan dari status order. Kunci manual berlaku sampai tanggal berakhirnya dan lepas keesokan harinya. Kunci otomatis hanya dihitung bila `lock_in_progress` aktif.
- Eskalasi: order Antri, tidak terkunci, lama menunggu lebih dari `max_wait_days` (7 hari belum, 8 hari ya). Order Dikerjakan tidak pernah dieskalasi.
- Urutan di dalam kelompok: Terkunci dan Biasa menurut skor, Eskalasi menurut lama menunggu lalu skor. Seri skor: tenggat terdekat, tanggal masuk lebih awal, lalu id order terkecil (supaya hasil tidak bergantung urutan data).
- Nilai kosong diberi skala 1 dan dicatat di `warnings` (`empty`). Nilai manual kedaluwarsa dicatat sebagai `stale`. Order belum lengkap tetap ikut dihitung (K-03). Opsi "wajib lengkap" belum dibuat.
- Pemeriksaan sebelum hitung: pemblokir (bobot dan skala siap, ada order aktif) menolak sama sekali. Order belum lengkap atau kedaluwarsa butuh konfirmasi, dijaga di server lewat `acknowledgeWarnings`. Jalur terjadwal (Fase 9) harus memberi `true` dan memakai pemicu `scheduled` tanpa pengguna.
- Evaluasi disimpan dalam satu transaksi bersama salinan kriteria, bobot, skala, dan pengaturan. Item disisipkan massal. Evaluasi dan item tidak bisa diubah atau dihapus di tingkat model, kecuali kolom alur status pada evaluasi (`Evaluation::WORKFLOW_COLUMNS`) dan `final_rank` serta `adjustment_reason` pada item (`EvaluationItem::ADJUSTABLE_COLUMNS`). `evaluated_at` memakai kolom datetime, bukan timestamp.
- `previous_rank` diambil dari evaluasi terakhir yang tidak Dibatalkan, hanya bila order ada di evaluasi itu. Pada evaluasi pertama kolom Perubahan di layar berisi tanda hubung.
- Pengaturan dibaca dari `settings` dengan bawaan: `max_wait_days` 7, `lock_in_progress` aktif, `top_n_priority` 5, `stale_value_days` 3. Salinannya disimpan di `settings_snapshot`.
- Halaman Jalankan Perhitungan hanya untuk Manajer. Tabel peringkat di halaman itu bersifat sementara sampai halaman evaluasi (P7-01 dan P7-02) ada. Salinan order tidak memuat harga, tetapi `raw_values` kriteria C6 memuat rupiah: tampilan evaluasi untuk Supervisor wajib menyembunyikannya.

## Hasil, persetujuan, dan kunci (Fase 7)

- Alur status: Draf, Diajukan, Disetujui, Diterapkan, serta Dibatalkan. Evaluasi yang ditolak kembali ke Draf dengan `rejection_note` yang terbaca di halaman, dan catatan itu dikosongkan saat diajukan lagi. Pengaturan `approval_required` bawaannya aktif. Bila dimatikan, Draf bisa langsung diterapkan dan tombol Ajukan tidak ada.
- Peran (EvaluationPolicy): ajukan oleh Manajer, setujui dan tolak oleh Pimpinan, terapkan, sesuaikan peringkat, dan batalkan oleh Manajer dan Pimpinan, lihat oleh semua. Batalkan adalah keputusan perancang: PRD menyebut status Dibatalkan tetapi tidak menyebut siapa yang membatalkan. Hanya evaluasi yang belum diterapkan yang bisa dibatalkan.
- Aturan peran dan status dijaga di server (`EvaluationWorkflow`, `EvaluationAdjuster`, `OrderLocker`) lewat Gate. Predikat status ada di model `Evaluation` (`allowsSubmit`, `allowsDecision`, `allowsApply`, `allowsAdjust`, `allowsCancel`) dan dipakai juga untuk menampilkan tombol.
- Terapkan: N order Antri teratas pada peringkat akhir mendapat label Prioritas Utama. N dibaca dari `top_n` yang tersimpan di evaluasi, status Antri dibaca dari order saat diterapkan, dan semua order lain yang masih berlabel dikembalikan ke Normal. Hanya evaluasi terbaru yang tidak Dibatalkan yang bisa diterapkan. Tidak ada kolom baru di tabel order.
- Slot dihitung `PriorityPlan`. Peringatan "slot penuh" muncul bila order Antri yang dikunci manual ditambah yang dieskalasi sudah sebanyak N atau lebih. Di halaman evaluasi, status Antri dibaca dari salinan order di evaluasi.
- Penyesuaian peringkat: pindah ke peringkat yang dipilih, order lain bergeser. Alasan wajib dan disimpan di order yang dipindah, peringkat sistem tidak berubah, hanya pada Draf atau Diajukan. Order Terkunci selalu harus berada di atas semua order Biasa, jadi order terkunci tidak boleh turun di bawah order biasa dan order biasa tidak boleh naik di atas order terkunci. Order Eskalasi boleh naik di atas order terkunci. Tiap penyesuaian tercatat di log.
- Kunci otomatis dipasang oleh hook `saving` di model `ProductionOrder`, jadi berlaku untuk formulir, ubah status, dan impor. Menjadi Dikerjakan memasang kunci otomatis (menggantikan kunci manual bila ada), kembali ke Antri melepas kunci otomatis saja, dan menjadi Selesai atau Dibatalkan melepas semua kunci dan mengembalikan label Prioritas Utama ke Normal. Tidak berjalan bila `lock_in_progress` mati.
- Kunci manual lewat `OrderLocker`: hanya Manajer dan Pimpinan, alasan wajib saat pasang dan lepas, tanggal berakhir boleh kosong tetapi tidak boleh sebelum hari ini, hanya untuk order yang masih terbuka dan belum terkunci. Kunci otomatis tidak dilepas dengan tangan. Tiap aksi menulis satu baris log (`order.locked` atau `order.unlocked`) dan memakai `saveQuietly` supaya tidak ada baris `order.updated` ganda.
- `isLocked()` dan scope `locked()` tidak menghitung kunci manual yang tanggal berakhirnya sudah lewat. Mesin evaluasi memakai aturan yang sama lewat `lockIsActive()`.
- Label Prioritas Utama tampil sebagai badge kedua di sel Status daftar order, bukan kolom baru, supaya muat di 1366 piksel. Kolom Produk disembunyikan bawaannya karena tabel order sebelumnya sudah lebih lebar dari 1366 piksel dengan menu kiri terbuka. Kolom Prioritas tetap ada dan tersembunyi.
- Halaman Hasil Evaluasi bisa dilihat semua peran. Supervisor tidak melihat kriteria nilai order (C6) di tab mana pun, dan di tab yang terdampak ada catatan bahwa kriteria itu tidak ditampilkan.
- Bandingkan evaluasi tidak punya menu sendiri, dibuka dari daftar dan dari halaman evaluasi. Naik berarti peringkat akhir lebih baik di evaluasi "sesudah".
- Grafik tren (`OrderRankTrendChart`) berada di infolist halaman order, sumbu peringkat dibalik (peringkat 1 di atas), evaluasi Dibatalkan tidak ikut.
- Notifikasi N1 sampai N10 dan pemantauan order Tertahan belum dibuat (Fase 8 dan 9).

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

- Pemantauan order Tertahan (Fase 8 dan 9), opsi "wajib lengkap", notifikasi (termasuk N1 sampai N10), dan evaluasi terjadwal belum ada. Pengaturan `approval_required`, `top_n_priority`, `max_wait_days`, dan `lock_in_progress` belum punya halaman, nilainya bawaan dari kode sampai P9-02.
- Kunci otomatis tetap ditampilkan walau `lock_in_progress` dimatikan, sedangkan mesin evaluasi mengabaikannya. Itu baru relevan setelah halaman pengaturan ada.
- Tabel `activity_logs` dan `settings` dimajukan dari Fase 9 dengan isi minimal. Halaman log (P9-01) dan halaman pengaturan (P9-02) belum ada. `stale_value_days`, `max_wait_days`, `lock_in_progress`, dan `top_n_priority` dibaca dari tabel `settings` dengan bawaan di kode.
- Kolom peringkat terakhir di daftar order belum ada. Grafik tren sudah ada di detail order.
- Angka demo "4 belum lengkap, 4 kedaluwarsa" hanya benar pada hari data di-seed, karena tanggal di seeder relatif terhadap hari ini.

## Batasan teknis yang disepakati

- Harus jalan di MySQL 8 dan MariaDB 10.4. Jangan pakai fungsi yang tidak ada di MariaDB 10.4, misalnya `JSON_ARRAYAGG`.
- Target hosting shared hosting cPanel tanpa terminal: tanpa `exec` dan `symlink`, tautan storage lewat salin folder, penjadwal lewat Cron Job yang memanggil `schedule:run`. Detail di `README.md`.
