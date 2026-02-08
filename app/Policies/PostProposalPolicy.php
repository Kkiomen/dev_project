<?php

namespace App\Policies;

use App\Models\PostProposal;
use App\Models\User;

class PostProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostProposal $postProposal): bool
    {
        return $user->id === $postProposal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PostProposal $postProposal): bool
    {
        return $user->id === $postProposal->user_id;
    }

    public function delete(User $user, PostProposal $postProposal): bool
    {
        return $user->id === $postProposal->user_id;
    }
}
