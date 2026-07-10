<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCartItem extends Model
{
  protected $table = 'webblocks_commerce_cart_items';

  protected $fillable = [
    'cart_id',
    'product_id',
    'quantity',
    'currency',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'quantity' => 'integer',
      'metadata' => 'array',
    ];
  }

  public function cart(): BelongsTo
  {
    return $this->belongsTo(CommerceCart::class, 'cart_id');
  }

  public function product(): BelongsTo
  {
    return $this->belongsTo(CommerceProduct::class, 'product_id');
  }
}
