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

## Dasbor dan laporan (Fase 8)

- Pustaka PDF: `barryvdh/laravel-dompdf` ^3.1, dipasang atas persetujuan pemilik proyek. Excel tetap OpenSpout.
- Tertahan (PRD F18 butir 6): Antri, tidak terkunci, di luar N order Antri teratas pada K evaluasi terakhir berturut-turut, menunggu sedikitnya M hari. K (`held_after_evaluations`, bawaan 5) dan M (`held_min_days`, bawaan 4) dibaca dari `settings`, N adalah `top_n` tiap evaluasi. "N order Antri teratas" dihitung di antara order Antri pada saat evaluasi (status di `order_snapshot`), bukan dari peringkat akhir mentah, karena order terkunci dan Dikerjakan menempati peringkat atas dan akan membuat semua order Antri tertahan. Evaluasi Dibatalkan tidak dihitung. Kurang dari K evaluasi berarti tidak ada yang tertahan. Kode: `HeldOrders`.
- Kartu Tertahan hanya menghitung order tertahan (sama dengan filter Tertahan di daftar order). Daftar di dasbor memuat tertahan dan dieskalasi dari evaluasi terbaru bukan Dibatalkan, dengan tertahan menang bila keduanya.
- Enam kartu memakai scope yang sama dengan filternya (`DashboardFigures`), dan tiap kartu menaut ke daftar dengan filter menyala (`?filters[...]`). Penilaian belum lengkap menaut ke Penilaian Order, yang kini menerima `filters` dari URL.
- Prioritas utama di dasbor: order berlabel dari evaluasi terakhir berstatus Diterapkan, urut peringkat evaluasi itu. Kolom Peringkat adalah peringkat akhir evaluasi (bisa mulai dari 7 karena order terkunci di atasnya).
- Dasbor menurut peran: bagian Menunggu persetujuan hanya Pimpinan, tombol Jalankan Perhitungan hanya Manajer (`RunEvaluation::canAccess()`), Admin dan Supervisor hanya melihat. Tidak ada rupiah di dasbor.
- Laporan: satu halaman `Reports` untuk lima jenis, semua peran boleh (PRD Bab 9). Semua laporan dibangun `ReportBuilder` menjadi `ReportDocument` (judul, keterangan, tabel bernilai mentah). Layar, PDF, dan Excel menggambar dokumen yang sama. Tidak ada rupiah di laporan, kecuali nilai asli C6 pada rincian perhitungan, yang disembunyikan dari Supervisor (aturan yang sama dengan tab hasil evaluasi).
- Excel menyimpan angka dan tanggal sungguhan (bukan teks) dengan format tampilan, satu sheet per tabel dan sheet Ringkasan berisi kop. Rincian perhitungan memuat tabel Bobot dan pembagi (bobot dan skala tertinggi per kriteria) supaya skor bisa dihitung ulang. PDF A4 mendatar dengan kop, judul kolom berulang di tiap halaman, nomor halaman, dan dua kolom tanda tangan: Dibuat oleh (pencetak) dan Diketahui oleh (Pimpinan). Kuesioner H6 tidak ada di berkas proyek.
- Pratinjau layar membatasi 50 baris pertama per tabel dengan catatan, berkas memuat semua baris.
- Periode: Order terlambat memakai tenggat (hanya order terbuka yang sudah lewat tenggat saat dicetak), Rekap order memakai tanggal masuk, Riwayat evaluasi memakai waktu hitung. Bawaan periode: awal bulan ini sampai hari ini. Periode terbalik ditolak dengan kalimat di pratinjau.
- Kop dari `Setting`: `company_name` (bawaan PT Weba International), `company_address`, `company_phone`, `company_logo` (jalur di disk public). Alamat atau telepon kosong tidak ditampilkan. Halaman pengaturan dan unggah logo ada di Fase 9.
- `DemoEvaluationSeeder` tidak ikut seed bawaan: lima evaluasi pada lima hari yang disetujui dan diterapkan, dijalankan manual agar dasbor terisi dan keadaan kosong Bab 8 dan 9 tetap bisa dipelajari.
- Widget dasbor tidak lazy dan dasbor bawaan Filament diganti kelas `Dashboard` sendiri. Widget sambutan dihapus.

## Log, pengaturan, jadwal, dan notifikasi (Fase 9)

