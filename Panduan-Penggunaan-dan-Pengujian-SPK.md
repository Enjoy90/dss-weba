# Panduan Penggunaan dan Pengujian SPK Prioritas Produksi

| Informasi | Isi |
|---|---|
| Aplikasi | SPK Prioritas Produksi (studi kasus PT Weba International) |
| Versi dokumen | 1.3 |
| Tanggal | 6 November 2026 |
| Cakupan | Fase 0 sampai Fase 5: masuk dan pengguna, pelanggan, produk, order produksi, kriteria, bobot, skala nilai, penilaian order, impor dan ekspor Excel |
| Alamat lokal | http://localhost:8001/admin |

## 1. Tentang Dokumen Ini

### 1.1 Tujuan

Dokumen ini menjelaskan, langkah demi langkah, cara memakai setiap fitur yang sudah selesai dibangun dan cara membuktikan bahwa fitur itu bekerja. Setiap bagian dimulai dari menu mana yang dibuka, apa gunanya, apa yang diklik, lalu apa yang seharusnya muncul di layar. Kalau hasil di layar sama dengan yang tertulis, pengujian itu berhasil.

Dokumen ini akan terus bertambah. Setiap fase yang selesai dibangun mendapat bab baru dengan pola yang sama, sehingga pada akhir pengerjaan dokumen ini bisa dipakai sebagai buku panduan pengguna dan sebagai bukti pengujian untuk skripsi.

### 1.2 Cara memakai dokumen ini

Setiap pengujian ditulis dengan urutan yang sama.

1. **Tujuan** menjelaskan apa yang sedang dibuktikan.
2. **Masuk sebagai** menyebut akun yang dipakai.
3. **Langkah** adalah urutan klik yang harus diikuti, satu per satu.
4. **Hasil yang diharapkan** adalah apa yang harus terlihat. Bila berbeda, lihat Bab 9.

Kerjakan pengujian berurutan dari atas. Beberapa pengujian mengubah data, misalnya menonaktifkan akun. Di bagian itu ada petunjuk untuk mengembalikannya. Bila data sudah terlanjur berantakan, data demo bisa dibuat ulang seperti di Bagian 2.2.

### 1.3 Akun demo

Semua akun memakai kata sandi `password`.

- `admin@spk.test` untuk peran Admin, staf administrasi yang mengelola pengguna dan data master.
- `manajer@spk.test` untuk Manajer Produksi, yang membuat dan mengubah order.
- `supervisor@spk.test` untuk Supervisor Produksi, yang memantau order dan memperbarui status.
- `pimpinan@spk.test` untuk Pimpinan, yang melihat seluruh data.

### 1.4 Yang belum ada di fase ini

Supaya tidak bingung saat mencoba, berikut hal yang memang belum dibuat dan baru hadir di fase berikutnya.

- Dasbor masih berisi sapaan saja. Kartu angka dan grafik dibuat di Fase 8.
- Kriteria, bobot, skala nilai, dan penilaian per order sudah ada, tetapi belum dipakai menghitung apa pun. Perhitungan SAW dibuat di Fase 6.
- Kunci order otomatis saat status menjadi Dikerjakan belum aktif. Ikon gembok pada data demo sudah terpasang oleh data awal, tetapi mengubah status order secara manual, atau mengimpor order berstatus Dikerjakan, belum memasang atau melepas gembok. Itu dikerjakan di Fase 7.
- Kolom Prioritas selalu bernilai Normal sampai fitur penerapan prioritas selesai di Fase 7.
- Halaman log aktivitas lengkap baru ada di Fase 9. Yang sudah ada hanya riwayat perubahan di halaman detail order.

## 2. Menyiapkan Aplikasi di Komputer Lokal

### 2.1 Menyalakan basis data

Aplikasi menyimpan semua data di MariaDB milik XAMPP.

1. Buka XAMPP Control Panel.
2. Pada baris MySQL, klik **Start**.
3. Tunggu sampai namanya berwarna hijau.

**Hasil yang diharapkan:** MySQL berstatus Running. Tanpa ini aplikasi akan menampilkan pesan gagal terhubung ke basis data.

### 2.2 Menyiapkan data demo

Langkah ini membuat semua tabel dari awal dan mengisinya dengan data contoh: empat akun, lima pelanggan, empat produk, 25 order, tujuh kriteria beserta skalanya, dan nilai penilaian untuk order yang berjalan. **Semua data yang ada sebelumnya akan hilang**, jadi jalankan hanya saat ingin mulai dari kondisi bersih.

1. Buka terminal di folder `dss`.
2. Jalankan perintah berikut.

```
php artisan migrate:fresh --seed
```

**Hasil yang diharapkan:** daftar migrasi berstatus DONE, disusul lima seeder (DemoUserSeeder, DemoMasterSeeder, DemoOrderSeeder, CriteriaSeeder, DemoAssessmentSeeder) yang juga berstatus DONE. Bila ada tulisan merah, periksa Bab 9.

Tanggal pada order demo dibuat relatif terhadap hari ini. Jadi beberapa order selalu terlambat dan beberapa selalu hampir jatuh tempo, kapan pun data dibuat. Nilai manual penilaian juga dibuat dengan usia yang berbeda, ada yang baru dan ada yang sudah lima hari.

### 2.3 Menjalankan aplikasi

1. Di terminal yang sama, jalankan perintah berikut.

```
php artisan serve --port=8001
```

2. Biarkan jendela terminal tetap terbuka selama aplikasi dipakai.

**Hasil yang diharapkan:** terminal menampilkan tulisan bahwa server berjalan di alamat `http://127.0.0.1:8001`.

### 2.4 Membuka aplikasi dan masuk

1. Buka peramban, lalu ketik `http://localhost:8001/admin`.
2. Anda akan melihat halaman **Masuk ke akun Anda**.

![Halaman masuk](01-masuk.png)

3. Isi email `admin@spk.test` dan kata sandi `password`, lalu klik **Masuk**.

**Hasil yang diharapkan:** Anda masuk ke halaman **Dasbor** dan melihat sapaan "Selamat Datang" beserta nama Anda.

![Dasbor Admin](03-dasbor-admin.png)

### 2.5 Mematikan aplikasi

1. Di jendela terminal yang menjalankan server, tekan **Ctrl + C**.
2. Bila ingin, matikan juga MySQL lewat tombol **Stop** di XAMPP.

## 3. Fase 1: Masuk, Profil, dan Pengguna

Bab ini membuktikan bahwa hanya orang yang berhak yang bisa masuk, dan bahwa Admin bisa mengatur siapa saja yang boleh memakai aplikasi.

### 3.1 Uji 1.1: Masuk dengan akun yang benar

**Tujuan:** memastikan pengguna aktif bisa masuk dan terarah ke halaman utama.
**Masuk sebagai:** belum masuk (buka jendela peramban baru atau klik Keluar dulu).

1. Buka `http://localhost:8001/admin`.
2. Isi email `manajer@spk.test` dan kata sandi `password`.
3. Klik **Masuk**.

**Hasil yang diharapkan:** halaman Dasbor terbuka dan sapaan memuat nama "Budi Santoso". Menu di sebelah kiri berisi Dasbor, Order Produksi, Pelanggan, dan Produk.

### 3.2 Uji 1.2: Masuk dengan kata sandi yang salah

**Tujuan:** memastikan akun tidak bisa dimasuki dengan kata sandi yang salah.

1. Buka halaman masuk.
2. Isi email `admin@spk.test` dan kata sandi apa saja yang salah, misalnya `sandi-salah`.
3. Klik **Masuk**.

**Hasil yang diharapkan:** Anda tetap di halaman masuk. Di bawah kolom email muncul tulisan merah "Kredensial yang diberikan tidak dapat ditemukan."

![Gagal masuk](02-masuk-gagal.png)

Pesan ini sengaja tidak menyebut apakah email atau kata sandinya yang salah, supaya orang tidak bisa menebak email yang terdaftar.

### 3.3 Uji 1.3: Akun yang dinonaktifkan

**Tujuan:** memastikan akun yang dinonaktifkan tidak bisa masuk dan mendapat pesan yang jelas.
**Masuk sebagai:** Admin.

1. Masuk dengan `admin@spk.test`.
2. Buka menu **Pengaturan**, lalu **Pengguna**.
3. Pada baris Siti Rahmawati, klik tiga titik di paling kiri baris, lalu pilih **Nonaktifkan**.
4. Pada jendela konfirmasi, klik **Konfirmasi**. Kolom Aktif untuk Siti berubah menjadi tanda silang.
5. Klik lingkaran nama Anda di pojok kanan atas, lalu **Keluar**.
6. Masuk dengan `supervisor@spk.test` dan kata sandi `password`.

**Hasil yang diharapkan:** masuk ditolak dengan tulisan merah "Akun Anda dinonaktifkan."

**Mengembalikan data:** masuk lagi sebagai Admin, buka Pengguna, lalu klik tiga titik pada baris Siti Rahmawati dan pilih **Aktifkan**.

### 3.4 Uji 1.4: Batas percobaan masuk

**Tujuan:** memastikan percobaan menebak kata sandi dibatasi.

1. Buka halaman masuk.
2. Isi email `admin@spk.test` dengan kata sandi yang salah, lalu klik **Masuk**. Ulangi sebanyak enam kali berturut-turut dalam waktu singkat.

**Hasil yang diharapkan:** lima percobaan pertama menampilkan pesan kredensial salah seperti biasa. Pada percobaan keenam muncul pemberitahuan "Terlalu banyak permintaan. Silakan coba lagi dalam sekian detik." Selama jeda itu, kata sandi yang benar pun tidak diterima. Setelah sekitar satu menit, masuk bisa dicoba lagi.

### 3.5 Uji 1.5: Profil dan ganti kata sandi

