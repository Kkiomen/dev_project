<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class PostProposal extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand_id',
        'scheduled_date',
        'scheduled_time',
        'title',
        'keywords',
        'notes',
        'status',
        'social_post_id',
        'position',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'keywords' => 'array',
        'status' => ProposalStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'user_id';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    // Scopes
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::Pending);
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::Used);
    }

    public function scopeScheduledBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('scheduled_date', [$start, $end]);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('scheduled_date', $year)
            ->whereMonth('scheduled_date', $month);
    }

    // Helpers
    public function markAsUsed(SocialPost $socialPost): self
    {
        $this->status = ProposalStatus::Used;
        $this->social_post_id = $socialPost->id;
        $this->save();

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === ProposalStatus::Pending;
    }

    public function isUsed(): bool
    {
        return $this->status === ProposalStatus::Used;
    }
}
