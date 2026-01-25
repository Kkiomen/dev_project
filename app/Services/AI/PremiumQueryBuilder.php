<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Premium Query Builder for Pexels Image Search.
 *
 * Builds enhanced search queries with industry-specific modifiers,
 * lighting keywords, and composition hints for higher quality results.
 */
class PremiumQueryBuilder
{
    /**
     * Industry-specific modifiers for premium image curation.
     * Prioritize clean backgrounds and minimalist aesthetics for split_content archetype.
     */
    public const INDUSTRY_MODIFIERS = [
        'beauty' => ['minimalist aesthetic', 'soft natural light', 'lifestyle', 'spa', 'wellness'],
        'gastro' => ['gourmet', 'flatlay', 'rustic texture', 'bokeh', 'close-up'],
        'fitness' => ['dark background aesthetic', 'minimalist gym', 'professional equipment', 'moody lighting', 'dramatic'],
        'medical' => ['clean', 'professional', 'bright', 'clinical', 'trustworthy'],
        // Technology: Use ABSTRACT concepts, NOT screenshots/websites
        'technology' => ['abstract dark', 'clean glass architecture', 'silk waves', 'neon glow', 'futuristic minimal'],
        'luxury' => ['premium', 'elegant', 'sophisticated', 'cinematic lighting'],
        'fashion' => ['editorial', 'studio lighting', 'high fashion', 'stylish'],
        'education' => ['bright', 'modern classroom', 'learning', 'inspiring'],
        'real_estate' => ['interior design', 'architectural', 'natural light', 'spacious'],
        'travel' => ['wanderlust', 'scenic', 'adventure', 'golden hour'],
    ];

    /**
     * Words that produce cluttered stock photos with too many details.
     * These get replaced with abstract alternatives.
     */
    public const PROBLEMATIC_QUERY_WORDS = [
        'website' => 'digital abstract',
        'screenshot' => 'technology abstract',
        'screen' => 'glass reflection',
        'app' => 'futuristic interface',
        'software' => 'digital abstract',
        'code' => 'technology pattern',
        'programming' => 'abstract lines',
        'computer' => 'minimalist tech',
        'corporate' => 'professional modern',
    ];

    /**
     * Abstract replacements for technology industry.
     * These produce clean photos with negative space for text.
     */
    public const ABSTRACT_TECH_REPLACEMENTS = [
        'abstract technology background dark blue',
        'silk waves technology futuristic',
        'clean glass architecture office',
        'neon light abstract minimal',
        'geometric pattern dark background',
        'digital abstract particles dark',
    ];

    /**
     * Background-specific modifiers for clean layouts.
     * Used when archetype needs a less busy photo (split_content, etc).
     */
    public const CLEAN_BACKGROUND_MODIFIERS = [
        'dark background',
        'minimalist',
        'clean aesthetic',
        'simple background',
        'studio shot',
        'isolated subject',
    ];

    /**
     * Negative modifiers (what to avoid in searches).
     */
    public const NEGATIVE_MODIFIERS = [
        'posed', 'fake smile', 'stock photo', 'cheesy', 'outdated',
    ];

    /**
     * Cheesy/emotional adjectives to remove from queries.
     * These produce unnatural, kitschy stock photos.
     */
    public const CHEESY_ADJECTIVES = [
        'happy', 'smiling', 'excited', 'joyful', 'cheerful', 'thrilled',
        'delighted', 'pleased', 'glad', 'content', 'satisfied', 'ecstatic',
    ];

    /**
     * Replacement keywords for authentic, editorial-style photos.
     */
    public const AUTHENTIC_REPLACEMENTS = [
        'candid', 'authentic', 'natural moment', 'real people',
    ];

    /**
     * Aesthetic keywords for premium Instagram-quality photos.
     * These create the modern, cinematic look that distinguishes premium content.
     * Focus on editorial/magazine quality to avoid "stocky" feel.
     */
    public const AESTHETIC_KEYWORDS = [
        'cinematic' => ['cinematic lighting', 'film look', 'moody atmosphere', 'shot on 35mm'],
        'depth' => ['depth of field', 'bokeh', 'blurred background', 'shallow focus'],
        'lighting' => ['architectural lighting', 'dramatic shadows', 'backlit silhouette', 'golden hour glow'],
        'premium' => ['high-end editorial', 'luxury minimalist', 'elegant composition', 'magazine quality'],
        'style' => ['minimalist aesthetic', 'clean modern', 'professional studio', 'editorial style'],
    ];

