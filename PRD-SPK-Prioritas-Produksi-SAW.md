# PRD: Sistem Pendukung Keputusan Penentuan Prioritas Penyelesaian Produksi Metode SAW

| Informasi | Isi |
|---|---|
| Nama produk | SPK Prioritas Produksi (nama aplikasi dapat diubah) |
| Studi kasus | PT Weba International |
| Versi dokumen | 1.5 (draf) |
| Tanggal | 5 November 2026 |
| Status | Draf, sebagian keputusan menunggu jawaban kuesioner client (lihat Bab 16) |
| Bentuk sistem | Aplikasi web baru yang dibangun sendiri (Pilihan B) |
| Cara pengisian nilai | Gabungan: sebagian otomatis, sebagian diisi manual atau diimpor dari Excel (Cara 3) |

---

## 1. Ringkasan

SPK Prioritas Produksi adalah aplikasi web yang membantu PT Weba International memutuskan order produksi mana yang perlu diselesaikan lebih dulu. Cara kerjanya sederhana. Setiap order yang sedang berjalan dinilai pada beberapa hal yang dianggap penting oleh perusahaan, seperti sisa waktu menuju tenggat, tingkat kepentingan pelanggan, dan kesiapan bahan baku. Tiap hal itu diberi bobot sesuai kebijakan perusahaan. Sistem kemudian menghitung nilai setiap order dengan metode Simple Additive Weighting (SAW) dan menyusun urutannya dari yang paling perlu didahulukan.

Manajer dapat memeriksa hasil urutan itu, menyesuaikannya bila perlu, lalu menetapkannya sebagai daftar prioritas yang dipakai di lantai produksi. Hasil dan rincian perhitungannya bisa dicetak atau diunduh sebagai PDF dan Excel.

Aplikasi ini dibangun tersendiri dan tidak bergantung pada sistem lain. Data order bisa diketik langsung atau diambil dari file Excel yang sudah ada di perusahaan.

## 2. Latar Belakang dan Masalah

Di PT Weba International banyak order sepatu dikerjakan pada waktu yang sama, sedangkan jumlah pekerja, mesin, dan bahan terbatas. Dalam keadaan seperti itu, menentukan order mana yang didahulukan bukan hal mudah. Saat ini keputusan tersebut lebih banyak mengandalkan perkiraan, pengalaman, atau order yang paling sering ditanyakan pelanggan. Belum ada cara penilaian yang sama untuk semua order.

Akibatnya, order yang tenggatnya sudah dekat atau berasal dari pelanggan penting kadang justru tertunda. Ketika terjadi keterlambatan, alasan pemilihan urutan kerja juga sulit dijelaskan kembali karena tidak ada catatannya.

Perusahaan membutuhkan alat bantu yang menilai setiap order dengan ukuran yang sama, menyimpan dasar perhitungannya, dan menampilkan hasilnya secara jelas. Gambaran kondisi yang sebenarnya, mulai dari alur kerja sampai seberapa sering keterlambatan terjadi, akan dilengkapi dari jawaban kuesioner Bagian A dan B.

Ada satu kelemahan yang sudah disadari sejak awal dan menjadi bagian dari rancangan ini. Karena urutan dihitung ulang setiap kali ada order baru, order lama bisa terus tergeser oleh pesanan baru yang nilainya lebih tinggi. Pergeseran itu sendiri wajar, sebab pesanan yang tenggatnya lebih dekat memang harus didahulukan. Yang berbahaya adalah ketika sebuah order lama kalah terus sampai tidak pernah kebagian dikerjakan. Dalam dunia penjadwalan keadaan ini dikenal sebagai starvation. Sistem ini sengaja dilengkapi pengaman khusus untuk mencegahnya, seperti dijelaskan pada Bab 7.7 dan fitur F18.

## 3. Tujuan dan Sasaran Terukur

| Kode | Tujuan | Ukuran keberhasilan |
|---|---|---|
| T1 | Menentukan urutan prioritas order secara objektif dan konsisten | Semua order aktif dinilai dengan kriteria dan bobot yang sama; perhitungan dapat diulang dengan hasil sama |
| T2 | Mempercepat penentuan prioritas | Perhitungan untuk 200 order selesai kurang dari 5 detik |
| T3 | Menyediakan dasar keputusan yang dapat ditelusuri | Setiap hasil menyimpan nilai, bobot, matriks, dan siapa yang menjalankan atau menyetujui |
| T4 | Hasil sesuai pertimbangan perusahaan | Korelasi peringkat sistem dengan urutan versi perusahaan (Spearman) minimal 0,70 pada data uji Bagian J |
| T5 | Perhitungan benar | Hasil sistem sama dengan perhitungan manual Excel untuk seluruh data uji (selisih skor kurang dari 0,0001) |
| T6 | Mudah dipakai | Skor SUS (System Usability Scale) pengguna minimal 68 |
| T7 | Tidak ada order yang tertahan terus-menerus | Pada simulasi 30 hari dengan pesanan baru berprioritas tinggi setiap hari, tidak ada order yang menunggu lebih lama dari batas tunggu maksimum tanpa dinaikkan ke kelompok eskalasi |

## 4. Ruang Lingkup

### 4.1 Termasuk

1. Login, profil, dan pengelolaan pengguna dengan empat peran.
2. Data master pelanggan dan produk.
3. Data order produksi (alternatif), termasuk impor dari Excel.
4. Pengaturan kriteria, bobot, dan skala nilai (sub-kriteria).
5. Penilaian order: nilai otomatis dan manual (gabungan), termasuk impor Excel.
6. Perhitungan SAW dengan tampilan langkah demi langkah.
7. Riwayat evaluasi, persetujuan hasil, penyesuaian manual beralasan, dan penerapan prioritas.
8. Dasbor ringkasan.
9. Laporan PDF dan Excel.
10. Log aktivitas, pengaturan sistem, evaluasi terjadwal, dan notifikasi.
11. Perlindungan order lama: kriteria lama menunggu, penguncian order yang sedang dikerjakan, penguncian manual, eskalasi otomatis, serta pemantauan order tertahan.

### 4.2 Tidak termasuk

1. Modul ERP seperti penjualan, pembelian, persediaan, akuntansi, dan penggajian.
2. Penjadwalan mesin rinci (Gantt) dan perhitungan kapasitas otomatis.
3. Aplikasi ponsel terpisah (tampilan web tetap responsif di ponsel).
4. Integrasi otomatis dengan aplikasi lain milik perusahaan (dapat dipertimbangkan setelah jawaban kuesioner I5).

## 5. Pengguna dan Peran

| Peran | Siapa | Kebutuhan utama |
|---|---|---|
| Admin | Staf administrasi atau IT | Mengelola akun pengguna, data master, pengaturan sistem |
| Manajer Produksi (PPIC) | Perencana produksi | Mengelola order, mengisi penilaian, mengatur kriteria, menjalankan perhitungan, mengusulkan prioritas |
| Supervisor Produksi | Pengawas lantai produksi | Melihat urutan prioritas, memperbarui progres order |
| Pimpinan | Direktur atau pemilik | Melihat dasbor dan laporan, menyetujui hasil prioritas, menetapkan bobot |

Peran dan pembagian hak akses final mengikuti jawaban kuesioner Bagian G.

## 6. Asumsi, Batasan, dan Ketergantungan

1. Kriteria awal memakai tujuh kriteria usulan (C1 sampai C7). Enam yang pertama berasal dari rancangan awal, dan C7 (lama menunggu) ditambahkan untuk mencegah order lama tertahan terus. Daftar final mengikuti kuesioner Bagian D.
2. Bobot awal memakai nilai usulan: C1 0,27; C2 0,18; C3 0,135; C4 0,135; C5 0,09; C6 0,09; dan C7 0,10 (total 1). Bobot final dihitung dari jawaban kuesioner Bagian E.
3. Skala nilai (sub-kriteria) awal memakai contoh rentang di Bab 11. Rentang final mengikuti kuesioner Bagian F.
4. Sistem dipasang di shared hosting cPanel (PHP 8.3, MySQL/MariaDB) kecuali kuesioner I3 menyatakan lain.
5. Satu order berisi satu produk. Bila kuesioner C5 menyatakan satu order dapat berisi beberapa produk, struktur order ditambah baris produk.
6. Hari dihitung sebagai hari kalender. Bila perusahaan ingin hari kerja, aturan libur ditambahkan (keputusan terbuka K-04).

## 7. Konsep Solusi

### 7.1 Gambaran alur kerja

1. Admin menyiapkan akun pengguna, data pelanggan, dan data produk.
2. Manajer Produksi memasukkan order produksi baru (manual atau impor Excel), termasuk tanggal tenggat.
3. Manajer atau Supervisor memperbarui nilai penilaian yang manual: ketersediaan bahan, sisa waktu pengerjaan, progres.
4. Sistem menghitung nilai otomatis: sisa hari menuju tenggat, urgensi dari data pelanggan, nilai order dari harga dan jumlah.
5. Manajer menjalankan perhitungan SAW (atau sistem menjalankannya otomatis setiap pagi).
6. Sistem menyusun peringkat dengan aturan perlindungan order lama: order yang terkunci diletakkan paling atas, lalu order yang sudah menunggu melewati batas tunggu maksimum (eskalasi), baru order lainnya menurut skor. Hasilnya disimpan sebagai evaluasi baru lengkap dengan matriks dan peringkat.
7. Manajer meninjau hasil, bila perlu menyesuaikan urutan atau mengunci order tertentu dengan alasan, lalu mengajukan.
8. Pimpinan menyetujui (bila persetujuan diwajibkan). Prioritas diterapkan: N order Antri teratas ditandai Prioritas Utama.
9. Supervisor melihat urutan prioritas di dasbor dan mengerjakan sesuai urutan. Laporan dicetak atau diunduh bila perlu.

### 7.2 Arsitektur

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Framework | Laravel 13, PHP 8.3 | Stabil, banyak dokumentasi, sudah dikuasai dari pekerjaan sebelumnya |
| Antarmuka admin | Filament 5 (Livewire 4) | Formulir, tabel, filter, dan dasbor cepat dibuat dengan tampilan konsisten |
| Basis data | MySQL 8 atau MariaDB 10.4 ke atas | Tersedia di hosting; hindari fungsi khusus MySQL 8 seperti JSON_ARRAYAGG |
| Impor dan ekspor Excel | maatwebsite/excel | Impor order dan penilaian, ekspor laporan |
| PDF | barryvdh/laravel-dompdf | Laporan dengan kop perusahaan |
| Hak akses | Peran sederhana di tabel users plus Laravel Policy | Kode sendiri, mudah dijelaskan di skripsi, tanpa paket izin yang rumit |
| Pengujian | Pest 4 | Uji unit mesin SAW dan uji fitur setiap halaman |

### 7.3 Metode SAW yang dipakai

Langkah perhitungan:

1. **Alternatif**: order berstatus aktif (Antri dan Dikerjakan).
2. **Nilai asli**: setiap order memiliki nilai asli per kriteria (hari, persen, jam, rupiah, atau tingkat 1 sampai 5).
3. **Konversi ke skala (crips)**: nilai asli diubah menjadi nilai 1 sampai 5 berdasarkan rentang sub-kriteria. Nilai 5 berarti paling perlu didahulukan.
4. **Matriks keputusan X**: berisi nilai skala setiap order pada setiap kriteria.
5. **Normalisasi R**: karena skala sudah searah prioritas (5 selalu paling didahulukan), semua kriteria dinormalisasi dengan rumus benefit: r_ij = x_ij / max(x_j).
6. **Skor preferensi**: V_i = jumlah dari (w_j x r_ij).
7. **Peringkat**: order disusun dalam tiga kelompok berurutan, yaitu order terkunci, order dieskalasi, lalu order biasa. Di dalam tiap kelompok, skor tertinggi mendapat peringkat lebih baik. Bila skor sama: tenggat terdekat, lalu tanggal order masuk lebih awal. Aturan kelompok dijelaskan pada Bab 7.7.

Alasan memakai skala searah prioritas:

1. Sesuai format kuesioner Bagian F yang diisi client (5 = paling didahulukan).
2. Nilai skala tidak pernah 0, sehingga masalah pembagian dengan nol pada rumus cost (yang ditemukan pada sistem sebelumnya) tidak terjadi.
3. Mudah dijelaskan kepada pengguna dan penguji.

Sifat benefit atau cost setiap kriteria tetap dicatat sebagai **arah nilai asli** dan dipakai untuk menyusun dan memvalidasi rentang skala (misalnya kriteria tenggat berarah "makin kecil makin didahulukan", sehingga rentang hari kecil mendapat skala 5).

Alternatif bila dosen meminta rumus benefit dan cost klasik: lihat keputusan terbuka K-01.

### 7.4 Cara pengisian nilai (gabungan)

| Kode | Kriteria | Sumber nilai | Keterangan |
|---|---|---|---|
| C1 | Sisa waktu menuju tenggat (hari) | Otomatis | Tenggat dikurangi tanggal evaluasi. Terlambat dihitung 0 hari atau kurang dan mendapat skala tertinggi |
| C2 | Urgensi pelanggan (1 sampai 5) | Otomatis dari data pelanggan, dapat diubah per order | Nilai bawaan diambil dari tingkat urgensi pelanggan |
| C3 | Ketersediaan bahan baku (%) | Manual | Diperbarui PPIC atau Gudang |
| C4 | Sisa waktu pengerjaan (jam) | Manual | Diperbarui Supervisor |
| C5 | Progres penyelesaian (%) | Manual | Diperbarui Supervisor; dapat dihitung dari jumlah selesai dibagi jumlah order bila diisi |
| C6 | Nilai order (Rp) | Otomatis bila harga satuan diisi (jumlah x harga), bila tidak manual | Hanya peran tertentu yang dapat melihat (K-06) |
| C7 | Lama menunggu (hari) | Otomatis | Tanggal evaluasi dikurangi tanggal order masuk. Makin lama menunggu, skala makin tinggi |