**Tujuan:** memastikan pengguna bisa mengganti nama dan kata sandinya sendiri.
**Masuk sebagai:** Admin.

1. Klik lingkaran nama Anda di pojok kanan atas, lalu pilih **Profil**.

![Halaman profil](06-profil.png)

2. Perhatikan bahwa kolom **Email** berwarna abu-abu dan tidak bisa diubah. Email adalah identitas untuk masuk sehingga tidak boleh diganti sendiri.
3. Ubah kolom **Nama** menjadi "Admin Sistem Uji", lalu klik **Simpan**.
4. Pada kolom **Kata sandi baru**, ketik `sandibaru123`. Kolom **Konfirmasi kata sandi** dan **Kata sandi saat ini** akan muncul.
5. Isi konfirmasi dengan `sandibaru123` dan kata sandi saat ini dengan `password`, lalu klik **Simpan**.
6. Keluar, lalu coba masuk dengan kata sandi lama `password`.

**Hasil yang diharapkan:** nama berubah dan muncul pemberitahuan tersimpan. Masuk dengan `password` ditolak, sedangkan `sandibaru123` diterima.

Kata sandi baru yang kurang dari 8 karakter ditolak dengan pesan merah di bawah kolomnya. Bila kata sandi saat ini salah, perubahan juga ditolak.

**Mengembalikan data:** ganti kata sandi kembali menjadi `password` dengan cara yang sama, atau buat ulang data demo seperti di Bagian 2.2.

### 3.6 Uji 1.6: Mengelola pengguna

**Tujuan:** memastikan Admin bisa menambah, mengubah, menonaktifkan pengguna, dan mengatur ulang kata sandi, dengan aturan pengaman yang berlaku.
**Masuk sebagai:** Admin.

Buka menu **Pengaturan**, lalu **Pengguna**. Halaman ini menampilkan nama, email, peran, status aktif, dan waktu terakhir masuk setiap pengguna. Aksi untuk setiap baris (ubah, nonaktifkan, atur ulang kata sandi) ada di menu tiga titik di paling kiri baris.

![Daftar pengguna](04-pengguna-daftar.png)

**Menambah pengguna**

1. Klik **Tambah pengguna** di pojok kanan atas.

![Formulir tambah pengguna](05-pengguna-tambah.png)

2. Isi Nama `Dewi Lestari`, Email `dewi@spk.test`, pilih Peran **Manajer Produksi**, dan Kata sandi awal `dewi12345`.
3. Biarkan **Akun aktif** menyala, lalu klik **Buat**.
4. Keluar, lalu masuk dengan `dewi@spk.test` dan `dewi12345`.

**Hasil yang diharapkan:** Dewi muncul di daftar dengan peran Manajer Produksi dan bisa langsung masuk. Email yang sudah terdaftar atau kata sandi kurang dari 8 karakter ditolak dengan pesan merah.

**Mencari dan menyaring**

1. Ketik `Dewi` di kotak cari di atas tabel. Hanya baris Dewi yang tersisa.
2. Hapus pencarian, lalu klik ikon corong (filter). Pilih Peran **Supervisor Produksi**, lalu **Terapkan filter**.

**Hasil yang diharapkan:** daftar hanya berisi pengguna yang cocok.

**Mengubah dan menonaktifkan**

1. Pada baris Dewi, klik tiga titik, pilih **Ubah**, ganti Peran menjadi **Supervisor Produksi**, lalu simpan. Perannya berubah di daftar.
2. Klik tiga titik pada baris Dewi, pilih **Nonaktifkan**, lalu konfirmasi. Kolom Aktif berubah menjadi tanda silang. Pengguna tidak dihapus, hanya tidak bisa masuk.
3. Klik tiga titik lagi dan pilih **Aktifkan** untuk mengembalikannya.
4. Klik tiga titik dan pilih **Atur ulang kata sandi**, isi kata sandi baru dua kali, lalu simpan. Dewi kini masuk dengan kata sandi baru itu.

**Aturan pengaman**

1. Pada baris Admin Sistem (akun Anda sendiri), klik tiga titik, pilih **Nonaktifkan**, lalu konfirmasi. **Hasil yang diharapkan:** muncul pemberitahuan merah "Anda tidak dapat menonaktifkan akun sendiri." dan akun tetap aktif.
2. Klik tiga titik pada akun Admin Sistem, pilih **Ubah**, ganti Peran menjadi Manajer Produksi, lalu simpan. **Hasil yang diharapkan:** ditolak dengan pesan bahwa Admin terakhir tidak dapat diubah perannya. Dengan demikian aplikasi tidak pernah kehilangan Admin.

Tidak ada tombol hapus pengguna. Pengguna hanya dinonaktifkan supaya jejak data lama tetap utuh.

### 3.7 Uji 1.7: Menu menurut peran

**Tujuan:** memastikan setiap peran hanya melihat menu yang menjadi haknya.

1. Masuk sebagai Admin. Menu **Pengaturan > Pengguna** tampil.

2. Keluar, lalu masuk sebagai `manajer@spk.test`. Menu Pengguna tidak tampil.
3. Dalam keadaan masuk sebagai Manajer, ketik langsung `http://localhost:8001/admin/users` di peramban.

![Dasbor Supervisor tanpa menu Pengguna](16-dasbor-supervisor.png)

**Hasil yang diharapkan:** untuk Manajer, Supervisor, dan Pimpinan, menu Pengguna tidak ada, dan membuka alamatnya langsung menampilkan halaman "403" (tidak diizinkan). Hanya Admin yang bisa membukanya.

## 4. Fase 2: Pelanggan, Produk, dan Order

Bab ini membuktikan bahwa data pelanggan, produk, dan order bisa dicatat, dicari, dan dipantau, dengan hak akses yang berbeda untuk tiap peran.

### 4.1 Uji 2.1: Data pelanggan

**Tujuan:** mencatat pelanggan beserta tingkat urgensinya.
**Masuk sebagai:** Manajer atau Admin.

1. Buka menu **Data Master**, lalu **Pelanggan**. Terlihat lima pelanggan demo.

![Daftar pelanggan](07-pelanggan-daftar.png)

2. Perhatikan kolom **Urgensi**. Angka 5 sampai 1 menunjukkan seberapa mendesak pelanggan itu biasanya, dari "Sangat mendesak" sampai "Sangat rendah". Nilai ini kelak menjadi nilai awal salah satu kriteria perhitungan prioritas.
3. Perhatikan kolom **Order aktif**, yaitu jumlah order milik pelanggan itu yang masih berstatus Antri atau Dikerjakan.
4. Klik **Tambah pelanggan**.

![Formulir tambah pelanggan](15-pelanggan-tambah.png)

5. Isi Kode `PLG-006`, Nama `PT Contoh Baru`, Tingkat urgensi **4 - Mendesak**, lalu klik **Buat**.

**Hasil yang diharapkan:** pelanggan baru muncul di daftar dengan Order aktif bernilai 0. Memakai kode `PLG-001` yang sudah ada ditolak dengan pesan merah. Pelanggan boleh disimpan tanpa kontak.

6. Klik tiga titik pada pelanggan baru, pilih **Nonaktifkan**, lalu konfirmasi.

**Hasil yang diharapkan:** kolom Aktif berubah menjadi tanda silang. Tombol Hapus tidak ada, karena pelanggan yang sudah punya riwayat tidak boleh dihapus. Klik tiga titik lagi dan pilih **Aktifkan** untuk mengembalikan.

### 4.2 Uji 2.2: Data produk

**Tujuan:** menyeragamkan penulisan produk di order.
**Masuk sebagai:** Manajer atau Admin.

1. Buka **Data Master**, lalu **Produk**. Terlihat empat produk demo lengkap dengan kategori dan harga satuan.

![Daftar produk](08-produk-daftar.png)

2. Klik **Tambah produk**. Isi Kode `PRD-005`, Nama `Sepatu Uji`, dan Harga satuan `150000`, lalu **Buat**. Harga dan waktu pengerjaan boleh dikosongkan.
3. Coba lagi dengan harga `-5`.

**Hasil yang diharapkan:** produk baru muncul dengan harga ditampilkan dalam Rupiah. Harga negatif dan kode kembar ditolak. Produk juga bisa dinonaktifkan seperti pelanggan.

### 4.3 Uji 2.3: Membaca daftar order

**Tujuan:** memahami cara membaca daftar order dengan sekali lihat.
**Masuk sebagai:** Manajer (`manajer@spk.test`).

1. Buka menu **Produksi**, lalu **Order Produksi**. Terlihat 25 order, 10 per halaman.

![Daftar order](09-order-daftar.png)

2. Baca kolomnya dari kiri ke kanan.
   - **Nomor** dibuat otomatis dengan pola ORD, tahun, dan urutan.
   - **Pelanggan**, **Produk**, dan **Jml** (jumlah pasang) adalah isi order.
   - **Tenggat** adalah tanggal batas penyelesaian.
   - **Sisa hari** memberi tahu keadaan tenggat dalam kata. Merah bertuliskan "Terlambat 6 hari" berarti tenggat sudah lewat. Kuning seperti "1 hari lagi" berarti tenggat dalam tiga hari ke depan. Abu-abu berarti masih longgar. Tanda "-" muncul pada order yang sudah Selesai atau Dibatalkan karena tenggat tidak lagi relevan.
   - **Status** adalah Antri, Dikerjakan, Selesai, atau Dibatalkan.
   - **Kunci** menampilkan ikon gembok pada order yang dilindungi dari disalip order baru. Arahkan kursor ke gembok untuk melihat alasannya.
3. Klik ikon kolom (tiga batang vertikal) di sebelah ikon corong. Centang **Masuk**, **Menunggu**, dan **Prioritas** untuk menampilkan kolom tambahan. Kolom itu disembunyikan secara bawaan supaya tabel tidak terlalu lebar.