    /**
     * Premium descriptors that work well with stock photo APIs.
     */
    public const PREMIUM_DESCRIPTORS = [
        'high-end photography',
        'professional photo',
        'editorial style',
    ];

    /**
     * Lighting quality keywords for different moods.
     */
    public const LIGHTING_KEYWORDS = [
        'soft_diffused' => 'soft diffused natural light',
        'golden_hour' => 'golden hour warm lighting',
        'dramatic' => 'dramatic rim light cinematic',
        'studio' => 'professional studio lighting',
        'moody' => 'moody atmospheric low light',
        'bright' => 'bright airy natural light',
        'neutral' => 'neutral balanced lighting',
    ];

    /**
     * Simple quality modifiers that actually work with stock APIs.
     * Keep it minimal - stock APIs don't understand complex descriptions.
     */
    public const QUALITY_MODIFIERS = [
        'professional',
        'high quality',
        'modern',
    ];

    /**
     * Fallback modifiers when no industry is specified.
     * These work well for generic premium results.
     */
    public const FALLBACK_MODIFIERS = [
        'professional',
        'modern',
        'high quality',
        'aesthetic',
        'lifestyle',
    ];

    /**
     * Keywords to detect industry from search query.
     */
    public const INDUSTRY_KEYWORDS = [
        'fitness' => ['gym', 'fitness', 'workout', 'exercise', 'training', 'sport', 'athlete', 'muscle', 'weight', 'treadmill', 'dumbbell'],
        'beauty' => ['beauty', 'cosmetic', 'makeup', 'skincare', 'spa', 'salon', 'hair', 'nail', 'massage', 'wellness'],
        'gastro' => ['food', 'restaurant', 'cafe', 'coffee', 'dish', 'meal', 'cooking', 'kitchen', 'chef', 'cuisine', 'gastro'],
        'medical' => ['medical', 'health', 'doctor', 'hospital', 'clinic', 'nurse', 'patient', 'healthcare', 'medicine'],
        'technology' => ['tech', 'computer', 'laptop', 'phone', 'software', 'digital', 'app', 'code', 'programming', 'startup'],
        'luxury' => ['luxury', 'premium', 'elegant', 'exclusive', 'high-end', 'vip', 'designer'],
        'fashion' => ['fashion', 'clothing', 'dress', 'style', 'outfit', 'model', 'runway', 'boutique'],
        'education' => ['education', 'school', 'university', 'student', 'learning', 'teacher', 'classroom', 'study'],
        'real_estate' => ['house', 'apartment', 'property', 'real estate', 'interior', 'home', 'room', 'living'],
        'travel' => ['travel', 'vacation', 'holiday', 'destination', 'tourism', 'adventure', 'explore', 'beach', 'mountain'],
    ];

    /**
     * Build premium search query.
     *
     * IMPORTANT: Stock photo APIs (Unsplash, Pexels) work best with SIMPLE queries.
     * Complex queries with many modifiers return 0 results.
     * Strategy: base query + ONE aesthetic/industry modifier.
     */
    public function buildQuery(
        string $baseQuery,
        ?string $industry = null,
        ?string $lighting = null,
        bool $authentic = true,
        bool $usePremiumAesthetics = true
    ): string {
        // Step 1: Remove cheesy/emotional adjectives that produce kitschy stock photos
        $cleanedQuery = $this->removeCheesyAdjectives($baseQuery);

        // Step 1.5: Replace problematic words that produce cluttered photos (website, screenshot, etc.)
        $cleanedQuery = $this->replaceProblematicWords($cleanedQuery);

        $parts = [$cleanedQuery];

        // Auto-detect industry from query if not provided
        $effectiveIndustry = $industry ?? $this->detectIndustryFromQuery($baseQuery);

        // Add only ONE modifier (not multiple!) - prefer aesthetic for premium look
        if ($usePremiumAesthetics && rand(0, 1) === 1) {
            // 50% chance to use aesthetic keyword for modern Instagram look
            $aestheticKeyword = $this->getRandomAestheticKeyword();
            if ($aestheticKeyword) {
                $parts[] = $aestheticKeyword;
            }
        } elseif ($effectiveIndustry && isset(self::INDUSTRY_MODIFIERS[$effectiveIndustry])) {
            $modifiers = self::INDUSTRY_MODIFIERS[$effectiveIndustry];
            // Pick just ONE modifier
            $parts[] = $modifiers[array_rand($modifiers)];
        } elseif (!$effectiveIndustry) {
            // No industry detected - use fallback modifier
            $parts[] = self::FALLBACK_MODIFIERS[array_rand(self::FALLBACK_MODIFIERS)];
        }

        // Skip lighting - too complex for stock APIs
        // Skip authentic/candid - too vague

        $finalQuery = implode(' ', $parts);

        Log::channel('single')->info('PremiumQueryBuilder: Built search query', [
            'base_query' => $baseQuery,
            'detected_industry' => $effectiveIndustry,
            'use_premium_aesthetics' => $usePremiumAesthetics,
            'final_query' => $finalQuery,
            'modifiers_added' => array_slice($parts, 1),
        ]);

        return $finalQuery;
    }

