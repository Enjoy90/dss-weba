<?php

namespace App\Services\Evaluation;

use App\Models\Setting;

/**
 * The settings that shape evaluations and the protection of old orders, read from the settings table
 * with the defaults of PRD chapter 10. The settings page (P9-02) will write the same keys.
 */
final class EvaluationSettings
{
    public const DEFAULT_MAX_WAIT_DAYS = 7;

    public const DEFAULT_TOP_N = 5;

    public static function maxWaitDays(): int
    {
        return max(1, (int) Setting::get('max_wait_days', self::DEFAULT_MAX_WAIT_DAYS));
    }

    public static function topN(): int
    {
        return max(1, (int) Setting::get('top_n_priority', self::DEFAULT_TOP_N));
    }

    /**
     * Whether an order that is being worked on locks itself.
     */
    public static function lockInProgress(): bool
    {
        return filter_var(Setting::get('lock_in_progress', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the leader has to approve an evaluation before it is applied.
     */
    public static function approvalRequired(): bool
    {
        return filter_var(Setting::get('approval_required', true), FILTER_VALIDATE_BOOLEAN);
    }
}
