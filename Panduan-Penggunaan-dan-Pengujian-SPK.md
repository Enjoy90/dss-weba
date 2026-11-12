# Panduan Penggunaan dan Pengujian SPK Prioritas Produksi

| Informasi | Isi |
|---|---|
| Aplikasi | SPK Prioritas Produksi (studi kasus PT Weba International) |
| Versi dokumen | 1.7 |
| Tanggal | 13 November 2026 |
| Cakupan | Fase 0 sampai Fase 9: masuk dan pengguna, pelanggan, produk, order produksi, kriteria, bobot, skala nilai, penilaian order, impor dan ekspor Excel, perhitungan SAW dan evaluasi, hasil evaluasi, persetujuan, penerapan prioritas, kunci order, dasbor, order tertahan, laporan PDF serta Excel, log aktivitas, pengaturan sistem, evaluasi terjadwal, dan notifikasi |
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
4. **Hasil yang diharapkan** adalah apa yang harus terlihat. Bila berbeda, lihat Bab 13.

Kerjakan pengujian berurutan dari atas. Beberapa pengujian mengubah data, misalnya menonaktifkan akun. Di bagian itu ada petunjuk untuk mengembalikannya. Bila data sudah terlanjur berantakan, data demo bisa dibuat ulang seperti di Bagian 2.2.

### 1.3 Akun demo

Semua akun memakai kata sandi `password`.

- `admin@spk.test` untuk peran Admin, staf administrasi yang mengelola pengguna dan data master.
- `manajer@spk.test` untuk Manajer Produksi, yang membuat dan mengubah order.
- `supervisor@spk.test` untuk Supervisor Produksi, yang memantau order dan memperbarui status.
- `pimpinan@spk.test` untuk Pimpinan, yang melihat seluruh data.

### 1.4 Yang belum ada di fase ini

Semua fitur yang direncanakan sudah dibuat sampai Fase 9. Fase berikutnya (Fase 10) berisi pengujian dan validasi dengan data, misalnya membandingkan hasil hitung aplikasi dengan hitungan manual dan menguji batas tunggu 5, 7, dan 10 hari. Fase itu tidak menambah menu baru.

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

**Hasil yang diharapkan:** daftar migrasi berstatus DONE, disusul lima seeder (DemoUserSeeder, DemoMasterSeeder, DemoOrderSeeder, CriteriaSeeder, DemoAssessmentSeeder) yang juga berstatus DONE. Bila ada tulisan merah, periksa Bab 13.

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

**Hasil yang diharapkan:** Anda masuk ke halaman **Dasbor** dan melihat enam kartu angka order. Inisial nama Anda tampil di lingkaran pojok kanan atas.

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

**Hasil yang diharapkan:** halaman Dasbor terbuka dan inisial "BS" (Budi Santoso) tampil di pojok kanan atas. Menu di sebelah kiri berisi Dasbor, Produksi, Prioritas, Data Master, dan Laporan.

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
   - **Pelanggan** dan **Jml** (jumlah pasang) adalah isi order. Kolom **Produk** disembunyikan bawaannya supaya tabel muat di layar laptop 1366 piksel.
   - **Tenggat** adalah tanggal batas penyelesaian.
   - **Sisa hari** memberi tahu keadaan tenggat dalam kata. Merah bertuliskan "Terlambat 6 hari" berarti tenggat sudah lewat. Kuning seperti "1 hari lagi" berarti tenggat dalam tiga hari ke depan. Abu-abu berarti masih longgar. Tanda "-" muncul pada order yang sudah Selesai atau Dibatalkan karena tenggat tidak lagi relevan.
   - **Status** adalah Antri, Dikerjakan, Selesai, atau Dibatalkan. Order yang sudah diberi label Prioritas Utama (Bab 9) menampilkan badge oranye kedua di bawah status.
   - **Kunci** menampilkan ikon gembok pada order yang dilindungi dari disalip order baru. Arahkan kursor ke gembok untuk melihat alasannya.
3. Klik ikon kolom (tiga batang vertikal) di sebelah ikon corong. Centang **Produk**, **Masuk**, **Menunggu**, dan **Prioritas** untuk menampilkan kolom tambahan. Kolom itu disembunyikan secara bawaan supaya tabel tidak terlalu lebar.

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

**Hasil yang diharapkan:** muncul pemberitahuan "Status order diubah menjadi Dikerjakan" dan badge di daftar ikut berubah. Order yang menjadi Dikerjakan langsung bergembok otomatis (Bab 9). Perubahan itu, termasuk gemboknya, tercatat di Riwayat perubahan order tersebut.

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

Order yang diimpor sebagai Dikerjakan langsung mendapat gembok otomatis, sama seperti saat statusnya diubah dengan tangan (Bab 9).

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

## 8. Fase 6: Mesin SAW dan Evaluasi

Bab ini membuktikan bahwa sistem bisa menghitung peringkat prioritas semua order yang masih aktif dengan metode SAW, lalu menyimpan hasilnya sebagai evaluasi. Menu barunya bernama **Jalankan Perhitungan**, ada di grup **Prioritas**, dan hanya untuk **Manajer**.

Halaman evaluasi yang lengkap (daftar evaluasi, tujuh tab rincian, penyesuaian peringkat, persetujuan, dan penerapan prioritas) dijelaskan di Bab 9. Di halaman Jalankan Perhitungan sendiri, hasil perhitungan terakhir tetap tampil sebagai tabel peringkat ringkas di bawah tombol hitung.

### 8.1 Cara sistem menghitung

1. Yang dihitung adalah order berstatus Antri dan Dikerjakan. Nilai tiap kriteria diubah ke skala 1 sampai 5 memakai aturan dari Bab 5. Nilai otomatis (tenggat, urgensi, nilai order, lama menunggu) dihitung ulang tepat saat tombol ditekan.
2. Setiap nilai skala dibagi nilai skala terbesar di kolom kriteria itu. Hasilnya disebut nilai normalisasi.
3. Skor sebuah order adalah jumlah dari bobot kriteria dikali nilai normalisasinya. Skor disimpan 6 desimal dan ditampilkan 4 desimal.
4. Peringkat sistem murni urutan skor, dari tertinggi. Bila skor sama, tenggat yang lebih dekat didahulukan, lalu tanggal masuk yang lebih awal.
5. Peringkat akhir disusun dalam tiga kelompok berurutan. **Terkunci** berisi order yang sedang dikerjakan atau yang dikunci Manajer. **Eskalasi** berisi order Antri yang tidak terkunci dan sudah menunggu lebih dari 7 hari, diurutkan dari yang paling lama menunggu. **Biasa** berisi semua order lain. Di dalam kelompok Terkunci dan Biasa, urutannya menurut skor.
6. Nilai manual yang masih kosong dihitung sebagai skala 1 (terburuk) dan dicatat sebagai peringatan.

Setiap perhitungan disimpan lengkap sebagai satu evaluasi: waktu, pelaksana, salinan kriteria, bobot, skala, dan pengaturan yang dipakai, serta semua nilai tiap order. Evaluasi yang sudah tersimpan tidak bisa diubah atau dihapus, jadi hasil lama tetap bisa diperiksa walau bobot dan skala berubah kemudian. Perubahan yang diizinkan hanya status evaluasi dan peringkat akhir hasil penyesuaian, yang dipakai di Bab 9.

### 8.2 Uji 6.1: Membuka halaman dan membaca kesiapan data

**Tujuan:** melihat apakah data siap dihitung sebelum menekan tombol.
**Masuk sebagai:** Manajer (`manajer@spk.test`).

1. Buka menu **Prioritas**, lalu **Jalankan Perhitungan**.

![Halaman Jalankan Perhitungan](50-hitung-halaman.png)

**Hasil yang diharapkan:** bagian **Kesiapan data** menyatakan data siap dihitung dan menampilkan empat angka: **Order yang dihitung 21**, **Belum lengkap 4**, **Perlu diperbarui 4**, dan **Kriteria aktif 7**. Bagian **Hasil perhitungan terakhir** di bawahnya menampilkan keadaan kosong "Belum ada hasil perhitungan" karena belum pernah ada yang dihitung.

Angka 4 dan 4 berlaku pada hari data demo dibuat, sama seperti di Bab 6.

### 8.3 Uji 6.2: Menjalankan perhitungan

**Tujuan:** menghitung peringkat dan memastikan order yang belum lengkap tidak lolos tanpa sepengetahuan Manajer.
**Masuk sebagai:** Manajer.

1. Klik **Jalankan Perhitungan** di pojok kanan atas.

![Jendela konfirmasi perhitungan](51-hitung-dialog.png)

2. Jendela yang terbuka menyebut jumlah order dan kriteria, lalu mendaftar order yang **Belum lengkap** (beserta kriteria yang kosong) dan yang **Perlu diperbarui**. Nama evaluasi dan catatan boleh dikosongkan. Bila nama kosong, evaluasi diberi nama tanggal dan jamnya.
3. Klik **Hitung sekarang** tanpa mencentang kotak di bagian bawah.

![Perhitungan ditolak tanpa centang](52-hitung-galat-centang.png)

**Hasil yang diharapkan:** jendela tetap terbuka dan muncul tulisan merah "Centang dulu untuk melanjutkan, atau tutup dan lengkapi nilainya lebih dulu." Belum ada evaluasi yang tersimpan.

4. Centang **Saya sudah membaca daftar di atas dan tetap ingin menghitung**, lalu klik **Hitung sekarang**.

![Hasil perhitungan](53-hitung-hasil.png)

**Hasil yang diharapkan:** muncul pemberitahuan "Perhitungan selesai. 21 order dihitung. Peringkat 1: ORD-2026-0005." Tabel hasil terisi 21 baris. Judul tabel menyebut nama evaluasi, waktunya, siapa yang menjalankan, dan statusnya (Draf).

Bila semua order lengkap dan segar, kotak centang tidak muncul dan perhitungan langsung jalan.

### 8.4 Uji 6.3: Membaca tabel hasil

**Tujuan:** memahami arti tiap kolom dan melihat peringkat sistem berdampingan dengan peringkat akhir.
**Masuk sebagai:** Manajer.

Pada tabel hasil dari Uji 6.2:

- **Peringkat** adalah peringkat akhir, sesudah kelompok diterapkan. **Sistem** adalah peringkat murni dari skor.
- **Order** memuat nomor dan nama pelanggan. **Tenggat** dan **Menunggu** (hari sejak order masuk) ikut ditampilkan karena keduanya dipakai aturan peringkat.
- **Skor** tampil dengan 4 desimal.
- **Kelompok** berisi Terkunci, Eskalasi, atau Biasa.
- **Perubahan** membandingkan dengan perhitungan sebelumnya. Pada perhitungan pertama kolom ini berisi tanda hubung karena belum ada pembanding.

**Hasil yang diharapkan:** tujuh baris teratas berkelompok **Terkunci**: enam order Dikerjakan (ORD-2026-0005 sampai 0010) dan ORD-2026-0019 yang dikunci manual. Di bawahnya ada enam baris **Eskalasi**, yaitu order Antri yang menunggu 9 hari atau lebih, dimulai dari yang paling lama (ORD-2026-0013, 14 hari). Sisanya delapan baris **Biasa**. Contoh pengaruh kelompok: ORD-2026-0011 skornya 0,8718, peringkat sistem 2, tetapi peringkat akhir 10 karena berada di kelompok Eskalasi di bawah semua order terkunci.

