<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function create(User $user, string $type, ?array $data = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $type, // Frontend will translate based on type
            'message' => null, // Frontend will build message from data
            'data' => $data,
        ]);

        broadcast(new NotificationCreated($notification));

        return $notification;
    }

    public function postGenerated(User $user, string $postTitle, string $brandName, string $postId): Notification
    {
        return $this->create(
            $user,
            'post_generated',
            [
                'post_id' => $postId,
                'post_title' => $postTitle,
                'brand_name' => $brandName,
            ]
        );
    }

    public function postPublished(User $user, string $postTitle, string $platform, string $postId): Notification
    {
        return $this->create(
            $user,
            'post_published',
            [
                'post_id' => $postId,
                'post_title' => $postTitle,
                'platform' => $platform,
            ]
        );
    }

    public function approvalRequired(User $user, string $postTitle, string $postId): Notification
    {
        return $this->create(
            $user,
            'approval_required',
            [
                'post_id' => $postId,
                'post_title' => $postTitle,
            ]
        );
    }
}
