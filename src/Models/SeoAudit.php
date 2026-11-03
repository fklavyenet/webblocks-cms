<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeoAudit extends Model
{
  protected $table = 'wbcms_seo_audits';

  protected $guarded = [];

  protected function casts(): array
  {
    return ['worker_expires_at' => 'datetime', 'completed_at' => 'datetime', 'total' => 'integer', 'processed' => 'integer', 'site_id' => 'integer'];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }
}