Setiap nilai manual menyimpan waktu terakhir diperbarui dan siapa yang memperbarui. Nilai yang lebih lama dari batas yang diatur (bawaan 3 hari) ditandai **perlu diperbarui** sebelum perhitungan.

### 7.5 Diagram Alur Sistem (Flowchart)

Alur utama dari order masuk sampai prioritas diterapkan.

```
                 ( Mulai )
                     |
                     v
        [ Input atau impor data order ]
                     |
                     v
   [ Lengkapi nilai penilaian order         ]
   [ - otomatis: C1 tenggat, C2 pelanggan,  ]
   [   C6 nilai order, C7 lama menunggu      ]
   [ - manual: C3 bahan, C4 sisa kerja, C5  ]
                     |
                     v
              < Total bobot = 1 ? >--- tidak -->[ Perbaiki / normalisasi bobot ]
                     | ya                                      |
                     v                                         |
         < Ada order aktif ? >--- tidak -->[ Berhenti, beri pesan ]
                     | ya
                     v
   [ Konversi nilai asli ke skala 1..5 ]
                     |
                     v
   [ Normalisasi matriks: r = x / maks kolom ]
                     |
                     v
   [ Hitung skor V = jumlah (bobot x r) ]
                     |
                     v
   [ Urutkan skor, susun peringkat akhir:    ]
   [ terkunci, eskalasi, biasa               ]
                     |
                     v
   [ Simpan sebagai Evaluasi (tidak dapat diubah) ]
                     |
                     v
        < Perlu penyesuaian manual ? >--- ya -->[ Ubah peringkat + alasan ]
                     | tidak                                   |
                     v <-----------------------------------------
        < Persetujuan Pimpinan wajib ? >--- ya -->[ Pimpinan setujui / tolak ]
                     | tidak                              | tolak -> kembali ke penilaian
                     v <--- setuju ----------------------
   [ Terapkan prioritas: N order Antri teratas = Prioritas Utama ]
                     |
                     v
   [ Tampil di dasbor, cetak / unduh laporan ]
                     |
                     v
                 ( Selesai )
```

### 7.6 Arsitektur Deployment

Sistem dipasang di shared hosting cPanel. Folder aplikasi dan berkas rahasia diletakkan di luar folder publik.

```
   +-----------------------------------------------+
   |  Pengguna                                     |
   |  PC / Laptop / Ponsel  +  Peramban (Chrome,   |
   |  Edge, Firefox)                               |
   +-----------------------------------------------+
                      |
                      |  HTTPS (port 443)
                      v
   +-----------------------------------------------+
   |  Server Hosting cPanel                        |
   |                                               |
   |  +-----------------------------------------+  |
   |  |  Web server (LiteSpeed / Apache)        |  |
   |  |    public_html/  -> index.php, aset     |  |
   |  +-----------------------------------------+  |
   |                 |  memanggil                  |
   |                 v                             |
   |  +-----------------------------------------+  |
   |  |  PHP 8.3  -  Aplikasi Laravel + Filament|  |
   |  |   - folder aplikasi di luar public      |  |
   |  |   - .env di luar folder publik          |  |
   |  +-----------------------------------------+  |
   |     |  koneksi lokal           ^              |
   |     v                          |              |
   |  +-------------------------+    |             |
   |  |  Basis data             |    |             |
   |  |  MySQL / MariaDB         |    |             |
   |  +-------------------------+    |             |
   |                                 |             |
   |  +-----------------------------------------+  |
   |  |  Cron Job cPanel                        |  |
   |  |   setiap hari 06.00:                    |  |
   |  |   php artisan schedule:run  ------------+  |
   |  |   (evaluasi terjadwal)                  |  |
   |  +-----------------------------------------+  |
   +-----------------------------------------------+
```

Catatan deployment: berkas `.env` disimpan di `<home>/env/<folder situs>/.env` di luar `public_html`; folder publik domain diarahkan ke folder `public` aplikasi; `APP_DEBUG` dimatikan; migrasi dan penjadwalan dijalankan melalui Cron Job karena hosting tanpa akses terminal.

### 7.7 Perlindungan Order Lama (Anti-Starvation)

Urutan prioritas dihitung ulang setiap kali evaluasi dijalankan, sehingga order baru bisa menyalip order lama. Itu wajar bila order baru memang lebih mendesak. Yang harus dicegah adalah order yang kalah terus sampai tidak pernah dikerjakan. Pengamannya dibuat berlapis, karena satu cara saja tidak cukup (alasannya dibuktikan lewat contoh di Bab 11.7).

Dua pengaman mendorong order lama naik secara alami:

1. **Tenggat (C1).** Makin dekat batas waktunya, makin tinggi skala C1 sebuah order, sehingga order lama naik sendiri begitu tenggatnya mendekat. Kriteria ini memiliki bobot terbesar.
2. **Lama menunggu (C7).** Kriteria baru yang menghitung berapa hari order sudah mengantri sejak masuk. Makin lama menunggu, makin tinggi skalanya.

Dua pengaman menahan order agar tidak diserobot:

3. **Kunci otomatis.** Order yang berstatus Dikerjakan terkunci sendiri dan tidak bisa disalip pesanan baru. Pesanan baru hanya bersaing untuk antrean yang belum mulai. Kunci dilepas saat order selesai atau dibatalkan. Perilaku ini dapat dimatikan lewat pengaturan.
4. **Kunci manual.** Manajer atau Pimpinan dapat mengunci order tertentu agar tetap di atas, dengan alasan wajib dan tanggal berakhir yang boleh dikosongkan. Setiap kunci dan pelepasannya tercatat di log.

Satu pengaman menjadi jaminan:

5. **Eskalasi otomatis.** Pengaman 1 sampai 4 sifatnya mendorong atau menahan, tetapi belum menjamin. Karena itu ada batas tunggu maksimum (bawaan 7 hari, dapat diubah dari pengaturan). Order Antri yang sudah menunggu lebih dari batas itu otomatis masuk kelompok eskalasi dan diletakkan di atas order biasa, apa pun skornya. Pimpinan dan Manajer menerima pemberitahuan. Angka 7 hari dipilih dari sisi perancangan karena cukup ketat agar order tidak menunggu terlalu lama, tetapi masih memberi ruang bagi order biasa untuk dikerjakan menurut skornya. Ketepatannya diuji lewat simulasi pembanding 5, 7, dan 10 hari pada Bab 13, dan angkanya dapat diubah dari pengaturan tanpa mengubah program.

Satu pengaman bertugas memantau:

6. **Pemantauan order tertahan.** Setiap order menyimpan riwayat peringkatnya pada tiap evaluasi, sehingga tren naik atau turun bisa dilihat. Order yang berada di luar daftar prioritas berturut-turut dan sudah menunggu cukup lama ditandai Tertahan dan muncul di dasbor.

Aturan penyusunan peringkat akhir. Skor SAW tetap dihitung murni dan menghasilkan peringkat sistem. Peringkat akhir kemudian disusun dengan tiga kelompok berurutan:

1. **Terkunci.** Order dengan kunci otomatis atau manual yang masih berlaku, diurutkan menurut skor.
2. **Eskalasi.** Order Antri yang tidak terkunci dan lama menunggunya melewati batas tunggu maksimum, diurutkan dari yang paling lama menunggu, lalu menurut skor.
3. **Biasa.** Semua order lainnya, diurutkan menurut skor.

Tabel yang menyimpan peringkat sistem dan peringkat akhir berdampingan membuat pengaruh aturan ini selalu terlihat dan bisa diperiksa. Aturan jumlah Prioritas Utama (N). Label Prioritas Utama menjawab pertanyaan "order mana yang harus dimulai berikutnya". Karena itu N hanya dihitung pada antrean yang belum dikerjakan, yaitu order berstatus Antri. Order yang sedang dikerjakan (kunci otomatis) tetap berada di kelompok teratas tetapi tidak memakai slot, sehingga pesanan baru yang mendesak selalu masih punya peluang mendapat label. Di dalam antrean itu, order dengan kunci manual dan order eskalasi berada paling atas dan memakai slot. Bila keduanya saja sudah memenuhi seluruh N, sistem menampilkan peringatan agar Manajer atau Pimpinan menilai apakah N perlu dinaikkan atau ada kunci yang bisa dilepas.

---

## 8. Spesifikasi Fitur

Setiap fitur dijelaskan dengan tujuannya, siapa yang memakainya, apa yang bisa dilakukan di dalamnya, aturan yang berlaku, dan tanda bahwa fitur itu sudah bekerja dengan benar.

### F01. Autentikasi dan Profil

**Tujuan:** hanya pengguna terdaftar yang dapat memakai sistem.
**Pengguna:** semua peran.

Cerita pengguna:
1. Sebagai pengguna, saya ingin masuk dengan email dan kata sandi agar dapat memakai sistem sesuai peran saya.
2. Sebagai pengguna, saya ingin mengganti kata sandi dan nama saya sendiri.

Rincian fungsi:
1. Halaman masuk: email, kata sandi, ingat saya.
2. Keluar dari menu pengguna.
3. Halaman profil: nama, email (hanya dibaca), kata sandi lama, kata sandi baru, konfirmasi.
4. Akun nonaktif tidak dapat masuk.

Aturan bisnis:
1. Kata sandi minimal 8 karakter, disimpan dengan hash bcrypt.
2. Lima kali gagal masuk dalam 1 menit memicu jeda 60 detik.

Kriteria penerimaan:
1. Pengguna nonaktif mendapat pesan "Akun Anda dinonaktifkan".
2. Setelah mengganti kata sandi, kata sandi lama tidak dapat dipakai.
3. Halaman selain login tidak dapat dibuka tanpa masuk.

### F02. Manajemen Pengguna

**Tujuan:** Admin mengatur siapa yang dapat memakai sistem dan perannya.
**Pengguna:** Admin.

Rincian fungsi:
1. Daftar pengguna: nama, email, peran, status aktif, terakhir masuk. Cari dan filter peran.
2. Tambah dan ubah pengguna: nama, email unik, peran, kata sandi awal, status aktif.
3. Nonaktifkan pengguna (tidak dihapus agar riwayat tetap utuh).
4. Atur ulang kata sandi pengguna lain.

Aturan bisnis:
1. Minimal harus ada satu Admin aktif; Admin terakhir tidak dapat dinonaktifkan.
2. Pengguna tidak dapat menonaktifkan dirinya sendiri.

Kriteria penerimaan:
1. Pengguna baru dapat langsung masuk dengan kata sandi awal.
2. Mencoba menonaktifkan Admin terakhir menampilkan pesan penolakan.

### F03. Data Master Pelanggan

**Tujuan:** menyimpan pelanggan beserta tingkat urgensi bawaannya (sumber nilai C2).
**Pengguna:** Admin, Manajer Produksi.

Rincian fungsi:
1. Daftar pelanggan: kode, nama, kontak, tingkat urgensi (1 sampai 5), jumlah order aktif.
2. Tambah, ubah, nonaktifkan pelanggan.
3. Tingkat urgensi memakai pedoman dari kuesioner D5 (misalnya 5 Sangat mendesak sampai 1 Sangat rendah), ditampilkan sebagai keterangan pada formulir.

Aturan bisnis:
1. Kode pelanggan unik.
2. Pelanggan yang masih memiliki order tidak dapat dihapus, hanya dinonaktifkan.

Kriteria penerimaan:
1. Mengubah urgensi pelanggan memengaruhi nilai C2 order aktif pelanggan tersebut yang belum diubah manual.

### F04. Data Master Produk

**Tujuan:** daftar produk agar penulisan order seragam.
**Pengguna:** Admin, Manajer Produksi.

Rincian fungsi:
1. Daftar produk: kode, nama, kategori, harga satuan (opsional), estimasi waktu pengerjaan per pasang (opsional).
2. Tambah, ubah, nonaktifkan produk.

Aturan bisnis:
1. Harga satuan dipakai untuk menghitung nilai order otomatis (C6).

Kriteria penerimaan:
1. Produk nonaktif tidak muncul pada pilihan order baru, tetapi tetap tampil pada order lama.

### F05. Data Order Produksi (Alternatif)

**Tujuan:** mencatat order yang akan dinilai.
**Pengguna:** Manajer Produksi (ubah), Supervisor (ubah status dan progres), Pimpinan dan Admin (lihat).

Rincian fungsi:
1. Daftar order: nomor, pelanggan, produk, jumlah, tanggal masuk, tenggat, sisa hari, lama menunggu, status, ikon kunci, prioritas, peringkat terakhir. Cari, filter status, filter terlambat, filter mendekati tenggat (3 hari), filter terkunci, dan filter tertahan.
2. Tambah dan ubah order: nomor (otomatis, dapat diubah), pelanggan, produk, jumlah (pasang), tanggal masuk, tanggal tenggat, harga satuan (terisi dari produk, dapat diubah), catatan.
3. Status order: Antri, Dikerjakan, Selesai, Dibatalkan (disesuaikan dengan kuesioner C3).
4. Penanda prioritas: Normal atau Prioritas Utama (diisi oleh fitur F10).
5. Halaman detail order: data order, nilai penilaian terakhir, riwayat peringkat pada evaluasi sebelumnya beserta grafik tren, status kunci, riwayat perubahan.

Aturan bisnis:
1. Nomor order unik.
2. Tenggat tidak boleh sebelum tanggal masuk.
3. Order berstatus Selesai atau Dibatalkan tidak ikut dinilai.
4. Order yang sudah tercantum di evaluasi tidak dapat dihapus, hanya dibatalkan.
5. Saat status berubah menjadi Dikerjakan, order terkunci otomatis (bila pengaturan kunci otomatis aktif). Saat status menjadi Selesai atau Dibatalkan, kunci dilepas. Aturan penguncian lengkap ada pada F18.

