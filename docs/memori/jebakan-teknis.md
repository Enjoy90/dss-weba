# Jebakan teknis

Masalah yang sudah pernah terjadi selama pembangunan, supaya tidak diulang.

## Lingkungan

- PHP harus 8.3 atau lebih baru. Di mesin pemilik proyek ada dua PHP, 8.2 sebagai bawaan dan 8.3 di `D:\php83`. Bila perintah artisan atau composer gagal karena versi, pasang PATH ke PHP 8.3 lebih dulu.
- Basis data `dss` dan `dss_test` harus ada dan kosong sebelum `migrate`. Tes memakai `dss_test` lewat `phpunit.xml`.
- MariaDB 10.4: jangan pakai `JSON_ARRAYAGG` dan fungsi MySQL 8 lain yang tidak ada di sana.
- Kolom `evaluated_at` tidak boleh memakai ON UPDATE. Di MariaDB 10.4 kolom timestamp pertama otomatis mendapat `ON UPDATE CURRENT_TIMESTAMP`, jadi migrasi memakai `dateTime`. Tes membaca `information_schema` untuk memastikannya.

## Eloquent dan model

- Riwayat aktivitas kosong karena tipe morph tidak cocok antara nama tabel dan nama kelas. Solusinya `Relation::morphMap` di `AppServiceProvider` dan `getMorphClass()`. Model baru yang masuk `activity_logs` wajib didaftarkan di morph map.
- Model baru bernilai null pada kolom berdefault (misalnya `lock_type`). Isi `$attributes` di model untuk nilai bawaan.
- `Collection::only()` memilih berdasarkan kunci, bukan kode kriteria. Untuk memilih per kode, pakai filter.
- `Criterion` yang disimpan otomatis menghitung ulang bobot. Seeder yang mengisi bobot harus menyetel `weight_mode` ke `direct` sebelum membuat kriteria, kalau tidak bobotnya tertimpa.

## Filament dan tampilan

- `->label('')` pada `TextEntry` membuat Filament menampilkan nama field sebagai label (misalnya "Warning hint"). Pakai `->hiddenLabel()`.
- `DatePicker` non-native bersifat baca-saja di peramban (kalender), jadi Playwright tidak bisa mengetik tanggal. Ubah tanggal uji lewat `php artisan tinker --execute`.
- Isi `modalDescription` tidak dirender di HTML tes Livewire. Baca lewat `$component->instance()->getMountedAction()->getModalDescription()`.

- Tabel melebar dan tombol terpotong di layar 1366 piksel. Sembunyikan kolom sekunder secara bawaan dan pindahkan aksi baris ke `ActionGroup` di kiri.
- Status "siap dihitung" harus ikut memeriksa skala tiap kriteria aktif, bukan hanya total bobot.
- Hak lihat rupiah dicek di banyak tempat. Fitur baru yang menampilkan harga, nilai order, atau kriteria C6 wajib memakai `User::canSeeOrderValue()`.

## Tes

- Properti `paths[]` di proxy `test()` milik Pest tidak bisa dipakai untuk pembersihan. Pakai fungsi shutdown untuk menghapus berkas sementara.
- `Illuminate\Http\UploadedFile` palsu tidak punya `$name`. Pakai `UploadedFile::fake()->createWithContent(...)`.
- Penanggalan Excel: serial 46462 adalah 16 Maret 2027. Hitung ulang sebelum menulis ekspektasi tanggal.
- Pint bisa mengubah nama kelas pada type hint `Testable`. Jalankan Pint lalu jalankan tes lagi.
- Mutasi lewat regex di PHP rawan gagal diterapkan karena `$user` ikut diinterpolasi. Ubah berkas dengan skrip yang mengganti string tepat, dan pastikan perubahan benar-benar terjadi sebelum menjalankan tes.

## Notifikasi dan PowerShell

- `Notification::sendToDatabase()` milik Filament memakai `notify()`, dan kelas `DatabaseNotification`-nya `ShouldQueue`. Dengan `QUEUE_CONNECTION=database` notifikasi hanya masuk tabel `jobs` dan tidak pernah sampai ke lonceng tanpa queue worker. Tes tidak menangkapnya karena `phpunit.xml` memakai antrean `sync`. Kirim dengan `$user->notifyNow($notification->toDatabase())`. Tes `NotificationTest` memakai antrean `database` untuk menjaga ini.
- Dua notifikasi dari satu perhitungan tersimpan pada detik yang sama, dan id-nya UUID. Tes jangan bergantung pada urutan atau `first()`, cari notifikasi menurut `data.viewData.code`.
- `Set-Content -Encoding utf8` di PowerShell 5.1 menambah BOM. Berkas PHP ber-BOM gagal dengan "Namespace declaration statement has to be the very first statement". Ubah berkas lewat Bash (`sed`) atau alat Edit.

