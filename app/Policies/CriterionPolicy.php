<?php

namespace App\Policies;

use App\Models\Criterion;
use App\Models\User;

class CriterionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isLeader();
    }

    public function view(User $user, Criterion $criterion): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isManager() || $user->isLeader();
    }

    public function update(User $user, Criterion $criterion): bool
    {
        return $user->isManager() || $user->isLeader();
    }

    public function delete(User $user, Criterion $criterion): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Only the production manager edits the scale ranges; admin, manager and leader can view them.
     */
    public function editScale(User $user): bool
    {
        return $user->isManager();
    }
}
