<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'settings',
        'onboarding_completed',
        'onboarding_data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'settings' => 'array',
            'onboarding_completed' => 'boolean',
            'onboarding_data' => 'array',
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function bases(): HasMany
    {
        return $this->hasMany(Base::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function approvalTokens(): HasMany
    {
        return $this->hasMany(ApprovalToken::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function brandMemberships(): HasMany
    {
        return $this->hasMany(BrandMember::class);
    }

    public function memberBrands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_members')
            ->withPivot(['role', 'invited_at', 'accepted_at', 'invited_by'])
            ->withTimestamps();
    }

    /**
     * Get all brands the user has access to (owned + member of)
     */
    public function allBrands()
    {
        $ownedBrandIds = $this->brands()->pluck('id');
        $memberBrandIds = $this->memberBrands()->pluck('brands.id');

        return Brand::whereIn('id', $ownedBrandIds->merge($memberBrandIds)->unique())
            ->where('is_active', true);
    }

    public function aiOperationLogs(): HasMany
    {
        return $this->hasMany(AiOperationLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function getCurrentBrandId(): ?int
    {
        return $this->getSetting('current_brand_id');
    }

    public function setCurrentBrand(Brand $brand): void
    {
        $this->setSetting('current_brand_id', $brand->id);
        $this->save();
    }

    public function getCurrentBrand(): ?Brand
    {
        $brandId = $this->getCurrentBrandId();

        if (!$brandId) {
            return null;
        }

        return $this->brands()->find($brandId);
    }
}