**Hasil yang diharapkan:** setiap order terbaca keadaannya tanpa membuka detail. Order yang terlambat langsung terlihat dari warna merah.

Pada layar yang sempit, tabel bisa digeser ke samping. Anda juga bisa mempersempit menu kiri dengan panah di sebelah nama aplikasi.

### 4.4 Uji 2.4: Mencari dan menyaring order

**Tujuan:** menemukan order tertentu dengan cepat.
**Masuk sebagai:** Manajer.

1. Ketik `Langkah` di kotak cari di atas tabel. Hanya order milik PT Langkah Mandiri yang tersisa. Cari juga memahami nomor order, misalnya `0005`.
2. Hapus pencarian, lalu klik ikon corong. Panel filter terbuka.

![Panel filter](09a-order-panel-filter.png)

3. Nyalakan **Terlambat**, lalu klik **Terapkan filter**.

![Order terlambat](10-order-terlambat.png)

**Hasil yang diharapkan:** hanya tiga order yang tampil, semuanya berstatus Antri dengan tenggat lewat. Order Selesai atau Dibatalkan yang tenggatnya lewat tidak ikut, karena tidak lagi dianggap terlambat.

4. Klik **Atur ulang filter**. Nyalakan **Mendekati tenggat (3 hari)**, terapkan. **Hasil yang diharapkan:** hanya order yang tenggatnya hari ini sampai tiga hari ke depan.
5. Atur ulang, nyalakan **Terkunci**, terapkan.

![Order terkunci](11-order-terkunci.png)

**Hasil yang diharapkan:** tujuh order bergembok tampil, yaitu enam yang sedang Dikerjakan dan satu yang dikunci manual oleh Pimpinan.

6. Atur ulang, lalu pada **Status** pilih Selesai. Hanya tiga order Selesai yang tampil.

### 4.5 Uji 2.5: Membuat order baru

**Tujuan:** mencatat order dengan data yang benar dan terlindung dari salah isi.
**Masuk sebagai:** Manajer.

1. Di halaman Order Produksi, klik **Tambah order**.

![Formulir order baru](12-order-tambah.png)

2. Biarkan **Nomor order** kosong. Di bawah kolomnya tertulis bahwa nomor akan dibuat otomatis.
3. Pilih **Pelanggan** `PT Langkah Mandiri` dan **Produk** `Sepatu Pantofel Pria`.
4. Perhatikan kolom **Harga satuan**. Begitu produk dipilih, harga terisi otomatis dari data produk. Harga ini boleh diubah khusus untuk order ini.
5. Isi **Jumlah** `100`. Setelah Anda keluar dari kolom itu, **Nilai order** di bagian bawah berubah menjadi hasil kali jumlah dan harga.
6. **Tanggal masuk** sudah terisi hari ini. Pilih **Tenggat** dua minggu ke depan lewat kalender.
7. Klik **Buat**.

**Hasil yang diharapkan:** Anda dibawa ke halaman detail order baru. Nomornya adalah nomor berikutnya dalam urutan, misalnya ORD-2026-0026, dan statusnya Antri.

**Uji penolakan.** Buka lagi formulir **Tambah order**, lalu coba hal berikut satu per satu.

- Pilih Tenggat yang lebih awal dari Tanggal masuk, lalu klik **Buat**. Ditolak dengan pesan merah di bawah kolom Tenggat.
- Isi Jumlah `0`. Ditolak karena jumlah harus lebih dari nol.
- Biarkan Pelanggan atau Produk kosong. Ditolak dengan tulisan wajib diisi.
- Isi Nomor order dengan nomor yang sudah ada, misalnya `ORD-2026-0001`. Ditolak karena nomor harus unik.

Tenggat yang sama dengan tanggal masuk diperbolehkan.

### 4.6 Uji 2.6: Detail order dan riwayat perubahan

**Tujuan:** melihat seluruh data satu order dan jejak perubahannya.
**Masuk sebagai:** peran mana pun.

1. Di daftar order, klik baris mana saja, misalnya order ORD-2026-0001. Seluruh baris bisa diklik.

![Halaman detail order](14-order-detail.png)

2. Di bagian **Data order** terlihat nomor, status, prioritas, pelanggan, produk, jumlah, tanggal, sisa hari, harga satuan, nilai order, lama menunggu, dan catatan.
3. Di bagian **Penguncian** terlihat apakah order terkunci, alasannya, siapa yang mengunci, dan sampai kapan.
   Di bawahnya ada bagian **Penilaian** berisi nilai tiap kriteria untuk order itu. Bagian ini dijelaskan di Bab 6.
4. Di bagian **Riwayat perubahan** terlihat waktu, pelaku, dan kegiatan. Order demo mencatat "Order dibuat" oleh Sistem. Di sebelahnya ada tab **Riwayat nilai** untuk perubahan nilai penilaian.
5. Bila Anda masuk sebagai Supervisor, baris **Harga satuan** dan **Nilai order** tidak tampil. Nilai order dalam rupiah memang tidak boleh dilihat peran itu.

**Hasil yang diharapkan:** semua isian terisi sesuai data order. Uji berikutnya (4.7) menambah baris baru di riwayat ini.

### 4.7 Uji 2.7: Mengubah order dan jejaknya

**Tujuan:** memastikan perubahan tercatat siapa, kapan, dan apa yang berubah.
**Masuk sebagai:** Manajer.

1. Buka detail order milik Anda yang baru dibuat di Uji 2.5, lalu klik **Ubah**.
2. Ubah **Jumlah** dari `100` menjadi `250`, lalu klik **Simpan**.
3. Kembali ke halaman detail dan lihat bagian Riwayat perubahan.

**Hasil yang diharapkan:** muncul baris baru "Order diubah" atas nama Budi Santoso dengan perubahan "Jumlah: 100 menjadi 250". Mengubah status, tenggat, atau harga juga tercatat dengan cara yang sama.

### 4.8 Uji 2.8: Mengubah status order

**Tujuan:** memastikan status bisa diperbarui dengan cara yang cepat dan terkendali.
**Masuk sebagai:** Supervisor (`supervisor@spk.test`).

1. Buka **Order Produksi**. Perhatikan bahwa tombol **Tambah order** tidak ada.
2. Pada satu baris, klik tiga titik di paling kiri.

![Menu aksi baris](13a-order-menu-aksi.png)

3. Pilih **Ubah status**. Supervisor hanya memiliki pilihan ini karena perannya memperbarui progres, bukan mengubah isi order.

![Jendela ubah status](13-order-ubah-status.png)

4. Pilih status baru, misalnya **Dikerjakan**, lalu klik **Simpan status**.

**Hasil yang diharapkan:** muncul pemberitahuan "Status order diubah menjadi Dikerjakan" dan badge di daftar ikut berubah. Perubahan itu tercatat di Riwayat perubahan order tersebut.

Supervisor juga tidak bisa membuka halaman ubah order. Mengetik alamat `/admin/production-orders/1/edit` langsung menghasilkan halaman 403.

**Mengembalikan data:** ubah kembali statusnya, atau buat ulang data demo seperti di Bagian 2.2.

### 4.9 Uji 2.9: Pelanggan dan produk nonaktif

**Tujuan:** memastikan data yang sudah tidak dipakai tidak mengganggu order baru, tetapi order lama tidak rusak.
**Masuk sebagai:** Manajer.

1. Buka **Pelanggan**, lalu nonaktifkan `Koperasi Seragam Sekolah Mekar` (konfirmasi).
2. Buka **Order Produksi**, lalu **Tambah order**, dan buka pilihan **Pelanggan**.

**Hasil yang diharapkan:** Koperasi Seragam Sekolah Mekar tidak ada di pilihan.

3. Kembali ke daftar order dan buka detail order milik koperasi itu, misalnya ORD-2026-0004, lalu klik **Ubah**.

**Hasil yang diharapkan:** pelanggan di formulir tetap tampil dengan tambahan "(nonaktif)". Order lama tetap utuh dan bisa dibaca.

**Mengembalikan data:** aktifkan kembali pelanggan itu. Aturan yang sama berlaku untuk produk.

### 4.10 Uji 2.10: Hak akses tiap peran

**Tujuan:** memastikan batas wewenang setiap peran sesuai rancangan.

Masuk bergantian dengan keempat akun, buka **Order Produksi**, lalu periksa.

- **Manajer:** ada tombol **Tambah order**, dan menu tiga titik berisi **Ubah** dan **Ubah status**.
- **Supervisor:** tidak ada tombol Tambah order, dan menu tiga titik hanya berisi **Ubah status**.
- **Admin dan Pimpinan:** tidak ada tombol Tambah order dan tidak ada menu tiga titik. Mereka hanya bisa melihat daftar dan detail.

![Daftar order untuk Pimpinan](18-order-pimpinan.png)

Pada **Pelanggan** dan **Produk**, Admin dan Manajer bisa menambah dan mengubah, sedangkan Supervisor dan Pimpinan hanya melihat. Kolom **Harga satuan** di daftar Produk tidak tampil untuk Supervisor.

**Hasil yang diharapkan:** tombol yang tidak berhak tidak tampil sama sekali, dan mengetik alamat halamannya langsung menghasilkan 403.

### 4.11 Isi data demo

Data demo sengaja dirancang agar semua kasus penting ada dari awal.

- 5 pelanggan dengan tingkat urgensi berbeda dan 4 produk dengan harga berbeda.
- 3 order Selesai dan 1 Dibatalkan, sebagai riwayat.
- 6 order Dikerjakan, semuanya bergembok otomatis.
- 3 order Antri yang sudah terlambat, dengan keterlambatan 1, 3, dan 6 hari.
- 2 order Antri yang tenggatnya dalam tiga hari.
- 3 order Antri yang sudah menunggu 9 sampai 12 hari tetapi tenggatnya masih jauh. Ini kasus order lama yang menjadi bahan uji perlindungan order lama di fase berikutnya.
- 1 order Antri yang dikunci manual oleh Pimpinan dengan alasan "Permintaan langsung dari pimpinan", berlaku tujuh hari.

