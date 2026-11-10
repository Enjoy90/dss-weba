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
     * The manager submits a draft for approval.
     */
    public function submit(User $user, Evaluation $evaluation): bool
    {
        return $user->isManager();
    }

    /**
     * Only the leader approves or rejects (PRD chapter 9).
     */
    public function approve(User $user, Evaluation $evaluation): bool
    {
        return $user->isLeader();
    }

    public function reject(User $user, Evaluation $evaluation): bool
    {
        return $user->isLeader();
    }

    /**
     * The manager and the leader apply the priority, adjust the ranking, and cancel an evaluation.
     */
    public function apply(User $user, Evaluation $evaluation): bool
    {
        return $user->isManager() || $user->isLeader();
    }

    public function adjust(User $user, Evaluation $evaluation): bool
    {
        return $user->isManager() || $user->isLeader();
    }

    public function cancel(User $user, Evaluation $evaluation): bool
    {
        return $user->isManager() || $user->isLeader();
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