## Evaluasi

- Model yang gagal `update()` karena penjaga kekekalan tetap kotor. Di tes, lakukan perubahan yang diizinkan lewat `$model->fresh()`.
- JSON mengembalikan `5` untuk `5.0`. Bandingkan nilai asli di tes dengan `toEqual`, bukan `toBe`.
- Item evaluasi disisipkan massal lewat `insert()` sehingga tidak melewati penjaga model. Jangan memakai jalur itu untuk mengubah data yang sudah tersimpan.
- Kelompok Terkunci menurut kolom `lock_type`. Order Dikerjakan yang kolomnya masih `none` (hasil impor atau ubah status tangan) dihitung Biasa sampai P7-08 selesai.

## Fase 7

- Widget di folder `app/Filament/Widgets` otomatis masuk ke dasbor. Widget yang hanya untuk satu halaman harus memakai `$isDiscovered = false`.
- Widget yang dipasang di halaman tampil sebagai komponen Livewire terpisah dan dimuat belakangan. Di tes pakai `assertSeeLivewire(...)` untuk halamannya dan `Livewire::test(Widget::class, ['record' => ...])` untuk isinya. Widget footer halaman resource tampil sesudah relation manager, jadi grafik di tengah halaman dipasang lewat `Livewire::make` di infolist.
- `forceFill` pada atribut yang belum dimuat mengubahnya dari tidak ada menjadi null, dan riwayat order menulis "kosong menjadi kosong". Kosongkan kolom hanya bila isinya tidak null.
- Aksi yang ditulis ke log secara eksplisit (kunci order) memakai `saveQuietly`, kalau tidak trait `LogsActivity` menulis baris `order.updated` kedua.
- Factory memberi harga dan urgensi pelanggan acak, sehingga skor dan peringkat ikut acak. Tes yang bergantung pada urutan peringkat harus menetapkan `unit_price`, `quantity`, dan urgensi (`Customer::factory()->urgency(3)`).
- Callout peringatan slot tampil dua kali (di atas tab dan di tab Peringatan). Tes yang memeriksa keberadaannya harus menghitung kemunculannya, kalau tidak merusak salah satunya tidak terdeteksi.
- Server pengembangan bisa lambat: pemberitahuan sesudah aksi bisa muncul sekitar tiga detik kemudian. Skrip Playwright perlu menunggu cukup lama sebelum membaca `.fi-no-notification` atau mengambil gambar.
- Mengukur muat tidaknya tabel di 1366 piksel: bandingkan `scrollWidth` dan `clientWidth` elemen `.fi-ta-content-ctn` (harus sama). Lebar halaman (`documentElement`) tidak menangkap tabel yang bergeser di dalam kartunya.
- Sumbu peringkat di grafik dibalik (peringkat 1 di atas), jadi garis yang naik berarti order naik di daftar.

## Tangkapan layar dan docx

