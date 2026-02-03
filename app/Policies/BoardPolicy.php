<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $board->brand->canUserView($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Board $board): bool
    {
        return $board->brand->canUserEdit($user);
    }

    public function delete(User $user, Board $board): bool
    {
        return $board->brand->canUserEdit($user);
    }
}