    /**
     * Get a random aesthetic keyword for premium photos.
     */
    protected function getRandomAestheticKeyword(): ?string
    {
        $categories = array_keys(self::AESTHETIC_KEYWORDS);
        $category = $categories[array_rand($categories)];
        $keywords = self::AESTHETIC_KEYWORDS[$category];

        return $keywords[array_rand($keywords)];
    }

    /**
     * Build query with premium descriptor for editorial-style results.
     */
    public function buildPremiumQuery(string $baseQuery, ?string $industry = null): string
    {
        $parts = [$baseQuery];

        // Add one premium descriptor
        $parts[] = self::PREMIUM_DESCRIPTORS[array_rand(self::PREMIUM_DESCRIPTORS)];

        return implode(' ', $parts);
    }

    /**
     * Build query optimized for clean/minimalist backgrounds.
     * Use this for split_content archetype where photo must not be busy.
     */
    public function buildCleanBackgroundQuery(
        string $baseQuery,
        ?string $industry = null,
        bool $preferDark = true
    ): string {
        // Step 1: Remove cheesy adjectives
        $cleanedQuery = $this->removeCheesyAdjectives($baseQuery);

        $parts = [$cleanedQuery];

        // Step 2: Add clean background modifier
        if ($preferDark) {
            // For dark themes (navy, black), prefer dark background photos
            $modifiers = ['dark background', 'moody lighting', 'studio shot'];
        } else {
            // For light themes, prefer clean/minimalist
            $modifiers = ['minimalist', 'clean aesthetic', 'simple background'];
        }
        $parts[] = $modifiers[array_rand($modifiers)];

        // Step 3: Add industry modifier if relevant
        $effectiveIndustry = $industry ?? $this->detectIndustryFromQuery($baseQuery);
        if ($effectiveIndustry && isset(self::INDUSTRY_MODIFIERS[$effectiveIndustry])) {
            // Only add if industry has minimalist-compatible modifiers
            $industryMods = self::INDUSTRY_MODIFIERS[$effectiveIndustry];
            foreach ($industryMods as $mod) {
                if (str_contains($mod, 'minimalist') || str_contains($mod, 'professional')) {
                    $parts[] = $mod;
                    break;
                }
            }
        }

        $finalQuery = implode(' ', $parts);

        Log::channel('single')->info('PremiumQueryBuilder: Built clean background query', [
            'base_query' => $baseQuery,
            'prefer_dark' => $preferDark,
            'final_query' => $finalQuery,
        ]);

        return $finalQuery;
    }