- Pengaturan (F15): satu halaman `SystemSettingsPage` (slug pengaturan). Admin mengubah semua, Pimpinan hanya aturan keputusan dan perlindungan order lama (`SystemSettings::DECISION_KEYS`). Kunci yang tidak boleh diubah ditolak di server oleh `SystemSettings::save`, bukan hanya disembunyikan. Satu simpan menulis satu baris log `setting.updated` berisi nilai lama dan baru, dan tidak menulis apa pun bila tidak ada yang berubah.
- Aturan silang: `held_min_days` harus lebih kecil dari `max_wait_days`, supaya peringatan Tertahan muncul sebelum eskalasi.
- Pengaturan tambahan yang tidak disebut PRD: `near_deadline_days` (bawaan 3, bagian Tampilan, hanya Admin) untuk filter, kartu, dan warna sisa hari. Opsi "wajib lengkap" (K-03) dibuat sebagai `incomplete_orders`: nilai terburuk dengan peringatan (bawaan) atau dikecualikan dari perhitungan.
- Evaluasi terjadwal (F16): `evaluation:run-scheduled` dipanggil penjadwal setiap menit. Perintah memutuskan sendiri: tidak jalan sebelum `schedule_time`, sekali sehari (`schedule_last_run`), tetap jalan bila menit tepatnya terlewat, tidak mencoba ulang setiap menit setelah gagal. `--force` mengabaikan jam dan sakelar tetapi tetap dihitung sebagai jatah hari itu. Hasilnya Draf, pemicu Terjadwal, `evaluated_by` null (tampil "Sistem (terjadwal)"), dan tetap lewat persetujuan. Order belum lengkap dan kedaluwarsa dikonfirmasi otomatis karena tidak ada orang yang bisa mengonfirmasi. Bila tidak ada order aktif atau perhitungan terblokir: tidak ada evaluasi, satu baris log (`evaluation.schedule_skipped` atau `schedule_failed`) dan notifikasi N7.
- Notifikasi (F17, PRD Bab 19): notifikasi basis data Filament dengan lonceng (polling 30 detik). Kode N1 sampai N10 disimpan di `viewData.code`. Penerima: N1 Pimpinan, N2 dan N3 Manajer yang mengajukan, N4 Supervisor, N5 dan N6 Manajer, N7 Admin dan Manajer, N8 sampai N10 Manajer dan Pimpinan. Hanya akun aktif. N8 dan N9 hanya untuk order yang baru tertahan atau baru dieskalasi dibanding sebelum perhitungan. Notifikasi dikirim sesudah perubahan tersimpan, di dalam `rescue()`, sehingga kegagalan kirim tidak membatalkan langkah. Pembatalan evaluasi tidak memberi notifikasi.
- Notifikasi disimpan langsung (`notifyNow`), tidak lewat antrean, karena shared hosting tidak punya queue worker. `notifications:prune` menghapus yang lebih tua dari 30 hari setiap pukul 01.00.
- Log aktivitas (F14): halaman `ActivityLogResource` (slug log-aktivitas) hanya baca, untuk Admin dan Pimpinan. Riwayat satu order di detail order tetap terlihat semua peran. Yang dicatat: masuk dan keluar (gagal masuk tidak), order, kriteria dan bobot, skala (hanya rentang yang berubah), nilai penilaian (dengan alasan bila urgensi ditimpa), langkah evaluasi, jadwal, dan pengaturan. Kolom yang tidak punya nilai lama, atau nilainya sama, ditulis sebagai keterangan ("Alasan: ...") dan bukan "kosong menjadi ...".

## Hak akses

Matriks lengkap di PRD Bab 9, ringkas di panduan Bab 14. Peran: Admin, Manajer Produksi, Supervisor, Pimpinan (enum `UserRole`: admin, manager, supervisor, leader). Semua lewat Policy. Aturan khusus:

- Supervisor tidak boleh melihat rupiah. Disembunyikan di detail order, kolom harga produk, kolom C6 di matriks penilaian, riwayat perubahan order, dan riwayat nilai C6.
- Pengguna tidak dihapus, hanya dinonaktifkan. Admin terakhir tidak boleh dinonaktifkan atau diubah perannya. Tidak boleh menonaktifkan diri sendiri.
- Impor dan ekspor hanya Admin dan Manajer.
- Log Aktivitas dan Pengaturan hanya Admin dan Pimpinan (Pimpinan sebagian pengaturan saja).
- Lima kali gagal masuk dalam satu menit memicu jeda 60 detik.

## Impor dan ekspor Excel (Fase 5)

- Memakai OpenSpout bawaan Filament, tanpa paket baru. Maksimal 1.000 baris.
- Baris bermasalah dilewati, baris benar tetap diimpor, semuanya dalam satu transaksi. Baris yang ditolak tetap tampil setelah impor.
- Berkas dibaca ulang saat konfirmasi, bukan memakai data pratinjau.
- Mode tambah saja atau tambah dan perbarui. Saat memperbarui, kolom wajib menimpa dan kolom opsional kosong membiarkan data lama.
- Ekspor mengikuti filter dan pencarian di daftar. Sebelas kolom pertama sama dengan templat sehingga hasil ekspor bisa diimpor lagi tanpa mengubah data.

## Sengaja belum dibuat atau ditunda

- Kunci otomatis tetap ditampilkan walau `lock_in_progress` dimatikan, sedangkan mesin evaluasi mengabaikannya.
- Semua pengaturan dibaca dari tabel `settings` dengan bawaan di kode (`EvaluationSettings`, `SystemSettings`). `Setting::get` tidak menyimpan hasil di memori, jadi pembacaan per baris tabel dibungkus `once()`.
- Kolom peringkat terakhir di daftar order belum ada. Grafik tren sudah ada di detail order.
- Angka demo "4 belum lengkap, 4 kedaluwarsa" hanya benar pada hari data di-seed, karena tanggal di seeder relatif terhadap hari ini.

## Batasan teknis yang disepakati

- Harus jalan di MySQL 8 dan MariaDB 10.4. Jangan pakai fungsi yang tidak ada di MariaDB 10.4, misalnya `JSON_ARRAYAGG`.
- Target hosting shared hosting cPanel tanpa terminal: tanpa `exec` dan `symlink`, tautan storage lewat salin folder, penjadwal lewat Cron Job yang memanggil `schedule:run`. Detail di `README.md`.
