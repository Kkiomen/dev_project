<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Text Optimization Service.
 *
 * Handles typography edge cases like widow/orphan prevention,
 * smart line breaking, and text truncation.
 */
class TextOptimizationService
{
    /**
     * Minimum word length to consider for widow prevention.
     */
    public const MIN_WIDOW_LENGTH = 4;

    /**
     * Maximum single-line length before considering multi-line.
     */
    public const MAX_SINGLE_LINE_CHARS = 35;

    /**
     * Common short words that should stay with the previous word.
     */
    public const CONNECTORS = [
        'a', 'i', 'o', 'u', 'w', 'z', // Polish single-letter prepositions/conjunctions
        'i', 'a', 'an', 'the', 'of', 'to', 'in', 'on', 'at', 'by', 'or', 'is', // English
    ];

    /**
     * Non-breaking space character.
     */
    public const NBSP = "\u{00A0}";

    /**
     * Optimize text for display.
     *
     * @param string $text The text to optimize
     * @param int $maxWidth Maximum width in pixels
     * @param int $fontSize Font size in pixels
     * @param string $font Font family name
     * @return array Optimized text and metadata
     */
    public function optimizeText(string $text, int $maxWidth, int $fontSize, string $font = 'Inter'): array
    {
        $originalText = $text;

        // Step 1: Prevent widows
        $text = $this->preventWidows($text);

        // Step 2: Prevent orphans
        $text = $this->preventOrphans($text);

        // Step 3: Smart line breaking if text is long
        $estimatedCharsPerLine = $this->estimateCharsPerLine($maxWidth, $fontSize);
        $lineInfo = $this->balanceLines($text, $estimatedCharsPerLine);

        return [
            'text' => $text,
            'original' => $originalText,
            'modified' => $text !== $originalText,
            'estimated_lines' => $lineInfo['line_count'],
            'balanced' => $lineInfo['balanced'],
        ];
    }

    /**
     * Prevent widows (single word on last line).
     *
     * Replaces the last space before short final words with a non-breaking space.
     */
    public function preventWidows(string $text): string
    {
        $text = trim($text);

        if (empty($text)) {
            return $text;
        }

        // Split into words
        $words = preg_split('/\s+/', $text);

        if (count($words) < 2) {
            return $text;
        }

        $lastWord = end($words);
        $lastWordLength = mb_strlen($lastWord);

        // If last word is short (potential widow), join it with previous word
        if ($lastWordLength <= self::MIN_WIDOW_LENGTH) {
            // Find the last space and replace with NBSP
            $lastSpacePos = mb_strrpos($text, ' ');
            if ($lastSpacePos !== false) {
                $text = mb_substr($text, 0, $lastSpacePos) . self::NBSP . mb_substr($text, $lastSpacePos + 1);
            }
        }

        // Also handle connector words (prepositions, articles)
        foreach (self::CONNECTORS as $connector) {
            $pattern = '/\s(' . preg_quote($connector, '/') . ')\s/iu';
            $text = preg_replace($pattern, self::NBSP . '$1 ', $text);
        }

        return $text;
    }

    /**
     * Prevent orphans (single word on first line).
     *
     * For multi-line text, ensures first line has at least 2 words.
     */
    public function preventOrphans(string $text): string
    {
        $text = trim($text);
        $words = preg_split('/\s+/', $text);

        if (count($words) < 3) {
            return $text;
        }

        // If the first word is very short and likely to be alone,
        // join it with the second word
        $firstWord = $words[0];
        if (mb_strlen($firstWord) <= 3 && in_array(mb_strtolower($firstWord), self::CONNECTORS)) {
            // Replace first space with NBSP
            $firstSpacePos = mb_strpos($text, ' ');
            if ($firstSpacePos !== false) {
                $text = mb_substr($text, 0, $firstSpacePos) . self::NBSP . mb_substr($text, $firstSpacePos + 1);
            }
        }

        return $text;
    }

    /**
     * Balance lines for visual appeal.
     *
     * @param string $text The text to balance
     * @param int $charsPerLine Estimated characters per line
     * @return array Line info with count and balance status
     */
    public function balanceLines(string $text, int $charsPerLine): array
    {
        $textLength = mb_strlen(str_replace(self::NBSP, ' ', $text));

        if ($textLength <= $charsPerLine) {
            return [
                'line_count' => 1,
                'balanced' => true,
                'lines' => [$text],
            ];
        }

        // Estimate number of lines
        $estimatedLines = ceil($textLength / $charsPerLine);

        // Calculate ideal chars per line for balanced distribution
        $idealCharsPerLine = (int) ceil($textLength / $estimatedLines);

        // Split text into balanced lines
        $words = preg_split('/\s+/', $text);
        $lines = [];
        $currentLine = '';
        $currentLength = 0;

        foreach ($words as $word) {
            $wordLength = mb_strlen($word);

            if ($currentLength + $wordLength + 1 > $idealCharsPerLine && !empty($currentLine)) {
                $lines[] = trim($currentLine);
                $currentLine = $word;
                $currentLength = $wordLength;
            } else {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
                $currentLength += $wordLength + ($currentLine ? 1 : 0);
            }
        }

        if (!empty($currentLine)) {
            $lines[] = trim($currentLine);
        }

        // Check if lines are balanced (similar lengths)
        $lineLengths = array_map('mb_strlen', $lines);
        $avgLength = array_sum($lineLengths) / count($lineLengths);
        $maxDeviation = max(array_map(fn($l) => abs($l - $avgLength), $lineLengths));
        $balanced = $maxDeviation <= $avgLength * 0.3;

        return [
            'line_count' => count($lines),
            'balanced' => $balanced,
            'lines' => $lines,
            'deviation' => $maxDeviation,
        ];
    }

