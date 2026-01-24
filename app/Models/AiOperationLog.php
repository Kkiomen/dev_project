<?php

namespace App\Models;

use App\Enums\ApiProvider;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiOperationLog extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'brand_id',
        'user_id',
        'operation',
        'provider',
        'model',
        'request_id',
        'endpoint',
        'input',
        'output',
        'tokens_used',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'duration_ms',
        'http_status',
        'status',
        'error_message',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'tokens_used' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'cost' => 'decimal:6',
        'duration_ms' => 'integer',
        'http_status' => 'integer',
        'provider' => ApiProvider::class,
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

    public function scopeForProvider(Builder $query, ApiProvider $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeForRequestId(Builder $query, string $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    // Static factory methods

    /**
     * Start logging an AI request (OpenAI, etc.)
     */
    public static function startAiRequest(
        ?Brand $brand,
        string $operation,
        array $input,
        ApiProvider $provider = ApiProvider::OPENAI,
        ?string $model = null,
        ?string $requestId = null
    ): self {
        return static::create([
            'brand_id' => $brand?->id,
            'user_id' => $brand?->user_id ?? auth()->id(),
            'operation' => $operation,
            'provider' => $provider,
            'model' => $model ?? config('services.openai.model', 'gpt-4o'),
            'request_id' => $requestId ?? Str::uuid()->toString(),
            'input' => $input,
            'status' => 'pending',
        ]);
    }

    /**
     * Start logging an external API request (Pexels, Facebook, etc.)
     */
    public static function startExternalRequest(
        ?Brand $brand,
        string $operation,
        ApiProvider $provider,
        string $endpoint,
        array $input = [],
        ?string $requestId = null
    ): self {
        return static::create([
            'brand_id' => $brand?->id,
            'user_id' => $brand?->user_id ?? auth()->id(),
            'operation' => $operation,
            'provider' => $provider,
            'endpoint' => $endpoint,
            'request_id' => $requestId ?? Str::uuid()->toString(),
            'input' => $input,
            'status' => 'pending',
        ]);
    }

    /**
     * Legacy start method for backwards compatibility
     */
    public static function start(Brand $brand, string $operation, array $input): self
    {
        return static::startAiRequest($brand, $operation, $input);
    }

    // Instance methods

    /**
     * Complete AI request with token details
     */
    public function completeAi(
        array $output,
        int $promptTokens,
        int $completionTokens,
        float $cost,
        int $durationMs
    ): self {
        $this->update([
            'output' => $output,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'tokens_used' => $promptTokens + $completionTokens,
            'cost' => $cost,
            'duration_ms' => $durationMs,
            'status' => 'completed',
        ]);

        return $this;
    }

    /**
     * Complete external API request
     */
    public function completeExternal(
        array $output,
        int $httpStatus,
        int $durationMs
    ): self {
        $this->update([
            'output' => $output,
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'status' => 'completed',
        ]);

        return $this;
    }

    /**
     * Legacy complete method for backwards compatibility
     */
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

    public function fail(string $errorMessage, int $durationMs = 0, ?int $httpStatus = null): self
    {
        $updateData = [
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'status' => 'failed',
        ];

        if ($httpStatus !== null) {
            $updateData['http_status'] = $httpStatus;
        }

        $this->update($updateData);

        return $this;
    }
}
