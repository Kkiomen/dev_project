<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateFont extends Model
{
    use HasFactory;

    protected $fillable = [
        'font_family',
        'font_file',
        'font_weight',
        'font_style',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    // Accessors
    public function getFontFaceNameAttribute(): string
    {
        $name = $this->font_family;

        if ($this->font_weight !== 'normal') {
            $name .= '-' . $this->font_weight;
        }

        if ($this->font_style !== 'normal') {
            $name .= '-' . $this->font_style;
        }

        return $name;
    }
}