### 8.5 Uji 6.4: Batas tunggu 7 hari dan 8 hari

**Tujuan:** memastikan order yang menunggu tepat 7 hari belum dieskalasi, dan yang menunggu 8 hari sudah.
**Masuk sebagai:** Manajer, dengan akses terminal di folder `dss`.

Pemilih tanggal di formulir order hanya bisa dipakai lewat kalender, jadi tanggal masuk diubah dengan perintah berikut. Perintah ini mengubah ORD-2026-0021 menjadi order yang masuk 7 hari lalu.

```
php artisan tinker --execute="App\Models\ProductionOrder::where('number','ORD-2026-0021')->update(['order_date' => today()->subDays(7)]);"
```

1. Jalankan perintah di atas, lalu klik **Jalankan Perhitungan**, centang kotak konfirmasi, dan klik **Hitung sekarang**.

**Hasil yang diharapkan:** ORD-2026-0021 tampil dengan **Menunggu 7 hari**, kelompok **Biasa**, peringkat 17, dan **Perubahan Tetap**. Belum dieskalasi karena batasnya lebih dari 7 hari.

2. Jalankan perintah yang sama dengan `subDays(8)`, lalu hitung lagi.

![Order lama dieskalasi pada hari ke-8](54-hitung-eskalasi.png)

**Hasil yang diharapkan:** ORD-2026-0021 tampil dengan **Menunggu 8 hari**, kelompok **Eskalasi**, **Peringkat 14**, tetapi **Sistem 13**. Skornya tetap 0,5500. Order itu naik di atas semua order Biasa walau skornya lebih rendah, dan peringkat sistem tidak ikut berubah, jadi pengaruh eskalasi selalu terlihat dari selisih dua kolom itu. Kolom Perubahan menyebut **Naik 3**, yaitu dari peringkat 17 di perhitungan sebelumnya.

### 8.6 Uji 6.5: Membaca perubahan antar perhitungan

**Tujuan:** melihat order mana yang naik atau turun sejak perhitungan sebelumnya.
**Masuk sebagai:** Manajer.

Pada hasil Uji 6.4 langkah 2, perhatikan kolom **Perubahan** dan badge warnanya.

**Hasil yang diharapkan:** **Naik n** berwarna hijau (order naik n tingkat), **Turun n** berwarna merah, **Tetap** berwarna abu-abu, dan **Baru** berwarna biru untuk order yang belum ada di perhitungan sebelumnya. Dalam contoh ini ORD-2026-0014, 0015, dan 0020 tampil **Turun 1** karena ORD-2026-0021 naik ke kelompok Eskalasi di atas mereka. Pembanding selalu perhitungan terakhir yang statusnya bukan Dibatalkan.

### 8.7 Uji 6.6: Perhitungan ditolak bila data belum siap

**Tujuan:** memastikan perhitungan tidak berjalan dengan bobot atau order yang tidak layak.
**Masuk sebagai:** Manajer.

1. Buka **Kriteria dan Bobot**. Klik tiga titik pada baris C7, pilih **Nonaktifkan**, lalu konfirmasi. Total bobot kini tinggal 90%.
2. Buka **Jalankan Perhitungan**.

![Perhitungan ditolak karena bobot](55-hitung-ditolak.png)

**Hasil yang diharapkan:** bagian Kesiapan data menyatakan "Perhitungan belum bisa dijalankan" dan menulis dengan huruf merah "Belum siap dihitung: total bobot harus tepat 100%." Tombol **Jalankan Perhitungan** berwarna pudar dan tidak bisa ditekan. Hal yang sama terjadi bila skala sebuah kriteria belum lengkap, atau bila tidak ada satu pun order Antri atau Dikerjakan (pesannya mengarahkan ke menu Order Produksi). Peringkat dari perhitungan sebelumnya tetap terlihat.

**Mengembalikan data:** aktifkan kembali C7, atau buat ulang data demo seperti di Bagian 2.2.

### 8.8 Uji 6.7: Hak akses

**Tujuan:** memastikan hanya Manajer yang bisa menjalankan perhitungan.

1. Masuk sebagai **Manajer**. Menu **Jalankan Perhitungan** tampil di grup Prioritas.
2. Masuk sebagai **Admin**, **Supervisor**, atau **Pimpinan**. Menu itu tidak tampil. Membuka `http://localhost:8001/admin/jalankan-perhitungan` langsung menghasilkan halaman 403.

**Hasil yang diharapkan:** sesuai di atas. Ketiga peran lain melihat hasil evaluasi lewat menu Hasil Evaluasi (Bab 9).

**Mengembalikan data:** buat ulang data demo seperti di Bagian 2.2. Evaluasi yang tersimpan ikut terhapus oleh perintah itu.

## 9. Fase 7: Hasil Evaluasi, Persetujuan, dan Perlindungan Order Lama

Bab ini membuktikan bahwa hasil perhitungan bisa dibaca lengkap, disesuaikan, disetujui, lalu diterapkan sebagai label **Prioritas Utama** di daftar order. Bab ini juga membuktikan bahwa order lama terlindungi: order bisa dikunci, order yang sedang dikerjakan terkunci sendiri, dan perjalanan peringkat tiap order bisa dilihat.

Menu barunya bernama **Hasil Evaluasi**, ada di grup **Prioritas**, dan bisa dibuka semua peran. Yang boleh mengubah sesuatu dijelaskan di Uji 7.10.

### 9.1 Alur status evaluasi

Evaluasi baru berstatus **Draf**. Alurnya Draf, Diajukan, Disetujui, lalu Diterapkan. Evaluasi yang belum diterapkan bisa **Dibatalkan**. Isi hasil perhitungan tidak pernah berubah, hanya status dan peringkat akhir hasil penyesuaian.

| Langkah | Siapa | Syarat |
|---|---|---|
| Ajukan untuk disetujui | Manajer | Status Draf, persetujuan Pimpinan diwajibkan |
| Setujui atau Tolak | Pimpinan | Status Diajukan. Menolak wajib memakai catatan |
| Sesuaikan peringkat | Manajer, Pimpinan | Status Draf atau Diajukan. Alasan wajib |
| Terapkan prioritas | Manajer, Pimpinan | Status Disetujui, dan hanya evaluasi terbaru |
| Batalkan evaluasi | Manajer, Pimpinan | Belum diterapkan |

Persetujuan Pimpinan diwajibkan secara bawaan. Bila nanti dimatikan di pengaturan (halaman pengaturannya dibuat di Fase 9), Manajer bisa menerapkan Draf langsung dan tombol Ajukan tidak tampil.

Label Prioritas Utama diberikan kepada sejumlah order **Antri** teratas pada peringkat akhir (bawaan 5). Order yang sedang Dikerjakan tetap berada di atas peringkat tetapi tidak memakai slot, supaya pesanan baru yang mendesak masih punya peluang. Order Antri yang dikunci manual dan yang dieskalasi berada paling atas dan ikut memakai slot.

### 9.2 Uji 7.1: Membuka daftar evaluasi dan menyaringnya

**Tujuan:** menemukan evaluasi yang pernah dijalankan.
**Masuk sebagai:** Manajer (`manajer@spk.test`), dengan data demo yang baru dibuat.

1. Buka menu **Prioritas**, lalu **Hasil Evaluasi**.

![Daftar evaluasi yang masih kosong](60-evaluasi-kosong.png)

**Hasil yang diharapkan:** keadaan kosong "Belum ada evaluasi" dengan tombol menuju Jalankan Perhitungan.

2. Jalankan satu perhitungan seperti di Uji 6.2, lalu buka lagi **Hasil Evaluasi**.

![Daftar evaluasi](61-evaluasi-daftar.png)

**Hasil yang diharapkan:** satu baris berisi nama, waktu, pemicu (Manual), jumlah order (21), status (Draf), dan siapa yang menjalankannya. Kolom **Disetujui oleh** masih berisi tanda hubung.

3. Klik ikon corong. Coba filter **Status**, **Pemicu**, dan **Tanggal**.

**Hasil yang diharapkan:** daftar menyempit sesuai pilihan. Memilih status Dibatalkan menampilkan keadaan kosong karena belum ada yang dibatalkan.

### 9.3 Uji 7.2: Membaca rincian evaluasi

**Tujuan:** memeriksa dari mana setiap skor dan peringkat berasal.
**Masuk sebagai:** Manajer.

1. Klik baris evaluasi di daftar.

![Rincian evaluasi](62-evaluasi-detail.png)

**Hasil yang diharapkan:** bagian **Ringkasan** menampilkan status Draf, waktu, pemicu, pelaksana, jumlah order (21), dan jumlah Prioritas Utama (5). Di bawahnya ada kotak kuning **Slot Prioritas Utama penuh**: dalam data demo ada 7 order Antri yang dikunci manual atau dieskalasi, lebih banyak dari 5 slot, jadi order Antri biasa tidak kebagian label. Tabel di tab pertama memuat kolom **Peringkat** (akhir), **Sistem**, **Order**, **Menunggu**, **Skor**, **Kelompok**, **Prioritas Utama**, **Perubahan**, dan **Disesuaikan**. Kolom **Tenggat** disembunyikan bawaannya dan bisa dinyalakan lewat ikon kolom.

2. Buka tab **Perhitungan skor**.

![Tab Perhitungan skor](63-evaluasi-tab-skor.png)

**Hasil yang diharapkan:** tiap order punya angka bobot dikali nilai normalisasi untuk setiap kriteria, dan di kolom paling kanan skor V yang sama dengan skor di tab pertama.

3. Buka tab **Peringatan**.

![Tab Peringatan](64-evaluasi-tab-peringatan.png)

**Hasil yang diharapkan:** tab ini memberi angka 20 di judulnya. Isinya delapan nilai kosong yang dihitung sebagai skala 1 dan dua belas nilai manual yang sudah lama, ditambah kotak kuning slot penuh.

4. Buka tab **Data nilai asli**, **Matriks keputusan**, **Matriks normalisasi**, dan **Kriteria dan skala**.

**Hasil yang diharapkan:** semua tab berisi data dan tidak ada yang kosong. Tab **Kriteria dan skala** menampilkan bobot, arah, dan rentang skala yang dipakai saat perhitungan, dan isinya tidak ikut berubah bila bobot atau skala diubah sesudahnya.

![Rincian evaluasi di layar laptop 1366 piksel](75-evaluasi-detail-1366.png)

Halaman ini juga muat di layar laptop 1366 piksel, seperti pada gambar di atas.

### 9.4 Uji 7.3: Menyesuaikan peringkat

**Tujuan:** memindahkan satu order ke peringkat lain dengan alasan, tanpa menghapus peringkat sistem.
**Masuk sebagai:** Manajer, pada evaluasi yang masih Draf.

1. Pada baris ORD-2026-0005 (peringkat 1, Terkunci), klik ikon panah atas bawah di sisi kiri. Isi **Peringkat baru** dengan `21` dan alasan apa saja, lalu klik **Simpan peringkat**.

