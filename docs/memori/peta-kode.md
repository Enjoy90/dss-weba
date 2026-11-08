# Peta kode

Pakai berkas ini sebagai ganti memindai folder `app`. Nama kelas, PHPDoc, dan komentar kode memakai bahasa Inggris, teks yang tampil ke pengguna memakai bahasa Indonesia. Panel Filament ada di `/admin`, tidak ada rute web lain yang berarti dan tidak ada API.

## Alur data singkat

Order produksi masuk lewat formulir atau impor Excel. Tiap order punya satu baris penilaian per kriteria. `AssessmentResolver` menentukan nilai asli tiap kriteria (otomatis atau manual), `ScaleRules` mengubahnya ke skala 1 sampai 5, dan `CriterionWeights` menjaga bobot. `EvaluationService` mengumpulkan nilai lewat `AssessmentResolver`, `SawCalculator` menghitung skor, `PriorityRanker` menyusun peringkat sistem dan peringkat akhir berkelompok, lalu hasilnya disimpan sebagai `Evaluation` dan `EvaluationItem`.

## Enum (`app/Enums`)

- `UserRole`: admin, manager, supervisor, leader (Pimpinan).
- `OrderStatus`: queued (Antri), in_progress (Dikerjakan), done, canceled.
- `OrderPriority`: normal, top (Prioritas Utama, diisi evaluasi nanti).
- `LockType`: none, auto, manual.
- `CriterionDirection`: higher_first, lower_first (untuk validasi arah skala).
- `ValueSource`: deadline (C1), customer_urgency (C2), order_value (C6), waiting (C7), manual (C3, C4, C5).
- `AssessmentSource`: automatic, manual, override, empty. `Completeness`: complete, incomplete, stale.
- `ImportMode`: add, upsert.
- `RankGroup`: locked (Terkunci), escalated (Eskalasi), regular (Biasa). `EvaluationStatus`: draft, submitted, approved, applied, canceled. `EvaluationTrigger`: manual, scheduled.

## Model (`app/Models`)

- `User`: peran, `isManager()` dan sejenisnya, `canSeeOrderValue()` (false untuk Supervisor), aturan Admin terakhir.
- `Customer` (urgensi 1 sampai 5), `Product`, `ProductionOrder` (soft delete, scope late, nearDeadline, locked, open; kolom kunci lock_type, lock_reason, locked_by, locked_at, lock_until).
- `Criterion` (kode C1 dan seterusnya, weight, importance, direction, value_source, is_active; saat disimpan memicu `CriterionWeights::refresh()`), `CriterionScale` (score, lower_bound, upper_bound).
- `Assessment` (satu per order per kriteria, raw_value, is_overridden, override_reason, value_updated_at), `AssessmentHistory`.
- `Setting` (`Setting::get($key, $default)`, `Setting::put`), kunci yang dipakai: `weight_mode`, `stale_value_days`.
- `Evaluation` (satu perhitungan: salinan kriteria dan pengaturan dalam JSON, `evaluated_at` tanpa ON UPDATE, tidak bisa diubah atau dihapus kecuali kolom alur status) dan `EvaluationItem` (satu order per evaluasi: nilai asli, skala, normalisasi, w x r, skor, `system_rank`, `final_rank`, `previous_rank`, `waiting_days`, `rank_group`, `warnings`; hanya `final_rank` dan `adjustment_reason` yang boleh berubah). `ProductionOrder::lockIsActive()` memutuskan kunci masih berlaku pada suatu hari.
- `ActivityLog` (riwayat perubahan, `changeLines(hidePrices)`), dipasang lewat trait `Concerns/LogsActivity`.
- Morph map didaftarkan di `AppServiceProvider` ('production_orders', 'criteria', 'evaluations'). Setiap model yang masuk ke `activity_logs` harus ada di morph map.

## Layanan (`app/Services`)

- `CriterionWeights`: bobot dalam basis poin, dua mode, `setDirect`, `useImportance`, `summary()` (status siap dihitung).
- `ScaleRules`: `convert`, `validate` (tumpang tindih, bolong, arah), `rowsFromScales`.
- `AssessmentResolver`: `resolve`, `resolveAll`, `completeness`, `orderIdsWith`. Nilai otomatis dihitung di sini setiap dipanggil. Kedaluwarsa dari `stale_value_days`.
- `AssessmentWriter`: `setManual`, `override`, `clearOverride`, `validateValue`. Satu-satunya jalan menulis nilai, ikut mencatat riwayat.
- `ResolvedValue` (objek nilai), `AssessmentFormatter` (format tampilan nilai dan satuan).
- `Import/`: `OrderSheetReader` (baca xlsx, batas 1.000 baris), `OrderImporter` (`analyze` lalu `commit` dalam satu transaksi), `RowResult`, `ImportReport`.
- `Export/OrderSheetWriter`: `writeTemplate`, `writeSample`, `writeOrders`.
- `Saw/` (murni, tanpa basis data): `SawCalculator` (`calculate(bobot, skala)` mengembalikan `SawResult`), `PriorityRanker` (`rank(kandidat, batasTunggu)` mengembalikan `RankPlacement`), `RankCandidate`. Contoh PRD 11.6 dan 11.7 diuji di `tests/Unit`.
- `Evaluation/`: `EvaluationService` (`preflight` tanpa menyimpan, `run` dalam satu transaksi, `currentSettings`), `EvaluationPreflight` (hasil pemeriksaan beserta data yang sudah dimuat), `EvaluationBlockedException` (bobot, skala, atau order belum siap), `EvaluationNeedsConfirmationException` (order belum lengkap atau kedaluwarsa dan belum dikonfirmasi).

