<?php

namespace App\Policies;

use App\Enums\ValueSource;
use App\Models\Criterion;
use App\Models\User;

class AssessmentPolicy
{
    /**
     * Criteria the production supervisor may update by hand (PRD chapter 9).
     *
     * @var array<int, string>
     */
    public const SUPERVISOR_CRITERIA = ['C4', 'C5'];

    /**
     * Who may open the assessment page. Admin and leader do not work with it.
     */
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->isSupervisor();
    }

    /**
     * Whether this user may write a value for the criterion. The manager writes every manual value
     * and the urgency override; the supervisor only the criteria in the list above.
     */
    public function updateValue(User $user, Criterion $criterion): bool
    {
        if ($user->isManager()) {
            return in_array($criterion->value_source, [ValueSource::Manual, ValueSource::OrderValue, ValueSource::CustomerUrgency], true);
        }

        if ($user->isSupervisor()) {
            return $criterion->value_source === ValueSource::Manual
                && in_array($criterion->code, self::SUPERVISOR_CRITERIA, true);
        }

        return false;
    }

    /**
     * Supervisors do not see the order value in rupiah, so that criterion is hidden from them.
     */
    public function viewCriterion(User $user, Criterion $criterion): bool
    {
        if ($criterion->value_source === ValueSource::OrderValue) {
            return $user->canSeeOrderValue();
        }

        return true;
    }
}
