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

    public const DEFAULT_HELD_EVALUATIONS = 5;

    public const DEFAULT_HELD_MIN_DAYS = 4;

    public static function maxWaitDays(): int
    {
        return max(1, (int) Setting::get('max_wait_days', self::DEFAULT_MAX_WAIT_DAYS));
    }

    public static function topN(): int
    {
        return max(1, (int) Setting::get('top_n_priority', self::DEFAULT_TOP_N));
    }

    /**
     * How many evaluations in a row an order must stay outside the priority list to count as held (PRD F18 function 6).
     */
    public static function heldAfterEvaluations(): int
    {
        return max(1, (int) Setting::get('held_after_evaluations', self::DEFAULT_HELD_EVALUATIONS));
    }

    /**
     * The least number of days an order must have waited to count as held.
     */
    public static function heldMinDays(): int
    {
        return max(0, (int) Setting::get('held_min_days', self::DEFAULT_HELD_MIN_DAYS));
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
