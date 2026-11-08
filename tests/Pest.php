<?php

use App\Services\Export\OrderSheetWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Writes an .xlsx file for import tests and returns its path.
 *
 * @param  array<int, array<int, mixed>>  $rows  the first row is the header
 */
function makeXlsx(array $rows, string $sheet = 'Order'): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName($sheet);

    foreach ($rows as $row) {
        $writer->addRow(new Row(array_map(
            fn (mixed $value): Cell => Cell::fromValue($value === null ? '' : $value),
            $row,
        )));
    }

    $writer->close();

    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/**
 * One order row in the template column order, with sensible defaults that tests override by position name.
 *
 * @param  array<string, mixed>  $overrides  keys: number, customer, product, quantity, entered, deadline, price, status, material, hours, progress
 * @return array<int, mixed>
 */
function templateRow(array $overrides = []): array
{
    $values = array_merge([
        'number' => '',
        'customer' => 'PLG-001',
        'product' => 'PRD-001',
        'quantity' => 100,
        'entered' => today()->format('Y-m-d'),
        'deadline' => today()->addDays(14)->format('Y-m-d'),
        'price' => '',
        'status' => 'Antri',
        'material' => '',
        'hours' => '',
        'progress' => '',
    ], $overrides);

    return array_values($values);
}

/**
 * The title row of the template.
 *
 * @return array<int, string>
 */
function templateHeader(): array
{
    return OrderSheetWriter::TEMPLATE_COLUMNS;
}