![Penyesuaian order terkunci ditolak](65-evaluasi-sesuaikan-ditolak.png)

**Hasil yang diharapkan:** muncul pemberitahuan "Tidak bisa dilanjutkan: Order terkunci tidak boleh turun ke bawah order biasa. Lepas kuncinya lebih dulu bila memang perlu." Peringkat tidak berubah.

2. Pada baris ORD-2026-0021 (peringkat 16, Biasa), buka ikon yang sama. Isi `12` dan alasan "Pelanggan meminta dikirim lebih awal".

![Form penyesuaian peringkat](66-evaluasi-sesuaikan-form.png)

3. Klik **Simpan peringkat**.

**Hasil yang diharapkan:** ORD-2026-0021 berada di peringkat 12 dengan tanda **Disesuaikan**, sedangkan kolom **Sistem** tetap 16. Arahkan kursor ke tanda itu untuk membaca alasannya. Order yang tadinya di peringkat 12 sampai 15 bergeser satu tingkat ke bawah. Mengosongkan alasan ditolak dengan pesan "wajib diisi", dan peringkat di luar 1 sampai 21 ditolak.

### 9.5 Uji 7.4: Mengajukan, menolak, dan menyetujui

**Tujuan:** memastikan evaluasi hanya bisa diterapkan setelah disetujui Pimpinan.

1. Masuk sebagai **Manajer**. Pada rincian evaluasi, klik **Ajukan untuk disetujui**, lalu **Ya, ajukan**.

**Hasil yang diharapkan:** status berubah menjadi **Diajukan**. Tombol Ajukan hilang dan tombol Terapkan belum ada.

2. Masuk sebagai **Pimpinan** (`pimpinan@spk.test`) dan buka evaluasi yang sama. Tombol **Setujui** dan **Tolak** tampil. Klik **Tolak**, isi catatan "Urutan ORD-2026-0021 belum sesuai, mohon periksa lagi", lalu klik **Tolak evaluasi**.

![Form penolakan](67-evaluasi-tolak-form.png)

![Evaluasi yang ditolak](68-evaluasi-ditolak.png)

**Hasil yang diharapkan:** status kembali **Draf** dan muncul kotak merah "Evaluasi ini dikembalikan oleh Pimpinan" berisi catatan tadi. Menolak tanpa catatan ditolak dengan pesan "wajib".

3. Masuk lagi sebagai **Manajer**, klik **Ajukan untuk disetujui**. Catatan penolakan hilang dari halaman.
4. Masuk sebagai **Pimpinan**, klik **Setujui**, lalu **Ya, setujui**.

**Hasil yang diharapkan:** status **Disetujui**, dan kolom Disetujui oleh di Ringkasan terisi nama Pimpinan beserta waktunya. Tombol **Terapkan prioritas** muncul.

### 9.6 Uji 7.5: Menerapkan prioritas

**Tujuan:** memberi label Prioritas Utama kepada order Antri teratas.
**Masuk sebagai:** Manajer atau Pimpinan, pada evaluasi yang sudah Disetujui.

1. Klik **Terapkan prioritas**.

![Konfirmasi penerapan](69-evaluasi-terapkan-konfirmasi.png)

**Hasil yang diharapkan:** jendela menyebut order yang akan berlabel, yaitu ORD-2026-0019, ORD-2026-0013, ORD-2026-0016, ORD-2026-0011, dan ORD-2026-0017, serta menjelaskan bahwa label dari evaluasi sebelumnya diganti dan order lain kembali Normal. Karena slot penuh, jendela juga memberi peringatan agar jumlah Prioritas Utama atau kunci ditinjau.

2. Klik **Ya, terapkan**.

**Hasil yang diharapkan:** status menjadi **Diterapkan** dan tombol Terapkan hilang.

3. Buka **Produksi**, lalu **Order Produksi**.

![Label Prioritas Utama di daftar order](70-order-prioritas-utama.png)

**Hasil yang diharapkan:** order yang berlabel menampilkan badge oranye **Prioritas Utama** di bawah badge status Antri. Badge itu terbaca penuh di layar 1366 piksel tanpa menggeser tabel. Order yang sedang Dikerjakan tidak diberi label walau berada di atas peringkat. Filter **Prioritas** di daftar menyaring order yang berlabel. Kolom **Produk** kini disembunyikan bawaannya supaya tabel muat, dan bisa dinyalakan lewat ikon kolom.

4. Jalankan perhitungan lagi (Bab 8), lalu coba menerapkan evaluasi yang lama.

**Hasil yang diharapkan:** evaluasi yang lama tidak punya tombol Terapkan lagi, karena hanya evaluasi terbaru yang bisa diterapkan. Menerapkan evaluasi baru menggantikan label yang lama.

### 9.7 Uji 7.6: Mengunci dan melepas kunci order

**Tujuan:** menahan order tertentu tetap di atas order biasa pada perhitungan berikutnya.
**Masuk sebagai:** Manajer atau Pimpinan.

1. Di **Order Produksi**, cari `ORD-2026-0025`. Klik tiga titik di sisi kiri barisnya, lalu **Pasang kunci**.

![Jendela pasang kunci](71-order-pasang-kunci.png)

2. Klik **Pasang kunci** tanpa mengisi alasan.

**Hasil yang diharapkan:** ditolak dengan pesan kolom wajib diisi.

3. Isi alasan "Pelanggan besar, harus selesai sebelum akhir bulan". Tanggal berakhir boleh dikosongkan (kunci tanpa batas). Klik **Pasang kunci**.

**Hasil yang diharapkan:** ikon gembok muncul di baris itu. Di detail order, bagian **Penguncian** memuat jenis kunci manual, alasan, siapa yang mengunci, dan waktunya. Tab **Riwayat perubahan** mencatat satu baris "Order dikunci". Tanggal berakhir yang sudah lewat ditolak. Kunci dengan tanggal berakhir lepas sendiri sehari sesudah tanggal itu.

4. Klik tiga titik lagi, pilih **Lepas kunci**, dan isi alasan.

**Hasil yang diharapkan:** gembok hilang dan Riwayat perubahan mencatat "Kunci dilepas" beserta alasannya. Melepas kunci tanpa alasan ditolak. Order yang sedang dikerjakan tidak bisa dikunci manual karena sudah terkunci otomatis.

### 9.8 Uji 7.7: Kunci otomatis mengikuti status

**Tujuan:** memastikan order yang sedang dikerjakan tidak bisa disalip pesanan baru.
**Masuk sebagai:** Manajer atau Supervisor.

1. Di **Order Produksi**, pilih satu order Antri, klik tiga titik, **Ubah status**, pilih **Dikerjakan**, lalu **Simpan status**.

**Hasil yang diharapkan:** ikon gembok muncul. Arahkan kursor ke gembok: "Kunci otomatis (sedang dikerjakan)". Pada perhitungan berikutnya order itu masuk kelompok Terkunci, di atas order biasa walau skornya lebih rendah.

2. Ubah status order itu kembali ke **Antri**.

**Hasil yang diharapkan:** gembok otomatis hilang. Kunci manual tidak ikut hilang bila order itu sebelumnya dikunci dengan tangan.

3. Ubah status order Dikerjakan lain menjadi **Selesai** atau **Dibatalkan**.

**Hasil yang diharapkan:** gembok hilang, begitu juga label Prioritas Utama bila ada. Order itu keluar dari perhitungan berikutnya.

Order yang diimpor dari Excel dengan status Dikerjakan juga langsung terkunci otomatis.

### 9.9 Uji 7.8: Tren peringkat di halaman order

**Tujuan:** melihat apakah sebuah order naik, turun, atau diam dari perhitungan ke perhitungan.
**Masuk sebagai:** peran apa pun.

1. Kunci ORD-2026-0025 seperti di Uji 7.6, lalu jalankan perhitungan lagi.
2. Buka detail ORD-2026-0025 dan turun ke bagian **Tren peringkat**.

![Tren peringkat order](72-order-tren-peringkat.png)

**Hasil yang diharapkan:** grafik garis dengan satu titik untuk tiap evaluasi. Order ini berada di peringkat 21 pada perhitungan pertama dan peringkat 8 sesudah dikunci. Peringkat 1 digambar di atas, jadi garis yang naik berarti order naik di daftar. Evaluasi yang dibatalkan tidak ikut digambar. Order yang belum pernah dihitung menampilkan keterangan "Belum ada evaluasi yang memuat order ini".

### 9.10 Uji 7.9: Membandingkan dua evaluasi

**Tujuan:** melihat perubahan peringkat semua order antara dua evaluasi.
**Masuk sebagai:** peran apa pun.

1. Di **Hasil Evaluasi**, klik **Bandingkan dua evaluasi**, atau klik **Bandingkan** di rincian sebuah evaluasi.
2. Pilih evaluasi pertama pada **Dari evaluasi (sebelum)** dan yang kedua pada **Ke evaluasi (sesudah)**.

![Perbandingan dua evaluasi](73-evaluasi-bandingkan.png)

**Hasil yang diharapkan:** ringkasan satu kalimat seperti "1 naik, 8 turun, 12 tetap, 0 baru di evaluasi sesudah, 0 tidak ada lagi", disusul tabel tiap order dengan peringkat sebelum, peringkat sesudah, dan perubahannya (Naik, Turun, atau Tetap) beserta kedua skor. Pada contoh ini ORD-2026-0025 naik 13 tingkat karena dikunci. Order yang hanya ada di salah satu evaluasi diberi keterangan "Baru di evaluasi sesudah" atau "Tidak ada di evaluasi sesudah". Memilih dua evaluasi yang sama meminta Anda memilih dua yang berbeda.

### 9.11 Uji 7.10: Hak akses

**Tujuan:** memastikan tiap peran hanya bisa melakukan bagiannya, dan Supervisor tidak melihat nilai rupiah.

1. Masuk sebagai **Admin** atau **Supervisor**. Buka **Hasil Evaluasi** dan sebuah evaluasi.

**Hasil yang diharapkan:** semua tab bisa dibaca, tetapi tidak ada satu pun tombol **Ajukan**, **Setujui**, **Tolak**, **Terapkan**, **Batalkan**, maupun ikon **Sesuaikan peringkat**. Di daftar order, menu tiga titik Supervisor hanya berisi **Ubah status** (tidak ada Pasang kunci).

2. Masuk sebagai **Supervisor**, buka tab **Data nilai asli**.

![Tab Data nilai asli untuk Supervisor](74-evaluasi-supervisor-tab-nilai.png)

**Hasil yang diharapkan:** kriteria Nilai order (C6) tidak tampil di tab mana pun, dan ada catatan "Kriteria nilai order tidak ditampilkan untuk peran Anda". Tidak ada angka rupiah di halaman.

3. Masuk sebagai **Pimpinan**.

**Hasil yang diharapkan:** tombol **Setujui** dan **Tolak** tampil pada evaluasi yang Diajukan, dan Pimpinan juga bisa menyesuaikan peringkat, menerapkan, membatalkan, serta memasang dan melepas kunci order. Tombol **Ajukan** tidak tampil untuk Pimpinan. Tombol **Jalankan Perhitungan** tetap hanya milik Manajer (Bab 8).

