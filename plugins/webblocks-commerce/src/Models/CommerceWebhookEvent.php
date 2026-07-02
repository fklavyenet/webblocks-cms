<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceWebhookEvent extends Model
{
  public const STATUS_RECEIVED = 'received';

  public const STATUS_PROCESSED = 'processed';

  public const STATUS_IGNORED = 'ignored';

  public const STATUS_FAILED = 'failed';

  protected $table = 'webblocks_commerce_webhook_events';

  protected $fillable = [
    'gateway',
    'event_id',
    'event_type',
    'processed_at',
    'payload_digest',
    'status',
    'message',
  ];

  protected function casts(): array
  {
    return [
      'processed_at' => 'datetime',
    ];
  }
}
