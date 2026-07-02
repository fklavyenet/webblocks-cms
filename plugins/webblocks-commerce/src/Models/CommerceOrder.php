<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebBlocks\Cms\Models\Site;

class CommerceOrder extends Model
{
  public const STATUS_PENDING = 'pending';

  public const STATUS_PAID = 'paid';

  public const STATUS_FAILED = 'failed';

  public const STATUS_CANCELLED = 'cancelled';

  public const STATUS_EXPIRED = 'expired';

  public const STATUS_REFUNDED = 'refunded';

  protected $table = 'webblocks_commerce_orders';

  protected $fillable = [
    'site_id',
    'order_number',
    'customer_email',
    'status',
    'subtotal_amount',
    'total_amount',
    'currency',
    'gateway',
    'gateway_checkout_id',
    'gateway_payment_id',
    'gateway_customer_id',
    'placed_at',
    'paid_at',
    'cancelled_at',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'subtotal_amount' => 'integer',
      'total_amount' => 'integer',
      'placed_at' => 'datetime',
      'paid_at' => 'datetime',
      'cancelled_at' => 'datetime',
      'metadata' => 'array',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function items(): HasMany
  {
    return $this->hasMany(CommerceOrderItem::class, 'order_id');
  }

  public function payments(): HasMany
  {
    return $this->hasMany(CommercePayment::class, 'order_id');
  }

  public function isPending(): bool
  {
    return $this->status === self::STATUS_PENDING;
  }
}