**Mengembalikan data:** buat ulang data demo seperti di Bagian 2.2. Evaluasi, label, dan kunci yang dibuat ikut terhapus oleh perintah itu.

## 10. Fase 8: Dasbor dan Laporan

Bab ini membuktikan bahwa dasbor menampilkan kondisi order dengan angka yang sama dengan daftar aslinya, bahwa order yang berulang kali tertinggal dari daftar prioritas ketahuan (order **Tertahan**), dan bahwa hasil perhitungan bisa dicetak atau diunduh sebagai laporan PDF dan Excel dengan angka yang sama dengan layar.

Dasbor adalah halaman pertama setelah masuk. Menu **Laporan** ada di grup **Laporan** dan bisa dibuka semua peran.

### 10.1 Menyiapkan data dan istilah baru

Dasbor paling berguna bila sudah ada riwayat evaluasi. Data demo bawaan sengaja tidak memuat evaluasi, supaya Bab 8 dan Bab 9 bisa dipelajari dari keadaan kosong. Untuk bab ini, isi riwayatnya dengan perintah tambahan berikut (jalankan sesudah `php artisan migrate:fresh --seed`).

```
php artisan db:seed --class=DemoEvaluationSeeder
```

**Hasil yang diharapkan:** seeder berstatus DONE. Isinya lima evaluasi pada lima hari berturut-turut, masing-masing sudah disetujui dan diterapkan. Perintah ini aman diulang hanya setelah data dibuat ulang dengan `migrate:fresh --seed`.

Tiga istilah baru dipakai di bab ini.

| Istilah | Arti |
|---|---|
| Tertahan | Order Antri yang tidak terkunci, berada di luar 5 order Antri teratas pada 5 evaluasi terakhir berturut-turut, dan sudah menunggu sedikitnya 4 hari. Peringatan ini muncul sebelum order mencapai batas tunggu 7 hari |
| Dieskalasi | Order Antri yang menunggu lebih dari 7 hari sehingga naik di atas order biasa pada evaluasi terakhir (Bab 8) |
| Prioritas utama (dasbor) | Order berlabel Prioritas Utama dari evaluasi terakhir yang diterapkan, urut menurut peringkat evaluasi itu |

"5 order Antri teratas" dihitung di antara order Antri saja, sama dengan aturan label Prioritas Utama: order yang sedang Dikerjakan tidak memakai tempat. Evaluasi yang Dibatalkan tidak dihitung, dan selama evaluasi belum mencapai 5 kali tidak ada order yang Tertahan. Angka 5 dan 4 adalah nilai bawaan, halaman pengaturannya dibuat di Fase 9.

### 10.2 Uji 8.1: Membaca dasbor

**Tujuan:** melihat kondisi order dan prioritas dalam satu layar.
**Masuk sebagai:** Manajer (`manajer@spk.test`), dengan data demo dan riwayat evaluasi dari Bagian 10.1.

1. Masuk, atau klik **Dasbor** di menu kiri.

![Dasbor Manajer](80-dasbor-manajer.png)

**Hasil yang diharapkan:** halaman Dasbor berisi, dari atas ke bawah:

- **Enam kartu angka:** Order aktif 21, Terlambat 3, Mendekati tenggat 2, Penilaian belum lengkap 4, Terkunci 7, dan Tertahan 1.
- **Perhitungan terakhir:** status evaluasi terbaru beserta waktu dan jumlah ordernya, tautan **Buka hasil**, dan tombol biru **Jalankan Perhitungan**.
- **Order per status:** grafik batang Antri 15, Dikerjakan 6, Selesai 3, dan Dibatalkan 1. Warna batang sama dengan warna lencana status di daftar order.
- **Prioritas utama:** lima order berlabel dari evaluasi terakhir yang diterapkan, lengkap dengan peringkat, tenggat, skor, dan status.
- **Order terlambat terlama:** tiga order terlambat, yang paling lama lewat tenggat di atas (ORD-2026-0013, terlambat 6 hari).
- **Order tertahan dan dieskalasi:** enam order Antri dari yang paling lama menunggu. Lima bertanda **Dieskalasi** dan satu bertanda **Tertahan** (ORD-2026-0018).

2. Arahkan penunjuk ke tanda **Tertahan** atau **Dieskalasi**.

**Hasil yang diharapkan:** muncul penjelasan singkat. Untuk Tertahan: "Di luar 5 teratas pada 5 evaluasi berturut-turut". Untuk Dieskalasi: "Menunggu lebih dari 7 hari, naik di atas order biasa pada evaluasi terakhir".

Kolom **Peringkat** pada daftar prioritas utama adalah peringkat akhir di evaluasi, jadi angkanya tidak harus 1 sampai 5: tujuh order teratas dalam data demo terkunci, dan label baru jatuh ke order Antri berikutnya (peringkat 7 sampai 11).

### 10.3 Uji 8.2: Kartu membuka daftar yang sama

**Tujuan:** memastikan angka di kartu sama dengan jumlah baris pada daftar yang dibuka.
**Masuk sebagai:** Manajer.

1. Di Dasbor, klik kartu **Terlambat**.

**Hasil yang diharapkan:** daftar Order Produksi terbuka dengan filter **Terlambat** menyala dan menampilkan 3 hasil, sama dengan angka di kartu.

2. Kembali ke Dasbor, lalu klik kartu **Terkunci**, **Mendekati tenggat**, dan **Order aktif** bergantian.

**Hasil yang diharapkan:** daftar menampilkan 7, 2, dan 21 hasil. Kartu Order aktif membuka daftar dengan filter Status Antri dan Dikerjakan.

3. Kembali ke Dasbor, klik kartu **Penilaian belum lengkap**.

![Penilaian Order dengan filter Belum lengkap](81-dasbor-kartu-penilaian.png)

**Hasil yang diharapkan:** halaman **Penilaian Order** terbuka dengan filter **Belum lengkap** menyala dan menampilkan 4 order, sama dengan kartu.

### 10.4 Uji 8.3: Order tertahan

**Tujuan:** melihat order yang berulang kali tertinggal dari daftar prioritas.
**Masuk sebagai:** Manajer.

1. Di Dasbor, klik kartu **Tertahan**.

![Daftar order dengan filter Tertahan](82-order-tertahan.png)

**Hasil yang diharapkan:** daftar Order Produksi dengan filter **Tertahan** menyala menampilkan satu order, ORD-2026-0018 (Koperasi Seragam Sekolah Mekar).

2. Buka panel filter lewat ikon corong, lalu matikan **Tertahan**. Cari ORD-2026-0018 dan buka halamannya.

**Hasil yang diharapkan:** grafik **Tren peringkat** memuat lima titik, satu untuk tiap evaluasi, dan semuanya berada di luar lima peringkat teratas (Bab 9). Kartu Tertahan hanya menghitung order yang memenuhi aturan di Bagian 10.1, sedangkan daftar di Dasbor juga memuat order yang dieskalasi.

3. Beri order itu kunci manual dengan alasan (Uji 7.6), lalu buka Dasbor.

**Hasil yang diharapkan:** kartu Tertahan menjadi 0, karena order terkunci tidak lagi tertahan. Lepas kuncinya untuk mengembalikan keadaan.

4. Ubah status ORD-2026-0018 menjadi **Dikerjakan**, lalu buka Dasbor.

**Hasil yang diharapkan:** order itu hilang dari daftar tertahan dan dieskalasi, karena hanya order Antri yang dihitung.

**Mengembalikan data:** buat ulang data demo dan jalankan lagi seeder riwayat evaluasi (Bagian 10.1).

### 10.5 Uji 8.4: Dasbor menurut peran

**Tujuan:** memastikan tiap peran melihat isi yang sesuai.

1. Masuk sebagai **Manajer**, buka **Jalankan Perhitungan**, hitung, lalu buka hasilnya dan klik **Ajukan untuk disetujui** (Uji 7.4). Keluar.
2. Masuk sebagai **Pimpinan** (`pimpinan@spk.test`).

![Dasbor Pimpinan](86-dasbor-pimpinan.png)

**Hasil yang diharapkan:** di bawah kartu muncul bagian **Menunggu persetujuan Anda** yang memuat evaluasi yang baru diajukan, lengkap dengan waktu pengajuan, jumlah order, dan jumlah Prioritas Utama. Mengklik barisnya membuka rincian evaluasi untuk disetujui. Tombol **Jalankan Perhitungan** tidak ada di Dasbor maupun di menu.

3. Masuk sebagai **Supervisor** (`supervisor@spk.test`) dan **Admin** (`admin@spk.test`).

![Dasbor Supervisor](16-dasbor-supervisor.png)

**Hasil yang diharapkan:** kedua peran melihat kartu, grafik, dan daftar yang sama dengan Manajer, tetapi tanpa tombol **Jalankan Perhitungan** dan tanpa bagian Menunggu persetujuan. Tidak ada satu pun angka rupiah di Dasbor, jadi Supervisor tidak melihat nilai order.

![Dasbor Admin](03-dasbor-admin.png)

4. Masuk sebagai Manajer lagi dan buka Dasbor.

**Hasil yang diharapkan:** bagian **Perhitungan terakhir** menampilkan status **Diajukan**, dan hanya Manajer yang melihat tombol **Jalankan Perhitungan**.

### 10.6 Uji 8.5: Membuka laporan dan membaca pratinjau

**Tujuan:** memilih laporan dan memeriksa isinya sebelum diunduh.
**Masuk sebagai:** Manajer.

1. Buka menu **Laporan** (grup **Laporan**).

![Halaman Laporan](83-laporan-peringkat.png)

**Hasil yang diharapkan:** halaman Laporan memuat kolom **Jenis laporan** yang sudah berisi *Peringkat prioritas (per evaluasi)*, kolom **Evaluasi** berisi evaluasi terbaru, dan bagian **Pratinjau** dengan nama perusahaan, judul laporan, keterangan evaluasi (waktu, pemicu, status, jumlah order, siapa yang menghitung dan menyetujui), tabel peringkat, dan tabel kriteria dan bobot. Tombol **Unduh PDF** dan **Unduh Excel** ada di kanan atas.

2. Buka daftar **Jenis laporan**.

**Hasil yang diharapkan:** ada lima pilihan: Peringkat prioritas, Rincian perhitungan SAW, Order terlambat, Rekap order, dan Riwayat evaluasi. Dua pilihan pertama meminta **Evaluasi**, tiga lainnya meminta periode **Dari tanggal** dan **Sampai tanggal** (awalnya awal bulan ini sampai hari ini).

3. Bila tabel lebih dari 50 baris, lihat catatan di bawah tabel.

**Hasil yang diharapkan:** pratinjau hanya menampilkan 50 baris pertama dan memberi catatan "Menampilkan 50 baris pertama dari ...". Berkas PDF dan Excel selalu memuat semua baris.

### 10.7 Uji 8.6: Mengunduh laporan peringkat

**Tujuan:** memastikan PDF dan Excel memuat angka yang sama dengan layar.
**Masuk sebagai:** Manajer.

1. Pada laporan **Peringkat prioritas**, klik **Unduh PDF**.

