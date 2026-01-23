<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = Str::ulid()->toBase32();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function findByPublicId(string $publicId): ?static
    {
        return static::where('public_id', $publicId)->first();
    }

    public static function findByPublicIdOrFail(string $publicId): static
    {
        return static::where('public_id', $publicId)->firstOrFail();
    }
}