Kriteria penerimaan:
1. Order baru langsung muncul di halaman penilaian dengan nilai otomatis terisi.
2. Badge terlambat tampil bila tenggat sudah lewat dan status belum Selesai.
3. Mengubah status order menjadi Dikerjakan menampilkan ikon kunci pada order itu, dan mengubahnya menjadi Selesai menghilangkan ikon tersebut.

### F06. Impor dan Ekspor Data Order

**Tujuan:** memindahkan data order yang sudah ada di Excel tanpa mengetik ulang.
**Pengguna:** Manajer Produksi, Admin.

Rincian fungsi:
1. Unduh templat Excel berisi kolom: nomor order, kode pelanggan, kode produk, jumlah, tanggal masuk, tenggat, harga satuan, status, bahan tersedia (%), sisa waktu pengerjaan (jam), progres (%).
2. Unggah file, lalu tampilkan pratinjau: baris valid, baris bermasalah beserta alasannya.
3. Pilih mode: tambah order baru saja, atau tambah dan perbarui order yang nomornya sudah ada.
4. Konfirmasi impor, lalu tampilkan ringkasan jumlah ditambah, diperbarui, dan dilewati.
5. Ekspor daftar order sesuai filter ke Excel.

Aturan bisnis:
1. Baris dengan pelanggan atau produk yang tidak dikenal ditolak dengan pesan jelas.
2. Impor dilakukan dalam satu transaksi: bila gagal di tengah, tidak ada data yang tersimpan.
3. Maksimal 1.000 baris per file.

Kriteria penerimaan:
1. Mengimpor templat contoh berisi 20 order menghasilkan 20 order baru dengan nilai penilaian terisi.
2. File dengan 2 baris rusak menampilkan 2 pesan kesalahan dan tidak menyimpan baris rusak tersebut.

### F07. Kriteria dan Bobot

**Tujuan:** mengatur dasar penilaian sesuai kebijakan perusahaan.
**Pengguna:** Manajer Produksi dan Pimpinan (ubah), Admin (lihat).

Rincian fungsi:
1. Daftar kriteria: kode, nama, satuan, arah nilai asli (makin besar didahulukan atau makin kecil didahulukan), sumber nilai (otomatis tenggat, otomatis pelanggan, otomatis nilai order, otomatis lama menunggu, manual), tingkat kepentingan (1 sampai 5), bobot hasil normalisasi, aktif.
2. Tambah dan ubah kriteria.
3. Bobot dihitung otomatis dari tingkat kepentingan: w_j = k_j / jumlah k seluruh kriteria aktif. Bobot ditampilkan dalam desimal dan persen.
4. Pilihan "atur bobot langsung": pengguna dapat mengisi persentase bobot langsung, dengan validasi total 100%.
5. Ringkasan di atas tabel: total bobot dan status siap hitung.

Aturan bisnis:
1. Kode kriteria unik.
2. Minimal dua kriteria aktif.
3. Total bobot kriteria aktif harus tepat 1 (pembulatan 4 desimal, selisih pembulatan ditambahkan ke bobot terbesar).
4. Kriteria yang sudah dipakai di evaluasi tidak dapat dihapus, hanya dinonaktifkan. Evaluasi lama tetap memakai salinan kriteria saat itu.
5. Setiap perubahan bobot tercatat di log aktivitas.

Kriteria penerimaan:
1. Mengisi tingkat kepentingan 5, 4, 3, 3, 2, 2 (jumlah 19) menghasilkan bobot 0,2631; 0,2105; 0,1579; 0,1579; 0,1053; 0,1053 (total tepat 1). Hasil pembulatan awal berjumlah 1,0001, sehingga selisih -0,0001 dibebankan ke bobot terbesar (0,2632 menjadi 0,2631).
2. Perhitungan SAW tidak dapat dijalankan bila kriteria aktif kurang dari dua.
3. Data awal sistem memuat tujuh kriteria dengan bobot C1 0,27; C2 0,18; C3 0,135; C4 0,135; C5 0,09; C6 0,09; C7 0,10, dengan total tepat 1.

### F08. Skala Nilai (Sub-kriteria)

**Tujuan:** mengubah nilai asli menjadi skala 1 sampai 5 secara seragam.
**Pengguna:** Manajer Produksi (ubah), lainnya lihat.

Rincian fungsi:
1. Setiap kriteria memiliki lima baris skala: nilai (5 sampai 1), keterangan, batas bawah, batas atas.
2. Untuk kriteria bertipe tingkat (misalnya urgensi 1 sampai 5), skala dapat langsung sama dengan nilai aslinya.
3. Uji rentang: pengguna dapat mengetik sebuah nilai asli contoh dan melihat skala yang dihasilkan.

Aturan bisnis:
1. Rentang tidak boleh tumpang tindih dan harus menutupi semua kemungkinan nilai (batas bawah kosong berarti tak terhingga ke bawah, batas atas kosong berarti tak terhingga ke atas). Batas bawah termasuk dalam rentang, batas atas tidak termasuk, sehingga nilai desimal seperti 79,5 persen selalu masuk tepat satu rentang.
2. Rentang harus konsisten dengan arah nilai asli: untuk arah "makin kecil makin didahulukan", nilai 5 berada pada rentang terkecil.
3. Perubahan skala tidak mengubah evaluasi lama.

Kriteria penerimaan:
1. Dengan skala contoh C1 (Bab 11), nilai asli 0 hari menjadi 5, 2 hari menjadi 4, dan 20 hari menjadi 1.
2. Menyimpan rentang yang tumpang tindih menampilkan pesan kesalahan.

### F09. Penilaian Order

**Tujuan:** memastikan setiap order aktif memiliki nilai lengkap untuk semua kriteria.
**Pengguna:** Manajer Produksi dan Supervisor (ubah nilai manual), lainnya lihat.

Rincian fungsi:
1. Halaman matriks penilaian: baris order aktif, kolom kriteria. Setiap sel menampilkan nilai asli, skala hasil konversi, dan penanda sumber (otomatis atau manual).
2. Sel manual dapat diubah langsung; sel otomatis (C1, C6, dan C7) hanya dibaca, kecuali C2 yang dapat ditimpa per order dengan alasan.
3. Penanda kelengkapan per order: Lengkap, Belum lengkap, Perlu diperbarui (nilai manual lebih lama dari batas hari).
4. Filter: belum lengkap, perlu diperbarui.
5. Impor nilai penilaian dari Excel (memakai templat F06 atau templat khusus penilaian).
6. Riwayat perubahan nilai per order: nilai lama, nilai baru, siapa, kapan.

Aturan bisnis:
1. Persentase dibatasi 0 sampai 100; jam dan rupiah tidak boleh negatif.
2. Nilai otomatis dihitung ulang setiap kali halaman dibuka dan setiap kali evaluasi dijalankan.
3. Order yang belum lengkap tetap dapat ikut perhitungan dengan aturan nilai kosong (Bab 11.4) dan diberi peringatan, atau dikecualikan bila pengaturan "wajib lengkap" aktif (K-03).

Kriteria penerimaan:
1. Mengubah tenggat order mengubah nilai C1 di matriks tanpa input tambahan.
2. Nilai manual yang diperbarui 4 hari lalu (batas 3 hari) tampil dengan penanda Perlu diperbarui.
3. Order yang masuk 10 hari lalu menampilkan nilai C7 sebesar 10 hari tanpa ada yang mengetiknya.

### F10. Perhitungan SAW dan Evaluasi

**Tujuan:** menghitung peringkat dan menyimpannya sebagai evaluasi yang dapat ditelusuri.
**Pengguna:** Manajer Produksi (menjalankan), sistem (terjadwal).

Rincian fungsi:
1. Tombol Jalankan Perhitungan: nama evaluasi (opsional), catatan (opsional).
2. Pemeriksaan sebelum hitung: total bobot 1, minimal satu order aktif, daftar order belum lengkap atau perlu diperbarui (konfirmasi untuk lanjut).
3. Proses hitung sesuai Bab 11: skor SAW murni dan peringkat sistem, lalu penyusunan peringkat akhir dengan tiga kelompok (terkunci, eskalasi, biasa) seperti Bab 7.7.
4. Simpan evaluasi: waktu, pemicu (manual atau terjadwal), pengguna, salinan kriteria, bobot, skala, pengaturan, dan seluruh nilai per order, termasuk lama menunggu, kelompok, dan peringkat pada evaluasi sebelumnya.
5. Hasil langsung terbuka di halaman detail evaluasi (F11).

Aturan bisnis:
1. Evaluasi yang tersimpan tidak dapat diubah atau dihapus (hanya dapat dibatalkan statusnya).
2. Waktu evaluasi disimpan sekali saat dibuat dan tidak berubah ketika status evaluasi diperbarui.
3. Perhitungan dilakukan di server dalam satu transaksi.
4. Peringkat sistem selalu murni berdasarkan skor. Pengaruh kunci dan eskalasi hanya tampak pada peringkat akhir.

Kriteria penerimaan:
1. Data contoh Bab 11.6 menghasilkan skor 0,8550; 0,7400; 0,7000 dan peringkat A, C, B.
2. 200 order selesai dihitung kurang dari 5 detik.
3. Data contoh Bab 11.7 menghasilkan order lama dalam kelompok eskalasi dan berada di atas order baru berskor lebih tinggi.

### F11. Hasil Evaluasi dan Persetujuan

**Tujuan:** meninjau hasil, menyesuaikan bila perlu, menyetujui, dan menerapkan prioritas.
**Pengguna:** Manajer Produksi (meninjau dan mengajukan), Pimpinan (menyetujui), Supervisor (melihat).

Rincian fungsi:
1. Daftar evaluasi: nama, waktu, pemicu, jumlah order, status (Draf, Diajukan, Disetujui, Diterapkan, Dibatalkan), dijalankan oleh, disetujui oleh.
2. Detail evaluasi dengan tab:
   - Ringkasan dan peringkat: peringkat akhir, peringkat sistem, order, pelanggan, tenggat, lama menunggu, skor V, kelompok (terkunci, eskalasi, biasa), dan perubahan peringkat dari evaluasi sebelumnya.
   - Data nilai asli.
   - Matriks keputusan (skala).
   - Matriks normalisasi.
   - Perhitungan skor: rincian w x r per kriteria untuk setiap order.
   - Kriteria, bobot, dan skala yang dipakai.
   - Peringatan: nilai kosong atau perlu diperbarui.
3. Penyesuaian manual: Manajer atau Pimpinan dapat memindahkan peringkat sebuah order dengan alasan wajib. Peringkat sistem tetap tersimpan berdampingan dengan peringkat akhir.
4. Ajukan, setujui, tolak (dengan catatan), terapkan.
5. Terapkan prioritas: N order Antri teratas pada peringkat akhir (pengaturan, bawaan 5) ditandai Prioritas Utama; order lain kembali Normal. Order yang sedang dikerjakan tidak memakai slot. Bila slot terisi penuh oleh order dengan kunci manual dan order eskalasi, sistem menampilkan peringatan.
6. Bandingkan dua evaluasi: perubahan peringkat setiap order (naik, turun, tetap).

Aturan bisnis:
1. Alur status: Draf ke Diajukan ke Disetujui ke Diterapkan. Bila pengaturan persetujuan tidak wajib (kuesioner H3), Draf dapat langsung Diterapkan oleh Manajer.
2. Hanya evaluasi terbaru yang dapat diterapkan.
3. Menerapkan evaluasi baru menggantikan penanda prioritas dari evaluasi sebelumnya.
4. Penyesuaian manual hanya pada status Draf atau Diajukan. Penyesuaian tidak dapat memindahkan order terkunci ke bawah order biasa, kecuali kuncinya dilepas lebih dulu (lihat F18).

Kriteria penerimaan:
1. Pimpinan menyetujui evaluasi, lalu Manajer menerapkan: lima order Antri teratas berlabel Prioritas Utama di daftar order, tanpa menghitung order yang sedang dikerjakan.
2. Penyesuaian tanpa alasan ditolak dengan pesan.

### F12. Dasbor

**Tujuan:** gambaran kondisi prioritas dalam satu layar.
**Pengguna:** semua peran (isi menyesuaikan peran).

Rincian fungsi:
1. Kartu angka: order aktif, terlambat, mendekati tenggat (3 hari), penilaian belum lengkap, terkunci, dan tertahan.
2. Daftar prioritas teratas dari evaluasi terakhir yang diterapkan.
3. Daftar order terlambat terlama.
4. Daftar order tertahan: order Antri yang berada di luar daftar prioritas berturut-turut atau sudah dieskalasi, lengkap dengan lama menunggunya.
5. Grafik jumlah order per status.
6. Status evaluasi terakhir dan tombol cepat Jalankan Perhitungan (Manajer).
7. Untuk Pimpinan: evaluasi yang menunggu persetujuan.

Kriteria penerimaan:
1. Angka di kartu sama dengan hasil filter di halaman order.
2. Dengan data demo, semua kartu dan daftar terisi, termasuk daftar order tertahan.

### F13. Laporan

**Tujuan:** menyediakan laporan resmi untuk rapat dan arsip.
**Pengguna:** Manajer Produksi, Pimpinan, Supervisor (sesuai kuesioner H5).

Rincian fungsi:
1. Laporan peringkat prioritas per evaluasi (PDF dan Excel).
2. Laporan rincian perhitungan (nilai asli, matriks, normalisasi, skor) per evaluasi.
3. Laporan order terlambat per periode.
4. Rekap order per periode (per status dan per pelanggan).
5. Riwayat evaluasi per periode.
6. Kop perusahaan, periode, tanggal cetak, nama pencetak, kolom tanda tangan (sesuai kuesioner H6).

