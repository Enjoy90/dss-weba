<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Pages\CompareEvaluations;
use App\Filament\Resources\Evaluations\EvaluationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compare')
                ->label('Bandingkan dua evaluasi')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->url(fn (): string => CompareEvaluations::getUrl()),
        ];
    }
}
