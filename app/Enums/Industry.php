<?php

namespace App\Enums;

enum Industry: string
{
    case Marketing = 'marketing';
    case Technology = 'technology';
    case Finance = 'finance';
    case Healthcare = 'healthcare';
    case Education = 'education';
    case Ecommerce = 'ecommerce';
    case RealEstate = 'real_estate';
    case Fitness = 'fitness';
    case Food = 'food';
    case Travel = 'travel';
    case Fashion = 'fashion';
    case Entertainment = 'entertainment';
    case Consulting = 'consulting';
    case Legal = 'legal';
    case NonProfit = 'non_profit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Technology => 'Technology / IT',
            self::Finance => 'Finance',
            self::Healthcare => 'Healthcare',
            self::Education => 'Education',
            self::Ecommerce => 'E-commerce',
            self::RealEstate => 'Real Estate',
            self::Fitness => 'Fitness / Wellness',
            self::Food => 'Food & Beverage',
            self::Travel => 'Travel & Hospitality',
            self::Fashion => 'Fashion & Beauty',
            self::Entertainment => 'Entertainment',
            self::Consulting => 'Consulting',
            self::Legal => 'Legal',
            self::NonProfit => 'Non-Profit',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