## 5. Fase 3: Kriteria, Bobot, dan Skala Nilai

Bab ini membuktikan bahwa dasar penilaian bisa diatur dari halaman, tanpa mengubah program. Ada tiga hal yang diatur. **Kriteria** adalah hal-hal yang dinilai pada setiap order, misalnya sisa hari menuju tenggat. **Bobot** adalah seberapa besar pengaruh tiap kriteria. **Skala nilai** adalah aturan yang mengubah angka asli (misalnya 3 hari) menjadi nilai 1 sampai 5, dengan 5 berarti paling perlu didahulukan.

Pada fase ini kriteria dan skala baru bisa diatur dan disimpan. Nilai order per kriteria diisi di Fase 4, dan perhitungan SAW dijalankan di Fase 6.

### 5.1 Uji 3.1: Melihat kriteria dan bobot awal

**Tujuan:** mengenali tujuh kriteria awal dan bobotnya.
**Masuk sebagai:** Manajer (`manajer@spk.test`).

1. Buka menu **Prioritas**, lalu **Kriteria dan Bobot**.

![Daftar kriteria dan bobot](20-kriteria-daftar.png)

2. Baca bagian atas halaman: "Total bobot 1,0000 (100%). Siap dihitung. Bobot sedang diatur langsung." Kalimat ini selalu menunjukkan apakah kriteria sudah siap dipakai menghitung.
3. Baca tabel dari kiri ke kanan.
   - **Kode** dan **Nama** mengenali kriteria, misalnya C1 Sisa waktu menuju tenggat.
   - **Satuan** adalah satuan nilai aslinya (hari, persen, jam, rupiah, tingkat).
   - **Arah nilai** menjelaskan mana yang didahulukan. Sisa hari "makin kecil didahulukan" karena order yang tenggatnya makin dekat makin mendesak, sedangkan urgensi pelanggan "makin besar didahulukan".
   - **Sumber nilai** menyebut apakah nilainya otomatis dari data order atau manual.
   - **Kepentingan** adalah tingkat penting dari 1 sampai 5.
   - **Bobot** ditulis dalam desimal dan persen, misalnya 0,2700 (27%).

**Hasil yang diharapkan:** ada tujuh kriteria. Bobotnya C1 0,27, C2 0,18, C3 0,135, C4 0,135, C5 0,09, C6 0,09, dan C7 0,10, dan totalnya tepat 100%.

### 5.2 Uji 3.2: Mengatur bobot langsung

**Tujuan:** memastikan bobot bisa diisi sendiri dengan syarat totalnya 100%.
**Masuk sebagai:** Manajer.

1. Di halaman Kriteria dan Bobot, klik **Atur bobot langsung**.

![Jendela atur bobot langsung](21-kriteria-atur-bobot.png)

2. Terlihat satu kolom persen untuk setiap kriteria aktif, dan baris **Total** di bagian bawah yang ikut berubah saat Anda mengetik.
3. Ubah C1 dari `27` menjadi `30` dan C7 dari `10` menjadi `7`. Totalnya tetap 100%. Klik **Simpan bobot**.

**Hasil yang diharapkan:** muncul pemberitahuan "Bobot disimpan". Di tabel, C1 menjadi 0,3000 (30%) dan C7 menjadi 0,0700 (7%).

4. Klik **Atur bobot langsung** lagi. Ubah C1 menjadi `35` tanpa mengubah yang lain, sehingga total menjadi 105%. Perhatikan baris Total menulis bahwa totalnya harus 100%. Klik **Simpan bobot**.

**Hasil yang diharapkan:** muncul pemberitahuan merah "Total bobot belum 100%" dan bobot di tabel tidak berubah.

**Mengembalikan data:** ubah lagi C1 menjadi `27` dan C7 menjadi `10`, atau buat ulang data demo seperti di Bagian 2.2.

### 5.3 Uji 3.3: Bobot dari tingkat kepentingan

**Tujuan:** memastikan bobot bisa dihitung otomatis dari tingkat kepentingan.
**Masuk sebagai:** Manajer.

1. Klik **Hitung dari tingkat kepentingan**. Tombol ini hanya muncul selama bobot diatur langsung. Klik **Konfirmasi**.

**Hasil yang diharapkan:** muncul pemberitahuan, dan bagian atas halaman berganti menjadi "Bobot dihitung dari tingkat kepentingan." Bobot sekarang mengikuti rumus: tingkat kepentingan kriteria dibagi jumlah seluruh tingkat kepentingan. Dengan tingkat 5, 4, 3, 3, 2, 2, 3 (jumlah 22), C1 menjadi 0,2272, C2 0,1818, C3 dan C4 0,1364, C5 dan C6 0,0909, dan C7 0,1364. Totalnya tetap tepat 1,0000. Selisih pembulatan 0,0001 dibebankan ke bobot terbesar, yaitu C1.

2. Klik tiga titik pada baris C5, pilih **Ubah**, ganti **Tingkat kepentingan** menjadi **5 - Sangat penting**, lalu simpan.

**Hasil yang diharapkan:** semua bobot dihitung ulang, bukan hanya C5. Jumlah tingkat kepentingan menjadi 25, sehingga C1 0,2000, C2 0,1600, C3 dan C4 0,1200, C5 0,2000, C6 0,0800, dan C7 0,1200. Totalnya tetap 100%.

Selama bobot mengikuti tingkat kepentingan, menambah kriteria atau menonaktifkannya juga menghitung ulang bobot. Bila Anda memakai **Atur bobot langsung**, perubahan tingkat kepentingan tidak menyentuh bobot yang sudah Anda isi.

**Mengembalikan data:** buat ulang data demo seperti di Bagian 2.2.

### 5.4 Uji 3.4: Menambah kriteria

**Tujuan:** memastikan kriteria baru bisa ditambahkan dengan isian yang lengkap.
**Masuk sebagai:** Manajer.

1. Klik **Tambah kriteria**.

![Formulir tambah kriteria](22-kriteria-tambah.png)

2. Isi Kode `C8`, Nama `Kerumitan model`, Satuan `tingkat`, Arah nilai asli **Makin besar didahulukan**, Sumber nilai **Manual**, dan Tingkat kepentingan **3 - Cukup penting**. Biarkan **Kriteria aktif** menyala, lalu klik **Buat**.

**Hasil yang diharapkan:** C8 muncul di daftar. Kode yang sudah ada, nama kosong, atau arah yang belum dipilih ditolak dengan pesan merah. Bila bobot sedang diatur langsung, bagian atas halaman memperingatkan bahwa total bobot belum 100%, karena C8 baru bobotnya 0. Atur ulang bobot dengan **Atur bobot langsung** atau **Hitung dari tingkat kepentingan**. Setelah bobotnya pas 100%, status berganti menjadi "Belum siap dihitung: skala nilai C8 belum lengkap atau belum benar", karena kriteria baru belum punya rentang skala. Buka **Skala Nilai**, pilih C8, isi lima rentangnya, lalu simpan. Setelah itu status kembali menjadi "Siap dihitung". Status ini memang baru benar-benar siap bila bobot pas 100% dan setiap kriteria aktif punya skala yang lengkap.

### 5.5 Uji 3.5: Menonaktifkan kriteria dan batas minimal dua

**Tujuan:** memastikan perhitungan tidak pernah kehilangan kriteria sampai kurang dari dua.
**Masuk sebagai:** Manajer.

1. Klik tiga titik pada baris C8, pilih **Nonaktifkan**, lalu konfirmasi. Kolom Aktif berubah menjadi tanda silang dan C8 tidak ikut dihitung. Kriteria tidak pernah dihapus.
2. Pilih **Aktifkan** untuk mengembalikan.
3. Untuk menguji batas, nonaktifkan kriteria satu per satu sampai tersisa dua yang aktif, lalu coba nonaktifkan salah satunya lagi.

**Hasil yang diharapkan:** ditolak dengan pesan "Minimal dua kriteria harus tetap aktif." Perhitungan SAW memang butuh sedikitnya dua kriteria.

**Mengembalikan data:** aktifkan kembali semua kriteria, atau buat ulang data demo seperti di Bagian 2.2.

### 5.6 Uji 3.6: Hak akses kriteria dan bobot

**Tujuan:** memastikan hanya yang berwenang bisa mengubah dasar penilaian.

1. Masuk sebagai **Manajer** atau **Pimpinan**. Terlihat tombol Tambah kriteria, Atur bobot langsung, dan menu tiga titik.
2. Masuk sebagai **Admin**. Daftar bisa dilihat, tetapi tidak ada tombol tambah, tidak ada Atur bobot langsung, dan tidak ada menu tiga titik.

![Kriteria dan bobot untuk Admin](26-kriteria-admin.png)

3. Masuk sebagai **Supervisor**. Menu Prioritas tidak tampil, dan membuka `http://localhost:8001/admin/criteria` langsung menghasilkan halaman 403.

**Hasil yang diharapkan:** semua sesuai di atas.

### 5.7 Uji 3.7: Melihat skala nilai

**Tujuan:** memahami cara angka asli diubah menjadi nilai 1 sampai 5.
**Masuk sebagai:** Manajer.

1. Buka menu **Prioritas**, lalu **Skala Nilai**.

![Halaman skala nilai](23-skala-nilai.png)

