<?php

namespace App\Policies;

use App\Models\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionOrder $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, ProductionOrder $order): bool
    {
        return $user->isManager();
    }

    public function changeStatus(User $user, ProductionOrder $order): bool
    {
        return $user->isManager() || $user->isSupervisor();
    }

    /**
     * Importing and exporting order lists is for admin and the production manager (PRD F06).
     */
    public function import(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function delete(User $user, ProductionOrder $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ProductionOrder $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductionOrder $order): bool
    {
        return false;
    }
}
