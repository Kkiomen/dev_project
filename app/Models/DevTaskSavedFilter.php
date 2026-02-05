<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevTaskSavedFilter extends Model
{
    use HasPublicId;

    protected $fillable = [
        'user_id',
        'name',
        'filters',
        'is_default',
        'position',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function setAsDefault(): void
    {
        static::forUser($this->user_id)->update(['is_default' => false]);
        $this->is_default = true;
        $this->save();
    }
}
