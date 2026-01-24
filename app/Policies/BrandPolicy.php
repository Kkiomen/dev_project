<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Brand $brand): bool
    {
        // Owner via user_id (backwards compatibility)
        if ($user->id === $brand->user_id) {
            return true;
        }

        // Check membership
        return $brand->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Brand $brand): bool
    {
        // Owner via user_id (backwards compatibility)
        if ($user->id === $brand->user_id) {
            return true;
        }

        // Check if user has edit permission via membership
        return $brand->canUserEdit($user);
    }

    public function delete(User $user, Brand $brand): bool
    {
        // Owner via user_id (backwards compatibility)
        if ($user->id === $brand->user_id) {
            return true;
        }

        // Only owners can delete
        return $brand->isOwnedBy($user);
    }

    public function manageMembers(User $user, Brand $brand): bool
    {
        // Owner via user_id (backwards compatibility)
        if ($user->id === $brand->user_id) {
            return true;
        }

        // Only owners can manage members
        return $brand->isOwnedBy($user);
    }
}
