<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PostApproval extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'social_post_id',
        'approval_token_id',
        'is_approved',
        'feedback_notes',
        'responded_at',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function approvalToken(): BelongsTo
    {
        return $this->belongsTo(ApprovalToken::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('is_approved');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('is_approved', false);
    }

    public function scopeResponded(Builder $query): Builder
    {
        return $query->whereNotNull('responded_at');
    }

    // Helper methods
    public function isPending(): bool
    {
        return is_null($this->is_approved);
    }

    public function isApproved(): bool
    {
        return $this->is_approved === true;
    }

    public function isRejected(): bool
    {
        return $this->is_approved === false;
    }

    public function approve(?string $notes = null): self
    {
        $this->is_approved = true;
        $this->feedback_notes = $notes;
        $this->responded_at = now();
        $this->save();

        // Update social post status if all approvals are approved
        $this->updateSocialPostStatus();

        return $this;
    }

    public function reject(?string $notes = null): self
    {
        $this->is_approved = false;
        $this->feedback_notes = $notes;
        $this->responded_at = now();
        $this->save();

        // Update social post status back to draft for changes
        $this->socialPost->update(['status' => 'draft']);

        return $this;
    }

    public function respond(bool $approved, ?string $notes = null): self
    {
        if ($approved) {
            return $this->approve($notes);
        }

        return $this->reject($notes);
    }

    protected function updateSocialPostStatus(): void
    {
        $socialPost = $this->socialPost;

        // Check if all approvals for this post are approved
        $pendingApprovals = $socialPost->approvals()
            ->whereNull('is_approved')
            ->count();

        $rejectedApprovals = $socialPost->approvals()
            ->where('is_approved', false)
            ->count();

        if ($rejectedApprovals > 0) {
            // If any rejection, set to draft
            $socialPost->update(['status' => 'draft']);
        } elseif ($pendingApprovals === 0) {
            // All approved
            $socialPost->approve();
        }
    }
}
