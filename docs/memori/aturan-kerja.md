# Aturan kerja

Cara bekerja yang diminta pemilik proyek. Aturan ini lahir dari koreksi nyata selama pembangunan Fase 0 sampai 5, jadi ikuti apa adanya.

## Sikap umum

- Hati-hati dan jangan melebih-lebihkan. Kerjakan yang diminta dan yang sudah disepakati di PRD dan TODO, tidak lebih.
- Jangan menambah intuisi sendiri. Fitur, kolom, atau perilaku yang belum disepakati tidak dibuat. Bila ada celah di spesifikasi, ambil keputusan sebagai perancang yang profesional, tulis alasannya di `keputusan-desain.md`, dan sebut keputusan itu saat melapor supaya pemilik proyek bisa menolaknya.
- Bila harus bertanya, beri satu rekomendasi yang paling masuk akal dan bisa diterapkan, bukan daftar pilihan terbuka.
- Pikirkan UX tiap halaman: keadaan kosong, pesan galat yang menjelaskan cara memperbaiki, urutan kolom, dan tampilan di layar laptop 1366 piksel. Contoh nyata: tabel order terpotong di 1366 piksel, diperbaiki dengan menyembunyikan kolom Masuk, Menunggu, dan Prioritas secara bawaan dan memindahkan menu aksi ke kiri.
- Validasi semuanya. Setiap masukan diperiksa di server, setiap aksi dijaga Policy, dan batasnya diuji.

## Pengujian

1. `php artisan test --compact` harus hijau sebelum melapor selesai.
2. Aturan yang penting diuji dengan mutasi. Rusakkan aturan itu sebentar (misalnya batas 1.000 baris, atau cek hak akses), jalankan tesnya, pastikan ada yang gagal, lalu pulihkan. Tes yang tetap hijau saat aturannya rusak berarti tesnya lemah.
3. Setiap fitur diuji seperti sistem berjalan dengan data demo, di peramban sungguhan (Playwright dengan Edge). Halaman tidak boleh kosong saat dibuka. Cek juga keempat peran: yang tidak berhak harus ditolak.
4. Setelah selesai uji, kembalikan data demo ke kondisi awal dengan `php artisan migrate:fresh --seed`.
5. Format dengan `vendor/bin/pint --dirty` sebelum selesai.
6. Jangan membuat skrip verifikasi sekali pakai bila tes sudah membuktikan hal yang sama.

## Alur satu fase

1. Baca tugas fase itu di `TODO-Pembangunan-SPK.md` dan bab terkait di PRD.
2. Kerjakan tugas berurutan. Tandai `[x]` hanya bila kriteria "Selesai bila" benar-benar terpenuhi.
3. Tulis tes bersama kodenya, bukan sesudahnya.
4. Isi dan periksa data demo, buka halamannya di peramban.
5. Tambahkan satu bab di `Panduan-Penggunaan-dan-Pengujian-SPK.md` dengan pola: Tujuan, Masuk sebagai, Langkah bernomor, Hasil yang diharapkan, dan cara mengembalikan data bila uji mengubah data. Tangkapan layar harus nyata dari peramban (viewport 1600x900), bukan gambar rekaan. Bangun ulang docx lewat `docs/alat-dokumen`.
6. Perbarui tabel ringkasan dan catatan kemajuan di TODO, serta bagian Status di `AGENTS.md`.
7. Lapor singkat, lalu berhenti. Fase berikutnya hanya dimulai setelah pemilik proyek bilang lanjut.

## Memelihara memori ini

Berkas di `docs/memori/` dan `AGENTS.md` dibaca AI dan developer lain supaya tidak perlu memindai repo, jadi harus tetap benar. Tiap fase selesai: perbarui bagian Status di `AGENTS.md`, tambahkan kelas atau halaman baru ke `peta-kode.md`, keputusan baru ke `keputusan-desain.md`, dan jebakan baru ke `jebakan-teknis.md`. Pindahkan item dari "Sengaja belum dibuat" bila sudah dikerjakan. Jangan menulis hal yang bisa dibaca langsung dari kode (isi fungsi, daftar kolom lengkap). Tulis letaknya, tujuannya, dan alasannya.

## Gaya tulisan

Berlaku untuk balasan, kode, komentar, pesan commit, dan semua dokumen.

- Bahasa Indonesia sehari-hari yang wajar, kalimat pendek dan lugas.
- Jangan pakai em dash, en dash, atau titik tengah sebagai pemisah. Pakai koma, titik, atau tanda kurung. Rentang angka pakai tanda hubung atau kata "sampai".
- Hindari pola khas tulisan AI: kalimat "bukan X, tetapi Y", titik dua lalu pengungkapan, label dalam tanda kutip, dan huruf tebal berlebihan.
- Teks antarmuka berbahasa Indonesia penuh.

## Tampilan

- Badge berwarna penuh (solid), bukan transparan. Sudah dipasang lewat render hook di `AdminPanelProvider`.
- Font tetap bawaan Filament. Jangan menambah `font-family` atau mengganti font panel.
- Jangan menghapus warna dan kartu secara drastis demi "terlihat tidak seperti AI". Bila diminta, ubah sedikit demi sedikit.
- Supervisor tidak boleh melihat nilai rupiah (harga satuan, nilai order, kriteria C6) di halaman, tabel, ekspor, maupun log.

## Dokumen docx

- Tampil profesional, tanpa warna biru, font ukuran 12, tabel seperlunya, tidak ada halaman kosong.
- Bangun dari berkas markdown, jangan menyunting docx dengan tangan. Panduan lengkap di `docs/alat-dokumen/README.md`.

## Git

- Commit dan push hanya atas persetujuan pemilik proyek.
- Pesan commit tanpa baris co-author AI.
- Satu commit satu maksud. Awali dengan jenis perubahan: `feat:`, `fix:`, `test:`, `docs:`, `chore:`.
- `.env` tidak pernah masuk repo. Kata sandi demo `password` hanya untuk lokal, ganti sebelum dipasang di server.