2. Di kolom **Kriteria**, pilih satu kriteria. Pertama kali terpilih C1 Sisa waktu menuju tenggat.
3. Baca tulisan **Arah nilai** di bawahnya: "Makin kecil didahulukan (hari). Skala 5 harus berada di rentang paling kecil."
4. Baca lima kartu skala dari Skala 5 sampai Skala 1. Setiap kartu punya keterangan, batas bawah, dan batas atas. Batas bawah termasuk dalam rentang, batas atas tidak. Contoh C1: Skala 5 dari kosong sampai 2, artinya kurang dari 2 hari. Skala 4 dari 2 sampai 4, artinya 2 hari sampai kurang dari 4 hari, dan seterusnya sampai Skala 1 dari 15 ke atas.
5. Pilih kriteria lain, misalnya C7 Lama menunggu. Skalanya berganti, dan arahnya menjadi "makin besar didahulukan".

**Hasil yang diharapkan:** setiap kriteria punya lima rentang yang lengkap. Batas kosong berarti tanpa batas, sehingga nilai sekecil atau sebesar apa pun tetap punya skala.

### 5.8 Uji 3.8: Menguji rentang sebelum disimpan

**Tujuan:** memastikan sebuah nilai contoh masuk ke skala yang benar.
**Masuk sebagai:** Manajer, pada kriteria C1.

1. Gulir ke bagian **Uji rentang**.
2. Ketik `0` di kolom **Nilai asli contoh**. Di sebelahnya muncul "Nilai 0 menjadi skala 5."
3. Ketik `2`. Hasilnya skala 4, karena batas bawah termasuk dalam rentang: 2 masuk rentang 2 sampai 4, bukan rentang di bawahnya.
4. Ketik `20`. Hasilnya skala 1.

![Uji rentang](24-skala-uji.png)

**Hasil yang diharapkan:** 0 menjadi skala 5, 2 menjadi skala 4, dan 20 menjadi skala 1. Uji ini memakai isian yang sedang tampil di layar, jadi bisa dipakai untuk mencoba rentang baru sebelum menyimpannya.

### 5.9 Uji 3.9: Mengubah dan menyimpan skala

**Tujuan:** memastikan rentang bisa diubah selama tetap benar.
**Masuk sebagai:** Manajer, pada kriteria C1.

1. Ubah rentang menjadi: Skala 5 sampai kurang dari 3, Skala 4 dari 3 sampai 5, Skala 3 dari 5 sampai 10, Skala 2 dari 10 sampai 20, dan Skala 1 dari 20 ke atas. Perhatikan bahwa batas atas satu skala harus sama dengan batas bawah skala di sebelahnya.
2. Ketik nilai contoh `4` dan lihat hasilnya menjadi skala 4.
3. Klik **Simpan skala**.

**Hasil yang diharapkan:** muncul pemberitahuan "Skala C1 disimpan". Pindah ke kriteria lain lalu kembali ke C1, dan rentang baru tetap ada.

**Mengembalikan data:** buat ulang data demo seperti di Bagian 2.2.

### 5.10 Uji 3.10: Rentang yang salah ditolak

**Tujuan:** memastikan rentang yang keliru tidak bisa tersimpan.
**Masuk sebagai:** Manajer, pada kriteria C1.

Coba satu per satu, lalu klik **Simpan skala**. Pesan muncul sebagai pemberitahuan merah di pojok kanan atas, dan data yang tersimpan tidak berubah.

- **Tumpang tindih.** Ubah batas atas Skala 5 menjadi `3`, sementara Skala 4 mulai dari 2. Pesan: "Rentang skala 5 dan skala 4 tumpang tindih."

![Pesan rentang tumpang tindih](25-skala-error.png)

- **Celah.** Ubah batas bawah Skala 4 menjadi `3` sementara batas atas Skala 5 tetap 2. Pesan menyebut nilai 2 sampai kurang dari 3 tidak punya skala.
- **Batas bawah tidak lebih kecil dari batas atas.** Ubah batas atas Skala 4 menjadi `2`. Pesan: batas bawah harus lebih kecil dari batas atas.
- **Rentang tidak terbuka di ujung.** Isi batas bawah pada rentang paling kecil, atau batas atas pada rentang paling besar. Pesan meminta batas itu dikosongkan, supaya nilai sekecil atau sebesar apa pun tetap punya skala.
- **Melawan arah nilai.** Tukar isi rentang Skala 5 dan Skala 1: beri Skala 5 rentang 15 ke atas dan Skala 1 rentang kurang dari 2. Pesan: arah "makin kecil didahulukan" mengharuskan Skala 5 di rentang paling kecil.

**Hasil yang diharapkan:** setiap kesalahan di atas menghasilkan pesan yang jelas, dan skala yang tersimpan tetap utuh.

### 5.11 Uji 3.11: Hak akses skala nilai

**Tujuan:** memastikan batas wewenang pada skala nilai.

1. Masuk sebagai **Manajer**. Tombol **Simpan skala** ada dan kolom bisa diubah.
2. Masuk sebagai **Admin** atau **Pimpinan**. Skala bisa dilihat dan diuji dengan nilai contoh, tetapi tombol Simpan skala tidak ada dan isian tidak bisa diubah.
3. Masuk sebagai **Supervisor**. Menu Skala Nilai tidak tampil, dan membuka `http://localhost:8001/admin/skala-nilai` langsung menghasilkan halaman 403.

**Hasil yang diharapkan:** sesuai di atas.

## 6. Fase 4: Penilaian Order

Bab ini membuktikan bahwa setiap order aktif punya nilai untuk semua kriteria, dan bahwa nilai itu bisa diisi, ditimpa, dan dilacak. Ada dua jenis nilai. **Nilai otomatis** diambil dari data order sehingga selalu terbaru: sisa hari menuju tenggat (C1), urgensi pelanggan (C2), nilai order (C6), dan lama menunggu (C7). **Nilai manual** diisi orang: ketersediaan bahan (C3), sisa waktu pengerjaan (C4), dan progres (C5). Nilai order (C6) menjadi manual hanya untuk order yang belum punya harga satuan.

Setiap nilai asli diubah menjadi skala 1 sampai 5 memakai aturan dari Bab 5. Nilai manual yang belum diisi dihitung sebagai skala 1 (terburuk) dan diberi tanda. Perhitungan SAW sendiri baru dibuat di Fase 6.

### 6.1 Uji 4.1: Membaca matriks penilaian

**Tujuan:** memahami cara membaca halaman penilaian.
**Masuk sebagai:** Manajer (`manajer@spk.test`).

1. Buka menu **Produksi**, lalu **Penilaian Order**.

![Matriks penilaian order](30-penilaian-matriks.png)

2. Baris adalah order yang masih berjalan (berstatus Antri atau Dikerjakan), jumlahnya 21 pada data demo. Order Selesai dan Dibatalkan tidak dinilai sehingga tidak tampil.
3. Kolom **C1** sampai **C7** adalah kriteria. Arahkan kursor ke judul kolom untuk melihat nama lengkapnya. Setiap sel memuat tiga hal: nilai asli di baris atas, lalu di bawahnya skala hasil konversi dan sumber nilainya, misalnya "Skala 4, manual".
4. Perhatikan tanda khusus pada sel.
   - Tulisan merah dengan lingkaran seru, "Belum diisi", berarti nilai manual belum ada. Perhitungan akan memakai skala 1.
   - Segitiga kuning berarti nilai manual sudah lama dan perlu diperbarui.
5. Kolom **Kelengkapan** merangkum satu order: **Lengkap**, **Perlu diperbarui**, atau **Belum lengkap**. Bila ada nilai yang belum diisi, statusnya Belum lengkap, walaupun ada nilai lain yang sudah lama.
6. Ikon panah di kiri setiap baris membuka halaman detail order.

**Hasil yang diharapkan:** 21 order tampil dengan nilai di semua sel, beberapa berkelengkapan Belum lengkap atau Perlu diperbarui. Halaman tidak kosong.

Tabel ini lebar. Persempit menu kiri dengan panah di sebelah nama aplikasi bila kolom di kanan terpotong.

### 6.2 Uji 4.2: Nilai otomatis selalu terbaru

**Tujuan:** memastikan nilai otomatis mengikuti data order tanpa ada yang mengetiknya.
**Masuk sebagai:** Manajer.

1. Di halaman **Penilaian Order**, cari order **ORD-2026-0014** lewat kotak cari. Catat nilai C1 (sisa hari menuju tenggat), kira-kira 1 hari dengan skala 5.
2. Buka **Order Produksi**, klik tiga titik pada ORD-2026-0014, pilih **Ubah**, ganti **Tenggat** menjadi 10 hari dari hari ini, lalu simpan.
3. Kembali ke **Penilaian Order**.

**Hasil yang diharapkan:** C1 untuk ORD-2026-0014 berubah menjadi "10 hari" dengan skala 2, tanpa Anda mengisi apa pun di halaman penilaian.

4. Cari **ORD-2026-0017**. Order ini masuk 10 hari sebelum data demo dibuat.

**Hasil yang diharapkan:** C7 menunjukkan "10 hari" dengan skala 5, dan sumbernya otomatis. Jumlah harinya ikut bertambah setiap hari, jadi esok hari menjadi 11 hari.

5. Perhatikan juga C2. Nilainya sama dengan urgensi pelanggan di menu Pelanggan. Ubah urgensi pelanggan itu, dan nilai C2 order yang belum ditimpa ikut berubah. C6 sama dengan jumlah dikali harga satuan order.

**Mengembalikan data:** ubah tenggat ORD-2026-0014 ke semula, atau buat ulang data demo seperti di Bagian 2.2.

### 6.3 Uji 4.3: Mengisi nilai manual

**Tujuan:** mengubah satu nilai manual dan memastikan skalanya ikut berubah.
**Masuk sebagai:** Manajer.

1. Di **Penilaian Order**, klik nilai **16 jam** pada kolom C4 untuk ORD-2026-0005. Hanya sel yang bisa diubah yang bisa diklik.

![Jendela ubah nilai](32-penilaian-ubah-nilai.png)

