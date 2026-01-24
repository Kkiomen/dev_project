<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;

class SocialPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id && $socialPost->canEdit();
    }

    public function delete(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id && $socialPost->canDelete();
    }

    public function duplicate(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id;
    }

    public function reschedule(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id && $socialPost->canSchedule();
    }

    public function requestApproval(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id;
    }

    public function uploadMedia(User $user, SocialPost $socialPost): bool
    {
        return $user->id === $socialPost->user_id && $socialPost->canEdit();
    }
}
