<?php

namespace App\Models;

use App\Models\Concerns\ValidatesBlockTranslationLocale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockContactFormTranslation extends Model
{
    use HasFactory;
    use ValidatesBlockTranslationLocale;

    protected $fillable = [
        'block_id',
        'locale_id',
        'title',
        'content',
        'submit_label',
        'success_message',
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