2. Jendela "Nilai C4 untuk ORD-2026-0005" terbuka. Di bawah kolom tertulis kapan nilai itu terakhir diperbarui dan oleh siapa. Satuan (jam) ada di ujung kolom.
3. Ganti nilai menjadi `6`, lalu klik **Simpan nilai**.

**Hasil yang diharapkan:** muncul pemberitahuan "Nilai C4 disimpan". Sel C4 menjadi "6 jam" dengan skala 5, karena kurang dari 8 jam termasuk skala 5.

4. Buka detail ORD-2026-0005 (ikon panah di kiri baris) dan lihat tab **Riwayat nilai**.

**Hasil yang diharapkan:** ada baris baru "C4 Sisa waktu pengerjaan, nilai lama 16 jam, nilai baru 6 jam" dengan nama Anda dan waktunya.

**Mengembalikan data:** isi kembali `16`, atau buat ulang data demo.

### 6.4 Uji 4.4: Batas nilai dan mengosongkan nilai

**Tujuan:** memastikan nilai yang tidak masuk akal ditolak.
**Masuk sebagai:** Manajer.

1. Klik sel **C3** (ketersediaan bahan, dalam persen), isi `120`, lalu **Simpan nilai**. **Hasil yang diharapkan:** ditolak dengan pesan merah, karena persentase harus antara 0 dan 100.
2. Klik sel **C4**, isi `-2`, lalu simpan. **Hasil yang diharapkan:** ditolak karena jam tidak boleh negatif.
3. Isi `79,5` pada C3 lalu simpan. **Hasil yang diharapkan:** diterima, dan skalanya 4 karena 75 sampai kurang dari 100 termasuk skala 4.
4. Klik sel C4 suatu order, hapus isinya sampai kosong, lalu simpan.

**Hasil yang diharapkan:** sel menjadi "Belum diisi" berwarna merah dengan "Skala 1, belum diisi", dan kolom Kelengkapan order itu berubah menjadi **Belum lengkap**. Perubahan ini juga tercatat di Riwayat nilai sebagai nilai baru "kosong".

**Mengembalikan data:** isi kembali nilainya, atau buat ulang data demo.

### 6.5 Uji 4.5: Menimpa urgensi pelanggan untuk satu order

**Tujuan:** memastikan urgensi satu order bisa berbeda dari pelanggannya, dengan alasan tertulis.
**Masuk sebagai:** Manajer.

1. Klik sel **C2** untuk ORD-2026-0014. Pelanggannya, PT Langkah Mandiri, berurgensi 5.
2. Nyalakan **Timpa urgensi pelanggan untuk order ini**.

![Jendela timpa urgensi](33-penilaian-timpa-urgensi.png)

3. Pilih **Urgensi untuk order ini** `3 - Biasa`, tetapi biarkan **Alasan menimpa** kosong, lalu klik **Simpan nilai**. **Hasil yang diharapkan:** ditolak, karena alasan wajib diisi.
4. Isi alasan `Pesanan contoh, tidak terburu-buru`, lalu simpan.

**Hasil yang diharapkan:** sel C2 menjadi "3" dengan keterangan "Skala 3, ditimpa". Urgensi PT Langkah Mandiri di menu Pelanggan tetap 5, dan order lain milik pelanggan itu tidak berubah.

5. Klik sel C2 itu lagi, matikan **Timpa urgensi pelanggan untuk order ini**, lalu simpan.

**Hasil yang diharapkan:** C2 kembali menjadi "5" dengan keterangan "Skala 5, otomatis". Kedua perubahan tercatat di Riwayat nilai.

Sel C2 hanya bisa ditimpa oleh Manajer.

### 6.6 Uji 4.6: Kelengkapan dan filter

**Tujuan:** menemukan order yang nilainya belum beres.
**Masuk sebagai:** Manajer.

1. Klik ikon corong di atas tabel, nyalakan **Belum lengkap**, lalu **Terapkan filter**.

![Order yang belum lengkap](31-penilaian-belum-lengkap.png)

**Hasil yang diharapkan:** pada hari data demo dibuat tampil empat order, yaitu ORD-2026-0013 (C4 kosong), ORD-2026-0018 dan ORD-2026-0025 (belum pernah dinilai), dan ORD-2026-0023 (C4 kosong).

2. Atur ulang filter, nyalakan **Perlu diperbarui**, lalu terapkan.

**Hasil yang diharapkan:** empat order lain tampil, yaitu ORD-2026-0007, 0010, 0015, dan 0020. Nilai manualnya diperbarui lima hari yang lalu, lebih lama dari batas tiga hari.

Angka empat dan empat ini berlaku pada hari data demo dibuat. Nilai manual yang diperbarui hari ini atau kemarin akan menjadi "perlu diperbarui" setelah lewat tiga hari, jadi pada hari-hari berikutnya jumlahnya bertambah. Untuk mengulang dari awal, buat ulang data demo seperti di Bagian 2.2.

### 6.7 Uji 4.7: Batas nilai kedaluwarsa

**Tujuan:** memastikan nilai manual yang lama ditandai dan tanda itu hilang setelah nilainya dikonfirmasi.
**Masuk sebagai:** Manajer.

1. Cari **ORD-2026-0007**. Nilai C3 (90%) dan C4 (24 jam) bertanda segitiga kuning dengan keterangan "manual, perlu diperbarui". Kelengkapannya Perlu diperbarui. Nilai itu diperbarui lima hari yang lalu, sedangkan batasnya tiga hari.
2. Klik sel C3, jangan ubah angkanya, lalu klik **Simpan nilai**.

**Hasil yang diharapkan:** segitiga pada C3 hilang, karena nilai dianggap baru dikonfirmasi hari ini. Tidak ada baris baru di Riwayat nilai karena angkanya tidak berubah. C4 masih bertanda segitiga sampai Anda mengonfirmasinya juga.

Batas tiga hari ini nanti bisa diubah dari halaman pengaturan (Fase 9). Hanya nilai manual yang bisa kedaluwarsa. Nilai otomatis dan urgensi yang ditimpa tidak.

### 6.8 Uji 4.8: Penilaian di halaman detail order

**Tujuan:** melihat seluruh nilai satu order di satu tempat.
**Masuk sebagai:** peran mana pun yang membuka Order Produksi.

1. Buka **Order Produksi**, lalu klik order **ORD-2026-0018** (belum pernah dinilai).

![Bagian penilaian pada detail order](34-order-penilaian.png)

2. Di bagian **Penilaian** terlihat kelengkapan **Belum lengkap** dan tujuh baris kriteria. C3, C4, dan C5 bertuliskan "Belum diisi" dengan skala 1. C1, C2, C6, dan C7 terisi otomatis.
3. Buka order **ORD-2026-0005**, lalu gulir ke bawah dan lihat tab **Riwayat nilai**.

![Riwayat nilai pada detail order](35-order-riwayat-nilai.png)

**Hasil yang diharapkan:** riwayat berisi perubahan nilai manual dengan nilai lama, nilai baru, siapa, dan kapan. Misalnya C5 diubah dari 50% menjadi 70% oleh Siti Rahmawati. Tab **Riwayat perubahan** di sebelahnya berisi perubahan data order seperti di Uji 2.7.

### 6.9 Uji 4.9: Hak akses penilaian

**Tujuan:** memastikan tiap peran hanya melihat dan mengubah yang menjadi haknya.

1. Masuk sebagai **Supervisor** (`supervisor@spk.test`) dan buka **Penilaian Order**.

![Penilaian Order untuk Supervisor](36-penilaian-supervisor.png)

   - Kolom **C6** (nilai order dalam rupiah) tidak ada, karena Supervisor tidak boleh melihat nilai rupiah.
   - Hanya sel **C4** dan **C5** yang bisa diklik. C3 dan C2 tidak.
2. Buka detail sebuah order sebagai Supervisor. Baris **Harga satuan** dan **Nilai order**, serta baris kriteria C6, tidak tampil. Di menu **Produk**, kolom Harga satuan juga tidak tampil.
3. Masuk sebagai **Admin** atau **Pimpinan**. Menu Penilaian Order tidak tampil, dan membuka `http://localhost:8001/admin/penilaian-order` langsung menghasilkan halaman 403. Mereka tetap melihat nilai penilaian di halaman detail order, tetapi tidak bisa mengubahnya.

**Hasil yang diharapkan:** sesuai di atas. Manajer bisa mengubah semua nilai manual dan menimpa urgensi, Supervisor hanya C4 dan C5.

### 6.10 Isi data demo penilaian

- 21 order berjalan memiliki nilai manual C3, C4, dan C5, atau dibiarkan kosong untuk beberapa.
- 4 order berkelengkapan **Belum lengkap**: dua dengan satu nilai kosong, dua belum pernah dinilai sama sekali.
- 4 order berkelengkapan **Perlu diperbarui**, nilainya lima hari yang lalu.
- 13 order **Lengkap**.
- 2 urgensi yang ditimpa, lengkap dengan alasan: ORD-2026-0017 menjadi 5 dan ORD-2026-0024 menjadi 1.
- Satu nilai yang pernah diubah (C5 pada ORD-2026-0005, dari 50% ke 70%) supaya Riwayat nilai ada isinya.
- Nilai otomatis (C1, C6, C7) tidak disimpan, karena dihitung dari data order setiap kali halaman dibuka.

## 7. Fase 5: Impor dan Ekspor Excel

Bab ini membuktikan bahwa order bisa dipindahkan dari Excel tanpa mengetik ulang, dan bahwa daftar order bisa dibawa keluar ke Excel. Fitur ini hanya untuk **Admin** dan **Manajer**.

