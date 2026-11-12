<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Nobody changes or deletes a row of the activity log. Reading the rows of one order (the "Riwayat perubahan" tab) is open
 * to everyone who may see the order. The full log page is limited to the admin and the leader (PRD F14) by
 * ActivityLogResource::canViewAny().
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }
}
