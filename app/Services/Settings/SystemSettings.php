<?php

namespace App\Services\Settings;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssessmentResolver;
use App\Services\Evaluation\EvaluationSettings;
use App\Services\Report\CompanyProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The system settings of PRD F15: the defaults, the rules each value must follow, who may change which, and the one
 * place that saves them. Every save is checked here on the server and written to the activity log with old and new values.
 * The decision rules are for the admin and the leader, the company profile and the schedule are for the admin only.
 */
final class SystemSettings
{
    public const DEFAULT_NEAR_DEADLINE_DAYS = 3;

    public const DEFAULT_SCHEDULE_TIME = '06:00';

    public const INCOMPLETE_WORST = 'worst';

    public const INCOMPLETE_EXCLUDE = 'exclude';

    /**
     * Rules of the evaluation and the protection of old orders. The leader may change these.
     *
     * @var array<int, string>
     */
    public const DECISION_KEYS = [
        'top_n_priority', 'approval_required', 'stale_value_days', 'incomplete_orders',
        'lock_in_progress', 'max_wait_days', 'held_after_evaluations', 'held_min_days',
    ];

    /**
     * Company profile, schedule and display settings. Only the admin may change these.
     *
     * @var array<int, string>
     */
    public const ADMIN_KEYS = [
        'company_name', 'company_address', 'company_phone', 'company_logo',
        'schedule_enabled', 'schedule_time', 'near_deadline_days',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'top_n_priority' => EvaluationSettings::DEFAULT_TOP_N,
            'approval_required' => true,
            'stale_value_days' => AssessmentResolver::DEFAULT_STALE_DAYS,
            'incomplete_orders' => self::INCOMPLETE_WORST,
            'lock_in_progress' => true,
            'max_wait_days' => EvaluationSettings::DEFAULT_MAX_WAIT_DAYS,
            'held_after_evaluations' => EvaluationSettings::DEFAULT_HELD_EVALUATIONS,
            'held_min_days' => EvaluationSettings::DEFAULT_HELD_MIN_DAYS,
            'company_name' => CompanyProfile::DEFAULT_NAME,
            'company_address' => null,
            'company_phone' => null,
            'company_logo' => null,
            'schedule_enabled' => true,
            'schedule_time' => self::DEFAULT_SCHEDULE_TIME,
            'near_deadline_days' => self::DEFAULT_NEAR_DEADLINE_DAYS,
        ];
    }

    /**
     * Every setting with the saved value, or the default when nothing is saved.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $values = [];

        foreach (self::defaults() as $key => $default) {
            $values[$key] = Setting::get($key, $default);
        }

        return $values;
    }

    public static function nearDeadlineDays(): int
    {
        return max(1, (int) Setting::get('near_deadline_days', self::DEFAULT_NEAR_DEADLINE_DAYS));
    }

    public static function scheduleEnabled(): bool
    {
        return filter_var(Setting::get('schedule_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The hour of the scheduled evaluation as "HH:MM".
     */
    public static function scheduleTime(): string
    {
        $time = (string) Setting::get('schedule_time', self::DEFAULT_SCHEDULE_TIME);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : self::DEFAULT_SCHEDULE_TIME;
    }

    /**
     * Whether an incomplete order is left out of a calculation instead of counting with the worst value.
     */
    public static function excludeIncomplete(): bool
    {
        return Setting::get('incomplete_orders', self::INCOMPLETE_WORST) === self::INCOMPLETE_EXCLUDE;
    }

    public static function canOpen(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isLeader());
    }

    public static function canChangeCompany(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'top_n_priority' => ['required', 'integer', 'min:1', 'max:50'],
            'approval_required' => ['required', 'boolean'],
            'stale_value_days' => ['required', 'integer', 'min:0', 'max:30'],
            'incomplete_orders' => ['required', 'in:'.self::INCOMPLETE_WORST.','.self::INCOMPLETE_EXCLUDE],
            'lock_in_progress' => ['required', 'boolean'],
            'max_wait_days' => ['required', 'integer', 'min:1', 'max:60'],
            'held_after_evaluations' => ['required', 'integer', 'min:1', 'max:20'],
            'held_min_days' => ['required', 'integer', 'min:0', 'max:59'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_logo' => ['nullable', 'string', 'max:255'],
            'schedule_enabled' => ['required', 'boolean'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'near_deadline_days' => ['required', 'integer', 'min:1', 'max:14'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'required' => 'Isian ini wajib diisi.',
            'integer' => 'Isi dengan bilangan bulat.',
            'min' => 'Nilai terlalu kecil (paling kecil :min).',
            'max' => 'Nilai terlalu besar (paling besar :max).',
            'in' => 'Pilihan tidak dikenal.',
            'date_format' => 'Isi jam dengan format JJ:MM, misalnya 06:00.',
            'boolean' => 'Pilihan tidak valid.',
        ];
    }

    /**
     * Saves the given settings and writes one log row with the old and new value of every setting that really changed.
     * Keys the user may not change are refused, not ignored.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array{old: mixed, new: mixed}> the changes, empty when nothing changed
     *
     * @throws AuthorizationException when the user may not change one of the keys
     * @throws ValidationException when a value breaks its rule
     */
    public static function save(array $input, User $by): array
    {
        $allowed = $by->isAdmin() ? array_merge(self::DECISION_KEYS, self::ADMIN_KEYS) : ($by->isLeader() ? self::DECISION_KEYS : []);

        if ($allowed === [] || array_diff(array_keys($input), $allowed) !== []) {
            throw new AuthorizationException('Anda tidak berhak mengubah pengaturan ini.');
        }

        $input = array_intersect_key($input, array_flip($allowed));
        $rules = array_intersect_key(self::rules(), $input);
        $validated = Validator::make($input, $rules, self::messages())->validate();

        $current = self::values();
        $merged = array_merge($current, $validated);

        if ((int) $merged['held_min_days'] >= (int) $merged['max_wait_days']) {
            throw ValidationException::withMessages([
                'held_min_days' => 'Lama menunggu minimal harus lebih pendek dari batas tunggu maksimum ('.(int) $merged['max_wait_days'].' hari), supaya peringatan Tertahan muncul sebelum order dieskalasi.',
            ]);
        }

        $changes = [];

        foreach ($validated as $key => $value) {
            $value = self::normalize($key, $value);

            if ($value === $current[$key]) {
                continue;
            }

            $changes[$key] = ['old' => $current[$key], 'new' => $value];
        }

        if ($changes === []) {
            return [];
        }

        foreach ($changes as $key => $change) {
            Setting::put($key, $change['new']);
        }

        if (isset($changes['company_logo']) && filled($changes['company_logo']['old'])) {
            Storage::disk('public')->delete($changes['company_logo']['old']);
        }

        ActivityLog::record(
            'setting.updated',
            null,
            array_map(fn (array $change): mixed => $change['old'], $changes),
            array_map(fn (array $change): mixed => $change['new'], $changes),
            $by,
        );

        return $changes;
    }

    private static function normalize(string $key, mixed $value): mixed
    {
        return match ($key) {
            'top_n_priority', 'stale_value_days', 'max_wait_days', 'held_after_evaluations', 'held_min_days', 'near_deadline_days' => (int) $value,
            'approval_required', 'lock_in_progress', 'schedule_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'company_name', 'company_address', 'company_phone', 'company_logo' => ($text = trim((string) $value)) === '' ? self::defaults()[$key] : $text,
            default => $value,
        };
    }
}
