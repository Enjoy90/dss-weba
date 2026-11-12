<?php

use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use Database\Seeders\CriteriaSeeder;

/*
 * P10-01: one place that checks every page and every policy ability against the access table of PRD chapter 9
 * (summarised in the guide, chapter 14). A role that is not listed must be refused.
 */

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->users = [
        'admin' => User::factory()->admin()->create(),
        'manager' => User::factory()->manager()->create(),
        'supervisor' => User::factory()->supervisor()->create(),
        'leader' => User::factory()->leader()->create(),
    ];
    $this->order = ProductionOrder::factory()->create();
    $this->evaluation = app(EvaluationService::class)->run($this->users['manager'], acknowledgeWarnings: true);
});

const ALL_ROLES = ['admin', 'manager', 'supervisor', 'leader'];

/**
 * Every page of the panel with the roles that may open it.
 *
 * @return array<string, array{0: string, 1: array<int, string>}>
 */
function accessPages(): array
{
    return [
        'Dasbor' => ['/admin', ALL_ROLES],
        'Profil' => ['/admin/profile', ALL_ROLES],
        'Pengguna' => ['/admin/users', ['admin']],
        'Pelanggan' => ['/admin/customers', ALL_ROLES],
        'Produk' => ['/admin/products', ALL_ROLES],
        'Order Produksi' => ['/admin/production-orders', ALL_ROLES],
        'Detail order' => ['/admin/production-orders/{order}', ALL_ROLES],
        'Tambah order' => ['/admin/production-orders/create', ['manager']],
        'Ubah order' => ['/admin/production-orders/{order}/edit', ['manager']],
        'Impor Order' => ['/admin/impor-order', ['admin', 'manager']],
        'Kriteria dan Bobot' => ['/admin/criteria', ['admin', 'manager', 'leader']],
        'Skala Nilai' => ['/admin/skala-nilai', ['admin', 'manager', 'leader']],
        'Penilaian Order' => ['/admin/penilaian-order', ['manager', 'supervisor']],
        'Jalankan Perhitungan' => ['/admin/jalankan-perhitungan', ['manager']],
        'Hasil Evaluasi' => ['/admin/evaluations', ALL_ROLES],
        'Rincian evaluasi' => ['/admin/evaluations/{evaluation}', ALL_ROLES],
        'Bandingkan evaluasi' => ['/admin/bandingkan-evaluasi', ALL_ROLES],
        'Laporan' => ['/admin/laporan', ALL_ROLES],
        'Log Aktivitas' => ['/admin/log-aktivitas', ['admin', 'leader']],
        'Pengaturan' => ['/admin/pengaturan', ['admin', 'leader']],
    ];
}

function accessUrl(string $path): string
{
    return str_replace(['{order}', '{evaluation}'], [(string) test()->order->id, (string) test()->evaluation->id], $path);
}

it('opens each page only for the roles that may use it', function (string $path, array $allowed): void {
    foreach (ALL_ROLES as $role) {
        $response = $this->actingAs($this->users[$role])->get(accessUrl($path));

        in_array($role, $allowed, true)
            ? $response->assertOk()
            : $response->assertForbidden();
    }
})->with(accessPages());

it('sends a guest to the login page from every page', function (string $path): void {
    auth()->logout();

    $this->get(accessUrl($path))->assertRedirect('/admin/login');
})->with(array_map(fn (array $page): array => [$page[0]], accessPages()));

it('refuses a deactivated user that still has a session on every page', function (string $path): void {
    $user = User::factory()->manager()->inactive()->create();

    $this->actingAs($user)->get(accessUrl($path))->assertForbidden();
})->with(array_map(fn (array $page): array => [$page[0]], accessPages()));

/**
 * Every policy ability with the roles that hold it. The subject is a class name or a callable that makes a record.
 *
 * @return array<string, array{0: string, 1: Closure, 2: array<int, string>}>
 */