**Hasil yang diharapkan:** peramban mengunduh berkas `laporan-peringkat-evaluasi-<nomor>-<tanggal>-<jam>.pdf`. Buka berkas itu.

![PDF laporan peringkat](89-pdf-peringkat.png)

PDF berukuran A4 mendatar. Di bagian atas ada **kop**: nama perusahaan dengan garis di bawahnya, lalu judul laporan, keterangan evaluasi, **Tanggal cetak**, dan **Dicetak oleh**. Tabel berisi Peringkat, Order, Pelanggan, Produk, Tenggat, Kelompok, Skor (empat desimal), Peringkat sistem, dan Alasan penyesuaian. Judul kolom diulang di tiap halaman dan nomor halaman ada di pojok kanan bawah.

2. Gulir ke halaman terakhir.

![Kolom tanda tangan](90-pdf-tanda-tangan.png)

**Hasil yang diharapkan:** di akhir ada dua kolom tanda tangan: **Dibuat oleh** (nama pencetak) dan **Diketahui oleh** (Pimpinan), dengan ruang kosong untuk tanda tangan.

3. Klik **Unduh Excel** dan buka berkas `.xlsx`-nya.

**Hasil yang diharapkan:** ada tiga lembar. **Ringkasan** memuat nama perusahaan, judul, keterangan evaluasi, tanggal cetak, dan nama pencetak. **Peringkat prioritas** memuat tabel yang sama dengan PDF, dan **Kriteria dan bobot** memuat daftar kriteria. Peringkat dan jumlah tertulis sebagai angka, skor sebagai angka dengan format empat desimal, dan tenggat sebagai tanggal sungguhan, jadi bisa diurutkan dan dihitung.

4. Bandingkan skor tiga order teratas di layar, PDF, dan Excel.

**Hasil yang diharapkan:** ORD-2026-0005 bernilai 0,8740, ORD-2026-0009 bernilai 0,8110, dan ORD-2026-0007 bernilai 0,7728 di ketiganya.

### 10.8 Uji 8.7: Rincian perhitungan dan hitung ulang manual

**Tujuan:** membuktikan bahwa skor bisa dihitung ulang dari angka di Excel.
**Masuk sebagai:** Manajer.

1. Pilih **Rincian perhitungan SAW (per evaluasi)**.

![Pratinjau rincian perhitungan](84-laporan-rincian.png)

**Hasil yang diharapkan:** pratinjau memuat enam tabel: Kriteria dan skala, Bobot dan pembagi, Nilai asli, Nilai skala 1 sampai 5, Nilai normalisasi, dan Nilai terbobot dan skor.

2. Klik **Unduh PDF**, lalu buka halaman yang memuat tabel *Nilai terbobot dan skor*.

![Tabel nilai terbobot di PDF](91-pdf-rincian-terbobot.png)

**Hasil yang diharapkan:** PDF terdiri dari lima halaman A4 mendatar. Tabel terakhir memuat satu kolom per kriteria (judulnya berisi bobotnya) dan kolom **Skor V** di paling kanan.

3. Klik **Unduh Excel** dan buka lembar **Bobot dan pembagi**.

**Hasil yang diharapkan:** tujuh baris kriteria berisi bobot dan nilai skala tertinggi: C1 0,27 dan 5, C2 0,18 dan 5, C3 0,135 dan 5, C4 0,135 dan 4, C5 0,09 dan 5, C6 0,09 dan 4, C7 0,10 dan 5. Di bawahnya ada rumusnya: nilai normalisasi adalah nilai skala dibagi nilai skala tertinggi kolomnya, dan Skor V adalah jumlah bobot kali nilai normalisasi.

4. Hitung ulang order peringkat 1 (ORD-2026-0005) dengan tangan memakai lembar **Nilai skala 1 sampai 5**.

**Hasil yang diharapkan:** C1 bernilai skala 3, jadi normalisasinya 3 dibagi 5 sama dengan 0,6 dan nilai terbobotnya 0,27 kali 0,6 sama dengan 0,162. Bila keenam kriteria lain dijumlahkan dengan cara yang sama, hasilnya 0,8740, sama dengan kolom **Skor V** di lembar **Nilai terbobot dan skor** dan sama dengan skor di layar.

### 10.9 Uji 8.8: Laporan periode

**Tujuan:** memastikan filter periode bekerja untuk laporan order dan riwayat.
**Masuk sebagai:** Manajer.

1. Pilih **Order terlambat (per periode)**. Periodenya awal bulan ini sampai hari ini.

**Hasil yang diharapkan:** tabel memuat tiga order terlambat (ORD-2026-0013 terlambat 6 hari, ORD-2026-0011 terlambat 3 hari, ORD-2026-0012 terlambat 1 hari). Di atasnya tertulis **Jumlah order terlambat** 3 dan **Rata-rata keterlambatan** 3,3 hari. Periode laporan ini mengacu pada tenggat, dan hanya order yang belum selesai dan sudah lewat tenggat pada hari cetak yang masuk.

2. Klik **Unduh PDF**.

![PDF laporan order terlambat](92-pdf-terlambat.png)

**Hasil yang diharapkan:** satu halaman dengan kop, ketiga order, dan kolom tanda tangan.

3. Pilih **Rekap order (per periode)**.

![Rekap order](87-laporan-rekap.png)

**Hasil yang diharapkan:** dua tabel: **Rekap per status** (Antri 11 order dan 3.505 pasang, Dikerjakan 4 order dan 880 pasang, total 15 order dan 4.385 pasang) dan **Rekap per pelanggan** dengan jumlah order tiap status. Periode rekap mengacu pada **tanggal masuk** order, jadi tiga order Selesai dan satu Dibatalkan (yang masuk lebih dari 10 hari lalu) tidak ikut. Baris **Total** tampil tebal.

4. Pilih **Riwayat evaluasi (per periode)**.

**Hasil yang diharapkan:** daftar evaluasi pada periode itu, yang terbaru di atas, dengan waktu hitung, pemicu, status, jumlah order, jumlah Prioritas Utama, siapa yang menghitung dan menyetujui, dan waktu penerapan. Dalam data demo ada enam baris (lima diterapkan dan satu diajukan).

5. Ubah **Dari tanggal** menjadi tanggal sesudah **Sampai tanggal**.

![Periode terbalik ditolak](88-laporan-periode-terbalik.png)

**Hasil yang diharapkan:** pratinjau diganti kalimat "Tanggal akhir tidak boleh sebelum tanggal awal", dan tombol Unduh tidak menghasilkan berkas apa pun.

6. Pilih periode lama yang tidak memuat data, misalnya satu tahun lalu.

**Hasil yang diharapkan:** pratinjau berisi kalimat keadaan kosong seperti "Tidak ada order terlambat dengan tenggat pada periode ini", dan PDF yang diunduh tetap berisi kop serta kalimat itu.

### 10.10 Uji 8.9: Kop dari profil perusahaan

**Tujuan:** memastikan kop laporan mengikuti profil perusahaan.
**Masuk sebagai:** Manajer.

Sejak Fase 9, profil perusahaan diisi Admin di halaman **Pengaturan**, bagian **Profil perusahaan** (Uji 9.1). Masuk sebagai Admin, isi Alamat dengan *Jl. Industri Sepatu No. 12, Sidoarjo, Jawa Timur* dan Telepon dengan *031-5550100*, lalu simpan. Cara lama lewat terminal tetap bisa dipakai:

```
php artisan tinker --execute="App\Models\Setting::put('company_address', 'Jl. Industri Sepatu No. 12, Sidoarjo, Jawa Timur'); App\Models\Setting::put('company_phone', '031-5550100');"
```

1. Unduh laporan PDF apa pun.

**Hasil yang diharapkan:** di bawah nama perusahaan muncul dua baris kecil: alamat dan "Telepon 031-5550100" (lihat gambar di Uji 8.6). Sebelum perintah dijalankan, kop hanya memuat nama **PT Weba International**, yaitu nama bawaan. Nama bisa diganti dengan kunci `company_name`. Alamat atau telepon yang kosong tidak ditampilkan sama sekali.

**Mengembalikan data:** kosongkan Alamat dan Telepon di halaman Pengaturan, hapus nilai itu dengan `php artisan tinker --execute="App\Models\Setting::whereIn('key', ['company_address', 'company_phone'])->delete();"`, atau buat ulang data demo.

### 10.11 Uji 8.10: Hak akses laporan dan dasbor

**Tujuan:** memastikan semua peran bisa mengunduh laporan, dan Supervisor tidak melihat rupiah.

1. Masuk sebagai **Admin**, **Supervisor**, dan **Pimpinan** bergantian. Buka **Laporan** dan unduh PDF dan Excel peringkat.

**Hasil yang diharapkan:** keempat peran bisa membuka halaman Laporan dan mengunduh kedua berkas. Dicetak oleh selalu memuat nama pengguna yang sedang masuk.

2. Masuk sebagai **Supervisor**, pilih **Rincian perhitungan SAW**, dan periksa tabelnya.

**Hasil yang diharapkan:** tidak ada kolom C6 (Nilai order) di tabel mana pun dan tidak ada angka rupiah. Pimpinan, Manajer, dan Admin melihat kriteria C6 lengkap. Laporan lain (peringkat, order terlambat, rekap, riwayat) memang tidak memuat nilai rupiah untuk siapa pun.

3. Buka `http://localhost:8001/admin/laporan` tanpa masuk.

**Hasil yang diharapkan:** peramban diarahkan ke halaman masuk.

**Mengembalikan data:** tidak ada yang berubah oleh uji ini, kecuali Uji 8.4 langkah 1 yang menambah satu evaluasi. Buat ulang data demo bila ingin mengulang dari awal.

## 11. Fase 9: Log Aktivitas, Pengaturan, Evaluasi Terjadwal, dan Notifikasi

Bab ini membuktikan empat hal: kegiatan penting tercatat dan bisa ditelusuri di **Log Aktivitas**, aturan sistem bisa diubah di halaman **Pengaturan** dan benar-benar dipakai, perhitungan bisa berjalan sendiri setiap hari pada jam yang diatur, dan pengguna yang perlu bertindak diberi tahu lewat ikon lonceng di kanan atas.

Menu **Log Aktivitas** dan **Pengaturan** ada di grup **Pengaturan** dan hanya tampil untuk Admin dan Pimpinan.

### 11.1 Menyiapkan data

Mulai dari data demo yang bersih, lalu jalankan satu perhitungan terjadwal supaya ada notifikasi untuk dicoba.

```
php artisan migrate:fresh --seed
php artisan evaluation:run-scheduled --force
```

**Hasil yang diharapkan:** baris terakhir berbunyi "Evaluasi terjadwal dibuat: Evaluasi terjadwal (tanggal dan jam) (21 order)." Pilihan `--force` menjalankan perhitungan sekarang juga tanpa menunggu jam yang diatur. Perhitungan paksa ini dihitung sebagai jatah hari ini, jadi jadwal biasa baru berjalan lagi besok.

Notifikasi yang dipakai di bab ini diberi kode supaya mudah dirujuk.

