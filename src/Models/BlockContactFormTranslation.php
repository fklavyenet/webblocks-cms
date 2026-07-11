<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Models\Concerns\ValidatesBlockTranslationLocale;
use WebBlocks\Cms\Support\Search\ReindexesPublicSearch;

class BlockContactFormTranslation extends CmsModel
{
  use HasFactory;
  use ReindexesPublicSearch;
  use ValidatesBlockTranslationLocale;

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
