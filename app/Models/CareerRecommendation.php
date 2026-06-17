<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CareerRecommendation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $recommendation) {
            if (Auth::check() && empty($recommendation->user_id)) {
                $recommendation->user_id = Auth::id();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'subject_id',
        'career_path_id',
        'career',
        'score',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'float',
    ];

    public function careerPath(): BelongsTo
    {
        return $this->belongsTo(CareerPath::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
