<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateRun extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_SUCCESS_WITH_WARNINGS = 'success_with_warnings';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'from_version',
        'to_version',
        'status',
        'summary',
        'output',
        'warning_count',
        'started_at',
        'finished_at',
        'duration_ms',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
