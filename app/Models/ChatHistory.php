<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    protected $table = 'chat_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'room_id',
        'sender_type',
        'message',
        'attachment_url',
        'is_deleted',
        'created_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'created_at' => 'datetime',
    ];
}