Kriteria penerimaan:
1. Angka di PDF dan Excel sama dengan tampilan layar.
2. Excel rincian perhitungan dapat dipakai menghitung ulang secara manual dengan hasil sama.

### F14. Log Aktivitas

**Tujuan:** jejak siapa mengubah apa, untuk akuntabilitas keputusan.
**Pengguna:** Admin dan Pimpinan.

Rincian fungsi:
1. Catat: masuk dan keluar, perubahan order, perubahan nilai penilaian, perubahan kriteria dan bobot, perhitungan, penyesuaian, persetujuan, penerapan.
2. Daftar log dengan filter pengguna, jenis kegiatan, dan tanggal.

Kriteria penerimaan:
1. Mengubah bobot menghasilkan satu baris log berisi nilai lama dan baru.

### F15. Pengaturan Sistem

**Tujuan:** menyesuaikan perilaku sistem tanpa mengubah kode.
**Pengguna:** Admin (dan Pimpinan untuk aturan keputusan).

Rincian fungsi:
1. Profil perusahaan: nama, alamat, telepon, logo (untuk kop laporan).
2. Jumlah order teratas yang dijadikan prioritas (bawaan 5).
3. Persetujuan Pimpinan wajib atau tidak.
4. Batas hari nilai manual dianggap perlu diperbarui (bawaan 3).
5. Order belum lengkap: ikut dengan nilai terburuk, atau dikecualikan.
6. Evaluasi terjadwal: aktif atau tidak, jam pelaksanaan (bawaan 06.00).
7. Batas "mendekati tenggat" (bawaan 3 hari).
8. Perlindungan order lama: kunci otomatis untuk order Dikerjakan (bawaan aktif), batas tunggu maksimum untuk eskalasi (bawaan 7 hari), serta ambang order tertahan, yaitu jumlah evaluasi berturut-turut di luar daftar prioritas (bawaan 5) dan lama menunggu minimal (bawaan 4 hari, sengaja lebih pendek dari batas eskalasi agar ada peringatan dini sebelum order dieskalasi).

### F16. Evaluasi Terjadwal

**Tujuan:** peringkat terbaru tersedia setiap pagi tanpa ditekan manual.
**Pengguna:** sistem.

Rincian fungsi:
1. Perintah terjadwal menjalankan F10 pada jam yang diatur dengan pemicu Terjadwal.
2. Di shared hosting dijalankan melalui Cron Job cPanel.
3. Evaluasi terjadwal berstatus Draf dan tetap mengikuti alur persetujuan.

Kriteria penerimaan:
1. Evaluasi terjadwal tidak dibuat bila tidak ada order aktif, dan kejadian itu tercatat di log.

### F17. Notifikasi

**Tujuan:** memberi tahu pengguna saat ada tugas atau keadaan yang perlu ditindaklanjuti.
**Pengguna:** Pimpinan, Manajer, Supervisor, Admin sesuai jenis notifikasi.

Rincian lengkap pemicu, penerima, dan aturan ada di Bab 19. Ringkasnya, sistem membuat notifikasi dalam aplikasi untuk pengajuan dan hasil persetujuan evaluasi, penerapan prioritas, order terlambat, nilai kedaluwarsa, kegagalan perhitungan terjadwal, serta order yang tertahan atau dieskalasi.

Kriteria penerimaan:
1. Saat Manajer mengajukan evaluasi dan persetujuan wajib aktif, Pimpinan menerima notifikasi.
2. Ikon lonceng menampilkan jumlah notifikasi belum dibaca.

### F18. Perlindungan Order Lama (Anti-Starvation)

**Tujuan:** memastikan order lama tidak kalah terus oleh pesanan baru, tanpa menghalangi pesanan baru yang memang lebih mendesak.
**Pengguna:** Manajer Produksi dan Pimpinan (mengunci, melepas kunci, memantau), Supervisor (memantau), sistem (eskalasi otomatis).

Konsep dan alasan perancangannya ada pada Bab 7.7. Fitur ini terdiri dari enam bagian.

Rincian fungsi:
1. **Kriteria lama menunggu (C7).** Dihitung otomatis dari tanggal order masuk sampai tanggal evaluasi, dalam hari kalender utuh. Ikut perhitungan SAW seperti kriteria lain, dengan bobot dan skala yang dapat diubah (F07 dan F08).
2. **Kunci otomatis.** Saat order berstatus Dikerjakan, sistem memberi kunci jenis otomatis. Kunci dilepas saat order Selesai atau Dibatalkan. Bila pengaturan kunci otomatis dimatikan, bagian ini tidak berjalan.
3. **Kunci manual.** Manajer atau Pimpinan dapat mengunci sebuah order dari daftar order atau halaman detail. Isian wajib: alasan. Isian opsional: tanggal berakhir, setelah itu kunci lepas sendiri. Pelepasan kunci manual juga meminta alasan.
4. **Eskalasi otomatis.** Pada setiap evaluasi, order Antri yang tidak terkunci dan lama menunggunya melebihi batas tunggu maksimum masuk kelompok eskalasi dan diletakkan di atas order biasa. Order yang dieskalasi ditandai di daftar dan di hasil evaluasi.
5. **Tren peringkat.** Halaman detail order menampilkan grafik peringkat akhir pada setiap evaluasi, sehingga terlihat apakah sebuah order naik, turun, atau stagnan.
6. **Order tertahan.** Order Antri yang berada di luar daftar prioritas pada sedikitnya sejumlah evaluasi berturut-turut (bawaan 5) dan sudah menunggu minimal 4 hari ditandai Tertahan, sehingga peringatan muncul sebelum order mencapai batas eskalasi. Daftarnya tampil di dasbor (F12) dan dapat difilter di daftar order. Sistem juga mengirim notifikasi (Bab 19).

Aturan bisnis:
1. Urutan kelompok mengikuti Bab 7.7: terkunci, eskalasi, lalu biasa. Skor SAW murni dan peringkat sistem tidak pernah diubah oleh kelompok.
2. Order terkunci tidak ikut eskalasi karena sudah berada di kelompok teratas.
3. Alasan kunci manual wajib diisi. Setiap pemasangan dan pelepasan kunci dicatat di log aktivitas beserta pelakunya.
4. Supervisor tidak dapat memasang atau melepas kunci manual, tetapi dapat melihat status kunci dan daftar tertahan.
5. Batas tunggu maksimum harus berupa bilangan bulat positif. Mengubahnya tidak mengubah evaluasi yang sudah tersimpan.
6. Kunci tidak menghalangi order Selesai atau Dibatalkan keluar dari perhitungan.

Kriteria penerimaan:
1. Order Antri yang sudah menunggu 8 hari (batas 7) muncul di kelompok eskalasi pada evaluasi berikutnya, di atas order biasa yang skornya lebih tinggi. Order yang tepat menunggu 7 hari belum dieskalasi.
2. Order yang statusnya diubah menjadi Dikerjakan tetap berada di atas order biasa pada evaluasi berikutnya, walaupun skornya lebih rendah.
3. Mengunci order tanpa alasan ditolak dengan pesan. Mengunci dengan alasan menghasilkan satu baris log.
4. Order Antri yang berada di luar lima prioritas teratas pada lima evaluasi berturut-turut dan sudah menunggu 5 hari tampil di daftar order tertahan.
5. Dengan enam order sedang dikerjakan dan N sama dengan 5, lima order Antri teratas tetap mendapat label Prioritas Utama.
6. Pada simulasi 30 hari (Bab 13), tidak ada order yang menunggu lebih lama dari batas tunggu maksimum tanpa berada di kelompok eskalasi atau terkunci.

---

## 9. Hak Akses (Usulan Awal)

| Kegiatan | Admin | Manajer Produksi | Supervisor | Pimpinan |
|---|---|---|---|---|
| Kelola pengguna | Ya | Tidak | Tidak | Tidak |
| Kelola pelanggan dan produk | Ya | Ya | Lihat | Lihat |
| Tambah dan ubah order | Lihat | Ya | Status dan progres | Lihat |
| Impor order | Ya | Ya | Tidak | Tidak |
| Ubah nilai penilaian manual | Tidak | Ya | Ya (C4, C5) | Tidak |
| Ubah kriteria, bobot, skala | Lihat | Ya | Tidak | Ya |
| Jalankan perhitungan | Tidak | Ya | Tidak | Tidak |
| Lihat hasil evaluasi | Ya | Ya | Ya | Ya |
| Penyesuaian peringkat | Tidak | Ya | Tidak | Ya |
| Setujui atau tolak hasil | Tidak | Tidak | Tidak | Ya |
| Terapkan prioritas | Tidak | Ya | Tidak | Ya |
| Pasang dan lepas kunci manual | Tidak | Ya | Tidak | Ya |
| Lihat status kunci dan order tertahan | Ya | Ya | Ya | Ya |
| Unduh laporan | Ya | Ya | Ya | Ya |
| Lihat nilai order (Rp) | Ya | Ya | Tidak | Ya |
| Log aktivitas | Ya | Tidak | Tidak | Ya |
| Pengaturan sistem | Ya | Tidak | Tidak | Sebagian |

Final mengikuti kuesioner G2 dan G3.

## 10. Model Data

### 10.1 Tabel

**users**: id, name, email (unik), password, role (admin, manager, supervisor, leader), is_active, last_login_at, timestamps.

**customers**: id, code (unik), name, contact, urgency_level (1 sampai 5), is_active, timestamps.

**products**: id, code (unik), name, category, unit_price (nullable), minutes_per_unit (nullable), is_active, timestamps.

**production_orders**: id, number (unik), customer_id, product_id, quantity, order_date, deadline_date, unit_price (nullable), status (queued, in_progress, done, canceled), priority (normal, top), lock_type (none, auto, manual), lock_reason, locked_by, locked_at, lock_until, notes, created_by, updated_by, timestamps, soft deletes.

**criteria**: id, code (unik), name, unit, direction (higher_first, lower_first), value_source (deadline, customer_urgency, order_value, waiting, manual), importance (1 sampai 5), weight (desimal 6,4), sort, is_active, timestamps.

**criterion_scales**: id, criterion_id, score (1 sampai 5), label, lower_bound (nullable), upper_bound (nullable), timestamps. Unik (criterion_id, score).

**assessments**: id, production_order_id, criterion_id, raw_value (desimal, nullable), is_overridden (untuk C2), override_reason, updated_by, value_updated_at, timestamps. Unik (production_order_id, criterion_id).

**assessment_histories**: id, assessment_id, old_value, new_value, changed_by, changed_at.

**evaluations**: id, name, trigger (manual, scheduled), status (draft, submitted, approved, applied, canceled), evaluated_at (diisi sekali, tanpa ON UPDATE), evaluated_by, submitted_by, submitted_at, approved_by, approved_at, rejection_note, applied_by, applied_at, top_n, criteria_snapshot (json), settings_snapshot (json), order_count, notes, timestamps.

**evaluation_items**: id, evaluation_id, production_order_id, order_snapshot (json: nomor, pelanggan, produk, tenggat), raw_values (json), scale_values (json), normalized_values (json), weighted_values (json), score (desimal 10,6), system_rank, final_rank, previous_rank (nullable), waiting_days, rank_group (locked, escalated, regular), adjustment_reason, warnings (json), timestamps.

**activity_logs**: id, user_id, action, subject_type, subject_id, old_values (json), new_values (json), ip_address, created_at.

**settings**: id, key (unik), value (json), timestamps.

### 10.2 Relasi utama

1. customers 1 ke banyak production_orders; products 1 ke banyak production_orders.
2. production_orders 1 ke banyak assessments; criteria 1 ke banyak assessments.
3. criteria 1 ke banyak criterion_scales.
4. evaluations 1 ke banyak evaluation_items; production_orders 1 ke banyak evaluation_items.
5. users terhubung ke order (pembuat), evaluasi (penjalan, penyetuju), log.

### 10.3 Kamus Data

Tipe singkat: PK kunci utama, FK kunci tamu, string(n) teks maksimal n karakter, int bilangan bulat, dec(p,s) desimal p digit dengan s di belakang koma, bool ya atau tidak, enum pilihan tetap, json data terstruktur, datetime tanggal dan jam. Kolom timestamps (created_at, updated_at) ada di semua tabel dan tidak diulang di bawah.

**users**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| name | string(100) | ya | | Nama pengguna |
| email | string(150) | ya | | Unik, untuk masuk |
| password | string(255) | ya | | Hash bcrypt |
| role | enum | ya | | admin, manager, supervisor, leader |
| is_active | bool | ya | true | Nonaktif tidak dapat masuk |
| last_login_at | datetime | tidak | kosong | Diisi saat berhasil masuk |

**customers**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| code | string(30) | ya | | Unik |
| name | string(150) | ya | | |
| contact | string(100) | tidak | kosong | Telepon atau email |
| urgency_level | int | ya | 3 | 1 sampai 5, sumber nilai C2 |
| is_active | bool | ya | true | |

**products**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| code | string(30) | ya | | Unik |
| name | string(150) | ya | | |
| category | string(80) | tidak | kosong | |
| unit_price | dec(15,2) | tidak | kosong | Untuk nilai order otomatis (C6) |
| minutes_per_unit | dec(8,2) | tidak | kosong | Estimasi waktu per pasang |
| is_active | bool | ya | true | |

