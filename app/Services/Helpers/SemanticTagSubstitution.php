<?php

namespace App\Services\Helpers;

use App\Enums\SemanticTag;

class SemanticTagSubstitution
{
    /**
     * Apply semantic tag substitutions to parsed PSD layers.
     * Only modifies content (text, images) and styles (colors) - never positions or sizes.
     *
     * @param array $layers Parsed PSD layers
     * @param array $data Substitution data (header, subtitle, primary_color, main_image, etc.)
     * @param array $tags Saved PSD layer tags keyed by layer path
     * @param array &$images Images array to modify (for adding new images)
     * @return array Modified layers
     */
    public static function applyToLayers(array $layers, array $data, array $tags, array &$images = []): array
    {
        return self::processLayersRecursive($layers, $data, $tags, '', $images);
    }

    /**
     * Process layers recursively, applying substitutions.
     */
    protected static function processLayersRecursive(
        array $layers,
        array $data,
        array $tags,
        string $parentPath,
        array &$images
    ): array {
        $result = [];

        foreach ($layers as $layer) {
            $layerPath = $parentPath ? $parentPath . '/' . $layer['name'] : $layer['name'];
            $properties = $layer['properties'] ?? [];
            $layerType = $layer['type'] ?? '';

            // Get semantic tag from saved PSD tags
            $semanticTagValue = $tags[$layerPath]['semantic_tag'] ?? null;

            if ($semanticTagValue) {
                $tag = SemanticTag::tryFrom($semanticTagValue);

                if ($tag) {
                    $inputKey = $tag->inputKey();
                    $value = $data[$inputKey] ?? null;

                    // Content tags
                    if ($tag->category() === 'content') {
                        if (empty($value)) {
                            // Hide layer if no value provided
                            $layer['visible'] = false;
                        } else {
                            // Apply substitution based on tag type
                            switch ($tag) {
                                case SemanticTag::Header:
                                case SemanticTag::Subtitle:
                                case SemanticTag::Paragraph:
                                case SemanticTag::Url:
                                case SemanticTag::SocialHandle:
                                case SemanticTag::CallToAction:
                                    // Text: only change the text content
                                    $properties['text'] = $value;
                                    break;

                                case SemanticTag::MainImage:
                                case SemanticTag::Logo:
                                    // Image: add to images array and update image_id
                                    $newImageId = 'substituted_' . md5($layerPath);
                                    $images[$newImageId] = [
                                        'id' => $newImageId,
                                        'data' => $value, // base64 data or URL
                                    ];
                                    $layer['image_id'] = $newImageId;
                                    break;
                            }
                        }
                    }
                    // Style tags (colors)
                    elseif ($tag->category() === 'style' && !empty($value)) {
                        switch ($tag) {
                            case SemanticTag::PrimaryColor:
                            case SemanticTag::SecondaryColor:
                                // For images: use tintColor to recolor icons/shapes
                                // For shapes/text: change fill color
                                if ($layerType === 'image') {
                                    $properties['tintColor'] = $value;
                                } else {
                                    $properties['fill'] = $value;
                                }
                                break;

                            case SemanticTag::TextPrimaryColor:
                            case SemanticTag::TextSecondaryColor:
                                // For text layers: change text color
                                $properties['fill'] = $value;
                                break;
                        }
                    }

                    $layer['properties'] = $properties;
                }
            }

            // Process children for groups
            if (!empty($layer['children'])) {
                $layer['children'] = self::processLayersRecursive(
                    $layer['children'],
                    $data,
                    $tags,
                    $layerPath,
                    $images
                );
            }

            $result[] = $layer;
        }

        return $result;
    }
}
