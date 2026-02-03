<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'description',
        'color',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->ordered();
    }

    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(BoardCard::class, BoardColumn::class, 'board_id', 'column_id');
    }

    // Scopes
    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }
}