| Kode | Kapan dikirim | Penerima |
|---|---|---|
| N1 | Evaluasi diajukan untuk disetujui | Pimpinan |
| N2 | Evaluasi disetujui | Manajer yang mengajukan |
| N3 | Evaluasi ditolak, beserta catatannya | Manajer yang mengajukan |
| N4 | Prioritas diterapkan | Supervisor |
| N5 | Ada order terlambat saat perhitungan | Manajer |
| N6 | Ada nilai manual yang kedaluwarsa | Manajer |
| N7 | Perhitungan terjadwal gagal atau tidak ada order untuk dihitung | Admin dan Manajer |
| N8 | Ada order yang baru menjadi Tertahan | Manajer dan Pimpinan |
| N9 | Ada order yang baru masuk kelompok eskalasi | Manajer dan Pimpinan |
| N10 | Order terkunci dan dieskalasi memakai semua slot Prioritas Utama | Manajer dan Pimpinan |

Notifikasi hanya dikirim ke akun yang aktif. N8 dan N9 hanya menyebut order yang baru tertahan atau baru dieskalasi, jadi order yang sama tidak diberitahukan berulang kali di setiap perhitungan. Notifikasi yang lebih tua dari 30 hari dihapus otomatis setiap malam, baik sudah dibaca maupun belum.

### 11.2 Uji 9.1: Membuka halaman Pengaturan

**Tujuan:** melihat semua pengaturan sistem di satu halaman.
**Masuk sebagai:** Admin (`admin@spk.test`).

1. Buka menu **Pengaturan** (grup **Pengaturan**).

![Halaman Pengaturan untuk Admin](100-pengaturan-admin.png)

**Hasil yang diharapkan:** halaman berisi lima bagian dengan nilai bawaan.

- **Profil perusahaan:** Nama perusahaan *PT Weba International*, Telepon dan Alamat kosong, dan tempat unggah **Logo** (PNG atau JPG, paling besar 1 MB). Isinya dipakai di kop laporan.
- **Aturan keputusan:** Jumlah order Prioritas Utama 5, Persetujuan Pimpinan wajib menyala, nilai manual kedaluwarsa setelah 3 hari, dan order yang belum lengkap *Ikut dihitung dengan nilai terburuk dan diberi peringatan*.
- **Perlindungan order lama:** kunci otomatis untuk order Dikerjakan menyala, batas tunggu maksimum 7 hari, order Tertahan setelah 5 evaluasi berturut-turut dan menunggu minimal 4 hari.
- **Evaluasi terjadwal:** menyala, jam pelaksanaan 06:00.
- **Tampilan:** batas "mendekati tenggat" 3 hari.

2. Klik **Simpan pengaturan** tanpa mengubah apa pun.

**Hasil yang diharapkan:** muncul pesan "Tidak ada perubahan untuk disimpan", dan tidak ada baris baru di Log Aktivitas.

### 11.3 Uji 9.2: Isian yang salah ditolak

**Tujuan:** memastikan pengaturan yang saling bertentangan tidak bisa disimpan.
**Masuk sebagai:** Admin.

1. Di bagian **Perlindungan order lama**, ubah **Order Tertahan setelah menunggu minimal (hari)** menjadi 7, sama dengan batas tunggu maksimum.
2. Klik **Simpan pengaturan**.

![Isian yang bertentangan ditolak](101-pengaturan-salah.png)

**Hasil yang diharapkan:** kolom itu diberi garis merah dengan pesan "Lama menunggu minimal harus lebih pendek dari batas tunggu maksimum (7 hari), supaya peringatan Tertahan muncul sebelum order dieskalasi." Tidak ada yang tersimpan. Peringatan Tertahan memang harus muncul lebih dulu, sebelum order mencapai batas eskalasi.

3. Coba juga isian di luar batas: Jumlah order Prioritas Utama 0 atau 51, batas tunggu maksimum 0, atau batas mendekati tenggat 15.

**Hasil yang diharapkan:** setiap isian ditolak dengan pesan di bawah kolomnya.

**Mengembalikan data:** muat ulang halaman. Isian yang ditolak tidak pernah tersimpan.

### 11.4 Uji 9.3: Pengaturan benar-benar dipakai

**Tujuan:** membuktikan perubahan pengaturan langsung memengaruhi aplikasi.
**Masuk sebagai:** Admin, lalu Manajer.

1. Lihat kartu **Mendekati tenggat** di Dasbor. Angkanya 2 dengan keterangan "Tenggat dalam 3 hari".
2. Buka **Pengaturan**, ubah **Batas "mendekati tenggat" (hari)** menjadi 5, lalu klik **Simpan pengaturan**.

**Hasil yang diharapkan:** muncul pesan "Pengaturan disimpan" dengan keterangan "1 pengaturan diubah dan dicatat di Log Aktivitas."

3. Buka Dasbor lagi.

**Hasil yang diharapkan:** kartu **Mendekati tenggat** menjadi 3 dengan keterangan "Tenggat dalam 5 hari". Di daftar Order Produksi, filter berubah nama menjadi **Mendekati tenggat (5 hari)** dan menampilkan 3 order, sama dengan kartu.

4. Kembali ke **Pengaturan**, ubah **Order yang belum lengkap** menjadi *Dikecualikan dari perhitungan*, lalu simpan. Keluar, lalu masuk sebagai **Manajer** dan buka **Jalankan Perhitungan**.

**Hasil yang diharapkan:** bagian Kesiapan data menampilkan kotak abu-abu **Dikecualikan** berisi 4, dengan penjelasan bahwa order yang belum lengkap tidak mendapat peringkat sampai nilainya dilengkapi. Setelah dihitung, evaluasi baru memuat 17 order, bukan 21. Keempat order yang dikecualikan adalah ORD-2026-0013, ORD-2026-0018, ORD-2026-0023, dan ORD-2026-0025.

Evaluasi yang sudah tersimpan tidak ikut berubah saat pengaturan diganti, karena setiap evaluasi menyimpan salinan pengaturan yang dipakainya (Bab 8).

**Mengembalikan data:** kembalikan batas mendekati tenggat ke 3 dan pilihan order belum lengkap ke nilai terburuk, atau buat ulang data demo.

### 11.5 Uji 9.4: Pengaturan untuk Pimpinan

**Tujuan:** memastikan Pimpinan hanya mengubah aturan keputusan.
**Masuk sebagai:** Pimpinan (`pimpinan@spk.test`).

1. Buka menu **Pengaturan**.

![Halaman Pengaturan untuk Pimpinan](104-pengaturan-pimpinan.png)

**Hasil yang diharapkan:** hanya dua bagian yang tampil, **Aturan keputusan** dan **Perlindungan order lama**. Keterangan di bawah judul berbunyi "Pengaturan lain hanya bisa diubah Admin." Profil perusahaan, evaluasi terjadwal, dan tampilan tidak terlihat dan tidak bisa diubah Pimpinan, walaupun dikirim lewat cara lain.

2. Ubah **Jumlah order Prioritas Utama** menjadi 6 dan simpan.

**Hasil yang diharapkan:** pesan "Pengaturan disimpan". Perhitungan berikutnya memberi label Prioritas Utama kepada enam order Antri teratas.

**Mengembalikan data:** kembalikan jumlahnya ke 5.

### 11.6 Uji 9.5: Membaca Log Aktivitas

**Tujuan:** menelusuri siapa melakukan apa dan kapan.
**Masuk sebagai:** Admin.

1. Buka menu **Log Aktivitas** (grup **Pengaturan**).

![Log Aktivitas](102-log-aktivitas.png)

**Hasil yang diharapkan:** daftar kegiatan dengan yang terbaru di atas. Setiap baris memuat **Waktu** sampai detik, **Pengguna** (tertulis *Sistem* untuk perhitungan terjadwal), **Kegiatan** seperti *Masuk*, *Pengaturan diubah*, *Evaluasi diajukan*, *Perhitungan dijalankan*, atau *Nilai penilaian diubah*, **Objek** yang diubah (misalnya *Penilaian ORD-2026-0024, C2*), dan **Perubahan** dalam kalimat biasa seperti "Jumlah Prioritas Utama: 5 menjadi 6" atau "Status: Draf menjadi Diajukan". Keterangan yang bukan perubahan, misalnya alasan atau nama evaluasi, ditulis tanpa kata "menjadi".

Kegiatan yang tercatat: masuk dan keluar, pembuatan dan perubahan order (termasuk status, kunci, dan order yang masuk lewat impor), perubahan kriteria, bobot, dan skala nilai, perubahan nilai penilaian, setiap langkah evaluasi (dihitung, diajukan, disetujui, ditolak, diterapkan, dibatalkan, peringkat disesuaikan), perhitungan terjadwal yang dilewati atau gagal, dan perubahan pengaturan. Percobaan masuk yang gagal tidak dicatat.

2. Klik ikon corong, pilih **Jenis kegiatan** *Pengaturan diubah*.

![Log yang disaring menurut jenis kegiatan](103-log-pengaturan.png)

**Hasil yang diharapkan:** hanya baris perubahan pengaturan yang tersisa, masing-masing dengan nilai lama dan baru.

3. Coba juga saringan **Pengguna** (misalnya Budi Santoso) dan **Tanggal** (Dari tanggal dan Sampai tanggal).

**Hasil yang diharapkan:** daftar hanya memuat kegiatan pengguna itu atau pada rentang tanggal itu. Kolom **Alamat IP** bisa ditampilkan lewat ikon kolom di kanan atas tabel.

4. Perhatikan bahwa tidak ada tombol tambah, ubah, atau hapus.

**Hasil yang diharapkan:** log hanya bisa dibaca. Tidak ada peran yang bisa mengubah atau menghapus catatan.

### 11.7 Uji 9.6: Notifikasi hasil perhitungan

**Tujuan:** memastikan Manajer diberi tahu hal yang perlu ditindaklanjuti setelah perhitungan.
**Masuk sebagai:** Manajer (`manajer@spk.test`), dengan data dari Bagian 11.1.

1. Perhatikan ikon lonceng di kanan atas. Klik lonceng itu.

![Notifikasi Manajer](106-notifikasi-manajer.png)

**Hasil yang diharapkan:** lonceng bertanda angka 4. Panel **Notifikasi** terbuka di kanan dan berisi empat notifikasi dari perhitungan terjadwal.

- **Ada order terlambat** (N5): 3 order sudah melewati tenggat, dengan tombol **Lihat order terlambat**.
- **Nilai penilaian perlu diperbarui** (N6): 4 order memiliki nilai manual yang kedaluwarsa, dengan tombol **Buka Penilaian Order**.
- **Order masuk kelompok eskalasi** (N9): 6 order menunggu lebih dari 7 hari, lima nomor pertama disebut lalu "dan 1 lainnya".
- **Slot Prioritas Utama penuh** (N10): order terkunci dan dieskalasi sudah memakai semua 5 slot.

2. Klik **Lihat order terlambat**.

**Hasil yang diharapkan:** daftar Order Produksi terbuka dengan filter Terlambat menyala (3 order), dan notifikasi itu ditandai sudah dibaca sehingga angka di lonceng berkurang satu.

3. Buka lonceng lagi, klik **Tandai semua sudah dibaca**, lalu **Bersihkan**.

