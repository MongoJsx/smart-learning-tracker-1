<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmailProviderAccount extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $account) {
            if (Auth::check() && empty($account->user_id)) {
                $account->user_id = Auth::id();
            }
        });
    }

    protected $table = 'email_provider_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'auth_type',
        'provider_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'status',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'scopes' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