**production_orders**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| number | string(40) | ya | otomatis | Unik, dapat diubah |
| customer_id | FK int | ya | | Ke customers |
| product_id | FK int | ya | | Ke products |
| quantity | int | ya | | Jumlah pasang, lebih dari 0 |
| order_date | date | ya | hari ini | Tanggal order masuk |
| deadline_date | date | ya | | Tidak boleh sebelum order_date |
| unit_price | dec(15,2) | tidak | dari produk | Harga satuan saat order dibuat |
| status | enum | ya | queued | queued, in_progress, done, canceled |
| priority | enum | ya | normal | normal, top |
| lock_type | enum | ya | none | none, auto (status Dikerjakan), manual (dipasang pengguna) |
| lock_reason | string(255) | tidak | kosong | Wajib bila lock_type manual |
| locked_by | FK int | tidak | | Ke users, kosong untuk kunci otomatis |
| locked_at | datetime | tidak | kosong | Waktu kunci dipasang |
| lock_until | date | tidak | kosong | Kunci manual lepas sendiri setelah tanggal ini |
| notes | string(500) | tidak | kosong | |
| created_by | FK int | ya | | Ke users |
| updated_by | FK int | tidak | | Ke users |
| deleted_at | datetime | tidak | kosong | Soft delete |

**criteria**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| code | string(10) | ya | | Unik, misalnya C1 |
| name | string(120) | ya | | |
| unit | string(30) | tidak | kosong | hari, persen, jam, rupiah, tingkat |
| direction | enum | ya | | higher_first, lower_first (arah nilai asli) |
| value_source | enum | ya | manual | deadline, customer_urgency, order_value, waiting, manual |
| importance | int | ya | 3 | 1 sampai 5 |
| weight | dec(6,4) | ya | 0 | Hasil normalisasi, total semua aktif = 1 |
| sort | int | ya | 0 | Urutan tampil |
| is_active | bool | ya | true | |

**criterion_scales**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| criterion_id | FK int | ya | | Ke criteria |
| score | int | ya | | 1 sampai 5, unik per kriteria |
| label | string(80) | tidak | kosong | Keterangan rentang |
| lower_bound | dec(15,2) | tidak | kosong | Termasuk; kosong berarti tak terbatas ke bawah |
| upper_bound | dec(15,2) | tidak | kosong | Tidak termasuk; kosong berarti tak terbatas ke atas |

**assessments**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| production_order_id | FK int | ya | | Unik bersama criterion_id |
| criterion_id | FK int | ya | | |
| raw_value | dec(15,2) | tidak | kosong | Nilai asli; kosong dianggap terburuk |
| is_overridden | bool | ya | false | Untuk C2 yang ditimpa manual |
| override_reason | string(255) | tidak | kosong | Wajib bila is_overridden true |
| updated_by | FK int | tidak | | Ke users |
| value_updated_at | datetime | tidak | kosong | Dasar penanda perlu diperbarui |

**assessment_histories**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| assessment_id | FK int | ya | | |
| old_value | dec(15,2) | tidak | kosong | |
| new_value | dec(15,2) | tidak | kosong | |
| changed_by | FK int | ya | | Ke users |
| changed_at | datetime | ya | sekarang | |

**evaluations**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| name | string(150) | tidak | tanggal dan jam | |
| trigger | enum | ya | manual | manual, scheduled |
| status | enum | ya | draft | draft, submitted, approved, applied, canceled |
| evaluated_at | datetime | ya | saat dibuat | Diisi sekali, tanpa ON UPDATE |
| evaluated_by | FK int | tidak | | Kosong bila terjadwal |
| submitted_by | FK int | tidak | | |
| submitted_at | datetime | tidak | | |
| approved_by | FK int | tidak | | |
| approved_at | datetime | tidak | | |
| rejection_note | string(500) | tidak | kosong | |
| applied_by | FK int | tidak | | |
| applied_at | datetime | tidak | | |
| top_n | int | tidak | dari pengaturan | Jumlah order jadi prioritas |
| criteria_snapshot | json | ya | | Salinan kriteria, bobot, skala |
| settings_snapshot | json | ya | | Salinan pengaturan saat hitung |
| order_count | int | ya | 0 | Jumlah order dinilai |
| notes | string(500) | tidak | kosong | |

**evaluation_items**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| evaluation_id | FK int | ya | | |
| production_order_id | FK int | ya | | |
| order_snapshot | json | ya | | Nomor, pelanggan, produk, tenggat saat hitung |
| raw_values | json | ya | | Nilai asli per kriteria |
| scale_values | json | ya | | Nilai skala per kriteria |
| normalized_values | json | ya | | Nilai normalisasi per kriteria |
| weighted_values | json | ya | | w x r per kriteria |
| score | dec(10,6) | ya | | Skor V |
| system_rank | int | ya | | Peringkat murni dari skor |
| final_rank | int | ya | | Peringkat setelah kelompok (kunci, eskalasi) dan penyesuaian manual |
| previous_rank | int | tidak | kosong | final_rank order ini pada evaluasi sebelumnya, dasar tren peringkat |
| waiting_days | int | ya | 0 | Lama menunggu saat evaluasi, nilai asli C7 |
| rank_group | enum | ya | regular | locked, escalated, regular |
| adjustment_reason | string(255) | tidak | kosong | Wajib bila final_rank beda dari system_rank |
| warnings | json | tidak | kosong | Daftar peringatan nilai kosong |

**activity_logs**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| user_id | FK int | tidak | | Kosong bila oleh sistem |
| action | string(60) | ya | | Jenis kegiatan |
| subject_type | string(60) | tidak | kosong | Nama tabel atau model |
| subject_id | int | tidak | kosong | |
| old_values | json | tidak | kosong | |
| new_values | json | tidak | kosong | |
| ip_address | string(45) | tidak | kosong | |
| created_at | datetime | ya | sekarang | |

**settings**

| Kolom | Tipe | Wajib | Bawaan | Keterangan |
|---|---|---|---|---|
| id | PK int | ya | otomatis | |
| key | string(60) | ya | | Unik, nama pengaturan |
| value | json | ya | | Nilai pengaturan |

Kunci pengaturan untuk perlindungan order lama adalah `lock_in_progress` (ya atau tidak, bawaan ya), `max_wait_days` (bawaan 7), `stuck_rank_evaluations` (bawaan 5), dan `stuck_min_wait_days` (bawaan 4). Pengaturan lain seperti `top_n_priority`, `approval_required`, dan `stale_value_days` memakai tabel yang sama.

### 10.4 Entity Relationship Diagram (ERD)

Sembilan entitas utama beserta kardinalitasnya. PENILAIAN adalah entitas penghubung antara ORDER PRODUKSI dan KRITERIA (relasi banyak ke banyak), dan ITEM EVALUASI adalah entitas penghubung antara EVALUASI dan ORDER PRODUKSI. Entitas pendukung (riwayat penilaian, log aktivitas, pengaturan) tidak digambar agar diagram tetap terbaca.

```
        PELANGGAN                         PRODUK
   (id, kode, nama,                 (id, kode, nama,
    urgensi, aktif)                  harga, aktif)
         |                                |
         | 1                            1 |
         |            memesan             |
         +--------< ORDER PRODUKSI >------+
                   (id, nomor, jumlah,
      +---------<   tenggat, status,  >---------+
      |          M  prioritas )  M              |
      | 1                                       | 1
      |                                         |
      v M                                       v M
  PENILAIAN                                 ITEM EVALUASI
 (id, nilai_asal,                         (id, skor, peringkat,
  sumber, waktu)                           nilai, peringatan)
      ^ M                                       ^ M
      | 1                                       | 1
      |                                         |
   KRITERIA                                  EVALUASI
 (id, kode, nama,                        (id, nama, waktu,
  arah, bobot) 1                          pemicu, status)
      |
      | 1
      v M
 SKALA KRITERIA
 (id, skor 1..5,
  batas bawah, batas atas)

 PENGGUNA (id, nama, email, peran, aktif)
   1 --- M ORDER PRODUKSI  (dibuat / diperbarui oleh)
   1 --- M EVALUASI        (dijalankan / disetujui oleh)
   1 --- M PENILAIAN       (diperbarui oleh)
```

Keterangan kardinalitas: tanda 1 dan M menunjukkan satu ke banyak. Contoh: satu PELANGGAN memiliki banyak ORDER PRODUKSI, satu ORDER PRODUKSI memiliki banyak PENILAIAN (satu untuk tiap kriteria), satu EVALUASI memiliki banyak ITEM EVALUASI (satu untuk tiap order yang dinilai).

### 10.5 Logical Record Structure (LRS)

LRS menunjukkan struktur tabel fisik beserta kunci utama (PK), kunci tamu (FK), dan relasi antar tabel. Garis relasi ditandai 1 dan M.

```
 +---------------------+          +---------------------+
 | customers           |          | products            |
 +---------------------+          +---------------------+
 | PK id               |          | PK id               |
 |    code             |          |    code             |
 |    name             |          |    name             |
 |    urgency_level    |          |    unit_price       |
 |    is_active        |          |    is_active        |
 +---------------------+          +---------------------+
          | 1                              | 1
          |                                |
          | M                              | M
          +----------+        +------------+
                     v        v
             +-----------------------------+
             | production_orders           |
             +-----------------------------+
        +--M | PK id                       | M--+
        |    |    number                   |    |
        |    | FK customer_id              |    |
        |    | FK product_id               |    |
        |    |    quantity                 |    |
        |    |    deadline_date            |    |
        |    |    status, priority         |    |
        |    | FK created_by               |    |
        |    +-----------------------------+    |
        |             | 1                       |
        | 1           | M                       | M
        v M           v                         v
 +-------------------+ +--------------------+  +--------------------+
 | assessments       | | assessment_        |  | evaluation_items   |
 +-------------------+ | histories          |  +--------------------+
 | PK id             | +--------------------+  | PK id              |
 | FK production_    | | PK id              |  | FK evaluation_id   |
 |    order_id       | | FK assessment_id   |  | FK production_     |
 | FK criterion_id   | |    old_value       |  |    order_id        |
 |    raw_value      | |    new_value       |  |    score           |
 |    value_updated_ | | FK changed_by      |  |    system_rank     |
 |    at             | +--------------------+  |    final_rank      |
 +-------------------+                         +--------------------+
          ^ M                                           ^ M
          | 1                                           | 1
 +-------------------+                         +--------------------+
 | criteria          |                         | evaluations        |
 +-------------------+                         +--------------------+
 | PK id             |                         | PK id              |
 |    code, name     |                         |    name            |
 |    direction      |                         |    trigger         |
 |    value_source   |                         |    status          |
 |    importance     |                         |    evaluated_at    |
 |    weight         |                         | FK evaluated_by    |
 +-------------------+                         | FK approved_by     |
          | 1                                  +--------------------+
          | M
          v
 +-------------------+          +---------------------+
 | criterion_scales  |          | users               |
 +-------------------+          +---------------------+
 | PK id             |          | PK id               |
 | FK criterion_id   |          |    name, email      |
 |    score (1..5)   |          |    role             |
 |    lower_bound    |          |    is_active        |
 |    upper_bound    |          +---------------------+
 +-------------------+          users 1--M production_orders,
                                evaluations, assessments

 Tabel pendukung tanpa relasi kunci tamu wajib:
 activity_logs (PK id, user_id, action, ...),
 settings (PK id, key, value).
```

## 11. Spesifikasi Perhitungan

### 11.1 Notasi

i = order (alternatif), j = kriteria, a_ij = nilai asli, x_ij = nilai skala 1 sampai 5, r_ij = nilai normalisasi, w_j = bobot, V_i = skor.

### 11.2 Konversi nilai asli ke skala

x_ij = nilai skala pada baris sub-kriteria j yang rentangnya memuat a_ij, dengan aturan batas bawah <= a_ij < batas atas. Batas yang kosong berarti tidak dibatasi.

Sisa hari C1 dihitung sebagai bilangan bulat: tanggal tenggat dikurangi tanggal evaluasi (hari kalender, tanpa jam). Order yang sudah lewat tenggat bernilai 0 atau negatif.

### 11.3 Normalisasi dan skor

r_ij = x_ij / max_i(x_ij)

V_i = jumlah j dari (w_j x r_ij), dibulatkan 6 desimal untuk disimpan dan 4 desimal untuk ditampilkan.

### 11.4 Nilai kosong

Bila a_ij kosong (misalnya nilai manual belum diisi), x_ij = 1 (skala terburuk) dan dicatat sebagai peringatan, kecuali pengaturan "wajib lengkap" aktif sehingga order tidak diikutkan.

### 11.5 Skala contoh (diganti jawaban kuesioner Bagian F)

Setiap sel ditulis sebagai rentang dengan batas bawah termasuk dan batas atas tidak termasuk.

| Skala | C1 Sisa hari | C2 Urgensi | C3 Bahan (%) | C4 Sisa kerja (jam) | C5 Progres (%) | C6 Nilai order (Rp) |
|---|---|---|---|---|---|---|
| 5 | kurang dari 2 (1 hari atau kurang, termasuk terlambat) | 5 | 100 | kurang dari 8 | 80 ke atas | 100 juta ke atas |
| 4 | 2 sampai kurang dari 4 | 4 | 75 sampai kurang dari 100 | 8 sampai kurang dari 24 | 60 sampai kurang dari 80 | 50 juta sampai kurang dari 100 juta |
| 3 | 4 sampai kurang dari 8 | 3 | 50 sampai kurang dari 75 | 24 sampai kurang dari 48 | 40 sampai kurang dari 60 | 25 juta sampai kurang dari 50 juta |
| 2 | 8 sampai kurang dari 15 | 2 | 25 sampai kurang dari 50 | 48 sampai kurang dari 96 | 20 sampai kurang dari 40 | 10 juta sampai kurang dari 25 juta |
| 1 | 15 ke atas | 1 | kurang dari 25 | 96 ke atas | kurang dari 20 | kurang dari 10 juta |

Catatan arah: C3 makin lengkap bahannya makin siap dikerjakan sehingga didahulukan; C4 makin sedikit sisa kerja makin cepat selesai sehingga didahulukan; C5 makin besar progres makin didahulukan agar segera tuntas. Arah final mengikuti kuesioner D4.

