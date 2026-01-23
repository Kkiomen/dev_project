<?php

namespace App\Policies;

use App\Models\Base;
use App\Models\User;

class BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Base $base): bool
    {
        return $user->id === $base->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Base $base): bool
    {
        return $user->id === $base->user_id;
    }

    public function delete(User $user, Base $base): bool
    {
        return $user->id === $base->user_id;
    }
}
