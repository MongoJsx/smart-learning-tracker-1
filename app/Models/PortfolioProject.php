<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'project_name',
        'project_description',
        'project_image',
        'project_url',
        'github_url',
        'technologies',
        'project_type',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
