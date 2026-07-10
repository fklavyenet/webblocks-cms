<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Models\Locale;

class CommerceProductTranslation extends Model
{
  protected $table = 'webblocks_commerce_product_translations';

  protected $fillable = [
    'product_id',
    'locale_id',
    'title',
    'description',
  ];

  public function product(): BelongsTo
  {
    return $this->belongsTo(CommerceProduct::class, 'product_id');
  }

  public function locale(): BelongsTo
  {
    return $this->belongsTo(Locale::class, 'locale_id');
  }
}
