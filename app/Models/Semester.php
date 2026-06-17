<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    use HasFactory;

    protected $table = 'semester';
    protected $primaryKey = 'semester_id';
    public $timestamps = false;

    protected $fillable = [
        'semester',
        'academic_year',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'semester_id', 'semester_id');
    }
}
