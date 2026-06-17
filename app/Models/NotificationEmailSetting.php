<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NotificationEmailSetting extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $setting) {
            if (Auth::check() && empty($setting->user_id)) {
                $setting->user_id = Auth::id();
            }
        });
    }

    protected $table = 'notification_email_settings';

    protected $fillable = [
        'id',
        'user_id',
        'email_enabled',
        'email_address',
        'digest_type',
        'days_ahead',
        'send_time',
        'timezone',
        'last_sent_at',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
