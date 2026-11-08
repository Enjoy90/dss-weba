<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function delete(User $user, Product $product): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
