<?php

namespace WebBlocks\Cms\Models;

use App\Models\Block;
use App\Models\Locale;
use App\Support\Search\ReindexesPublicSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Models\Concerns\ValidatesBlockTranslationLocale;

class BlockImageTranslation extends Model
{
    use HasFactory;
    use ReindexesPublicSearch;
    use ValidatesBlockTranslationLocale;

    protected $fillable = [
        'block_id',
        'locale_id',
        'caption',
        'alt_text',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $translation): void {
            if ($translation->block instanceof Block || $translation->block()->exists()) {
                static::refreshSearchForBlock($translation->block ?? $translation->block()->first());
            }
        });

        static::deleted(function (self $translation): void {
            if ($translation->block instanceof Block || $translation->block()->exists()) {
                static::refreshSearchForBlock($translation->block ?? $translation->block()->first());
            }
        });
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }
}
