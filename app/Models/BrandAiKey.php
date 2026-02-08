<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandAiKey extends Model
{
    protected $fillable = [
        'brand_id',
        'provider',
        'api_key',
        'is_active',
    ];

    protected $casts = [
        'provider' => AiProvider::class,
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? encrypt($value) : null;
    }

    public function getApiKeyAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    public static function getKeyForProvider(Brand $brand, AiProvider $provider): ?string
    {
        $key = $brand->aiKeys()->where('provider', $provider->value)->where('is_active', true)->first();

        return $key?->api_key;
    }
}