Skala contoh untuk C7 (lama menunggu, dalam hari kalender). Makin lama menunggu, makin tinggi skalanya. Rentangnya disusun supaya skala 5 tercapai tepat di batas tunggu maksimum (bawaan 7 hari), dan eskalasi mulai bekerja sesudahnya, yaitu pada hari ke-8:

1. Skala 5: 7 hari ke atas.
2. Skala 4: 5 sampai kurang dari 7 hari.
3. Skala 3: 3 sampai kurang dari 5 hari.
4. Skala 2: 1 sampai kurang dari 3 hari.
5. Skala 1: kurang dari 1 hari (baru masuk hari ini).

Bila batas tunggu maksimum diubah di pengaturan, rentang skala C7 perlu disesuaikan agar skala 5 tetap jatuh di batas yang baru.

### 11.6 Contoh perhitungan

Contoh ini memakai enam kriteria pertama dengan bobot rancangan awal supaya hitungannya ringkas. Mesin SAW bekerja dengan jumlah kriteria berapa pun, dan peran C7 diperlihatkan terpisah pada Bab 11.7.

Bobot: C1 0,30; C2 0,20; C3 0,15; C4 0,15; C5 0,10; C6 0,10.

Matriks keputusan (skala):

| Order | C1 | C2 | C3 | C4 | C5 | C6 |
|---|---|---|---|---|---|---|
| A | 5 | 4 | 5 | 3 | 4 | 3 |
| B | 3 | 5 | 2 | 4 | 2 | 4 |
| C | 4 | 2 | 4 | 5 | 5 | 2 |
| Maksimum | 5 | 5 | 5 | 5 | 5 | 4 |

Normalisasi (x dibagi maksimum kolom):

| Order | r1 | r2 | r3 | r4 | r5 | r6 |
|---|---|---|---|---|---|---|
| A | 1,00 | 0,80 | 1,00 | 0,60 | 0,80 | 0,75 |
| B | 0,60 | 1,00 | 0,40 | 0,80 | 0,40 | 1,00 |
| C | 0,80 | 0,40 | 0,80 | 1,00 | 1,00 | 0,50 |

Skor:

1. V_A = 0,30(1,00) + 0,20(0,80) + 0,15(1,00) + 0,15(0,60) + 0,10(0,80) + 0,10(0,75) = 0,300 + 0,160 + 0,150 + 0,090 + 0,080 + 0,075 = **0,8550**
2. V_B = 0,30(0,60) + 0,20(1,00) + 0,15(0,40) + 0,15(0,80) + 0,10(0,40) + 0,10(1,00) = 0,180 + 0,200 + 0,060 + 0,120 + 0,040 + 0,100 = **0,7000**
3. V_C = 0,30(0,80) + 0,20(0,40) + 0,15(0,80) + 0,15(1,00) + 0,10(1,00) + 0,10(0,50) = 0,240 + 0,080 + 0,120 + 0,150 + 0,100 + 0,050 = **0,7400**

Peringkat: 1. A (0,8550), 2. C (0,7400), 3. B (0,7000). Contoh ini menjadi uji unit wajib mesin SAW.

### 11.7 Contoh perlindungan order lama

Contoh ini memakai ketujuh kriteria dengan bobot C1 0,27; C2 0,18; C3 0,135; C4 0,135; C5 0,09; C6 0,09; C7 0,10 (total 1) dan hanya dua order, supaya mudah diikuti.

Order **L** adalah order lama dari pelanggan biasa dengan tenggat masih jauh. Skalanya C1 1, C2 2, C3 4, C4 3, C5 3, C6 2. Order **B** adalah pesanan baru yang mendesak. Skalanya C1 3, C2 5, C3 4, C4 3, C5 1, C6 4, dan C7 selalu 1 karena baru masuk. Skala C7 untuk L bergantung pada lama menunggunya.

**Saat L baru masuk (0 hari)** (C7 untuk L dan B sama-sama 1). Nilai maksimum kolom: C1 3, C2 5, C3 4, C4 3, C5 3, C6 4, C7 1.

1. V_L = 0,27(1/3) + 0,18(2/5) + 0,135(1) + 0,135(1) + 0,09(1) + 0,09(2/4) + 0,10(1) = 0,0900 + 0,0720 + 0,1350 + 0,1350 + 0,0900 + 0,0450 + 0,1000 = **0,6670**
2. V_B = 0,27(1) + 0,18(1) + 0,135(1) + 0,135(1) + 0,09(1/3) + 0,09(1) + 0,10(1) = 0,2700 + 0,1800 + 0,1350 + 0,1350 + 0,0300 + 0,0900 + 0,1000 = **0,9400**

B unggul jauh, dengan selisih 0,2730. Ini wajar karena B memang lebih mendesak.

**Saat L menunggu 8 hari** (C7 untuk L menjadi 5). Lalu datang pesanan mendesak baru, sebut **B2**, dengan skala yang sama seperti B. Nilai maksimum C7 kini 5, sehingga r7 untuk L adalah 5/5 = 1 dan untuk B2 adalah 1/5 = 0,2. Kriteria lain tidak berubah.

1. V_L tetap **0,6670**. Nilai r7 milik L sudah 1 sejak awal, karena pada hari pertama ia sama-sama terbesar di kolom C7 bersama B.
2. V_B2 = 0,2700 + 0,1800 + 0,1350 + 0,1350 + 0,0300 + 0,0900 + 0,10(0,2) = **0,8600**. Yang berubah adalah r7 milik B2, yang turun dari 1 menjadi 0,2 karena sekarang ada order yang jauh lebih lama menunggu.

Selisihnya menyempit dari 0,2730 menjadi 0,1930, tetapi B2 masih di atas L. Artinya kriteria lama menunggu membantu, tetapi sendirian belum cukup untuk menjamin order lama terangkat. Pada titik inilah eskalasi bekerja. L sudah menunggu 8 hari, lebih dari batas 7 hari, sehingga masuk kelompok eskalasi.

| Keadaan | Skor L | Skor B | Peringkat sistem | Peringkat akhir |
|---|---|---|---|---|
| L baru masuk (0 hari) | 0,6670 | 0,9400 | B lalu L | B lalu L |
| L menunggu 8 hari | 0,6670 | 0,8600 | B2 lalu L | L (eskalasi) lalu B2 |

Peringkat sistem tetap murni dari skor, sehingga pengaruh eskalasi kelihatan jelas dari selisih dua kolom terakhir. Contoh ini menjadi uji unit wajib untuk pengelompokan peringkat.

## 12. Kebutuhan Non-fungsional

| Kode | Aspek | Kebutuhan |
|---|---|---|
| NF01 | Kinerja | Halaman daftar terbuka kurang dari 2 detik untuk 1.000 order; perhitungan 200 order kurang dari 5 detik |
| NF02 | Keamanan | Hash kata sandi, perlindungan CSRF, validasi semua input, hak akses diperiksa di server (Policy) untuk setiap aksi, tidak ada data sensitif di URL |
| NF03 | Keamanan data | Nilai order hanya untuk peran yang berhak; file .env di luar folder publik; APP_DEBUG mati di produksi |
| NF04 | Ketertelusuran | Evaluasi tidak dapat diubah; log aktivitas untuk perubahan penting |
| NF05 | Kegunaan | Bahasa Indonesia penuh, format tanggal Indonesia, format Rupiah, pesan kesalahan jelas |
| NF06 | Kompatibilitas | Chrome, Edge, Firefox versi terbaru; tampilan responsif hingga lebar 360 piksel |
| NF07 | Pemasangan | Berjalan di shared hosting cPanel PHP 8.3 tanpa terminal (migrasi melalui Cron Job atau skrip terkunci) |
| NF08 | Basis data | Kompatibel MySQL 8 dan MariaDB 10.4; hindari fungsi dan fitur yang tidak tersedia di keduanya |
| NF09 | Cadangan | Panduan backup basis data harian melalui cPanel; ekspor Excel sebagai cadangan tambahan |
| NF10 | Kualitas kode | Format kode Laravel Pint, uji otomatis Pest untuk mesin SAW dan setiap halaman |

## 13. Rencana Pengujian dan Validasi

1. **Uji unit mesin SAW**: contoh Bab 11.6, kasus satu order, kasus semua nilai sama, kasus nilai kosong, kasus skor sama (aturan peringkat). Untuk perlindungan order lama: contoh Bab 11.7, order terkunci selalu di atas order biasa, order di batas tunggu maksimum (tepat 7 hari belum dieskalasi, 8 hari sudah), order terkunci tidak ikut eskalasi, dan kunci manual yang kedaluwarsa kembali menjadi order biasa.
2. **Uji fitur**: setiap halaman terbuka sesuai peran, setiap aksi ditolak untuk peran yang tidak berhak, impor Excel valid dan tidak valid.
3. **Uji black box**: tabel skenario per fitur (masukan, langkah, hasil yang diharapkan, hasil nyata).
4. **Uji akurasi perhitungan**: data uji kuesioner Bagian J dihitung manual di Excel dan dengan sistem; selisih skor kurang dari 0,0001 untuk semua order.
5. **Validasi terhadap keputusan perusahaan**: peringkat sistem dibandingkan dengan urutan versi perusahaan (kuesioner J3) memakai korelasi peringkat Spearman; sasaran minimal 0,70. Perbedaan dianalisis per order.
6. **Uji penerimaan pengguna (UAT)**: pengguna dari setiap peran menjalankan skenario harian, lalu mengisi kuesioner SUS.
7. **Uji kinerja**: data sintetis 1.000 order, ukur waktu perhitungan dan waktu muat halaman.
8. **Simulasi order tertahan (starvation)**: simulasi 30 hari dengan satu order lama berpelanggan biasa dan tenggat jauh, sementara setiap hari masuk dua pesanan baru berprioritas tinggi. Simulasi dijalankan dua kali, yaitu tanpa perlindungan (kontrol) dan dengan perlindungan penuh, dengan data awal yang sama. Yang diukur: hari ke berapa order lama masuk daftar prioritas, jumlah order yang menunggu lebih dari batas tunggu maksimum, dan peringkat tertinggi yang pernah dicapai order lama. Sasarannya, dengan perlindungan tidak ada order yang melewati batas tunggu maksimum di luar kelompok eskalasi atau terkunci, dan hasil tanpa perlindungan menjadi pembanding yang menunjukkan masalahnya memang ada. Simulasi dengan perlindungan dijalankan tiga kali dengan batas tunggu maksimum 5, 7, dan 10 hari. Yang dibandingkan: rata-rata dan maksimum lama menunggu, jumlah order yang dieskalasi, dan berapa kali pesanan mendesak baru harus menunggu karena slot Prioritas Utama terisi eskalasi. Hasilnya dipakai untuk menguatkan atau menggeser nilai 7 hari.

## 14. Rencana Pengerjaan dan Daftar Tugas

Ukuran: **S** kurang dari 2 jam, **M** sekitar setengah hari, **L** sekitar satu hari atau lebih. Setiap fase ditutup dengan uji otomatis dan pengecekan langsung di peramban memakai data demo, sehingga tidak ada halaman kosong.

### Fase 0. Persiapan

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P0-01 | Membuat project Laravel 13 baru di folder dss, repo git terpisah | S | Aplikasi kosong berjalan di lokal |
| P0-02 | Memasang Filament 5, panel admin, tema, logo teks, bahasa Indonesia, zona waktu Asia/Jakarta | M | Halaman masuk tampil dalam bahasa Indonesia |
| P0-03 | Memasang Pest, Pint, konfigurasi basis data uji | S | Perintah uji berjalan hijau |
| P0-04 | Memeriksa kompatibilitas hosting (PHP 8.3, ekstensi, MariaDB) | S | Daftar kebutuhan hosting tercatat |

### Fase 1. Fondasi, Autentikasi, Pengguna

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P1-01 | Migrasi users dengan role dan is_active, enum peran | S | Migrasi berjalan, model punya helper peran |
| P1-02 | Login, keluar, pembatasan percobaan, tolak akun nonaktif | M | Kriteria penerimaan F01 terpenuhi |
| P1-03 | Halaman profil dan ganti kata sandi | S | Kata sandi baru berlaku |
| P1-04 | Halaman manajemen pengguna (F02) dengan aturan Admin terakhir | M | Kriteria penerimaan F02 terpenuhi |
| P1-05 | Policy dasar per peran dan menu yang menyesuaikan peran | M | Menu tiap peran sesuai Bab 9 |
| P1-06 | Seeder akun demo empat peran | S | Empat akun dapat masuk |

### Fase 2. Data Master dan Order

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P2-01 | Pelanggan: migrasi, model, halaman, validasi (F03) | M | CRUD pelanggan berjalan |
| P2-02 | Produk: migrasi, model, halaman (F04) | M | CRUD produk berjalan |
| P2-03 | Order produksi: migrasi, model, status, nomor otomatis | M | Order tersimpan dengan nomor unik |
| P2-04 | Halaman daftar order dengan filter terlambat dan mendekati tenggat, badge status | M | Filter memberi hasil benar |
| P2-05 | Formulir order dengan validasi tanggal dan harga dari produk | M | Kriteria penerimaan F05 terpenuhi |
| P2-06 | Halaman detail order dan riwayat perubahan | M | Detail menampilkan data lengkap |
| P2-07 | Seeder data demo: 5 pelanggan, 4 produk, 25 order beragam status dan tenggat, termasuk beberapa order lama yang sudah lama menunggu dan order Dikerjakan | M | Semua halaman berisi data, termasuk kasus order lama |
| P2-08 | Kolom penguncian pada order (jenis, alasan, pemasang, waktu, tanggal berakhir) dan ikon kunci di daftar order | S | Kolom tersedia dan ikon tampil pada order terkunci |