**Hasil yang diharapkan:** angka di lonceng hilang, lalu panel menjadi kosong. Lonceng memeriksa notifikasi baru setiap 30 detik tanpa perlu memuat ulang halaman.

### 11.8 Uji 9.7: Notifikasi alur persetujuan

**Tujuan:** memastikan setiap langkah persetujuan memberi tahu orang berikutnya.

1. Masuk sebagai **Manajer**, buka **Hasil Evaluasi**, buka evaluasi terjadwal dari Bagian 11.1, lalu klik **Ajukan untuk disetujui**. Keluar.
2. Masuk sebagai **Pimpinan** dan klik lonceng.

![Notifikasi Pimpinan](105-notifikasi-pimpinan.png)

**Hasil yang diharapkan:** paling atas ada **Evaluasi menunggu persetujuan Anda** (N1) dengan nama evaluasi dan tombol **Tinjau evaluasi** yang membuka rincian evaluasi itu. Pimpinan juga menerima N9 dan N10 dari perhitungan terjadwal, tetapi tidak menerima N5 dan N6 yang khusus untuk Manajer.

3. Klik **Tinjau evaluasi**, lalu **Tolak** dengan catatan "Bobot perlu dicek lagi". Keluar, masuk sebagai Manajer, dan buka lonceng.

**Hasil yang diharapkan:** ada **Evaluasi ditolak** (N3) yang memuat catatan "Bobot perlu dicek lagi".

4. Ajukan lagi sebagai Manajer, setujui sebagai Pimpinan, lalu terapkan sebagai Manajer.

**Hasil yang diharapkan:** Manajer menerima **Evaluasi disetujui** (N2). Setelah diterapkan, Supervisor (`supervisor@spk.test`) menerima **Daftar prioritas baru ditetapkan** (N4) dengan tombol **Lihat daftar order** yang membuka daftar order berfilter Prioritas Utama. Bila Persetujuan Pimpinan wajib dimatikan di Pengaturan, Supervisor tetap menerima N4 saat Manajer menerapkan langsung dari Draf.

5. Batalkan sebuah evaluasi Draf.

**Hasil yang diharapkan:** tidak ada notifikasi yang dikirim untuk pembatalan.

### 11.9 Uji 9.8: Evaluasi terjadwal

**Tujuan:** memastikan perhitungan berjalan sendiri satu kali sehari pada jam yang diatur.
**Masuk sebagai:** Admin, dengan terminal di folder `dss`.

1. Di **Pengaturan**, ubah **Jam pelaksanaan** menjadi satu atau dua menit dari sekarang, lalu simpan. Pastikan **Evaluasi terjadwal aktif** menyala.
2. Jalankan penjadwal di terminal dan biarkan terbuka.

```
php artisan schedule:work
```

**Hasil yang diharapkan:** setiap menit muncul satu baris yang menjalankan `evaluation:run-scheduled`. Sebelum jam yang diatur, perintah itu tidak berbuat apa-apa. Begitu jamnya tiba, di **Hasil Evaluasi** bertambah satu evaluasi bernama *Evaluasi terjadwal (tanggal dan jam)* dengan pemicu **Terjadwal**, status **Draf**, dan kolom Dijalankan oleh *Sistem (terjadwal)*. Evaluasi ini tetap harus diajukan dan disetujui seperti biasa. Log Aktivitas mencatat *Perhitungan dijalankan* dengan pengguna *Sistem*.

3. Biarkan penjadwal berjalan beberapa menit lagi.

**Hasil yang diharapkan:** tidak ada evaluasi tambahan. Perhitungan terjadwal hanya berjalan sekali sehari. Bila komputer atau server mati tepat pada jamnya, perhitungan tetap berjalan pada menit pertama setelahnya di hari yang sama.

4. Hentikan penjadwal dengan Ctrl dan C. Matikan **Evaluasi terjadwal aktif** bila tidak ingin perhitungan otomatis.

**Hasil yang diharapkan:** selama sakelar mati, perhitungan terjadwal tidak berjalan. Perintah `php artisan evaluation:run-scheduled --force` tetap bisa dipakai untuk menjalankannya dengan tangan.

Bila perhitungan terjadwal tidak bisa berjalan, misalnya karena tidak ada order Antri atau Dikerjakan, atau total bobot belum 100%, tidak ada evaluasi yang dibuat. Admin dan Manajer menerima notifikasi **Perhitungan terjadwal tidak berjalan** (N7) beserta alasannya, dan Log Aktivitas mencatat *Perhitungan terjadwal dilewati* atau *Perhitungan terjadwal gagal*. Perhitungan tidak dicoba ulang setiap menit, melainkan besok pada jam yang sama.

**Mengembalikan data:** kembalikan jam pelaksanaan ke 06:00.

### 11.10 Uji 9.9: Hak akses Log Aktivitas dan Pengaturan

**Tujuan:** memastikan hanya Admin dan Pimpinan yang membuka kedua halaman itu.

1. Masuk sebagai **Manajer**, lalu sebagai **Supervisor**. Perhatikan menu kiri.

**Hasil yang diharapkan:** grup **Pengaturan** dengan menu Log Aktivitas dan Pengaturan tidak ada.

2. Ketik langsung `http://localhost:8001/admin/pengaturan` dan `http://localhost:8001/admin/log-aktivitas` di peramban.

**Hasil yang diharapkan:** keduanya menampilkan halaman **403 Forbidden**.

3. Masuk sebagai **Supervisor** dan buka halaman detail sebuah order.

**Hasil yang diharapkan:** riwayat perubahan order itu tetap tampil di halaman detail (Bab 4), walaupun halaman Log Aktivitas tertutup.

### 11.11 Memasang penjadwal di cPanel

Di komputer lokal penjadwal dijalankan dengan `schedule:work` seperti Uji 9.8. Di hosting cPanel tidak ada terminal yang bisa dibiarkan terbuka, jadi penjadwal dipanggil oleh **Cron Job** setiap menit.

1. Masuk ke cPanel, buka **Cron Jobs**.
2. Pada **Common Settings**, pilih **Once Per Minute (\* \* \* \* \*)**.
3. Isi **Command** dengan perintah berikut. Ganti `namapengguna` dan `dss` dengan nama akun cPanel dan folder aplikasi.

```
cd /home/namapengguna/dss && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

4. Klik **Add New Cron Job**.

**Hasil yang diharapkan:** keesokan harinya, pada jam yang diatur di halaman Pengaturan, muncul evaluasi terjadwal baru di Hasil Evaluasi. Penjadwal yang sama juga menghapus notifikasi yang lebih tua dari 30 hari setiap pukul 01.00.

Bila hosting memakai PHP lain sebagai bawaan, ganti `/usr/local/bin/php` dengan jalur PHP 8.3 yang tertera di cPanel (sering berbentuk `/opt/cpanel/ea-php83/root/usr/bin/php`). Bila hosting tidak mengizinkan Cron setiap menit, jadwal setiap 5 menit tetap bisa dipakai: perhitungan berjalan pada pemanggilan pertama setelah jam yang diatur. Notifikasi tidak membutuhkan antrean (*queue worker*), jadi tidak ada Cron lain yang perlu dipasang.

## 12. Menjalankan Pengujian Otomatis

Selain diuji dengan tangan seperti di atas, aplikasi punya pengujian otomatis yang menjalankan ratusan pemeriksaan dalam hitungan detik. Pengujian ini memakai basis data terpisah bernama `dss_test`, jadi tidak menyentuh data demo Anda.

### 12.1 Cara menjalankan

1. Pastikan MySQL menyala dan basis data `dss_test` ada.
2. Di terminal folder `dss`, jalankan perintah berikut.

```
php artisan test --compact
```

3. Tunggu sekitar setengah menit.

**Hasil yang diharapkan:** ringkasan di akhir menyatakan semua tes lulus, saat ini 552 tes dengan 3.010 pemeriksaan (tulisan seperti `Tests: 79 passed`). Bila ada yang gagal, ringkasan menyebut nama tes dan penyebabnya.

### 12.2 Apa saja yang diuji

- **Masuk dan profil:** login berhasil dan gagal, akun nonaktif, batas lima percobaan, halaman yang tertutup bagi tamu, ganti nama dan kata sandi.
- **Pengguna:** hak akses Admin, tambah, ubah, nonaktifkan, atur ulang kata sandi, serta aturan Admin terakhir dan larangan menonaktifkan diri sendiri.
- **Pelanggan dan produk:** tambah, ubah, nonaktifkan, kode unik, hak akses per peran, jumlah order aktif.
- **Order:** nomor otomatis berurutan, hitungan sisa hari dan batas terlambat, filter, pencarian, validasi formulir, harga otomatis dari produk, hak akses per peran, ubah status, riwayat perubahan.
- **Kriteria dan bobot:** bobot dari tingkat kepentingan (contoh 5, 4, 3, 3, 2, 2), total selalu tepat 1, bobot diatur langsung, batas minimal dua kriteria aktif, status siap hitung (termasuk skala yang belum lengkap), hak akses per peran, dan riwayat perubahan bobot.
- **Skala nilai:** rentang yang valid dan yang salah (tumpang tindih, celah, terbalik arah, batas tidak terbuka), konversi nilai asli ke skala termasuk batas bawah dan atas, serta contoh pada spesifikasi.
- **Penilaian:** nilai otomatis (sisa hari, urgensi, nilai order, lama menunggu) dan perubahannya, nilai manual dengan batas persen, jam, dan rupiah, urgensi yang ditimpa dengan alasan wajib, batas kedaluwarsa tiga hari, kelengkapan dan filternya, riwayat nilai, hak akses Manajer dan Supervisor, serta nilai rupiah yang disembunyikan dari Supervisor.
- **Impor dan ekspor Excel:** pembacaan berkas dan judul kolom, aturan tiap kolom (kode, jumlah, tanggal, harga, status, persen, jam), dua puluh order benar dan dua baris rusak, mode tambah saja dan tambah serta perbarui, kolom opsional yang kosong tidak menimpa, batas 1.000 baris, berkas bukan Excel, penyimpanan satu transaksi yang batal total bila gagal, ekspor sesuai filter dan pencarian, serta ekspor yang diimpor kembali tanpa mengubah data.
- **Mesin SAW dan evaluasi:** contoh hitung spesifikasi (skor 0,8550, 0,7400, 0,7000 dan peringkat A, C, B), kasus khusus (satu order, nilai sama, nilai kosong, skor sama), pengelompokan peringkat dan contoh order lama yang dieskalasi, batas 7 dan 8 hari, kunci otomatis dan manual beserta kunci yang kedaluwarsa, pemeriksaan sebelum hitung, konfirmasi order belum lengkap, salinan kriteria dan pengaturan yang tidak ikut berubah, peringkat sebelumnya, evaluasi yang tidak bisa diubah atau dihapus, penyimpanan satu transaksi, kinerja 200 dan 1.000 order, serta hak akses halaman hitung.
- **Hasil evaluasi dan persetujuan:** alur Draf, Diajukan, Disetujui, Diterapkan, dan Dibatalkan beserta peran yang berhak di tiap langkah, penolakan wajib bercatatan, hanya evaluasi terbaru yang bisa diterapkan, label Prioritas Utama untuk N order Antri teratas dan tidak untuk order Dikerjakan (enam order Dikerjakan dan N sama dengan 5 tetap memberi label ke lima order Antri), peringatan slot penuh, penyesuaian peringkat dengan alasan wajib dan larangan menurunkan order terkunci di bawah order biasa, bandingkan dua evaluasi, grafik tren, serta kriteria nilai order yang disembunyikan dari Supervisor.
- **Kunci order:** kunci manual dengan alasan wajib, tanggal berakhir, satu baris log untuk tiap pasang dan lepas, kunci otomatis yang mengikuti status (termasuk order hasil impor), pelepasan saat Selesai atau Dibatalkan, dan hak akses Manajer serta Pimpinan.
- **Dasbor:** enam kartu angka yang sama dengan hasil filter di daftar asalnya (termasuk tautan tiap kartu), daftar order terlambat terlama, daftar prioritas utama dari evaluasi terakhir yang diterapkan, grafik order per status, bagian persetujuan yang hanya tampil untuk Pimpinan, tombol hitung yang hanya tampil untuk Manajer, serta dasbor tanpa rupiah untuk Supervisor.
- **Order tertahan:** aturan lima evaluasi berturut-turut di luar lima order Antri teratas dan menunggu empat hari, evaluasi dibatalkan yang tidak dihitung, order Dikerjakan yang tidak memakai tempat, order terkunci atau tidak lagi Antri yang tidak tertahan, pengaturan jumlah evaluasi dan hari, daftar tertahan dan dieskalasi di dasbor, serta filter Tertahan di daftar order.
- **Laporan:** lima laporan (peringkat, rincian perhitungan, order terlambat, rekap order, riwayat evaluasi) dengan batas periode yang tepat, kop dari pengaturan perusahaan, angka Excel dan PDF yang sama dengan layar, Excel dengan angka dan tanggal sungguhan, skor yang bisa dihitung ulang dari lembar Excel, nama lembar yang valid, teks data yang diloloskan di PDF, periode terbalik ditolak, laporan kosong, kriteria nilai order yang disembunyikan dari Supervisor, dan kinerja laporan 200 dan 1.000 order.
- **Log aktivitas:** catatan masuk dan keluar (dan tidak untuk masuk yang gagal), perubahan nilai penilaian beserta alasan, perubahan skala yang hanya mencatat rentang yang berubah, satu baris untuk perubahan bobot, langkah evaluasi dalam kalimat biasa, urutan terbaru di atas, saringan pengguna, jenis kegiatan, dan tanggal, log yang tidak bisa diubah atau dihapus, serta halaman yang hanya dibuka Admin dan Pimpinan.
- **Pengaturan:** nilai bawaan, batas tiap isian, aturan menunggu minimal Tertahan yang harus lebih pendek dari batas tunggu, bagian yang boleh diubah Admin dan Pimpinan (termasuk penolakan di sisi server), pencatatan setiap perubahan, unggah logo, dan pengaruh tiap pengaturan pada perhitungan, dasbor, filter, dan pengecualian order belum lengkap.
- **Evaluasi terjadwal:** terdaftar di penjadwal, tidak berjalan sebelum jamnya, berjalan pada jam yang diatur atau menit sesudahnya, hanya sekali sehari, berhenti saat sakelar mati, bisa dipaksa, hasil berstatus Draf dengan pemicu Terjadwal, pencatatan dan notifikasi bila tidak ada order atau perhitungan gagal, dan penghapusan notifikasi yang lebih tua dari 30 hari.
- **Notifikasi:** N1 sampai N10 sampai ke peran yang tepat dan tidak ke peran lain, akun nonaktif yang dilewati, N8 dan N9 yang hanya dikirim untuk order yang baru tertahan atau baru dieskalasi, daftar nomor order yang dipendekkan, notifikasi yang langsung tersimpan tanpa antrean, dan langkah persetujuan atau perhitungan yang tetap tersimpan walaupun pengiriman notifikasi gagal.
- **Data demo:** jumlah dan sebaran data, kriteria awal dan skalanya, nilai penilaian demo, dan setiap halaman terbuka tanpa kosong untuk keempat peran.

### 12.3 Menjalankan sebagian tes

Untuk memeriksa satu bagian saja, tambahkan `--filter` dengan kata kunci nama tes, misalnya:

```
php artisan test --compact --filter="order"
```

## 13. Jika Ada Masalah

**Halaman tidak bisa dibuka atau muncul "gagal terhubung".** Pastikan perintah `php artisan serve --port=8001` masih berjalan di terminal dan alamatnya `http://localhost:8001/admin`.

