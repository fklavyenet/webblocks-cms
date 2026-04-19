<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemReleasePublish extends Model
{
    protected $fillable = [
        'version',
        'channel',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'published_at' => 'datetime',
    ];
}
