<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AutomaticPageRule extends Model
{
  protected $table = 'wbcms_automatic_page_rules';

  protected $fillable = ['name', 'site_id', 'source_handle', 'template_page_id', 'catalogue_page_id', 'base_path'];

  protected function casts(): array
  {
    return ['revision' => 'integer'];
  }

  protected static function booted(): void
  {
    self::saving(function (self $rule): void {
      if (($rule->status ?? 'draft') !== 'draft') {
        throw new LogicException('Automatic page rules are draft-only in this phase.');
      }
      $rule->status = 'draft';
    });
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function templatePage(): BelongsTo
  {
    return $this->belongsTo(Page::class, 'template_page_id');
  }

  public function cataloguePage(): BelongsTo
  {
    return $this->belongsTo(Page::class, 'catalogue_page_id');
  }
}
