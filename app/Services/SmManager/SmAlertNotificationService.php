<?php

namespace App\Services\SmManager;

use App\Models\Brand;
use App\Models\SmAlertRule;
use App\Models\SmCrisisAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SmAlertNotificationService
{
    /**
     * Send notifications based on an alert rule's configured channels.
     *
     * Updates last_triggered_at on the rule after sending.
     */
    public function notifyAlertRule(Brand $brand, SmAlertRule $rule, array $context): void
    {
        $channels = $rule->notify_via ?? ['email'];

        foreach ($channels as $channel) {
            $this->sendViaChannel($brand, $channel, array_merge($context, [
                'alert_type' => $rule->alert_type,
                'threshold' => $rule->threshold,
                'source' => 'alert_rule',
                'rule_id' => $rule->id,
            ]));
        }

        $rule->update(['last_triggered_at' => now()]);

        Log::info('SmAlertNotification: alert rule notification sent', [
            'brand_id' => $brand->id,
            'rule_id' => $rule->id,
            'channels' => $channels,
        ]);
    }

    /**
     * Send crisis notifications via all available channels.
     *
     * Crisis alerts always use every channel for maximum visibility.
     */
    public function notifyCrisis(Brand $brand, SmCrisisAlert $alert): void
    {
        $context = [
            'source' => 'crisis_alert',
            'alert_id' => $alert->id,
            'severity' => $alert->severity,
            'trigger_type' => $alert->trigger_type,
            'description' => $alert->description,
            'related_items' => $alert->related_items ?? [],
            'created_at' => $alert->created_at->toIso8601String(),
            'action_items' => $this->buildCrisisActionItems($alert),
        ];

        $allChannels = ['email', 'push', 'slack'];

        foreach ($allChannels as $channel) {
            $this->sendViaChannel($brand, $channel, $context);
        }

        Log::warning('SmAlertNotification: crisis notification sent via all channels', [
            'brand_id' => $brand->id,
            'alert_id' => $alert->id,
            'severity' => $alert->severity,
            'trigger_type' => $alert->trigger_type,
        ]);
    }

    /**
     * Route notification to the appropriate channel handler.
     */
    protected function sendViaChannel(Brand $brand, string $channel, array $context): void
    {
        match ($channel) {
            'email' => $this->sendEmail($brand, $context),
            'push' => $this->sendPush($brand, $context),
            'slack' => $this->sendSlack($brand, $context),
            default => Log::warning("SmAlertNotification: unknown notification channel", [
                'channel' => $channel,
                'brand_id' => $brand->id,
            ]),
        };
    }

    /**
     * Send email notification.
     *
     * TODO: Implement email notification via Laravel Mail with a dedicated Mailable.
     */
    protected function sendEmail(Brand $brand, array $context): void
    {
        Log::info('SmAlertNotification: email notification', [
            'brand_id' => $brand->id,
            'source' => $context['source'] ?? 'unknown',
            'severity' => $context['severity'] ?? null,
            'context' => $context,
        ]);

        // TODO: Implement email notification via Laravel Mail
        // Example:
        // $user = $brand->user;
        // if ($user?->email) {
        //     Mail::to($user->email)->send(new SmAlertMail($brand, $context));
        // }
    }

    /**
     * Send push notification.
     *
     * TODO: Implement push notification (e.g., via Firebase Cloud Messaging or Laravel Echo).
     */
    protected function sendPush(Brand $brand, array $context): void
    {
        Log::info('SmAlertNotification: push notification', [
            'brand_id' => $brand->id,
            'source' => $context['source'] ?? 'unknown',
            'severity' => $context['severity'] ?? null,
            'context' => $context,
        ]);

        // TODO: Implement push notification
        // Example:
        // $user = $brand->user;
        // if ($user) {
        //     $user->notify(new SmAlertPushNotification($brand, $context));
        // }
    }

    /**
     * Send Slack webhook notification.
     *
     * TODO: Implement Slack webhook notification.
     */
    protected function sendSlack(Brand $brand, array $context): void
    {
        Log::info('SmAlertNotification: slack notification', [
            'brand_id' => $brand->id,
            'source' => $context['source'] ?? 'unknown',
            'severity' => $context['severity'] ?? null,
            'context' => $context,
        ]);

        // TODO: Implement Slack webhook notification
        // Example:
        // $webhookUrl = config('services.slack.sm_alerts_webhook');
        // if ($webhookUrl) {
        //     Http::post($webhookUrl, [
        //         'text' => $this->formatSlackMessage($brand, $context),
        //     ]);
        // }
    }

    /**
     * Build action items for a crisis alert to include in notifications.
     */
    protected function buildCrisisActionItems(SmCrisisAlert $alert): array
    {
        $actions = [];

        $actions[] = 'Review the flagged content immediately';

        match ($alert->trigger_type) {
            'negative_sentiment_spike' => $actions = array_merge($actions, [
                'Check recent comments for common themes',
                'Prepare an official response if needed',
                'Monitor if sentiment continues to decline',
            ]),
            'mention_volume_spike' => $actions = array_merge($actions, [
                'Identify the source of increased mentions',
                'Determine if this is positive or negative attention',
                'Prepare talking points if media coverage is involved',
            ]),
            'flagged_comments' => $actions = array_merge($actions, [
                'Review all flagged comments',
                'Hide or respond to inappropriate content',
                'Consider if a public statement is needed',
            ]),
            default => $actions[] = 'Investigate the root cause and take appropriate action',
        };

        $actions[] = 'Document actions taken for the resolution notes';

        return $actions;
    }
}
