<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    /**
     * Every role may read evaluation results (PRD chapter 9).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return true;
    }

    /**
     * Only the production manager runs a calculation by hand.
     */
    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /**
     * A saved evaluation is never edited or deleted. Its status moves through the approval flow (Phase 7).
     */
    public function update(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }
}