Alurnya empat langkah: unduh templat, isi di Excel, unggah dan periksa, lalu impor. Pemeriksaan dilakukan sebelum ada yang disimpan, jadi Anda bisa melihat dulu baris mana yang bermasalah. Baris yang bermasalah dilewati dan baris yang benar tetap diimpor. Semua baris yang benar disimpan sekaligus: bila ada kegagalan di tengah jalan, tidak ada satu pun yang tersimpan.

### 7.1 Aturan pengisian

Satu berkas memuat paling banyak 1.000 baris order dan berukuran paling besar 5 MB. Data diisi di lembar **Order**. Jangan mengubah judul kolom di baris pertama.

- **Kolom wajib:** Kode pelanggan, Kode produk, Jumlah, Tanggal masuk, Tenggat.
- **Kolom boleh kosong:** Nomor order, Harga satuan, Status, Bahan tersedia (%), Sisa waktu pengerjaan (jam), Progres (%).
- **Nomor order** kosong berarti nomor dibuat otomatis. Nomor yang sudah ada dipakai untuk memperbarui order bila Anda memilih mode perbarui.
- **Kode pelanggan dan kode produk** harus sama dengan kode di menu Data Master. Pelanggan atau produk yang nonaktif tidak bisa dipakai untuk order baru.
- **Jumlah** berupa bilangan bulat lebih dari 0.
- **Tanggal** berupa tanggal Excel atau tulisan hari/bulan/tahun, misalnya 25/12/2026. Tenggat tidak boleh sebelum tanggal masuk.
- **Harga satuan** kosong berarti memakai harga dari produk. Angka boleh ditulis 150000, 150.000, atau Rp 150.000.
- **Status** berisi Antri, Dikerjakan, Selesai, atau Dibatalkan. Kosong berarti Antri.
- **Bahan tersedia dan Progres** berupa persen 0 sampai 100. **Sisa waktu pengerjaan** berupa jam dan tidak boleh negatif. Ketiganya mengisi nilai penilaian C3, C4, dan C5, dengan batas yang sama seperti di halaman Penilaian Order. Angka desimal boleh memakai koma, misalnya 79,5.
- Saat **memperbarui** order yang sudah ada, kolom wajib selalu menimpa data lama. Kolom yang boleh kosong dibiarkan seperti semula bila Anda mengosongkannya.

### 7.2 Uji 5.1: Mengunduh templat

**Tujuan:** mendapatkan berkas Excel dengan susunan kolom yang benar.
**Masuk sebagai:** Manajer (`manajer@spk.test`) atau Admin.

1. Buka menu **Produksi**, lalu **Order Produksi**. Di pojok kanan atas ada tombol **Impor Excel** dan **Ekspor Excel**.

![Tombol impor dan ekspor di daftar order](40-order-impor-ekspor.png)

2. Klik **Impor Excel**. Halaman **Impor Order** terbuka. Halaman yang sama juga ada di menu **Produksi** sebagai **Impor Order**.

![Halaman Impor Order](41-impor-halaman.png)

3. Klik **Unduh templat** di pojok kanan atas.
4. Buka berkas `templat-order.xlsx` di Excel.

**Hasil yang diharapkan:** berkas punya dua lembar. Lembar **Order** berisi 11 judul kolom dan dua baris contoh. Lembar **Petunjuk** menjelaskan cara mengisi, sama dengan Bagian 7.1. Kode pelanggan dan produk pada baris contoh diambil dari data yang ada.

### 7.3 Uji 5.2: Menyiapkan berkas uji

**Tujuan:** punya berkas berisi 20 order benar dan 2 baris rusak untuk dicoba.

Untuk menghemat waktu, buat berkas uji dengan perintah berikut di terminal folder `dss`.

```
php artisan orders:sample-import contoh-20-order.xlsx
```

**Hasil yang diharapkan:** muncul tulisan "Berkas contoh dibuat". Berkas berisi 20 order yang benar memakai kode pelanggan PLG-001 sampai PLG-005 dan kode produk PRD-001 sampai PRD-004 dari data demo. Dua baris terakhir sengaja rusak: baris 22 memakai kode pelanggan yang tidak ada, dan baris 23 memiliki tenggat sebelum tanggal masuk serta bahan tersedia 130%. Perintah ini hanya membuat berkas dan tidak menyentuh data di aplikasi.

Anda juga bisa mengisi templat sendiri. Pastikan kode pelanggan dan produknya ada di menu Data Master.

### 7.4 Uji 5.3: Memeriksa berkas sebelum diimpor

**Tujuan:** melihat apa yang akan terjadi tanpa menyimpan apa pun.
**Masuk sebagai:** Manajer.

1. Di halaman **Impor Order**, klik kotak **Berkas Excel** lalu pilih `contoh-20-order.xlsx`, atau seret berkasnya ke kotak itu. Tunggu sampai tertulis pengunggahan selesai.
2. Pada **Cara mengimpor**, biarkan **Tambah order baru saja**.
3. Klik **Periksa berkas**.

![Hasil pemeriksaan berkas](42-impor-pratinjau.png)

**Hasil yang diharapkan:** muncul pemberitahuan "2 baris bermasalah" dan bagian **Hasil pemeriksaan** dengan empat angka: **Akan ditambah 20**, **Akan diperbarui 0**, **Dilewati 0**, dan **Bermasalah 2**.

Di bawahnya ada daftar **Baris yang tidak akan diimpor**:
- Baris 22, Ditolak: Kode pelanggan "PLG-XYZ" tidak dikenal.
- Baris 23, Ditolak: Tenggat tidak boleh sebelum tanggal masuk, dan Bahan tersedia: Persentase harus antara 0 dan 100.

Nomor baris sama dengan nomor baris di Excel, jadi mudah dicari. Di bawah daftar itu terlihat 10 contoh baris yang akan diimpor, dengan keterangan "dan 10 baris lainnya" untuk sisanya. Pada tahap ini belum ada order yang tersimpan: buka **Order Produksi** dan jumlahnya masih 25.

### 7.5 Uji 5.4: Mengimpor

**Tujuan:** menyimpan baris yang benar, dengan nilai penilaian ikut terisi.
**Masuk sebagai:** Manajer.

1. Setelah pemeriksaan di Uji 5.3, tombol hijau **Impor sekarang** muncul di sebelah Periksa berkas. Klik tombol itu.

![Konfirmasi impor](43-impor-konfirmasi.png)

2. Jendela konfirmasi menyebut jumlahnya: "Akan ditambah 20 order dan diperbarui 0 order." Klik **Ya, impor**.

![Hasil impor](44-impor-hasil.png)

**Hasil yang diharapkan:** muncul pemberitahuan "Impor selesai, Ditambah 20, diperbarui 0". Bagian **Hasil impor** menampilkan **Ditambah 20** dan **Ditolak 2**, dan daftar dua baris yang ditolak tetap terlihat supaya bisa diperbaiki di Excel lalu diunggah lagi.

3. Buka **Order Produksi**. Sekarang ada 45 order. Order baru bernomor berurutan (ORD-2026-0026 dan seterusnya) dan berstatus Antri, kecuali setiap baris keempat yang berstatus Dikerjakan.
4. Buka **Penilaian Order**.

**Hasil yang diharapkan:** order baru langsung punya nilai di C3, C4, dan C5 sesuai kolom Excel (misalnya order pertama: bahan 42%, sisa waktu 13 jam, progres 3%). Di detail order, tab **Riwayat nilai** mencatat nama Anda sebagai pengisinya.

Order yang diimpor sebagai Dikerjakan belum mendapat gembok otomatis. Gembok otomatis dikerjakan di Fase 7, sama seperti saat status diubah dengan tangan.

**Mengembalikan data:** buat ulang data demo seperti di Bagian 2.2.

### 7.6 Uji 5.5: Memperbarui order lewat Excel

**Tujuan:** mengubah order yang sudah ada tanpa menambah order baru.
**Masuk sebagai:** Manajer.

1. Di **Order Produksi**, nyalakan filter **Terlambat** (ikon corong, lalu Terapkan filter). Tiga order tampil.
2. Klik **Ekspor Excel**. Berkas `order-produksi-...xlsx` terunduh dan memuat tiga order itu.
3. Buka berkas di Excel, ubah **Jumlah (pasang)** pada order ORD-2026-0011 menjadi `999`, lalu simpan.
4. Di halaman **Impor Order**, unggah berkas itu. Pilih **Tambah baru dan perbarui order yang nomornya sudah ada**, lalu **Periksa berkas**.

**Hasil yang diharapkan:** **Akan diperbarui 3**, **Akan ditambah 0**. Ketiga order dihitung diperbarui walau hanya satu yang angkanya berubah, karena nomornya sudah ada.

5. Klik **Impor sekarang**, lalu **Ya, impor**.

**Hasil yang diharapkan:** "Diperbarui 3". Di detail ORD-2026-0011, Jumlah menjadi 999 dan tab **Riwayat perubahan** mencatat "Jumlah: ... menjadi 999" atas nama Anda. Order lain tidak berubah isinya.

6. Ulangi dari langkah 4 dengan berkas yang sama, tetapi pilih **Tambah order baru saja**.

**Hasil yang diharapkan:** **Dilewati 3** dengan alasan "Nomor ORD-2026-0011 sudah ada, dilewati karena mode tambah saja". Tidak ada yang berubah.

**Mengembalikan data:** ubah lagi jumlah ORD-2026-0011, atau buat ulang data demo.

### 7.7 Uji 5.6: Mengekspor daftar order

**Tujuan:** membawa daftar order ke Excel persis seperti yang tampil di layar.
**Masuk sebagai:** Manajer atau Admin.

1. Di **Order Produksi**, klik **Ekspor Excel** tanpa filter. Berkas berisi seluruh order.
2. Nyalakan filter **Terkunci**, lalu **Ekspor Excel** lagi. Berkas hanya berisi order bergembok.
3. Ketik sebuah nama pelanggan di kotak cari, lalu ekspor. Berkas hanya berisi order pelanggan itu.

