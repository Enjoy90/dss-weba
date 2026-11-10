<?php

namespace App\Filament\Widgets;

use App\Enums\EvaluationStatus;
use App\Filament\Pages\RunEvaluation;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Evaluation;
use Filament\Widgets\Widget;

/**
 * Where the newest calculation stands, with a shortcut to run a new one for the production manager
 * (PRD F12 function 6).
 */
class LatestEvaluationWidget extends Widget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.latest-evaluation';

    public function latest(): ?Evaluation
    {
        return Evaluation::query()
            ->where('status', '!=', EvaluationStatus::Canceled->value)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latest = $this->latest();

        return [
            'evaluation' => $latest,
            'evaluationUrl' => $latest === null ? null : EvaluationResource::getUrl('view', ['record' => $latest]),
            'canRun' => RunEvaluation::canAccess(),
            'runUrl' => RunEvaluation::getUrl(),
        ];
    }
}
