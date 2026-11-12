<?php

namespace App\Console\Commands;

use App\Enums\EvaluationTrigger;
use App\Models\ActivityLog;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Services\Evaluation\EvaluationBlockedException;
use App\Services\Evaluation\EvaluationService;
use App\Services\Notifications\EvaluationNotifier;
use App\Services\Settings\SystemSettings;
use Illuminate\Console\Command;

/**
 * The scheduled evaluation of PRD F16. The server runs it every minute (or every few minutes) through the Laravel
 * scheduler. It does nothing until the hour in the settings has come, and it tries only once a day, so a missed
 * minute does not skip the day and a failure is not repeated every minute.
 * The result is a Draft evaluation with the trigger "Terjadwal" that still follows the approval flow.
 */
class RunScheduledEvaluation extends Command
{
    public const LAST_RUN_KEY = 'schedule_last_run';

    protected $signature = 'evaluation:run-scheduled {--force : Jalankan sekarang tanpa menunggu jam dan hari yang diatur}';

    protected $description = 'Menjalankan perhitungan prioritas terjadwal bila sudah waktunya';

    public function handle(EvaluationService $service, EvaluationNotifier $notifier): int
    {
        if (! $this->option('force')) {
            if (! SystemSettings::scheduleEnabled()) {
                return self::SUCCESS;
            }

            if (now()->format('H:i') < SystemSettings::scheduleTime()) {
                return self::SUCCESS;
            }

            if (Setting::get(self::LAST_RUN_KEY) === today()->toDateString()) {
                return self::SUCCESS;
            }
        }

        Setting::put(self::LAST_RUN_KEY, today()->toDateString());

        if (! ProductionOrder::query()->open()->exists()) {
            $reason = 'Tidak ada order berstatus Antri atau Dikerjakan, jadi tidak ada yang dihitung.';
            ActivityLog::record('evaluation.schedule_skipped', null, null, ['reason' => $reason]);
            $notifier->scheduledProblem($reason);
            $this->warn($reason);

            return self::SUCCESS;
        }

        try {
            $evaluation = $service->run(
                null,
                EvaluationTrigger::Scheduled,
                'Evaluasi terjadwal '.now()->format('d/m/Y H:i'),
                acknowledgeWarnings: true,
            );
        } catch (EvaluationBlockedException $exception) {
            $reason = 'Perhitungan terjadwal gagal: '.$exception->getMessage();
            ActivityLog::record('evaluation.schedule_failed', null, null, ['reason' => $reason]);
            $notifier->scheduledProblem($reason);
            $this->error($reason);

            return self::FAILURE;
        }

        $this->info('Evaluasi terjadwal dibuat: '.$evaluation->name.' ('.$evaluation->order_count.' order).');

        return self::SUCCESS;
    }
}
