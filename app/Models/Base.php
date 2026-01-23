<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Base extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class)->ordered();
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    // Helper methods
    public function createTable(string $name, ?string $description = null): Table
    {
        $table = $this->tables()->create([
            'name' => $name,
            'description' => $description,
        ]);

        // Create default primary text field
        $table->fields()->create([
            'name' => 'Name',
            'type' => 'text',
            'is_primary' => true,
        ]);

        return $table;
    }
}
