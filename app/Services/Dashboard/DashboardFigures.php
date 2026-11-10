<?php

namespace App\Services\Dashboard;

use App\Enums\Completeness;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Services\AssessmentResolver;

/**
 * The six numbers on the dashboard cards (PRD F12 function 1). Each one is counted with the same scope as
 * the filter it links to, so a card always equals the rows of the list it opens.
 */
class DashboardFigures
{
    /**
     * @return array{active: int, late: int, near: int, incomplete: int, locked: int, held: int}
     */
    public function counts(): array
    {
        return [
            'active' => ProductionOrder::query()->open()->count(),
            'late' => ProductionOrder::query()->late()->count(),
            'near' => ProductionOrder::query()->nearDeadline()->count(),
            'incomplete' => count(AssessmentResolver::make()->orderIdsWith(
                Completeness::Incomplete,
                Criterion::query()->active()->ordered()->with('scales')->get(),
            )),
            'locked' => ProductionOrder::query()->locked()->count(),
            'held' => ProductionOrder::query()->held()->count(),
        ];
    }
}
