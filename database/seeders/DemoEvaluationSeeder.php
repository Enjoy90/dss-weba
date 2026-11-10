<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use Illuminate\Database\Seeder;

/**
 * Five evaluations on five consecutive days, each approved and applied, so the dashboard has a history to show:
 * the order list carries the Prioritas Utama label, the rank trend has points, and orders that keep missing the
 * priority list show up as held. It is not part of the default seed, because the evaluation screens are easier to
 * learn from an empty start. Run it after the default seed with:
 * php artisan db:seed --class=DemoEvaluationSeeder
 */
class DemoEvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::query()->where('email', 'manajer@spk.test')->firstOrFail();
        $leader = User::query()->where('email', 'pimpinan@spk.test')->firstOrFail();

        $service = app(EvaluationService::class);
        $workflow = app(EvaluationWorkflow::class);

        foreach ([4, 3, 2, 1, 0] as $daysAgo) {
            $evaluation = $service->run(
                $manager,
                acknowledgeWarnings: true,
                at: now()->subDays($daysAgo)->setTime(8, 0),
            );

            $workflow->submit($evaluation, $manager);
            $workflow->approve($evaluation->fresh(), $leader);
            $workflow->apply($evaluation->fresh(), $manager);
        }
    }
}
