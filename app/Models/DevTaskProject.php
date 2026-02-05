<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DevTaskProject extends Model
{
    protected $fillable = [
        'prefix',
        'name',
        'next_sequence',
    ];

    protected $casts = [
        'next_sequence' => 'integer',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(DevTask::class, 'project', 'prefix');
    }

    public function getNextIdentifier(): string
    {
        return DB::transaction(function () {
            $project = static::where('id', $this->id)->lockForUpdate()->first();
            $sequence = $project->next_sequence;
            $project->increment('next_sequence');

            return "{$this->prefix}-{$sequence}";
        });
    }

    public static function findByPrefixOrFail(string $prefix): static
    {
        return static::where('prefix', $prefix)->firstOrFail();
    }
}