function accessAbilities(): array
{
    $order = fn () => test()->order;
    $evaluation = fn () => test()->evaluation;
    $criterion = fn () => Criterion::query()->where('code', 'C3')->first();
    $user = fn () => test()->users['manager'];

    return [
        'Pengguna: lihat daftar' => ['viewAny', fn () => User::class, ['admin']],
        'Pengguna: tambah' => ['create', fn () => User::class, ['admin']],
        'Pengguna: ubah' => ['update', $user, ['admin']],
        'Pengguna: hapus' => ['delete', $user, []],
        'Pelanggan: lihat' => ['viewAny', fn () => Customer::class, ALL_ROLES],
        'Pelanggan: tambah' => ['create', fn () => Customer::class, ['admin', 'manager']],
        'Pelanggan: ubah' => ['update', fn () => Customer::factory()->create(), ['admin', 'manager']],
        'Pelanggan: hapus' => ['delete', fn () => Customer::factory()->create(), []],
        'Produk: tambah' => ['create', fn () => Product::class, ['admin', 'manager']],
        'Produk: hapus' => ['delete', fn () => Product::factory()->create(), []],
        'Order: lihat' => ['view', $order, ALL_ROLES],
        'Order: tambah' => ['create', fn () => ProductionOrder::class, ['manager']],
        'Order: ubah' => ['update', $order, ['manager']],
        'Order: ubah status' => ['changeStatus', $order, ['manager', 'supervisor']],
        'Order: kunci manual' => ['lock', $order, ['manager', 'leader']],
        'Order: impor' => ['import', fn () => ProductionOrder::class, ['admin', 'manager']],
        'Order: ekspor' => ['export', fn () => ProductionOrder::class, ['admin', 'manager']],
        'Order: hapus' => ['delete', $order, []],
        'Kriteria: lihat' => ['viewAny', fn () => Criterion::class, ['admin', 'manager', 'leader']],
        'Kriteria: tambah' => ['create', fn () => Criterion::class, ['manager', 'leader']],
        'Kriteria: ubah dan atur bobot' => ['update', $criterion, ['manager', 'leader']],
        'Kriteria: ubah skala nilai' => ['editScale', fn () => Criterion::class, ['manager']],
        'Kriteria: hapus' => ['delete', $criterion, []],
        'Penilaian: buka halaman' => ['viewAny', fn () => Assessment::class, ['manager', 'supervisor']],
        'Evaluasi: lihat' => ['view', $evaluation, ALL_ROLES],
        'Evaluasi: hitung' => ['create', fn () => Evaluation::class, ['manager']],
        'Evaluasi: ajukan' => ['submit', $evaluation, ['manager']],
        'Evaluasi: setujui' => ['approve', $evaluation, ['leader']],
        'Evaluasi: tolak' => ['reject', $evaluation, ['leader']],
        'Evaluasi: terapkan' => ['apply', $evaluation, ['manager', 'leader']],
        'Evaluasi: sesuaikan peringkat' => ['adjust', $evaluation, ['manager', 'leader']],
        'Evaluasi: batalkan' => ['cancel', $evaluation, ['manager', 'leader']],
        'Evaluasi: ubah' => ['update', $evaluation, []],
        'Evaluasi: hapus' => ['delete', $evaluation, []],
        'Log: ubah' => ['update', fn () => ActivityLog::record('auth.login', null, null, null), []],
        'Log: hapus' => ['delete', fn () => ActivityLog::record('auth.login', null, null, null), []],
    ];
}

it('grants each policy ability only to the roles in the access table', function (string $ability, Closure $subject, array $allowed): void {
    $target = $subject();

    foreach (ALL_ROLES as $role) {
        expect($this->users[$role]->can($ability, $target))->toBe(in_array($role, $allowed, true), "{$role} {$ability}");
    }
})->with(accessAbilities());

it('lets each role write only the assessment values in the access table', function (): void {
    $writable = [
        'manager' => ['C2', 'C3', 'C4', 'C5', 'C6'],
        'supervisor' => ['C4', 'C5'],
        'admin' => [],
        'leader' => [],
    ];

    foreach (Criterion::query()->orderBy('code')->get() as $criterion) {
        foreach ($writable as $role => $codes) {
            expect($this->users[$role]->can('updateValue', [Assessment::class, $criterion]))
                ->toBe(in_array($criterion->code, $codes, true), "{$role} {$criterion->code}");
        }
    }
});

it('hides the order value in rupiah from the supervisor only', function (): void {
    $c6 = Criterion::query()->where('code', 'C6')->first();

    foreach (ALL_ROLES as $role) {
        expect($this->users[$role]->can('viewCriterion', [Assessment::class, $c6]))->toBe($role !== 'supervisor')
            ->and($this->users[$role]->canSeeOrderValue())->toBe($role !== 'supervisor');
    }
});
