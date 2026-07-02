<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercePayment extends Model
{
  public const STATUS_PENDING = 'pending';

  public const STATUS_SUCCEEDED = 'succeeded';

  public const STATUS_FAILED = 'failed';

  public const STATUS_CANCELLED = 'cancelled';

  public const STATUS_REFUNDED = 'refunded';

  protected $table = 'webblocks_commerce_payments';

  protected $fillable = [
    'order_id',
    'gateway',
    'gateway_payment_id',
    'gateway_checkout_id',
    'status',
    'amount',
    'currency',
    'raw_event_id',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'amount' => 'integer',
      'metadata' => 'array',
    ];
  }

  public function order(): BelongsTo
  {
    return $this->belongsTo(CommerceOrder::class, 'order_id');
  }
}
