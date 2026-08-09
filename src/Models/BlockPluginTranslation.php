<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Models\Concerns\ValidatesBlockTranslationLocale;
use WebBlocks\Cms\Support\Search\ReindexesPublicSearch;

/**
 * One translated field of one plugin-declared block, in one locale.
 *
 * Every other translation family is a table of columns, because core knows the
 * fields before the migration is written. A plugin's block is declared at install
 * time by a package core has never seen, so its fields arrive as data — and a row per
 * field is the shape that lets a plugin name its own copy without core shipping a
 * release first.
 */
class BlockPluginTranslation extends CmsModel
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
    'field',
    'value',
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