### Fase 3. Kriteria, Bobot, Skala

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P3-01 | Migrasi criteria (termasuk sumber nilai lama menunggu) dan criterion_scales | S | Tabel tersedia |
| P3-02 | Halaman kriteria dengan tingkat kepentingan dan bobot otomatis | M | Contoh bobot F07 tepat |
| P3-03 | Mode atur bobot langsung dengan validasi total | S | Total selain 100% ditolak |
| P3-04 | Halaman skala per kriteria dengan validasi rentang dan uji rentang | L | Kriteria penerimaan F08 terpenuhi |
| P3-05 | Seeder tujuh kriteria (C1 sampai C7) dan skala contoh Bab 11.5 | S | Kriteria siap pakai, total bobot 1 |

### Fase 4. Penilaian

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P4-01 | Migrasi assessments dan assessment_histories | S | Tabel tersedia |
| P4-02 | Layanan nilai otomatis: sisa hari, urgensi pelanggan, nilai order, lama menunggu | M | Nilai otomatis benar di uji unit |
| P4-03 | Layanan konversi nilai asli ke skala | M | Contoh F08 lolos uji |
| P4-04 | Halaman matriks penilaian dengan edit sel, penanda sumber, kelengkapan, kedaluwarsa | L | Kriteria penerimaan F09 terpenuhi |
| P4-05 | Penimpaan urgensi per order dengan alasan | S | Alasan wajib |
| P4-06 | Riwayat perubahan nilai | S | Setiap ubah tercatat |
| P4-07 | Seeder nilai penilaian demo, termasuk beberapa yang kosong dan kedaluwarsa | S | Penanda tampil di demo |

### Fase 5. Impor dan Ekspor Excel

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P5-01 | Templat Excel order dan penilaian | S | Templat dapat diunduh |
| P5-02 | Impor dengan pratinjau, validasi per baris, mode tambah atau perbarui, transaksi | L | Kriteria penerimaan F06 terpenuhi |
| P5-03 | Ekspor daftar order | S | File sesuai filter |

### Fase 6. Mesin SAW dan Evaluasi

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P6-01 | Kelas SawCalculator murni (tanpa basis data) sesuai Bab 11 | M | Contoh 11.6 lolos uji |
| P6-02 | Uji unit kasus khusus (satu order, nilai sama, nilai kosong, skor sama) | M | Semua lolos |
| P6-03 | Migrasi evaluations dan evaluation_items (tanpa ON UPDATE pada evaluated_at) | S | Tabel tersedia |
| P6-04 | Layanan evaluasi: pemeriksaan awal, hitung, simpan salinan | M | Evaluasi tersimpan lengkap |
| P6-05 | Aksi Jalankan Perhitungan dengan konfirmasi data belum lengkap | M | Kriteria penerimaan F10 terpenuhi |
| P6-06 | Uji kinerja 200 dan 1.000 order | S | Waktu sesuai NF01 |
| P6-07 | Penyusunan peringkat akhir berkelompok: terkunci, eskalasi, biasa (Bab 7.7), dengan peringkat sistem tetap murni | M | Urutan kelompok dan aturan seri sesuai spesifikasi |
| P6-08 | Uji unit pengelompokan dan eskalasi: contoh Bab 11.7, batas 7 dan 8 hari, order terkunci, kunci kedaluwarsa | M | Semua kasus lolos |
| P6-09 | Simpan lama menunggu, kelompok, dan peringkat sebelumnya pada setiap item evaluasi | S | Kolom terisi dan tren peringkat bisa dibaca |

### Fase 7. Hasil, Persetujuan, Penerapan

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P7-01 | Daftar evaluasi dengan status dan filter | M | Daftar tampil |
| P7-02 | Detail evaluasi tujuh tab | L | Semua tab berisi data |
| P7-03 | Penyesuaian peringkat dengan alasan | M | Peringkat sistem dan akhir tersimpan |
| P7-04 | Alur ajukan, setujui, tolak, terapkan sesuai pengaturan | L | Kriteria penerimaan F11 terpenuhi |
| P7-05 | Penanda Prioritas Utama di order | S | Label tampil di daftar order |
| P7-06 | Bandingkan dua evaluasi | M | Naik, turun, tetap tampil benar |
| P7-07 | Aksi pasang dan lepas kunci manual dengan alasan wajib dan tanggal berakhir opsional | M | Kriteria penerimaan F18 nomor 3 terpenuhi |
| P7-08 | Kunci otomatis saat status Dikerjakan dan pelepasan saat Selesai atau Dibatalkan, mengikuti pengaturan | S | Ikon kunci muncul dan hilang sesuai status |
| P7-09 | Grafik tren peringkat per order di halaman detail | M | Grafik sesuai riwayat evaluasi |
| P7-10 | Peringatan bila slot Prioritas Utama penuh oleh kunci manual dan eskalasi; order Dikerjakan tidak dihitung | S | Peringatan tampil di hasil evaluasi |

### Fase 8. Dasbor dan Laporan

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P8-01 | Widget kartu angka, prioritas teratas, order terlambat, grafik status | L | Kriteria penerimaan F12 terpenuhi |
| P8-02 | Dasbor menyesuaikan peran (Pimpinan melihat persetujuan) | S | Isi sesuai peran |
| P8-03 | Kop laporan dari pengaturan perusahaan | S | Kop tampil |
| P8-04 | Laporan peringkat dan rincian perhitungan PDF dan Excel | L | Angka sama dengan layar |
| P8-05 | Laporan order terlambat, rekap order, riwayat evaluasi | M | Filter periode berjalan |
| P8-06 | Widget order tertahan di dasbor dan filter Tertahan di daftar order | M | Kriteria penerimaan F18 nomor 4 terpenuhi |

### Fase 9. Log, Pengaturan, Penjadwalan

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P9-01 | Pencatat log aktivitas dan halaman log | M | Kriteria penerimaan F14 terpenuhi |
| P9-02 | Halaman pengaturan (F15), termasuk kunci otomatis, batas tunggu maksimum, dan ambang order tertahan | M | Semua pengaturan tersimpan dan dipakai |
| P9-03 | Perintah evaluasi terjadwal dan dokumentasi Cron cPanel | S | Evaluasi terjadwal tercipta pada jam yang diatur |
| P9-04 | Notifikasi dalam aplikasi (F17) untuk pengajuan, persetujuan, penerapan, order terlambat, nilai kedaluwarsa, order tertahan, dan order dieskalasi | M | Kriteria penerimaan F17 terpenuhi |

### Fase 10. Pengujian dan Validasi

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P10-01 | Uji hak akses semua peran untuk setiap halaman dan aksi | M | Tidak ada akses tanpa izin |
| P10-02 | Skenario uji black box per fitur | M | Tabel skenario lengkap dan lolos |
| P10-03 | File Excel perhitungan manual untuk data uji Bagian J | M | Selisih skor di bawah 0,0001 |
| P10-04 | Hitung korelasi Spearman dengan urutan perusahaan | S | Nilai dan analisis tercatat |
| P10-05 | UAT dan kuesioner SUS | M | Hasil tercatat |
| P10-06 | Simulasi order tertahan 30 hari, tanpa dan dengan perlindungan (batas 5, 7, dan 10 hari), lalu bandingkan hasilnya | M | Tabel perbandingan tercatat dan sasaran T7 tercapai |

### Fase 11. Pemasangan dan Dokumentasi

| ID | Tugas | Ukuran | Selesai bila |
|---|---|---|---|
| P11-01 | Paket rilis dan pemasangan di staging (.env di luar folder publik) | M | Staging dapat dipakai semua peran |
| P11-02 | Buku panduan pengguna per peran (docx) | L | Panduan lengkap dengan tangkapan layar |
| P11-03 | Dokumen teknis: arsitektur, ERD, use case, rumus, pengujian | L | Siap dipakai untuk Bab 3 dan 4 |
| P11-04 | README dan repositori GitHub | S | Repo terunggah |

**Jumlah tugas:** 72 tugas dalam 12 fase.

## 15. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Kuesioner terlambat kembali | Kriteria, bobot, skala belum final | Bangun dengan nilai usulan yang dapat diubah dari halaman pengaturan; tidak ada nilai yang ditanam di kode |
| Data nilai manual jarang diperbarui | Hasil peringkat tidak akurat | Penanda Perlu diperbarui, peringatan sebelum hitung, kriteria tenggat dihitung otomatis |
| Dosen meminta rumus benefit dan cost klasik | Perubahan cara normalisasi | Mesin SAW menyediakan dua mode (K-01), dipilih lewat pengaturan |
| Hosting membatasi fungsi PHP atau terminal | Migrasi dan jadwal sulit dijalankan | Rilis berbentuk paket siap pakai, migrasi melalui Cron, uji di staging sejak awal |
| Pemindai keamanan hosting memblokir unggahan | Pemasangan gagal sebagian | Unggah satu paket zip dan periksa ukuran setiap file setelah ekstrak |
| Peringkat sistem berbeda jauh dari keputusan perusahaan | Validasi rendah | Analisis perbedaan per order, sesuaikan bobot dan skala bersama client |
| Order lama tertahan terus karena tersalip pesanan baru (starvation) | Order tidak pernah dikerjakan, pelanggan lama kecewa | Enam pengaman pada Bab 7.7: kriteria tenggat, kriteria lama menunggu, kunci otomatis, kunci manual, eskalasi pada batas tunggu maksimum, dan pemantauan order tertahan. Dibuktikan lewat simulasi 30 hari (Bab 13) |
| Kriteria lama menunggu saja tidak cukup mengangkat order lama | Perlindungan semu | Eskalasi otomatis menjadi jaminan keras, dan contoh Bab 11.7 menunjukkan kenapa pengaman ini diperlukan |
| Terlalu banyak order terkunci atau dieskalasi sehingga pesanan mendesak yang baru tidak kebagian slot | Pesanan penting terlambat | Peringatan slot penuh (K-13), pimpinan dapat menaikkan N atau melepas kunci manual; order yang sedang dikerjakan tidak memakai slot, batas tunggu maksimum dapat diatur |
| Kunci manual dipakai berlebihan atau disalahgunakan | Hasil SAW tidak lagi diikuti | Alasan wajib, tanggal berakhir opsional, hanya Manajer dan Pimpinan yang berhak, setiap kunci tercatat di log dan dapat dilaporkan |
| Batas tunggu maksimum dan bobot lama menunggu belum sesuai kebiasaan perusahaan | Eskalasi terlalu cepat atau terlalu lambat | Nilai awal dapat diubah dari pengaturan, nilai bawaan diuji lewat simulasi 5, 7, dan 10 hari (K-14) |

## 16. Keputusan Terbuka

| Kode | Pertanyaan | Sumber jawaban | Usulan sementara |
|---|---|---|---|
| K-01 | Normalisasi memakai skala searah prioritas (semua benefit) atau rumus benefit dan cost klasik | Dosen pembimbing | Skala searah prioritas, mode klasik tersedia sebagai pilihan |
| K-02 | Daftar kriteria final dan arahnya | Kuesioner D1 sampai D4 | Tujuh kriteria usulan (C1 sampai C7) |
| K-03 | Order belum lengkap ikut dengan nilai terburuk atau dikecualikan | Kuesioner H, dosen | Ikut dengan nilai terburuk dan peringatan |
| K-04 | Sisa hari dihitung hari kalender atau hari kerja | Kuesioner A7 | Hari kalender |
| K-05 | Persetujuan Pimpinan wajib atau tidak | Kuesioner H3 | Dapat diatur, bawaan wajib |
| K-06 | Siapa yang boleh melihat nilai order | Kuesioner D6 dan G3 | Admin, Manajer, Pimpinan |
| K-07 | Jumlah order teratas yang dijadikan prioritas | Kuesioner H2 | 5 |
| K-08 | Satu order satu produk atau banyak produk | Kuesioner C5 | Satu produk |
| K-09 | Lokasi pemasangan (hosting atau server kantor) | Kuesioner I3 | Hosting |
| K-10 | Judul penelitian final | Dosen pembimbing | Sistem Pendukung Keputusan Penentuan Prioritas Penyelesaian Produksi Menggunakan Metode SAW Berbasis Web (Studi Kasus: PT Weba International) |
| K-11 | Apakah order yang terlambat lebih lama perlu didahulukan dibanding yang baru terlambat (pada skala C1 semuanya bernilai 5) | Kuesioner F dan dosen | Aturan seri sudah mendahulukan tenggat paling lama terlewati; pembedaan lebih lanjut hanya bila client meminta |
| K-12 | Apakah order yang sedang dikerjakan boleh disalip pesanan baru yang sangat mendesak | Keputusan perancang | Diputuskan: tidak boleh disalip (kunci otomatis aktif), karena pekerjaan yang sudah berjalan memakai bahan dan jam mesin sehingga menyalipnya menimbulkan pemborosan; Pimpinan dapat melepas kunci dengan alasan bila ada kasus darurat |
| K-13 | Apakah order terkunci dan dieskalasi dihitung dalam jumlah Prioritas Utama (N) | Keputusan perancang | Diputuskan: N hanya berlaku pada antrean Antri; order Dikerjakan tidak dihitung, kunci manual dan eskalasi dihitung, dengan peringatan bila slot penuh; label Prioritas Utama menjawab pertanyaan order mana yang dimulai berikutnya, jadi pekerjaan yang sudah berjalan tidak perlu memakai slot |
| K-14 | Berapa batas tunggu maksimum, bobot C7, dan ambang order tertahan yang sesuai kebiasaan perusahaan | Keputusan perancang, diuji simulasi | Nilai awal 7 hari, bobot C7 0,10, tertahan setelah 5 evaluasi dan 4 hari. Semuanya dapat diubah dari pengaturan; simulasi 5, 7, dan 10 hari pada Bab 13 dipakai untuk menguji kewajarannya |

