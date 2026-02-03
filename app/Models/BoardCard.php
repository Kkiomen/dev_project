<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardCard extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'column_id',
        'created_by',
        'title',
        'description',
        'position',
        'color',
        'due_date',
        'labels',
    ];

    protected $casts = [
        'position' => 'integer',
        'due_date' => 'date',
        'labels' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'column_id';
    }

    // Relationships
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'column_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helpers
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }
}
