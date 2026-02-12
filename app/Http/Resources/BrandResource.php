<?php

namespace App\Http\Resources;

use App\Enums\Platform;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'industry' => $this->industry,
            'description' => $this->description,
            'target_audience' => [
                'age_range' => $this->getAgeRange(),
                'gender' => $this->getTargetGender(),
                'interests' => $this->getInterests(),
                'pain_points' => $this->getPainPoints(),
            ],
            'voice' => [
                'tone' => $this->getTone(),
                'personality' => $this->getPersonality(),
                'language' => $this->getLanguage(),
                'emoji_usage' => $this->getEmojiUsage(),
            ],
            'content_pillars' => $this->getContentPillars(),
            'posting_preferences' => $this->posting_preferences ?? [
                'frequency' => [],
                'best_times' => [],
                'auto_schedule' => false,
            ],
            'platforms' => $this->buildPlatformsArray(),
            'enabled_platforms' => array_map(
                fn($p) => $p->value,
                $this->getEnabledPlatforms()
            ),
            'onboarding_completed' => $this->onboarding_completed,
            'is_active' => $this->is_active,
            'automation_enabled' => $this->automation_enabled ?? false,
            'content_queue_days' => $this->content_queue_days ?? 7,
            'automation_settings' => $this->automation_settings ?? [],
            'last_automation_run' => $this->last_automation_run,
            'posts_count' => $this->whenCounted('posts'),
            'templates_count' => $this->whenCounted('templates'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function buildPlatformsArray(): array
    {
        $platforms = [];

        foreach (Platform::cases() as $platform) {
            $config = $this->getPlatformConfig($platform);
            $platforms[$platform->value] = [
                'enabled' => $config['enabled'] ?? false,
                'label' => $platform->label(),
                'icon' => $platform->icon(),
                'color' => $platform->color(),
                ...$this->getPlatformSpecificFields($platform, $config),
            ];
        }

        return $platforms;
    }

    protected function getPlatformSpecificFields(Platform $platform, array $config): array
    {
        return match ($platform) {
            Platform::Facebook => ['page_id' => $config['page_id'] ?? null],
            Platform::Instagram => ['account_id' => $config['account_id'] ?? null],
            Platform::YouTube => ['channel_id' => $config['channel_id'] ?? null],
            default => [],
        };
    }
}
