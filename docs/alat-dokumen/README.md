# Alat dokumen

Perangkat untuk membangun docx panduan dan PRD dari berkas markdown di akar repo, dan untuk mengambil tangkapan layar aplikasi. Terpisah dari aplikasi Laravel, punya `package.json` sendiri.

Aturan: markdown adalah sumber. Ubah `.md`, lalu bangun ulang docx. Jangan menyunting docx dengan tangan.

## Kebutuhan

- Node.js 18 atau lebih baru. Jalankan `npm install` di folder ini.
- Python 3 dengan PyMuPDF: `pip install pymupdf`.
- LibreOffice. Skrip mencari `soffice` di PATH atau di `C:\Program Files\LibreOffice`. Bila lain, set variabel `SOFFICE`.
- Untuk tangkapan layar: Microsoft Edge (atau set `BROWSER_PATH` ke peramban lain berbasis Chromium) dan aplikasi yang sedang berjalan.

## Membangun docx

```
bash bangun.sh panduan    # Panduan-Penggunaan-dan-Pengujian-SPK.md  ->  .docx di akar repo
bash bangun.sh prd        # PRD-SPK-Prioritas-Produksi-SAW.md        ->  .docx di akar repo
```

Skrip membangun tiga kali karena nomor halaman daftar isi dibaca dari PDF hasil putaran sebelumnya, lalu memeriksa nomor itu sudah stabil. Hasil akhir: jumlah halaman dan, bila skill docx Claude terpasang, hasil validasi skema Word. Berkas perantara (`doc.docx`, `*.pdf`, `pages-*.json`) diabaikan git.

Format markdown yang dikenali: `#` sebagai judul dokumen, `##` sebagai bab (muncul di daftar isi), `###` dan `####` sebagai anak bab, paragraf, daftar bernomor dan berpoin, tabel, dan gambar dengan sintaks `![Keterangan gambar](nama-berkas.png)` yang berkasnya ada di `gambar-panduan`. Contoh terbaik adalah menyalin pola bab terakhir.

## Tangkapan layar

Gambar panduan ada di `gambar-panduan` dengan nomor berurutan per fase (01 sampai 18 fase 1 dan 2, 20 sampai 26 fase 3, 30 sampai 36 fase 4, 40 sampai 44 fase 5). Gambar diagram PRD ada di `gambar-prd`, dibuat oleh `diagram-prd.py` (butuh matplotlib).

Cara mengambil gambar baru untuk fase berikutnya:

1. `php artisan migrate:fresh --seed` lalu `php artisan serve --port=8001`.
2. Salin salah satu skrip di `tangkap-layar` (misalnya `fase5.js`) sebagai `fase6.js`, ganti halaman dan nama gambar. Pola: login, buka halaman, tunggu, simpan `NN-nama.png`.
3. `node tangkap-layar/fase6.js`. Alamat aplikasi bisa diubah lewat `APP_URL`. Viewport 1600x900 atau 1600x1100.
4. Batas login 5 kali per menit: beri jeda sekitar 70 detik bila menjalankan beberapa skrip beruntun.
5. Berkas Excel uji untuk skrip impor dibuat dengan `php artisan orders:sample-import tangkap-layar/berkas-uji/contoh-20-order.xlsx`.

## Menambah bab panduan

Pola tiap uji: Tujuan, Masuk sebagai, Langkah bernomor, Hasil yang diharapkan, dan cara mengembalikan data bila uji mengubah data. Naikkan nomor versi dan cakupan di tabel awal dokumen, perbarui jumlah tes di bab pengujian otomatis, dan perbarui lampiran hak akses bila ada fitur baru. Lalu `bash bangun.sh panduan`.
