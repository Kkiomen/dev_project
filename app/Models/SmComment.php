<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmComment extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_comments';

    protected $fillable = [
        'brand_id',
        'platform',
        'external_post_id',
        'external_comment_id',
        'social_post_id',
        'author_handle',
        'author_name',
        'author_avatar',
        'text',
        'sentiment',
        'is_replied',
        'reply_text',
        'replied_at',
        'is_hidden',
        'is_flagged',
        'posted_at',
    ];

    protected $casts = [
        'is_replied' => 'boolean',
        'is_hidden' => 'boolean',
        'is_flagged' => 'boolean',
        'replied_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function scopeUnreplied($query)
    {
        return $query->where('is_replied', false);
    }

    public function scopeNegative($query)
    {
        return $query->where('sentiment', 'negative');
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function isNegative(): bool
    {
        return in_array($this->sentiment, ['negative', 'crisis']);
    }

    public function markAsReplied(string $replyText): self
    {
        $this->is_replied = true;
        $this->reply_text = $replyText;
        $this->replied_at = now();
        $this->save();

        return $this;
    }
}
