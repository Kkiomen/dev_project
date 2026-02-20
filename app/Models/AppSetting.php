<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'app_setting:';
    private const CACHE_TTL = 3600;

    public static function getValue(string $key, mixed $default = null): ?string
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey) ?? $default;
        }

        $value = static::where('key', $key)->value('value');
        Cache::put($cacheKey, $value, self::CACHE_TTL);

        return $value ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
