<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Removes notifications older than 30 days, read or not (PRD chapter 19 rule 2). It runs once a day.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=30 : Umur notifikasi (hari) yang dihapus}';

    protected $description = 'Menghapus notifikasi yang sudah lebih dari 30 hari';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DatabaseNotification::query()->where('created_at', '<', now()->subDays($days))->delete();

        $this->info($deleted.' notifikasi lama dihapus.');

        return self::SUCCESS;
    }
}