## Hak akses (`app/Policies`)

Satu Policy per model: User, Customer, Product, Evaluation (`create` hanya Manajer, `update` dan `delete` selalu false), ProductionOrder (termasuk `changeStatus`, `import`, `export`), Criterion (termasuk `editScale`), Assessment (`updateValue`, `viewCriterion`, `SUPERVISOR_CRITERIA` = C4 dan C5). Policy yang baru wajib dibuat bersamaan dengan resource-nya.

## Filament (`app/Filament`)

- Panel: `app/Providers/Filament/AdminPanelProvider.php`. Merek, grup menu (Produksi, Prioritas, Data Master, Laporan, Pengaturan), sidebar bisa dipersempit, CSS badge berwarna penuh lewat render hook, halaman masuk dan profil khusus.
- Resource: Users, Customers, Products (modal sederhana), ProductionOrders (daftar dengan tombol Impor dan Ekspor, detail dengan bagian Penilaian, relation manager Riwayat nilai dan Riwayat perubahan), Criteria (ManageCriteria dengan aksi Atur bobot langsung dan Hitung dari tingkat kepentingan).
- Halaman khusus: `ScaleSettings` (slug skala-nilai), `AssessmentMatrix` (slug penilaian-order), `ImportOrders` (slug impor-order), `RunEvaluation` (slug jalankan-perhitungan, grup Prioritas, hanya Manajer, memuat tabel peringkat hasil terakhir sementara sampai Fase 7), `Auth/Login`, `Auth/EditProfile`.
- Pola yang dipakai: aksi baris dikelompokkan dalam `ActionGroup` di sisi kiri (`RecordActionsPosition::BeforeColumns`), kolom bawaan disembunyikan agar muat di 1366 piksel, sel matriks memakai aksi kolom, `#[Exposed]` dan `#[Unexposed]` untuk properti Livewire, `FileUpload` dengan `storeFiles(false)` di impor.

## Basis data (`database`)

Migrasi: users, cache, jobs, customers, products, production_orders, activity_logs, criteria, criterion_scales, settings, assessments, assessment_histories, evaluations, evaluation_items (skema di PRD Bab 10).

Seeder (urutan di `DatabaseSeeder`): `DemoUserSeeder` (4 akun), `DemoMasterSeeder` (5 pelanggan PLG-001 sampai 005, 4 produk PRD-001 sampai 004), `DemoOrderSeeder` (25 order, tanggal relatif hari ini), `CriteriaSeeder` (C1 sampai C7 dengan skala contoh, mode bobot direct), `DemoAssessmentSeeder` (57 penilaian). Perintah `php artisan orders:sample-import {path}` membuat berkas Excel uji berisi 20 baris benar dan 2 rusak.

## Tes (`tests`)

Pest 4 dengan `RefreshDatabase`, basis data `dss_test` (MariaDB). Satu folder per area di `tests/Feature` (Auth, Users, Master, Orders, Criteria, Assessments, Import) dan `tests/Unit` (`ScaleRulesTest`, `SawCalculatorTest`, `PriorityRankerTest`); Evaluation untuk layanan, halaman, dan kinerja. Pembantu di `tests/Pest.php`: `makeXlsx`, `templateHeader`, `templateRow`. Total saat ini 347 tes, 1.645 assertion.

Pola tes: login dengan `$this->actingAs(User::factory()->manager()->create())`, lalu `Livewire::test(KelasHalaman::class, [...])`. Buat data lewat factory di `database/factories` dan pakai state yang sudah ada sebelum menulis manual: User (`admin`, `manager`, `supervisor`, `leader`, `inactive`), ProductionOrder (`status`, `inProgress`, `enteredDaysAgo`, `dueInDays`, `late`), Customer (`urgency`, `inactive`), Criterion (`importance`, `inactive`, `lowerFirst`), Assessment (`updatedDaysAgo`).

## Yang belum ada (jangan dikira sudah)

Daftar dan detail evaluasi, penyesuaian peringkat, persetujuan, penerapan prioritas, kunci otomatis saat ganti status, dasbor berisi grafik, laporan, halaman log, halaman pengaturan, penjadwalan. Semua ada di TODO Fase 7 sampai 11.
