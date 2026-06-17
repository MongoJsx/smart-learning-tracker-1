<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareerPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function recommendations(): HasMany
    {
        return $this->hasMany(CareerRecommendation::class);
    }
}
