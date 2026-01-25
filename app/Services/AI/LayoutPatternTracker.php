<?php

namespace App\Services\AI;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;

class LayoutPatternTracker
{
    /**
     * Photo positions that AI can choose from.
     */
    public const LAYOUT_TYPES = [
        'left',
        'right',
        'top',
        'bottom',
        'center',
        'full_bleed',
        'diagonal',
    ];

    /**
     * How many recent photo positions to track per brand.
     */
    protected int $trackingLimit = 2;

    /**
     * Cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    /**
     * Get the cache key for a brand's layout history.
     */
    protected function getCacheKey(Brand $brand): string
    {
        return "layout_history:brand:{$brand->id}";
    }

    /**
     * Get recently used layouts for a brand.
     */
    public function getRecentLayouts(?Brand $brand): array
    {
        if (! $brand) {
            return [];
        }

        return Cache::get($this->getCacheKey($brand), []);
    }

    /**
     * Get layouts that should NOT be used (recently used ones).
     */
    public function getForbiddenLayouts(?Brand $brand): array
    {
        return $this->getRecentLayouts($brand);
    }

    /**
     * Get layouts that ARE allowed (not recently used).
     */
    public function getAllowedLayouts(?Brand $brand): array
    {
        $forbidden = $this->getForbiddenLayouts($brand);

        return array_values(array_diff(self::LAYOUT_TYPES, $forbidden));
    }

    /**
     * Record that a layout was used for a brand.
     */
    public function recordLayoutUsage(?Brand $brand, string $layoutType): void
    {
        if (! $brand || ! in_array($layoutType, self::LAYOUT_TYPES)) {
            return;
        }

        $history = $this->getRecentLayouts($brand);

        // Remove if already in history (to move it to front)
        $history = array_values(array_diff($history, [$layoutType]));

        // Add to front
        array_unshift($history, $layoutType);

        // Keep only the last N layouts
        $history = array_slice($history, 0, $this->trackingLimit);

        Cache::put($this->getCacheKey($brand), $history, $this->cacheTtl);
    }

    /**
     * Clear layout history for a brand.
     */
    public function clearHistory(?Brand $brand): void
    {
        if (! $brand) {
            return;
        }

        Cache::forget($this->getCacheKey($brand));
    }

    /**
     * Generate prompt instructions about forbidden layouts.
     */
    public function getPromptInstructions(?Brand $brand): string
    {
        $forbidden = $this->getForbiddenLayouts($brand);
        $allowed = $this->getAllowedLayouts($brand);

        if (empty($forbidden)) {
            return '';
        }

        $forbiddenList = implode(', ', $forbidden);
        $allowedList = implode(', ', $allowed);

        return <<<PROMPT

##############################################################################
# LAYOUT RESTRICTIONS - DO NOT USE RECENTLY USED LAYOUTS!
##############################################################################

FORBIDDEN LAYOUTS (recently used for this brand, DO NOT use these):
- {$forbiddenList}

ALLOWED LAYOUTS (you MUST choose one of these):
- {$allowedList}

Pick a layout from the ALLOWED list to ensure visual variety.
##############################################################################
PROMPT;
    }
}
