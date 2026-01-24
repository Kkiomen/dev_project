<?php

namespace App\Policies;

use App\Models\ApprovalToken;
use App\Models\User;

class ApprovalTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ApprovalToken $approvalToken): bool
    {
        return $user->id === $approvalToken->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ApprovalToken $approvalToken): bool
    {
        return $user->id === $approvalToken->user_id;
    }

    public function delete(User $user, ApprovalToken $approvalToken): bool
    {
        return $user->id === $approvalToken->user_id;
    }

    public function regenerate(User $user, ApprovalToken $approvalToken): bool
    {
        return $user->id === $approvalToken->user_id;
    }
}