    /**
     * Detect industry from search query keywords.
     */
    public function detectIndustryFromQuery(string $query): ?string
    {
        $queryLower = strtolower($query);

        foreach (self::INDUSTRY_KEYWORDS as $industry => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($queryLower, $keyword)) {
                    return $industry;
                }
            }
        }

        return null;
    }

    /**
     * Build query with composition hints based on archetype.
     *
     * NOTE: Composition hints are REMOVED because stock APIs don't understand them.
     * "subject on right side" doesn't work - these APIs are keyword-based.
     */
    public function buildQueryWithComposition(
        string $baseQuery,
        string $archetype,
        ?string $industry = null,
        ?string $lighting = null
    ): string {
        // Just use simple query - composition hints don't work with stock APIs
        return $this->buildQuery($baseQuery, $industry, null, false);
    }

    /**
     * Get suggested lighting style for industry.
     */
    public function getSuggestedLighting(?string $industry): string
    {
        return match ($industry) {
            'beauty', 'fashion' => 'soft_diffused',
            'fitness' => 'dramatic',
            'gastro' => 'golden_hour',
            'medical', 'technology' => 'bright',
            'luxury' => 'studio',
            'travel' => 'golden_hour',
            default => 'neutral',
        };
    }

    /**
     * Build multiple query variations for better results.
     */
    public function buildQueryVariations(
        string $baseQuery,
        ?string $industry = null,
        int $count = 3
    ): array {
        $variations = [];
        $lightingOptions = array_keys(self::LIGHTING_KEYWORDS);

        shuffle($lightingOptions);

        for ($i = 0; $i < $count; $i++) {
            $lighting = $lightingOptions[$i % count($lightingOptions)];
            $authentic = $i % 2 === 0;

            $variations[] = $this->buildQuery(
                $baseQuery,
                $industry,
                $lighting,
                $authentic
            );
        }

        return $variations;
    }

    /**
     * Enhance a basic query for premium results.
     */
    public function enhanceQuery(string $basicQuery, ?string $industry = null): string
    {
        // If query is too basic, enhance it
        if (str_word_count($basicQuery) <= 2) {
            $lighting = $this->getSuggestedLighting($industry);
            return $this->buildQuery($basicQuery, $industry, $lighting);
        }

        // Query already has detail, just add industry modifiers
        return $this->buildQuery($basicQuery, $industry, null, false);
    }

    /**
     * Remove cheesy/emotional adjectives that produce kitschy stock photos.
     * "happy business team" → "business team" (candid modifier added separately)
     */
    protected function removeCheesyAdjectives(string $query): string
    {
        $words = explode(' ', $query);
        $filteredWords = [];
        $removedAny = false;

        foreach ($words as $word) {
            $wordLower = strtolower($word);
            if (!in_array($wordLower, self::CHEESY_ADJECTIVES)) {
                $filteredWords[] = $word;
            } else {
                $removedAny = true;
                Log::channel('single')->debug('PremiumQueryBuilder: Removed cheesy adjective', [
                    'removed' => $word,
                    'reason' => 'Produces unnatural stock photos',
                ]);
            }
        }

        $cleanedQuery = implode(' ', $filteredWords);

        // If we removed emotional words, add "candid" for authentic look
        if ($removedAny && !str_contains(strtolower($cleanedQuery), 'candid')) {
            $cleanedQuery .= ' candid';
            Log::channel('single')->info('PremiumQueryBuilder: Added candid modifier', [
                'original_query' => $query,
                'cleaned_query' => $cleanedQuery,
            ]);
        }

        return $cleanedQuery;
    }

    /**
     * Replace problematic words that produce cluttered stock photos.
     * "corporate website" → "professional modern digital abstract"
     *
     * These words produce screenshots, UI mockups, and images with too many details.
     */
    protected function replaceProblematicWords(string $query): string
    {
        $queryLower = strtolower($query);
        $replacedAny = false;

        foreach (self::PROBLEMATIC_QUERY_WORDS as $problematic => $replacement) {
            if (str_contains($queryLower, $problematic)) {
                $query = preg_replace('/\b' . preg_quote($problematic, '/') . '\b/i', $replacement, $query);
                $replacedAny = true;

                Log::channel('single')->info('PremiumQueryBuilder: Replaced problematic word', [
                    'replaced' => $problematic,
                    'with' => $replacement,
                    'reason' => 'Produces cluttered stock photos with no negative space',
                ]);
            }
        }

        return $query;
    }

    /**
     * Build optimized query for technology industry.
     * Technology queries often produce screenshot/website images that are too busy.
     * This method forces abstract, clean imagery instead.
     */
    public function buildTechnologyQuery(string $baseQuery): string
    {
        // Check if query is too literal (website, software, etc.)
        $queryLower = strtolower($baseQuery);
        $isTooLiteral = false;

        foreach (array_keys(self::PROBLEMATIC_QUERY_WORDS) as $word) {
            if (str_contains($queryLower, $word)) {
                $isTooLiteral = true;
                break;
            }
        }

        if ($isTooLiteral) {
            // Use abstract replacement instead
            $abstractQuery = self::ABSTRACT_TECH_REPLACEMENTS[array_rand(self::ABSTRACT_TECH_REPLACEMENTS)];

            Log::channel('single')->info('PremiumQueryBuilder: Using abstract tech query', [
                'original' => $baseQuery,
                'abstract' => $abstractQuery,
                'reason' => 'Original query would produce cluttered screenshots',
            ]);

            return $abstractQuery;
        }

        // Query is OK, just add tech modifiers
        return $this->buildQuery($baseQuery, 'technology');
    }
}
