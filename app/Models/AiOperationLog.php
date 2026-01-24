<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOperationLog extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'brand_id',
        'user_id',
        'operation',
        'input',
        'output',
        'tokens_used',
        'cost',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'tokens_used' => 'integer',
        'cost' => 'decimal:6',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForOperation(Builder $query, string $operation): Builder
    {
        return $query->where('operation', $operation);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // Static factory methods
    public static function start(Brand $brand, string $operation, array $input): self
    {
        return static::create([
            'brand_id' => $brand->id,
            'user_id' => $brand->user_id,
            'operation' => $operation,
            'input' => $input,
            'status' => 'pending',
        ]);
    }

    // Instance methods
    public function complete(array $output, int $tokensUsed, float $cost, int $durationMs): self
    {
        $this->update([
            'output' => $output,
            'tokens_used' => $tokensUsed,
            'cost' => $cost,
            'duration_ms' => $durationMs,
            'status' => 'completed',
        ]);

        return $this;
    }

    public function fail(string $errorMessage, int $durationMs = 0): self
    {
        $this->update([
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'status' => 'failed',
        ]);

        return $this;
    }
}