    /**
     * Truncate text safely with ellipsis.
     *
     * @param string $text Text to truncate
     * @param int $maxChars Maximum characters
     * @param string $ellipsis Ellipsis string
     * @return string Truncated text
     */
    public function truncateText(string $text, int $maxChars, string $ellipsis = '...'): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        // Find a word boundary to break at
        $truncated = mb_substr($text, 0, $maxChars - mb_strlen($ellipsis));

        // Remove partial word at end
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxChars * 0.5) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        // Remove trailing punctuation
        $truncated = rtrim($truncated, '.,;:!?-');

        return $truncated . $ellipsis;
    }

    /**
     * Estimate characters per line based on font metrics.
     *
     * Uses approximate character widths for common fonts.
     */
    public function estimateCharsPerLine(int $maxWidth, int $fontSize): int
    {
        // Average character width is approximately 0.5-0.6 of font size for sans-serif
        $avgCharWidth = $fontSize * 0.55;

        return max(1, (int) floor($maxWidth / $avgCharWidth));
    }

    /**
     * Optimize text layers in a template.
     *
     * @param array $layers Template layers
     * @return array Optimized layers
     */
    public function optimizeTextLayers(array $layers): array
    {
        $optimizedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';

            if (!in_array($type, ['text', 'textbox'])) {
                $optimizedLayers[] = $layer;
                continue;
            }

            $text = $layer['properties']['text'] ?? '';
            $fontSize = $layer['properties']['fontSize'] ?? 16;
            $width = $layer['width'] ?? 300;
            $font = $layer['properties']['fontFamily'] ?? 'Inter';
            $lineHeight = $layer['properties']['lineHeight'] ?? 1.2;

            if (empty($text)) {
                $optimizedLayers[] = $layer;
                continue;
            }

            $result = $this->optimizeText($text, $width, $fontSize, $font);

            if ($result['modified']) {
                $layer['properties']['text'] = $result['text'];
            }

            // CRITICAL: Calculate and update layer height based on actual text content
            $requiredHeight = $this->calculateRequiredHeight(
                $result['text'] ?? $text,
                $width,
                $fontSize,
                $lineHeight
            );

            $currentHeight = $layer['height'] ?? 50;

            // Only increase height if needed (never shrink)
            if ($requiredHeight > $currentHeight) {
                $oldHeight = $layer['height'] ?? 0;
                $layer['height'] = $requiredHeight;

                Log::channel('single')->info('Text layer height adjusted', [
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_height' => $oldHeight,
                    'new_height' => $requiredHeight,
                    'estimated_lines' => $result['estimated_lines'],
                    'fontSize' => $fontSize,
                    'lineHeight' => $lineHeight,
                ]);
            }

            Log::channel('single')->info('Text optimized for typography', [
                'layer' => $layer['name'] ?? 'unknown',
                'original' => $result['original'],
                'optimized' => $result['text'],
                'estimated_lines' => $result['estimated_lines'],
            ]);

            $optimizedLayers[] = $layer;
        }

        return $optimizedLayers;
    }

    /**
     * Calculate required height for text layer based on content.
     *
     * @param string $text The text content
     * @param int $width Layer width in pixels
     * @param int $fontSize Font size in pixels
     * @param float $lineHeight Line height multiplier
     * @return int Required height in pixels
     */
    public function calculateRequiredHeight(string $text, int $width, int $fontSize, float $lineHeight = 1.2): int
    {
        // Estimate characters per line
        $charsPerLine = $this->estimateCharsPerLine($width, $fontSize);

        // Calculate number of lines
        $lineInfo = $this->balanceLines($text, $charsPerLine);
        $numLines = $lineInfo['line_count'];

        // Calculate height: lines * fontSize * lineHeight + padding
        $lineHeightPx = $fontSize * $lineHeight;
        $textHeight = (int) ceil($numLines * $lineHeightPx);

        // Add padding (top and bottom)
        $padding = (int) ($fontSize * 0.5);

        $totalHeight = $textHeight + $padding;

        Log::channel('single')->debug('TextOptimization: Calculated required height', [
            'text_length' => mb_strlen($text),
            'chars_per_line' => $charsPerLine,
            'num_lines' => $numLines,
            'fontSize' => $fontSize,
            'lineHeight' => $lineHeight,
            'calculated_height' => $totalHeight,
        ]);

        return $totalHeight;
    }

    /**
     * Check if text has potential widow issues.
     */
    public function hasWidowRisk(string $text, int $charsPerLine): bool
    {
        $words = preg_split('/\s+/', trim($text));

        if (count($words) < 2) {
            return false;
        }

        $lastWord = end($words);
        $textLength = mb_strlen($text);

        // Risk exists if last word is short and total length suggests it will be alone
        return mb_strlen($lastWord) <= self::MIN_WIDOW_LENGTH &&
               $textLength > $charsPerLine;
    }

    /**
     * Check if text has potential orphan issues.
     */
    public function hasOrphanRisk(string $text, int $charsPerLine): bool
    {
        $words = preg_split('/\s+/', trim($text));

        if (count($words) < 3) {
            return false;
        }

        $firstWord = $words[0];
        $textLength = mb_strlen($text);

        // Risk exists if first word is short and text is multi-line
        return mb_strlen($firstWord) <= 3 &&
               in_array(mb_strtolower($firstWord), self::CONNECTORS) &&
               $textLength > $charsPerLine;
    }
}
