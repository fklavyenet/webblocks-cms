<?php

namespace App\Models;

use App\Models\Concerns\ValidatesBlockTranslationLocale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockTextTranslation extends Model
{
    use HasFactory;
    use ValidatesBlockTranslationLocale;

    protected $fillable = [
        'block_id',
        'locale_id',
        'title',
        'subtitle',
        'content',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }
}
