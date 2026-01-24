<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\SocialPost;
use App\Models\PlatformPost;

class ContentSyncService
{
    public function __construct(
        protected LinkPreviewService $linkPreviewService
    ) {}

    public function syncToPlatforms(SocialPost $post): void
    {
        // Ensure platform posts exist
        $post->createPlatformPosts();
        $post->load('platformPosts');

        foreach ($post->platformPosts as $platformPost) {
            if (!$platformPost->enabled) {
                continue;
            }

            // Only sync if no override is set
            if ($platformPost->hasOverride()) {
                continue;
            }

            $this->syncPlatformPost($platformPost, $post->main_caption);
        }
    }

    protected function syncPlatformPost(PlatformPost $platformPost, string $content): void
    {
        $formatted = match ($platformPost->platform) {
            Platform::Instagram => $this->formatForInstagram($content),
            Platform::Facebook => $this->formatForFacebook($content),
            Platform::YouTube => $this->formatForYouTube($content),
        };

        $platformPost->update($formatted);
    }

    public function formatForInstagram(string $content): array
    {
        $hashtags = $this->extractHashtags($content);
        $cleanContent = $this->removeHashtags($content);

        return [
            'hashtags' => $hashtags,
            'platform_caption' => null, // Use main caption
        ];
    }

    public function formatForFacebook(string $content): array
    {
        $url = $this->extractFirstUrl($content);
        $linkPreview = null;

        if ($url) {
            $linkPreview = $this->linkPreviewService->fetch($url);
        }

        return [
            'link_preview' => $linkPreview,
            'platform_caption' => null,
        ];
    }

    public function formatForYouTube(string $content): array
    {
        // Split content into title and description
        $lines = explode("\n", $content, 2);
        $title = trim($lines[0] ?? '');
        $description = trim($lines[1] ?? $content);

        // Limit title length for YouTube (100 chars max recommended)
        if (strlen($title) > 100) {
            $title = substr($title, 0, 97) . '...';
        }

        return [
            'video_title' => $title,
            'video_description' => $description,
            'platform_caption' => null,
        ];
    }

    public function extractHashtags(string $content): array
    {
        preg_match_all('/#(\w+)/u', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    public function removeHashtags(string $content): string
    {
        return trim(preg_replace('/#\w+\s*/u', '', $content));
    }

    public function extractFirstUrl(string $content): ?string
    {
        $pattern = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';

        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        return null;
    }

    public function extractAllUrls(string $content): array
    {
        $pattern = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';

        preg_match_all($pattern, $content, $matches);

        return array_unique($matches[0] ?? []);
    }
}
