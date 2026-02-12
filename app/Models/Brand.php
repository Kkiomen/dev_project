<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\BrandTone;
use App\Enums\EmojiUsage;
use App\Enums\Industry;
use App\Enums\Platform;
use App\Enums\PublishingProvider;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'industry',
        'description',
        'target_audience',
        'voice',
        'content_pillars',
        'posting_preferences',
        'platforms',
        'onboarding_completed',
        'is_active',
        'automation_enabled',
        'content_queue_days',
        'automation_settings',
        'publishing_provider',
        'last_automation_run',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'voice' => 'array',
        'content_pillars' => 'array',
        'posting_preferences' => 'array',
        'platforms' => 'array',
        'onboarding_completed' => 'boolean',
        'is_active' => 'boolean',
        'automation_enabled' => 'boolean',
        'content_queue_days' => 'integer',
        'automation_settings' => 'array',
        'publishing_provider' => PublishingProvider::class,
        'last_automation_run' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'target_audience' => '{}',
        'voice' => '{}',
        'content_pillars' => '[]',
        'posting_preferences' => '{}',
        'platforms' => '{}',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function postProposals(): HasMany
    {
        return $this->hasMany(PostProposal::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function aiOperationLogs(): HasMany
    {
        return $this->hasMany(AiOperationLog::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BrandMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_members')
            ->withPivot(['role', 'invited_at', 'accepted_at', 'invited_by'])
            ->withTimestamps();
    }

    public function contentQueue(): HasMany
    {
        return $this->hasMany(ContentQueue::class);
    }

    public function pillarTrackings(): HasMany
    {
        return $this->hasMany(PillarTracking::class);
    }

    public function platformCredentials(): HasMany
    {
        return $this->hasMany(PlatformCredential::class);
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function aiKeys(): HasMany
    {
        return $this->hasMany(BrandAiKey::class);
    }

    public function rssFeeds(): HasMany
    {
        return $this->hasMany(RssFeed::class);
    }

    public function rssArticles(): HasMany
    {
        return $this->hasMany(RssArticle::class);
    }

    // Social Media Manager
    public function smAccounts(): HasMany
    {
        return $this->hasMany(SmAccount::class);
    }

    public function smBrandKit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SmBrandKit::class);
    }

    public function smDesignTemplates(): HasMany
    {
        return $this->hasMany(SmDesignTemplate::class);
    }

    public function smGeneratedAssets(): HasMany
    {
        return $this->hasMany(SmGeneratedAsset::class);
    }

    public function smContentTemplates(): HasMany
    {
        return $this->hasMany(SmContentTemplate::class);
    }

    public function smStrategies(): HasMany
    {
        return $this->hasMany(SmStrategy::class);
    }

    public function smContentPlans(): HasMany
    {
        return $this->hasMany(SmContentPlan::class);
    }

    public function smScheduledPosts(): HasMany
    {
        return $this->hasMany(SmScheduledPost::class);
    }

    public function smAnalyticsSnapshots(): HasMany
    {
        return $this->hasMany(SmAnalyticsSnapshot::class);
    }

public function smWeeklyReports(): HasMany
    {
        return $this->hasMany(SmWeeklyReport::class);
    }

    public function smComments(): HasMany
    {
        return $this->hasMany(SmComment::class);
    }

    public function smMessages(): HasMany
    {
        return $this->hasMany(SmMessage::class);
    }

    public function smAutoReplyRules(): HasMany
    {
        return $this->hasMany(SmAutoReplyRule::class);
    }

    public function smCrisisAlerts(): HasMany
    {
        return $this->hasMany(SmCrisisAlert::class);
    }

    public function smMonitoredKeywords(): HasMany
    {
        return $this->hasMany(SmMonitoredKeyword::class);
    }

    public function smMentions(): HasMany
    {
        return $this->hasMany(SmMention::class);
    }

    public function smAlertRules(): HasMany
    {
        return $this->hasMany(SmAlertRule::class);
    }

    public function smListeningReports(): HasMany
    {
        return $this->hasMany(SmListeningReport::class);
    }

    // Member helpers
    public function getMemberRole(User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->role;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function canUserEdit(User $user): bool
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->canEdit() ?? false;
    }

    public function canUserView(User $user): bool
    {
        // Owner via user_id (backwards compatibility) or via membership
        if ($this->user_id === $user->id) {
            return true;
        }

        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->canView() ?? false;
    }

    // Scopes
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOnboardingCompleted(Builder $query): Builder
    {
        return $query->where('onboarding_completed', true);
    }

    // Target Audience Helpers
    public function getAgeRange(): ?string
    {
        return $this->target_audience['age_range'] ?? null;
    }

    public function getTargetGender(): ?string
    {
        return $this->target_audience['gender'] ?? 'all';
    }

    public function getInterests(): array
    {
        return $this->target_audience['interests'] ?? [];
    }

    public function getPainPoints(): array
    {
        return $this->target_audience['pain_points'] ?? [];
    }

    // Voice Helpers
    public function getTone(): ?string
    {
        return $this->voice['tone'] ?? null;
    }

    public function getPersonality(): array
    {
        return $this->voice['personality'] ?? [];
    }

    public function getLanguage(): string
    {
        return $this->voice['language'] ?? 'en';
    }

    public function getEmojiUsage(): string
    {
        return $this->voice['emoji_usage'] ?? 'sometimes';
    }

    // Content Pillars Helpers
    public function getContentPillars(): array
    {
        return $this->content_pillars ?? [];
    }

    public function getPillarByName(string $name): ?array
    {
        foreach ($this->content_pillars as $pillar) {
            if ($pillar['name'] === $name) {
                return $pillar;
            }
        }
        return null;
    }

    // Posting Preferences Helpers
    public function getPostingFrequency(Platform $platform): int
    {
        return $this->posting_preferences['frequency'][$platform->value] ?? 0;
    }

    public function getBestTimes(Platform $platform): array
    {
        return $this->posting_preferences['best_times'][$platform->value] ?? [];
    }

    public function hasAutoSchedule(): bool
    {
        return $this->posting_preferences['auto_schedule'] ?? false;
    }

    // Platform Helpers
    public function isPlatformEnabled(Platform $platform): bool
    {
        return $this->platforms[$platform->value]['enabled'] ?? false;
    }

    public function getEnabledPlatforms(): array
    {
        $enabled = [];
        foreach (Platform::cases() as $platform) {
            if ($this->isPlatformEnabled($platform)) {
                $enabled[] = $platform;
            }
        }
        return $enabled;
    }

    public function getPlatformConfig(Platform $platform): array
    {
        return $this->platforms[$platform->value] ?? ['enabled' => false];
    }

    // State Helpers
    public function completeOnboarding(): self
    {
        $this->onboarding_completed = true;
        $this->save();

        return $this;
    }

    public function activate(): self
    {
        $this->is_active = true;
        $this->save();

        return $this;
    }

    public function deactivate(): self
    {
        $this->is_active = false;
        $this->save();

        return $this;
    }

    // AI Context Builder
    public function buildAiContext(): array
    {
        return [
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
            'enabled_platforms' => array_map(fn($p) => $p->value, $this->getEnabledPlatforms()),
        ];
    }

    // Automation Helpers
    public function isAutomationEnabled(): bool
    {
        return $this->automation_enabled ?? false;
    }

    public function getContentQueueDays(): int
    {
        return $this->content_queue_days ?? 7;
    }

    public function getAutomationSettings(): array
    {
        return $this->automation_settings ?? [];
    }

    public function enableAutomation(): self
    {
        $this->automation_enabled = true;
        $this->save();

        return $this;
    }

    public function disableAutomation(): self
    {
        $this->automation_enabled = false;
        $this->save();

        return $this;
    }

    public function updateLastAutomationRun(): self
    {
        $this->last_automation_run = now();
        $this->save();

        return $this;
    }

    public function scopeWithAutomationEnabled(Builder $query): Builder
    {
        return $query->where('automation_enabled', true);
    }

    // Webhook Helpers
    public function getWebhookUrl(string $type): ?string
    {
        return $this->automation_settings['webhooks'][$type . '_url'] ?? null;
    }

    public function getWebhookPrompt(string $type): ?string
    {
        return $this->automation_settings['webhooks'][$type . '_prompt'] ?? null;
    }

    public function hasWebhook(string $type): bool
    {
        return !empty($this->getWebhookUrl($type));
    }

    // Platform Credential Helpers
    public function getPlatformCredential(string $platform): ?PlatformCredential
    {
        return $this->platformCredentials()->where('platform', $platform)->first();
    }

    public function isPlatformConnected(string $platform): bool
    {
        $credential = $this->getPlatformCredential($platform);

        return $credential && !$credential->isExpired();
    }

    public function getConnectedPlatformNames(): array
    {
        return $this->platformCredentials()
            ->pluck('platform')
            ->toArray();
    }

    // AI Key Helpers
    public function getAiKey(AiProvider $provider): ?string
    {
        return BrandAiKey::getKeyForProvider($this, $provider);
    }
}