- Batas masuk 5 kali per menit. Skrip tangkap layar yang dijalankan beruntun harus diberi jeda sekitar 70 detik, atau login akan ditolak.
- Mengambil tangkapan layar halaman penuh (`fullPage`) menaruh bilah atas yang menempel di tengah gambar. Untuk halaman panjang, pakai jendela yang cukup tinggi (lihat `tangkap-layar/ambil-ulang-halaman-panjang.js`).
- Nomor halaman daftar isi docx dibaca dari PDF hasil konversi. Jangan memakai PDF lama bila konversi gagal, hapus dulu PDF-nya. `bangun.sh` sudah melakukannya.
- Dua build docx yang berjalan bersamaan saling menimpa berkas. Jalankan satu per satu.
- Tangkapan layar harus diambil sesudah `php artisan migrate:fresh --seed`, supaya angka di gambar sama dengan angka di teks panduan.
- Membangun docx butuh LibreOffice dan PyMuPDF (`pip install pymupdf`). Di mesin pemilik proyek keduanya baru dipasang saat Fase 6 (LibreOffice lewat winget). Jangan menjalankan `bangun.sh` selagi pemasang LibreOffice masih berjalan, percobaan pertama berakhir tanpa membangun apa pun.
- `tangkap-layar/ambil-ulang-daftar-order.js` mengambil ulang gambar daftar order Fase 2 dan 5 (09, 09a, 10, 11, 17, 18, 40) dan harus dijalankan tepat sesudah `migrate:fresh --seed`, sebelum ada evaluasi. `fase7.js` memakai akun semua peran, menyimpan dua evaluasi, mengunci order, dan mengambil ulang gambar 34 dan 35. Jalankan `migrate:fresh --seed` sesudahnya.
- Tes Pest memuat semua berkas, jadi fungsi bantu global (`dashOrder`, `repOrder`, `reportManyOrders`, ...) harus bernama unik per berkas. Fungsi dari berkas lain tidak ada bila hanya satu berkas dijalankan, jadi jangan dipakai silang.
- `assertCountTableRecords` pada tabel Filament menghitung dengan kueri hitung yang mengabaikan `limit()`. Untuk widget yang memakai `limit`, uji baris yang tampil dengan `assertCanSeeTableRecords` dan `assertCanNotSeeTableRecords`.
- Hook `saving` model order mencabut label Prioritas Utama saat status menjadi Selesai atau Dibatalkan dengan menyetel `priority` ke Normal. Pada model basi yang sudah berisi Normal di memori, nilai itu tidak dianggap berubah dan label di basis data tidak tercabut. Di aplikasi model selalu baru dimuat. Di tes, pakai `$order->fresh()->update(...)`.
- `Livewire::test()` pada halaman dengan `DatePicker` mengisi nilai lewat `fillForm`. Di peramban (Playwright) kolom tanggal hanya bisa dipilih dari kalender. Untuk tangkapan layar, isi nilainya lewat `window.Livewire.all()` lalu `$wire.set(...)` (lihat `fase8b.js`).
- Widget Filament lazy tampil kosong pada tangkapan layar yang diambil terlalu cepat. Widget dasbor dibuat `$isLazy = false`. Grafik (Chart.js) tetap butuh sekitar 1 detik setelah halaman muat.
- Kelas Tailwind yang hanya ada di view Blade buatan sendiri (misalnya `grid-cols-2`) tidak ada di CSS Filament yang sudah dikompilasi. Untuk view widget dan laporan, pakai blok `<style>` dengan variabel `--gray-*` seperti di `table.blade.php` dan `latest-evaluation.blade.php`.
- dompdf: pakai `@page` dan CSS sederhana (tanpa variabel CSS, flex, atau grid), logo sebagai data URI, `thead { display: table-header-group }` agar judul kolom berulang. Nomor halaman digambar lewat `$canvas->page_text()` setelah `render()`, jangan memakai skrip PHP di dalam view.
- Heredoc besar di Bash (lebih dari beberapa puluh baris, atau memuat tanda petik rumit) gagal dengan "unexpected EOF". Tulis berkas lewat alat Write.
- XAMPP MariaDB tidak menyala sendiri setelah komputer dimulai ulang. Bila semua tes gagal dengan "connection refused", nyalakan `C:\xampp\mysql\bin\mysqld.exe` atau lewat panel XAMPP. Gejalanya bisa juga tes yang diam bermenit-menit tanpa keluaran apa pun. Folder MySQL Laragon di mesin ini hanya berisi `my.ini`, bukan server.
- `fase8.js` dan `fase8b.js` butuh data dari `migrate:fresh --seed` ditambah `db:seed --class=DemoEvaluationSeeder`, dan satu evaluasi yang diajukan (`EvaluationWorkflow::submit`) agar bagian Menunggu persetujuan Pimpinan terisi. `fase8b.js` mengambil ulang gambar 03 dan 16. Skrip ini menyimpan unduhan ke `tangkap-layar/berkas-uji/unduhan`. Jalankan `migrate:fresh --seed` sesudahnya.
- `fase9.js` butuh `migrate:fresh --seed`, `evaluation:run-scheduled --force`, dan satu evaluasi yang diajukan (perintah lengkap di kepala skrip). Skrip mengubah dan mengembalikan jumlah Prioritas Utama. Gambar Fase 9 bernomor 100 sampai 106 karena 90 sampai 92 sudah dipakai gambar PDF Fase 8.
- Skrip `tangkap-layar/fase6.js` mengubah data (tanggal masuk ORD-2026-0021, menonaktifkan C7, menyimpan evaluasi). Jalankan `migrate:fresh --seed` sesudahnya.