**Muncul pesan kesalahan basis data (misalnya "Connection refused").** MySQL belum menyala. Buka XAMPP dan klik Start pada MySQL, lalu muat ulang halaman.

**Data berantakan setelah banyak mencoba.** Buat ulang data demo dengan `php artisan migrate:fresh --seed`. Seluruh data akan kembali ke kondisi awal dan kata sandi kembali menjadi `password`.

**Lupa kata sandi akun demo.** Jalankan `php artisan db:seed --class=DemoUserSeeder`. Perintah ini mengembalikan keempat akun ke kata sandi `password` tanpa menghapus data lain.

**Masuk ditolak terus padahal kata sandi benar.** Tunggu satu menit bila baru gagal beberapa kali, karena ada batas percobaan. Bila pesannya "Akun Anda dinonaktifkan", akun harus diaktifkan oleh Admin.

**Tabel order terpotong di sisi kanan.** Persempit menu kiri dengan panah di sebelah nama aplikasi, atau perkecil zoom peramban dengan Ctrl dan tanda minus.

**Unggahan berkas gagal atau tidak bergerak.** Pastikan berkas berformat .xlsx dan berukuran di bawah 5 MB. Bila memakai Excel versi lama dengan .xls, simpan ulang sebagai .xlsx.

**Semua baris ditolak saat memeriksa berkas.** Baca pesan di bagian Hasil pemeriksaan. Penyebab paling sering: kode pelanggan atau produk belum ada di Data Master, atau judul kolom di baris pertama diubah. Unduh ulang templat dan salin data Anda ke dalamnya.

**Tombol Terapkan prioritas atau Ajukan tidak tampil.** Tombol itu hanya tampil pada status dan peran yang tepat: Ajukan untuk Manajer pada Draf, Setujui dan Tolak untuk Pimpinan pada Diajukan, Terapkan pada Disetujui dan hanya untuk evaluasi terbaru.

**Pesan "Order terkunci tidak boleh turun ke bawah order biasa".** Lepas kunci order itu lebih dulu di Order Produksi, atau pindahkan ke peringkat yang masih di atas semua order biasa.

**Tombol Jalankan Perhitungan pudar dan tidak bisa ditekan.** Baca tulisan merah di bagian Kesiapan data. Penyebabnya total bobot belum 100%, skala nilai sebuah kriteria belum lengkap, atau belum ada order Antri atau Dikerjakan.

**Daftar Prioritas utama atau Order tertahan di Dasbor kosong.** Daftar prioritas utama terisi setelah sebuah evaluasi diterapkan, dan order baru bisa Tertahan setelah lima evaluasi berturut-turut. Untuk data demo, jalankan `php artisan db:seed --class=DemoEvaluationSeeder` (Bagian 10.1).

**Unduhan laporan tidak muncul.** Pastikan jenis laporan, evaluasi atau periode sudah terisi. Bila pratinjau berisi kalimat seperti "Tanggal akhir tidak boleh sebelum tanggal awal", perbaiki isian itu lebih dulu karena tombol Unduh tidak menghasilkan apa pun selama pilihannya belum lengkap.

**PDF laporan terlalu lama dibuat.** Laporan rincian perhitungan dengan ratusan order menghasilkan banyak halaman dan butuh beberapa detik. Unduh versi Excel bila hanya angkanya yang dibutuhkan.

**Tombol Impor sekarang tidak muncul.** Tombol itu baru tampil setelah Periksa berkas dan hanya bila ada sedikitnya satu baris yang benar.

**Lonceng tidak pernah berisi notifikasi.** Notifikasi hanya dikirim untuk peristiwa di Bagian 11.1 dan hanya ke peran yang tercantum. Admin, misalnya, hanya menerima N7. Lonceng memeriksa notifikasi baru setiap 30 detik, jadi tunggu sebentar atau muat ulang halaman.

**Evaluasi terjadwal tidak muncul.** Pastikan **Evaluasi terjadwal aktif** menyala di Pengaturan, Cron Job sudah dipasang (Bagian 11.11), dan jam server sesuai. Bila hari itu sudah ada perhitungan terjadwal, termasuk yang dipaksa dengan `--force`, perhitungan berikutnya baru berjalan besok. Lihat juga Log Aktivitas: bila tertulis *Perhitungan terjadwal dilewati* atau *gagal*, alasannya tercantum di kolom Perubahan.

**Pesan "Lama menunggu minimal harus lebih pendek dari batas tunggu maksimum".** Turunkan isian Order Tertahan setelah menunggu minimal, atau naikkan batas tunggu maksimum, lalu simpan lagi.

**Tes otomatis gagal terhubung atau berhenti tanpa keluaran.** Pastikan basis data `dss_test` sudah dibuat dan MySQL menyala. Bila MySQL mati, tes bisa diam lama tanpa pesan apa pun sebelum akhirnya gagal.

## 14. Lampiran: Ringkasan Hak Akses

Ringkasan berikut berlaku untuk fitur yang sudah ada sampai Fase 9.

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
| Jalankan perhitungan SAW | Tidak | Ya | Tidak | Tidak |
| Lihat daftar dan rincian hasil evaluasi | Ya | Ya | Ya | Ya |
| Ajukan evaluasi untuk disetujui | Tidak | Ya | Tidak | Tidak |
| Setujui atau tolak evaluasi | Tidak | Tidak | Tidak | Ya |
| Sesuaikan peringkat, terapkan prioritas, batalkan evaluasi | Tidak | Ya | Tidak | Ya |
| Pasang dan lepas kunci manual order | Tidak | Ya | Tidak | Ya |
| Lihat kriteria nilai order (rupiah) di hasil evaluasi | Ya | Ya | Tidak | Ya |
| Lihat dasbor | Ya | Ya | Ya | Ya |
| Jalankan Perhitungan dari tombol di dasbor | Tidak | Ya | Tidak | Tidak |
| Lihat bagian Menunggu persetujuan di dasbor | Tidak | Tidak | Tidak | Ya |
| Lihat order tertahan | Ya | Ya | Ya | Ya |
| Unduh laporan PDF dan Excel | Ya | Ya | Ya | Ya |
| Lihat Log Aktivitas | Ya | Tidak | Tidak | Ya |
| Ubah aturan keputusan dan perlindungan order lama | Ya | Tidak | Tidak | Ya |
| Ubah profil perusahaan, evaluasi terjadwal, dan tampilan | Ya | Tidak | Tidak | Tidak |
| Terima notifikasi (lihat tabel di Bagian 11.1) | N7 | N2 sampai N3, N5 sampai N10 | N4 | N1, N8 sampai N10 |
| Ganti nama dan kata sandi sendiri | Ya | Ya | Ya | Ya |