### 16.1 Keterkaitan kuesioner dan PRD

Jawaban kuesioner dipakai untuk melengkapi dan menegaskan isi dokumen ini. Bagian A dan B menjadi dasar latar belakang pada Bab 2 serta sasaran keberhasilan pada Bab 3. Bagian C menentukan kolom data order dan fitur impor Excel. Bagian D, E, dan F menjadi acuan utama untuk daftar kriteria, bobot, dan skala nilai, sekaligus menutup keputusan terbuka tentang kriteria, nilai order, dan jumlah produk per order. Bagian G menentukan peran pengguna dan pembagian hak akses. Bagian H menentukan alur persetujuan, jumlah order yang dijadikan prioritas, serta bentuk laporan dan isi dasbor. Bagian I memastikan sistem sesuai dengan perangkat dan jaringan perusahaan. Bagian J menyediakan data nyata untuk menguji ketepatan perhitungan, dan Bagian K menampung harapan yang memengaruhi urutan pengerjaan fitur.

Tiga keputusan yang muncul dari perlindungan order lama (K-12, K-13, dan K-14) diambil oleh perancang berdasarkan pertimbangan perancangan sistem dan hasil simulasi, tanpa menunggu kuesioner tambahan: order yang sedang dikerjakan tidak boleh disalip, N hanya menghitung antrean Antri, dan batas tunggu maksimum bawaan 7 hari. Ketiganya disimpan sebagai pengaturan sehingga perusahaan tetap dapat menyesuaikannya saat sistem dipakai (fitur F15), dan kuesioner yang sudah disusun tidak perlu diubah.

## 17. Daftar Use Case dan Aktor

### 17.1 Aktor

| Aktor | Keterangan |
|---|---|
| Admin | Mengelola pengguna, data master, pengaturan, log |
| Manajer Produksi | Mengelola order dan penilaian, kriteria dan bobot, menjalankan perhitungan, mengusulkan dan menerapkan prioritas |
| Supervisor | Memperbarui status dan progres order, melihat prioritas |
| Pimpinan | Menyetujui hasil, menetapkan bobot, melihat dasbor dan laporan |
| Sistem (penjadwal) | Menjalankan perhitungan terjadwal secara otomatis |

### 17.2 Daftar Use Case

| Kode | Use case | Aktor utama | Fitur | Ringkasan |
|---|---|---|---|---|
| UC01 | Masuk ke sistem | Semua | F01 | Masuk dengan email dan kata sandi sesuai peran |
| UC02 | Mengubah profil dan kata sandi | Semua | F01 | Memperbarui nama dan kata sandi sendiri |
| UC03 | Mengelola pengguna | Admin | F02 | Menambah, mengubah, menonaktifkan pengguna, atur ulang kata sandi |
| UC04 | Mengelola pelanggan | Admin, Manajer | F03 | Menambah dan mengubah pelanggan beserta tingkat urgensi |
| UC05 | Mengelola produk | Admin, Manajer | F04 | Menambah dan mengubah produk beserta harga satuan |
| UC06 | Mengelola order produksi | Manajer | F05 | Menambah, mengubah, membatalkan order |
| UC07 | Memperbarui status dan progres order | Supervisor | F05, F09 | Mengubah status order dan nilai C4, C5 |
| UC08 | Mengimpor order dari Excel | Manajer, Admin | F06 | Mengunggah file order dengan pratinjau dan validasi |
| UC09 | Mengelola kriteria dan bobot | Manajer, Pimpinan | F07 | Mengatur kriteria, tingkat kepentingan, bobot |
| UC10 | Mengelola skala nilai | Manajer | F08 | Mengatur rentang sub-kriteria 1 sampai 5 |
| UC11 | Mengisi penilaian order | Manajer, Supervisor | F09 | Mengisi nilai manual, meninjau nilai otomatis |
| UC12 | Menjalankan perhitungan SAW | Manajer, Sistem | F10 | Menghitung skor dan menyimpan evaluasi |
| UC13 | Meninjau hasil evaluasi | Manajer, Pimpinan, Supervisor | F11 | Melihat peringkat, matriks, dan rincian perhitungan |
| UC14 | Menyesuaikan peringkat | Manajer, Pimpinan | F11 | Memindahkan peringkat order dengan alasan |
| UC15 | Menyetujui atau menolak hasil | Pimpinan | F11 | Menyetujui atau menolak evaluasi yang diajukan |
| UC16 | Menerapkan prioritas | Manajer, Pimpinan | F11 | Menandai N order Antri teratas sebagai Prioritas Utama |
| UC17 | Membandingkan dua evaluasi | Manajer, Pimpinan | F11 | Melihat perubahan peringkat antar evaluasi |
| UC18 | Melihat dasbor | Semua | F12 | Melihat ringkasan sesuai peran |
| UC19 | Mencetak dan mengunduh laporan | Manajer, Pimpinan, Supervisor | F13 | Menghasilkan laporan PDF dan Excel |
| UC20 | Melihat log aktivitas | Admin, Pimpinan | F14 | Menelusuri riwayat perubahan |
| UC21 | Mengubah pengaturan sistem | Admin, Pimpinan | F15 | Mengatur profil perusahaan dan aturan keputusan |
| UC22 | Menerima notifikasi | Pimpinan, Manajer | F17 | Menerima pemberitahuan tugas dan peringatan |
| UC23 | Mengunci dan melepas kunci order | Manajer, Pimpinan | F18 | Memasang kunci manual dengan alasan, atau melepasnya |
| UC24 | Memantau order tertahan | Manajer, Pimpinan, Supervisor | F18, F12 | Melihat daftar order tertahan, order dieskalasi, dan tren peringkat |

### 17.3 Use Case Diagram

Aktor berada di luar kotak sistem, use case berada di dalam. Garis menghubungkan aktor dengan use case yang dapat dijalankannya.

```
                  +==================================================+
                  |            SPK Prioritas Produksi                |
                  |                                                  |
   Admin ---------+-- UC03 Kelola Pengguna                           |
     |            |   UC20 Lihat Log Aktivitas                       |
     +------------+-- UC21 Ubah Pengaturan Sistem --------+          |
                  |   UC04 Kelola Pelanggan               |          |
   Manajer -------+-- UC05 Kelola Produk                  |-- Pimpinan
     Produksi     |   UC06 Kelola Order                   |          |
     |            |   UC08 Impor Order dari Excel         |          |
     |            |   UC09 Kelola Kriteria dan Bobot ------+          |
     |            |   UC10 Kelola Skala Nilai                        |
     +------------+-- UC11 Isi Penilaian Order ----------+           |
     |            |   UC12 Jalankan Perhitungan SAW       |           |
     |            |   UC14 Sesuaikan Peringkat -----------+-- Pimpinan
     |            |   UC16 Terapkan Prioritas            |
     +------------+-- UC17 Bandingkan Evaluasi           |
                  |   UC13 Tinjau Hasil Evaluasi --------+-- Supervisor
   Supervisor ----+-- UC07 Perbarui Status dan Progres   |
     |            |   UC19 Cetak dan Unduh Laporan -------+-- Pimpinan
     +------------+-- UC18 Lihat Dasbor -----------------+
                  |   UC15 Setujui atau Tolak Hasil ------------ Pimpinan
                  |   UC01 Masuk, UC02 Ubah Profil  (semua aktor)    |
                  |   UC22 Terima Notifikasi  (Pimpinan, Manajer)    |
                  |                                                  |
   Sistem --------+-- UC12 Jalankan Perhitungan Terjadwal            |
   (penjadwal)    +==================================================+
```

Catatan relasi: UC12 dijalankan oleh Manajer maupun Sistem (penjadwal). UC15 hanya Pimpinan. UC13, UC18, dan UC19 dapat diakses Manajer, Supervisor, dan Pimpinan sesuai hak akses pada Bab 9. UC23 (kunci order) hanya untuk Manajer dan Pimpinan, sedangkan UC24 (pantau order tertahan) juga dapat dilihat Supervisor. Dokumen versi docx memakai gambar diagram yang sudah memuat UC23 dan UC24.

## 18. Struktur Menu per Peran

Tanda centang berarti menu tampil untuk peran tersebut.

| Menu | Admin | Manajer | Supervisor | Pimpinan |
|---|---|---|---|---|
| Dasbor | ya | ya | ya | ya |
| Order Produksi | lihat | ya | status dan progres | lihat |
| Penilaian Order | tidak | ya | sebagian (C4, C5) | tidak |
| Prioritas SAW (evaluasi) | lihat | ya | lihat | ya |
| Kriteria dan Bobot | lihat | ya | tidak | ya |
| Skala Nilai | lihat | ya | tidak | lihat |
| Pelanggan | ya | ya | lihat | lihat |
| Produk | ya | ya | lihat | lihat |
| Laporan | ya | ya | ya | ya |
| Pengguna | ya | tidak | tidak | tidak |
| Log Aktivitas | ya | tidak | tidak | ya |
| Pengaturan | ya | tidak | tidak | sebagian |

Pengelompokan menu: **Beranda** (Dasbor), **Produksi** (Order Produksi, Penilaian Order), **Prioritas** (Prioritas SAW, Kriteria dan Bobot, Skala Nilai), **Data Master** (Pelanggan, Produk), **Laporan**, dan **Pengaturan** (Pengguna, Log Aktivitas, Pengaturan). Menu yang tidak berhak dibuka peran tertentu tidak ditampilkan.

## 19. Notifikasi

**Tujuan:** memberi tahu pengguna saat ada tugas atau keadaan yang perlu ditindaklanjuti.
**Fitur terkait:** F17 (ditambahkan ke Bab 8 sebagai fitur baru pada revisi ini).

| Kode | Pemicu | Penerima | Isi |
|---|---|---|---|
| N1 | Evaluasi diajukan untuk disetujui | Pimpinan | Ada evaluasi menunggu persetujuan Anda |
| N2 | Evaluasi disetujui | Manajer pengaju | Evaluasi telah disetujui dan siap diterapkan |
| N3 | Evaluasi ditolak | Manajer pengaju | Evaluasi ditolak beserta catatan |
| N4 | Prioritas diterapkan | Supervisor | Daftar prioritas baru telah ditetapkan |
| N5 | Ada order terlambat saat perhitungan | Manajer | Sejumlah order telah melewati tenggat |
| N6 | Nilai penilaian perlu diperbarui sebelum perhitungan | Manajer | Sejumlah order memiliki nilai kedaluwarsa |
| N7 | Perhitungan terjadwal gagal atau tidak ada order aktif | Admin, Manajer | Keterangan kegagalan perhitungan terjadwal |
| N8 | Order ditandai Tertahan pada evaluasi terbaru | Manajer, Pimpinan | Sejumlah order Antri berada di luar daftar prioritas terlalu lama |
| N9 | Order masuk kelompok eskalasi | Manajer, Pimpinan | Order tertentu telah menunggu melewati batas tunggu maksimum dan dinaikkan |
| N10 | Slot Prioritas Utama penuh oleh kunci manual dan eskalasi | Manajer, Pimpinan | Pesanan baru tidak kebagian slot, jumlah prioritas perlu ditinjau |

Rincian fungsi:
1. Notifikasi tampil pada ikon lonceng di bilah atas dan dapat ditandai sudah dibaca.
2. Notifikasi dibuat di dalam sistem (tabel notifikasi bawaan Laravel). Pengiriman email bersifat opsional dan diatur pada pengaturan sistem.
3. Daftar notifikasi diperbarui otomatis secara berkala.

Aturan bisnis:
1. Notifikasi N1 hanya dibuat bila pengaturan persetujuan wajib aktif.
2. Notifikasi lama dibersihkan otomatis setelah 30 hari.

Kriteria penerimaan:
1. Saat Manajer mengajukan evaluasi, Pimpinan menerima notifikasi N1.
2. Menandai semua sudah dibaca mengosongkan penanda jumlah pada ikon lonceng.

## 20. Glosarium

| Istilah | Arti |
|---|---|
| SPK atau DSS | Sistem pendukung keputusan: sistem yang membantu, bukan menggantikan, pengambil keputusan |
| SAW | Simple Additive Weighting: metode penjumlahan terbobot dari nilai yang sudah dinormalisasi |
| Alternatif | Pilihan yang dinilai, di sini order produksi |
| Kriteria | Aspek penilaian setiap alternatif |
| Bobot | Tingkat kepentingan kriteria, total 1 |
| Sub-kriteria atau skala crips | Pembagian nilai asli ke dalam rentang bernilai 1 sampai 5 |
| Normalisasi | Penyamaan skala nilai ke rentang 0 sampai 1 |
| Skor preferensi (V) | Hasil akhir SAW untuk setiap alternatif |
| Evaluasi | Satu kali perhitungan yang hasilnya disimpan |
| Korelasi Spearman | Ukuran kemiripan dua urutan peringkat, bernilai -1 sampai 1 |
| SUS | System Usability Scale, kuesioner standar kemudahan penggunaan |
| Starvation | Keadaan ketika sebuah order kalah terus oleh order baru sehingga tidak pernah kebagian dikerjakan |
| Lama menunggu (aging) | Jumlah hari sebuah order sudah mengantri sejak masuk, dipakai sebagai kriteria C7 |
| Kunci order | Penanda yang menahan order di kelompok teratas, otomatis saat Dikerjakan atau manual oleh Manajer dan Pimpinan |
| Eskalasi | Kenaikan otomatis order Antri ke kelompok di atas order biasa setelah menunggu melewati batas tunggu maksimum |
| Order tertahan | Order Antri yang berada di luar daftar prioritas berturut-turut dan sudah menunggu cukup lama |
| Peringkat sistem dan peringkat akhir | Peringkat sistem murni dari skor, peringkat akhir sudah memperhitungkan kunci, eskalasi, dan penyesuaian manual |
