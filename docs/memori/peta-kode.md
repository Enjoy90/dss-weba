# Peta kode

Pakai berkas ini sebagai ganti memindai folder `app`. Nama kelas, PHPDoc, dan komentar kode memakai bahasa Inggris, teks yang tampil ke pengguna memakai bahasa Indonesia. Panel Filament ada di `/admin`, tidak ada rute web lain yang berarti dan tidak ada API.

## Alur data singkat

Order produksi masuk lewat formulir atau impor Excel. Tiap order punya satu baris penilaian per kriteria. `AssessmentResolver` menentukan nilai asli tiap kriteria (otomatis atau manual), `ScaleRules` mengubahnya ke skala 1 sampai 5, dan `CriterionWeights` menjaga bobot. Perhitungan SAW dan evaluasi belum dibuat (Fase 6).

## Enum (`app/Enums`)

- `UserRole`: admin, manager, supervisor, leader (Pimpinan).
- `OrderStatus`: queued (Antri), in_progress (Dikerjakan), done, canceled.
- `OrderPriority`: normal, top (Prioritas Utama, diisi evaluasi nanti).
- `LockType`: none, auto, manual.
- `CriterionDirection`: higher_first, lower_first (untuk validasi arah skala).
- `ValueSource`: deadline (C1), customer_urgency (C2), order_value (C6), waiting (C7), manual (C3, C4, C5).
- `AssessmentSource`: automatic, manual, override, empty. `Completeness`: complete, incomplete, stale.
- `ImportMode`: add, upsert.

## Model (`app/Models`)

- `User`: peran, `isManager()` dan sejenisnya, `canSeeOrderValue()` (false untuk Supervisor), aturan Admin terakhir.
- `Customer` (urgensi 1 sampai 5), `Product`, `ProductionOrder` (soft delete, scope late, nearDeadline, locked, open; kolom kunci lock_type, lock_reason, locked_by, locked_at, lock_until).
- `Criterion` (kode C1 dan seterusnya, weight, importance, direction, value_source, is_active; saat disimpan memicu `CriterionWeights::refresh()`), `CriterionScale` (score, lower_bound, upper_bound).
- `Assessment` (satu per order per kriteria, raw_value, is_overridden, override_reason, value_updated_at), `AssessmentHistory`.
- `Setting` (`Setting::get($key, $default)`, `Setting::put`), kunci yang dipakai: `weight_mode`, `stale_value_days`.
- `ActivityLog` (riwayat perubahan, `changeLines(hidePrices)`), dipasang lewat trait `Concerns/LogsActivity`.
- Morph map didaftarkan di `AppServiceProvider` ('production_orders', 'criteria'). Setiap model yang masuk ke `activity_logs` harus ada di morph map.

## Layanan (`app/Services`)

- `CriterionWeights`: bobot dalam basis poin, dua mode, `setDirect`, `useImportance`, `summary()` (status siap dihitung).
- `ScaleRules`: `convert`, `validate` (tumpang tindih, bolong, arah), `rowsFromScales`.
- `AssessmentResolver`: `resolve`, `resolveAll`, `completeness`, `orderIdsWith`. Nilai otomatis dihitung di sini setiap dipanggil. Kedaluwarsa dari `stale_value_days`.
- `AssessmentWriter`: `setManual`, `override`, `clearOverride`, `validateValue`. Satu-satunya jalan menulis nilai, ikut mencatat riwayat.
- `ResolvedValue` (objek nilai), `AssessmentFormatter` (format tampilan nilai dan satuan).
- `Import/`: `OrderSheetReader` (baca xlsx, batas 1.000 baris), `OrderImporter` (`analyze` lalu `commit` dalam satu transaksi), `RowResult`, `ImportReport`.
- `Export/OrderSheetWriter`: `writeTemplate`, `writeSample`, `writeOrders`.

## Hak akses (`app/Policies`)

Satu Policy per model: User, Customer, Product, ProductionOrder (termasuk `changeStatus`, `import`, `export`), Criterion (termasuk `editScale`), Assessment (`updateValue`, `viewCriterion`, `SUPERVISOR_CRITERIA` = C4 dan C5). Policy yang baru wajib dibuat bersamaan dengan resource-nya.

## Filament (`app/Filament`)

- Panel: `app/Providers/Filament/AdminPanelProvider.php`. Merek, grup menu (Produksi, Prioritas, Data Master, Laporan, Pengaturan), sidebar bisa dipersempit, CSS badge berwarna penuh lewat render hook, halaman masuk dan profil khusus.
- Resource: Users, Customers, Products (modal sederhana), ProductionOrders (daftar dengan tombol Impor dan Ekspor, detail dengan bagian Penilaian, relation manager Riwayat nilai dan Riwayat perubahan), Criteria (ManageCriteria dengan aksi Atur bobot langsung dan Hitung dari tingkat kepentingan).
- Halaman khusus: `ScaleSettings` (slug skala-nilai), `AssessmentMatrix` (slug penilaian-order), `ImportOrders` (slug impor-order), `Auth/Login`, `Auth/EditProfile`.
- Pola yang dipakai: aksi baris dikelompokkan dalam `ActionGroup` di sisi kiri (`RecordActionsPosition::BeforeColumns`), kolom bawaan disembunyikan agar muat di 1366 piksel, sel matriks memakai aksi kolom, `#[Exposed]` dan `#[Unexposed]` untuk properti Livewire, `FileUpload` dengan `storeFiles(false)` di impor.

## Basis data (`database`)

Migrasi: users, cache, jobs, customers, products, production_orders, activity_logs, criteria, criterion_scales, settings, assessments, assessment_histories. Tabel evaluasi dibuat di Fase 6 (`evaluations`, `evaluation_items`, skema di PRD Bab 10).

Seeder (urutan di `DatabaseSeeder`): `DemoUserSeeder` (4 akun), `DemoMasterSeeder` (5 pelanggan PLG-001 sampai 005, 4 produk PRD-001 sampai 004), `DemoOrderSeeder` (25 order, tanggal relatif hari ini), `CriteriaSeeder` (C1 sampai C7 dengan skala contoh, mode bobot direct), `DemoAssessmentSeeder` (57 penilaian). Perintah `php artisan orders:sample-import {path}` membuat berkas Excel uji berisi 20 baris benar dan 2 rusak.

## Tes (`tests`)

Pest 4 dengan `RefreshDatabase`, basis data `dss_test` (MariaDB). Satu folder per area di `tests/Feature` (Auth, Users, Master, Orders, Criteria, Assessments, Import) dan `tests/Unit/ScaleRulesTest.php`. Pembantu di `tests/Pest.php`: `makeXlsx`, `templateHeader`, `templateRow`. Total saat ini 282 tes, 1.332 assertion.

Pola tes: login dengan `$this->actingAs(User::factory()->manager()->create())`, lalu `Livewire::test(KelasHalaman::class, [...])`. Buat data lewat factory di `database/factories` dan pakai state yang sudah ada sebelum menulis manual: User (`admin`, `manager`, `supervisor`, `leader`, `inactive`), ProductionOrder (`status`, `inProgress`, `enteredDaysAgo`, `dueInDays`, `late`), Customer (`urgency`, `inactive`), Criterion (`importance`, `inactive`, `lowerFirst`), Assessment (`updatedDaysAgo`).

## Yang belum ada (jangan dikira sudah)

Mesin SAW, evaluasi, peringkat, persetujuan, penerapan prioritas, kunci otomatis saat ganti status, dasbor berisi grafik, laporan, halaman log, halaman pengaturan, penjadwalan. Semua ada di TODO Fase 6 sampai 11.
