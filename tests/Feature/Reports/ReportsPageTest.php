<?php

use App\Enums\OrderStatus;
use App\Filament\Pages\Reports;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->admin = User::factory()->admin()->create();
});

function pageEvaluation(int $orders = 4): Evaluation
{
    foreach (range(1, $orders) as $n) {
        $order = ProductionOrder::factory()->dueInDays(2 + $n * 3)->create(['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100]);

        foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
            Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => test()->criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
        }
    }

    return app(EvaluationService::class)->run(test()->manager);
}

it('opens the report page for every role and puts it in the Laporan menu group', function (): void {
    foreach ([$this->admin, $this->manager, $this->supervisor, $this->leader] as $user) {
        $this->actingAs($user)->get('/admin/laporan')->assertOk()->assertSee('Pilih laporan');
    }

    $this->actingAs($this->manager)->get('/admin')->assertSee('Laporan');
    expect(Reports::getNavigationGroup())->toBe('Laporan');
});

it('needs a login', function (): void {
    $this->get('/admin/laporan')->assertRedirect();
});

it('starts with the ranking of the newest evaluation', function (): void {
    pageEvaluation();
    $newest = pageEvaluation(2);

    $this->actingAs($this->manager);
    $page = Livewire::test(Reports::class);

    expect((int) $page->get('data.evaluation_id'))->toBe($newest->id)
        ->and($page->get('data.type'))->toBe('ranking');

    $page->assertSee('Laporan Peringkat Prioritas Produksi')->assertSee($newest->name);
});

it('shows a clear message instead of a report when there is no evaluation yet', function (): void {
    $this->actingAs($this->manager);

    Livewire::test(Reports::class)
        ->assertSee('Belum ada evaluasi')
        ->assertDontSee('Dicetak oleh');
});

it('shows the same rows on the screen as in the document', function (): void {
    $evaluation = pageEvaluation(5);
    $this->actingAs($this->supervisor);

    $page = Livewire::test(Reports::class);
    $items = $evaluation->items()->get();

    foreach ($items as $item) {
        $page->assertSee($item->order_snapshot['number']);
    }

    $page->assertSee(number_format((float) $items[0]->score, 4, ',', '.'));
});

it('switches to a period report and applies the period', function (): void {
    ProductionOrder::factory()->create(['order_date' => today()->subDays(2), 'deadline_date' => today()->subDay(), 'status' => OrderStatus::Queued]);
    ProductionOrder::factory()->create(['order_date' => today()->subDays(90), 'deadline_date' => today()->subDays(60), 'status' => OrderStatus::Queued]);

    $this->actingAs($this->manager);
    $page = Livewire::test(Reports::class)
        ->fillForm(['type' => 'late', 'from' => today()->subDays(5)->toDateString(), 'to' => today()->toDateString()])
        ->assertSee('Laporan Order Terlambat')
        ->assertSee('Jumlah order terlambat');

    expect($page->instance()->document()->meta['Jumlah order terlambat'])->toBe('1');

    $page->fillForm(['from' => today()->subDays(100)->toDateString()]);
    expect($page->instance()->document()->meta['Jumlah order terlambat'])->toBe('2');
});

it('refuses a period that ends before it starts', function (): void {
    $this->actingAs($this->manager);

    $page = Livewire::test(Reports::class)
        ->fillForm(['type' => 'recap', 'from' => today()->toDateString(), 'to' => today()->subDays(3)->toDateString()])
        ->assertSee('Tanggal akhir tidak boleh sebelum tanggal awal');

    expect($page->instance()->document())->toBeNull();

    $page->callAction('downloadPdf')->assertNoFileDownloaded();
});

it('needs both dates for a period report', function (): void {
    $this->actingAs($this->manager);

    $page = Livewire::test(Reports::class)->fillForm(['type' => 'history', 'from' => null, 'to' => null]);

    expect($page->instance()->problem())->toBe('Isi tanggal awal dan tanggal akhir periode.');
});

it('downloads the report as PDF for every role', function (): void {
    $evaluation = pageEvaluation();

    foreach ([$this->admin, $this->manager, $this->supervisor, $this->leader] as $user) {
        $this->actingAs($user);
        $page = Livewire::test(Reports::class)->callAction('downloadPdf');

        $page->assertFileDownloaded();
    }

    expect(Evaluation::query()->count())->toBe(1)->and($evaluation->status->value)->toBe('draft');
});

it('downloads the report as Excel', function (): void {
    pageEvaluation();
    $this->actingAs($this->manager);

    Livewire::test(Reports::class)->callAction('downloadExcel')->assertFileDownloaded();
});

it('downloads nothing when there is nothing to report', function (): void {
    $this->actingAs($this->manager);

    Livewire::test(Reports::class)->callAction('downloadPdf')->assertNoFileDownloaded();
    Livewire::test(Reports::class)->callAction('downloadExcel')->assertNoFileDownloaded();
});

it('hides the rupiah criterion from the supervisor in the calculation preview', function (): void {
    pageEvaluation();
    $this->actingAs($this->supervisor);

    $page = Livewire::test(Reports::class)->fillForm(['type' => 'calculation']);
    $document = $page->instance()->document();

    expect(collect($document->tables)->flatMap(fn ($table) => $table->headings())->all())->not->toContain('C6');

    $this->actingAs($this->manager);
    $document = Livewire::test(Reports::class)->fillForm(['type' => 'calculation'])->instance()->document();

    expect(collect($document->tables)->flatMap(fn ($table) => $table->headings())->all())->toContain('C6');
});
