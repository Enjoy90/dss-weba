<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Services\Evaluation\EvaluationSettings;
use App\Services\Evaluation\HeldOrders;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Queued orders that the ranking keeps pushing back (PRD F12 function 4): the held orders and the orders
 * that were escalated, with how long each has waited. The "Tertahan" card counts the first kind only.
 */
class HeldOrdersWidget extends TableWidget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    /**
     * @var Collection<int, string>|null
     */
    protected ?Collection $reasons = null;

    /**
     * @return Collection<int, string> "held" or "escalated" by order id
     */
    protected function reasons(): Collection
    {
        return $this->reasons ??= app(HeldOrders::class)->watchList();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Order tertahan dan dieskalasi')
            ->description('Order Antri yang lama tidak masuk prioritas, dari yang paling lama menunggu.')
            ->query(fn () => ProductionOrder::query()
                ->with('customer')
                ->whereKey($this->reasons()->keys()->all())
                ->orderBy('order_date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->description(fn (ProductionOrder $record): string => $record->customer->name)
                    ->url(fn (ProductionOrder $record): string => ProductionOrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('waiting')
                    ->label('Menunggu')
                    ->state(fn (ProductionOrder $record): string => $record->waitingDays().' hari')
                    ->alignEnd(),
                TextColumn::make('reason')
                    ->label('Keterangan')
                    ->state(fn (ProductionOrder $record): string => $this->reasons()->get($record->id) === 'held' ? 'Tertahan' : 'Dieskalasi')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Tertahan' ? 'warning' : 'danger')
                    ->tooltip(fn (ProductionOrder $record): string => $this->reasons()->get($record->id) === 'held'
                        ? 'Di luar '.EvaluationSettings::topN().' teratas pada '.EvaluationSettings::heldAfterEvaluations().' evaluasi berturut-turut.'
                        : 'Menunggu lebih dari '.EvaluationSettings::maxWaitDays().' hari, naik di atas order biasa pada evaluasi terakhir.'),
                TextColumn::make('last_rank')
                    ->label('Peringkat')
                    ->state(fn (ProductionOrder $record): string => (string) (EvaluationItem::query()
                        ->where('production_order_id', $record->id)
                        ->orderByDesc('evaluation_id')
                        ->value('final_rank') ?? '-'))
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak ada order tertahan')
            ->emptyStateDescription('Belum ada order Antri yang berulang kali tertinggal dari daftar prioritas.');
    }
}
