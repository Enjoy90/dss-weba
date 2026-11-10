<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The dashboard (PRD F12). The widgets in app/Filament/Widgets decide for themselves which roles see them.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dasbor';

    protected static ?string $navigationLabel = 'Dasbor';

    public function getColumns(): int|array
    {
        return 2;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gambaran kondisi order dan prioritas produksi hari ini.';
    }
}
