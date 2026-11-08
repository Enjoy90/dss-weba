<?php

use App\Enums\ImportMode;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Import\OrderImporter;
use App\Services\Import\OrderSheetReader;
use App\Services\Import\RowResult;
use Database\Seeders\DatabaseSeeder;

it('builds the sample file the guide uses: twenty good rows and two broken ones', function (): void {
    $this->seed(DatabaseSeeder::class);

    $path = sys_get_temp_dir().'/contoh-'.uniqid().'.xlsx';

    $this->artisan('orders:sample-import', ['path' => $path])->assertSuccessful();

    $read = (new OrderSheetReader)->read($path);
    $report = app(OrderImporter::class)->analyze($read['rows'], ImportMode::AddOnly);

    expect($read['errors'])->toBe([])
        ->and($read['rows'])->toHaveCount(22)
        ->and($report->created())->toBe(20)
        ->and($report->rejected())->toBe(2);

    $broken = array_values(array_filter($report->rows, fn (RowResult $row): bool => $row->action === RowResult::ERROR));

    expect($broken[0]->row)->toBe(22)
        ->and($broken[0]->messages[0])->toContain('PLG-XYZ')
        ->and($broken[1]->row)->toBe(23)
        ->and(implode(' ', $broken[1]->messages))->toContain('Tenggat')->toContain('Bahan tersedia');

    $before = ProductionOrder::count();

    app(OrderImporter::class)->commit($report, User::query()->where('email', 'manajer@spk.test')->firstOrFail());

    expect(ProductionOrder::count())->toBe($before + 20);

    @unlink($path);
});
