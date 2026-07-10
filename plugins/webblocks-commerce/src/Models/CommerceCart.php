<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebBlocks\Cms\Models\Site;

class CommerceCart extends Model
{
  public const STATUS_OPEN = 'open';

  public const STATUS_CONVERTED = 'converted';

  public const STATUS_ABANDONED = 'abandoned';

  protected $table = 'webblocks_commerce_carts';

  protected $fillable = [
    'token',
    'site_id',
    'locale',
    'currency',
    'status',
    'customer_email',
    'converted_order_id',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'metadata' => 'array',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function items(): HasMany
  {
    return $this->hasMany(CommerceCartItem::class, 'cart_id');
  }

  public function convertedOrder(): BelongsTo
  {
    return $this->belongsTo(CommerceOrder::class, 'converted_order_id');
  }

  public function isOpen(): bool
  {
    return $this->status === self::STATUS_OPEN;
  }
}
