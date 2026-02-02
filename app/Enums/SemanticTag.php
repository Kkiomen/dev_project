<?php

namespace App\Enums;

enum SemanticTag: string
{
    // Content tags (what data goes into layer)
    case Header = 'header';
    case Subtitle = 'subtitle';
    case Paragraph = 'paragraph';
    case Url = 'url';
    case SocialHandle = 'social_handle';
    case MainImage = 'main_image';
    case Logo = 'logo';
    case CallToAction = 'cta';

    // Style tags (how layer is styled)
    case PrimaryColor = 'primary_color';
    case SecondaryColor = 'secondary_color';
    case TextPrimaryColor = 'text_primary_color';
    case TextSecondaryColor = 'text_secondary_color';

    public function label(): string
    {
        return match ($this) {
            self::Header => __('graphics.semantic_tags.header'),
            self::Subtitle => __('graphics.semantic_tags.subtitle'),
            self::Paragraph => __('graphics.semantic_tags.paragraph'),
            self::Url => __('graphics.semantic_tags.url'),
            self::SocialHandle => __('graphics.semantic_tags.social_handle'),
            self::MainImage => __('graphics.semantic_tags.main_image'),
            self::Logo => __('graphics.semantic_tags.logo'),
            self::CallToAction => __('graphics.semantic_tags.cta'),
            self::PrimaryColor => __('graphics.semantic_tags.primary_color'),
            self::SecondaryColor => __('graphics.semantic_tags.secondary_color'),
            self::TextPrimaryColor => __('graphics.semantic_tags.text_primary_color'),
            self::TextSecondaryColor => __('graphics.semantic_tags.text_secondary_color'),
        };
    }

    /**
     * Get the category of this semantic tag.
     * Content tags define what data goes into a layer.
     * Style tags define how a layer is styled (colors).
     */
    public function category(): string
    {
        return match ($this) {
            self::Header, self::Subtitle, self::Paragraph,
            self::Url, self::SocialHandle, self::MainImage,
            self::Logo, self::CallToAction => 'content',
            self::PrimaryColor, self::SecondaryColor,
            self::TextPrimaryColor, self::TextSecondaryColor => 'style',
        };
    }

    /**
     * Get layer types that this semantic tag can be applied to.
     *
     * @return array<LayerType>
     */
    public function applicableToTypes(): array
    {
        return match ($this) {
            self::Header, self::Subtitle, self::Paragraph,
            self::Url, self::SocialHandle, self::CallToAction => [
                LayerType::TEXT,
                LayerType::TEXTBOX,
            ],
            self::PrimaryColor, self::SecondaryColor => [
                LayerType::TEXT,
                LayerType::TEXTBOX,
                LayerType::RECTANGLE,
                LayerType::ELLIPSE,
            ],
            self::TextPrimaryColor, self::TextSecondaryColor => [
                LayerType::TEXT,
                LayerType::TEXTBOX,
            ],
            self::MainImage, self::Logo => [
                LayerType::IMAGE,
            ],
        };
    }

    /**
     * Check if this tag can be applied to the given layer type.
     */
    public function isApplicableTo(LayerType $type): bool
    {
        return in_array($type, $this->applicableToTypes(), true);
    }

    /**
     * Get the property key that this tag affects.
     * Used for substitution during template rendering.
     */
    public function affectsProperty(): string
    {
        return match ($this) {
            self::Header, self::Subtitle, self::Paragraph,
            self::Url, self::SocialHandle, self::CallToAction => 'text',
            self::PrimaryColor, self::SecondaryColor,
            self::TextPrimaryColor, self::TextSecondaryColor => 'fill',
            self::MainImage, self::Logo => 'src',
        };
    }

    /**
     * Get the input field key that provides the value for this tag.
     * Some tags use values from different input fields.
     */
    public function inputKey(): string
    {
        return match ($this) {
            self::Header => 'header',
            self::Subtitle => 'subtitle',
            self::Paragraph => 'paragraph',
            self::Url => 'url',
            self::SocialHandle => 'social_handle',
            self::CallToAction => 'cta',
            self::MainImage => 'main_image',
            self::Logo => 'logo',
            self::PrimaryColor, self::TextPrimaryColor => 'primary_color',
            self::SecondaryColor, self::TextSecondaryColor => 'secondary_color',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options filtered by layer type.
     *
     * @param LayerType|null $layerType
     * @return array
     */
    public static function optionsForType(?LayerType $layerType = null): array
    {
        $cases = $layerType
            ? array_filter(self::cases(), fn($case) => $case->isApplicableTo($layerType))
            : self::cases();

        return collect($cases)->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'category' => $case->category(),
        ])->values()->toArray();
    }

    /**
     * Get content tags filtered by layer type.
     *
     * @param LayerType|null $layerType
     * @return array
     */
    public static function contentTagsForType(?LayerType $layerType = null): array
    {
        $cases = array_filter(self::cases(), function ($case) use ($layerType) {
            if ($case->category() !== 'content') {
                return false;
            }
            return $layerType === null || $case->isApplicableTo($layerType);
        });

        return collect($cases)->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->values()->toArray();
    }

    /**
     * Get style tags filtered by layer type.
     *
     * @param LayerType|null $layerType
     * @return array
     */
    public static function styleTagsForType(?LayerType $layerType = null): array
    {
        $cases = array_filter(self::cases(), function ($case) use ($layerType) {
            if ($case->category() !== 'style') {
                return false;
            }
            return $layerType === null || $case->isApplicableTo($layerType);
        });

        return collect($cases)->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->values()->toArray();
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'category' => $case->category(),
        ])->toArray();
    }
}
