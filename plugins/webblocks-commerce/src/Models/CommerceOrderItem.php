<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceOrderItem extends Model
{
  protected $table = 'webblocks_commerce_order_items';

  protected $fillable = [
    'order_id',
    'product_id',
    'title',
    'sku',
    'quantity',
    'unit_amount',
    'total_amount',
    'tax_amount',
    'tax_rate',
    'tax_class',
    'currency',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'quantity' => 'integer',
      'unit_amount' => 'integer',
      'total_amount' => 'integer',
      'tax_amount' => 'integer',
      'tax_rate' => 'integer',
      'metadata' => 'array',
    ];
  }

  public function order(): BelongsTo
  {
    return $this->belongsTo(CommerceOrder::class, 'order_id');
  }

  public function product(): BelongsTo
  {
    return $this->belongsTo(CommerceProduct::class, 'product_id');
  }
}
