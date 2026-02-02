<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateGroup extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'source_filename',
        'template_count',
    ];

    protected $casts = [
        'template_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class)->orderBy('variant_order');
    }

    public function primaryTemplate(): ?Template
    {
        return $this->templates()->where('variant_order', 1)->first();
    }

    public function updateTemplateCount(): self
    {
        $this->update([
            'template_count' => $this->templates()->count(),
        ]);

        return $this;
    }
}
