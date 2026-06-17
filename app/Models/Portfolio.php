<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'full_name',
        'nickname',
        'description',
        'cover_image',
        'profile_image',
        'age',
        'ethnicity',
        'nationality',
        'religion',
        'family_history',
        'father_name',
        'father_phone',
        'mother_name',
        'mother_phone',
        'education_history',
        'special_abilities',
        'awards_summary',
        'phone',
        'address',
        'theme_color',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(PortfolioSkill::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(PortfolioInterest::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PortfolioImage::class);
    }
}