**Hasil yang diharapkan:** isi berkas selalu sama dengan tabel di layar setelah filter dan pencarian. Sebelas kolom pertama sama persis dengan templat (nomor, kode pelanggan, kode produk, jumlah, tanggal, harga, status, bahan, sisa waktu, progres). Setelah itu ada kolom tambahan untuk dibaca: nama pelanggan, nama produk, sisa hari, nilai order, prioritas, dan kunci. Karena kolom pertamanya sama, hasil ekspor bisa diubah di Excel lalu diimpor kembali dengan mode perbarui.

### 7.8 Uji 5.7: Berkas yang bermasalah

**Tujuan:** memastikan berkas yang keliru ditolak dengan pesan yang jelas.
**Masuk sebagai:** Manajer.

Coba satu per satu di halaman **Impor Order**, lalu klik **Periksa berkas**.

- **Bukan berkas Excel.** Ganti nama sebuah berkas teks menjadi `.xlsx` lalu unggah. Pesan: "Berkas tidak bisa dibaca. Pastikan berupa file Excel berformat .xlsx".
- **Kolom wajib hilang.** Hapus kolom Tenggat dari templat. Pesan: "Kolom wajib tidak ditemukan: Tenggat".
- **Lebih dari 1.000 baris.** Pesan menyebut jumlah baris dan batasnya.
- **Nomor kembar di dalam berkas.** Pakai nomor yang sama di dua baris. Baris kedua ditolak dengan menyebut baris pertama yang memakainya.
- **Pelanggan atau produk nonaktif.** Nonaktifkan satu pelanggan di Data Master lalu pakai kodenya. Pesan: "Pelanggan ... sudah nonaktif".
- **Jumlah nol atau pecahan, tanggal "kemarin", status "Setengah jadi".** Masing-masing ditolak dengan pesan yang menyebut isian yang salah.

**Hasil yang diharapkan:** kesalahan pada satu berkas (bukan Excel, kolom hilang, terlalu banyak baris) menolak seluruh berkas. Kesalahan pada satu baris hanya menolak baris itu, dan baris lain tetap bisa diimpor.

### 7.9 Uji 5.8: Hak akses impor dan ekspor

**Tujuan:** memastikan hanya yang berwenang yang bisa memasukkan data massal.

1. Masuk sebagai **Admin** atau **Manajer**. Tombol Impor Excel dan Ekspor Excel tampil di Order Produksi, dan menu **Impor Order** ada.
2. Masuk sebagai **Supervisor** atau **Pimpinan**. Kedua tombol dan menu Impor Order tidak tampil. Membuka `http://localhost:8001/admin/impor-order` langsung menghasilkan halaman 403.

**Hasil yang diharapkan:** sesuai di atas.

## 8. Menjalankan Pengujian Otomatis

Selain diuji dengan tangan seperti di atas, aplikasi punya pengujian otomatis yang menjalankan ratusan pemeriksaan dalam hitungan detik. Pengujian ini memakai basis data terpisah bernama `dss_test`, jadi tidak menyentuh data demo Anda.

### 5.1 Cara menjalankan

1. Pastikan MySQL menyala dan basis data `dss_test` ada.
2. Di terminal folder `dss`, jalankan perintah berikut.

```
php artisan test --compact
```

3. Tunggu sekitar setengah menit.

**Hasil yang diharapkan:** ringkasan di akhir menyatakan semua tes lulus, saat ini 282 tes dengan 1.332 pemeriksaan (tulisan seperti `Tests: 79 passed`). Bila ada yang gagal, ringkasan menyebut nama tes dan penyebabnya.

### 5.2 Apa saja yang diuji

- **Masuk dan profil:** login berhasil dan gagal, akun nonaktif, batas lima percobaan, halaman yang tertutup bagi tamu, ganti nama dan kata sandi.
- **Pengguna:** hak akses Admin, tambah, ubah, nonaktifkan, atur ulang kata sandi, serta aturan Admin terakhir dan larangan menonaktifkan diri sendiri.
- **Pelanggan dan produk:** tambah, ubah, nonaktifkan, kode unik, hak akses per peran, jumlah order aktif.
- **Order:** nomor otomatis berurutan, hitungan sisa hari dan batas terlambat, filter, pencarian, validasi formulir, harga otomatis dari produk, hak akses per peran, ubah status, riwayat perubahan.
- **Kriteria dan bobot:** bobot dari tingkat kepentingan (contoh 5, 4, 3, 3, 2, 2), total selalu tepat 1, bobot diatur langsung, batas minimal dua kriteria aktif, status siap hitung (termasuk skala yang belum lengkap), hak akses per peran, dan riwayat perubahan bobot.
- **Skala nilai:** rentang yang valid dan yang salah (tumpang tindih, celah, terbalik arah, batas tidak terbuka), konversi nilai asli ke skala termasuk batas bawah dan atas, serta contoh pada spesifikasi.
- **Penilaian:** nilai otomatis (sisa hari, urgensi, nilai order, lama menunggu) dan perubahannya, nilai manual dengan batas persen, jam, dan rupiah, urgensi yang ditimpa dengan alasan wajib, batas kedaluwarsa tiga hari, kelengkapan dan filternya, riwayat nilai, hak akses Manajer dan Supervisor, serta nilai rupiah yang disembunyikan dari Supervisor.
- **Impor dan ekspor Excel:** pembacaan berkas dan judul kolom, aturan tiap kolom (kode, jumlah, tanggal, harga, status, persen, jam), dua puluh order benar dan dua baris rusak, mode tambah saja dan tambah serta perbarui, kolom opsional yang kosong tidak menimpa, batas 1.000 baris, berkas bukan Excel, penyimpanan satu transaksi yang batal total bila gagal, ekspor sesuai filter dan pencarian, serta ekspor yang diimpor kembali tanpa mengubah data.
- **Data demo:** jumlah dan sebaran data, kriteria awal dan skalanya, nilai penilaian demo, dan setiap halaman terbuka tanpa kosong untuk keempat peran.

### 5.3 Menjalankan sebagian tes

Untuk memeriksa satu bagian saja, tambahkan `--filter` dengan kata kunci nama tes, misalnya:

```
php artisan test --compact --filter="order"
```

## 9. Jika Ada Masalah

**Halaman tidak bisa dibuka atau muncul "gagal terhubung".** Pastikan perintah `php artisan serve --port=8001` masih berjalan di terminal dan alamatnya `http://localhost:8001/admin`.

**Muncul pesan kesalahan basis data (misalnya "Connection refused").** MySQL belum menyala. Buka XAMPP dan klik Start pada MySQL, lalu muat ulang halaman.

**Data berantakan setelah banyak mencoba.** Buat ulang data demo dengan `php artisan migrate:fresh --seed`. Seluruh data akan kembali ke kondisi awal dan kata sandi kembali menjadi `password`.

**Lupa kata sandi akun demo.** Jalankan `php artisan db:seed --class=DemoUserSeeder`. Perintah ini mengembalikan keempat akun ke kata sandi `password` tanpa menghapus data lain.

**Masuk ditolak terus padahal kata sandi benar.** Tunggu satu menit bila baru gagal beberapa kali, karena ada batas percobaan. Bila pesannya "Akun Anda dinonaktifkan", akun harus diaktifkan oleh Admin.

**Tabel order terpotong di sisi kanan.** Persempit menu kiri dengan panah di sebelah nama aplikasi, atau perkecil zoom peramban dengan Ctrl dan tanda minus.

**Unggahan berkas gagal atau tidak bergerak.** Pastikan berkas berformat .xlsx dan berukuran di bawah 5 MB. Bila memakai Excel versi lama dengan .xls, simpan ulang sebagai .xlsx.

**Semua baris ditolak saat memeriksa berkas.** Baca pesan di bagian Hasil pemeriksaan. Penyebab paling sering: kode pelanggan atau produk belum ada di Data Master, atau judul kolom di baris pertama diubah. Unduh ulang templat dan salin data Anda ke dalamnya.

**Tombol Impor sekarang tidak muncul.** Tombol itu baru tampil setelah Periksa berkas dan hanya bila ada sedikitnya satu baris yang benar.

**Tes otomatis gagal terhubung.** Pastikan basis data `dss_test` sudah dibuat dan MySQL menyala.

## 10. Lampiran: Ringkasan Hak Akses

Ringkasan berikut berlaku untuk fitur yang sudah ada sampai Fase 2.

| Kegiatan | Admin | Manajer | Supervisor | Pimpinan |
|---|---|---|---|---|
| Kelola pengguna | Ya | Tidak | Tidak | Tidak |
| Lihat pelanggan dan produk | Ya | Ya | Ya | Ya |
| Tambah dan ubah pelanggan dan produk | Ya | Ya | Tidak | Tidak |
| Lihat daftar dan detail order | Ya | Ya | Ya | Ya |
| Tambah dan ubah order | Tidak | Ya | Tidak | Tidak |
| Ubah status order | Tidak | Ya | Ya | Tidak |
| Lihat kriteria, bobot, dan skala nilai | Ya | Ya | Tidak | Ya |
| Tambah dan ubah kriteria, atur bobot | Tidak | Ya | Tidak | Ya |
| Ubah skala nilai | Tidak | Ya | Tidak | Tidak |
| Buka halaman Penilaian Order | Tidak | Ya | Ya | Tidak |
| Ubah nilai manual | Tidak | Ya | C4 dan C5 saja | Tidak |
| Timpa urgensi pelanggan pada satu order | Tidak | Ya | Tidak | Tidak |
| Lihat nilai order dalam rupiah | Ya | Ya | Tidak | Ya |
| Impor dan ekspor Excel | Ya | Ya | Tidak | Tidak |
| Ganti nama dan kata sandi sendiri | Ya | Ya | Ya | Ya |
