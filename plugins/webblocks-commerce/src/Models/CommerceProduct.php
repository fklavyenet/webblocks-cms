<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Site;

class CommerceProduct extends Model
{
  public const STATUS_DRAFT = 'draft';

  public const STATUS_ACTIVE = 'active';

  public const STATUS_ARCHIVED = 'archived';

  public const TAX_CLASS_STANDARD = 'standard';

  public const TAX_CLASS_REDUCED = 'reduced';

  public const TAX_CLASS_ZERO = 'zero';

  protected $table = 'webblocks_commerce_products';

  protected $fillable = [
    'site_id',
    'image_media_id',
    'title',
    'slug',
    'description',
    'status',
    'price_amount',
    'currency',
    'tax_class',
    'inventory_quantity',
    'sku',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'metadata' => 'array',
      'price_amount' => 'integer',
      'inventory_quantity' => 'integer',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function imageMedia(): BelongsTo
  {
    return $this->belongsTo(Media::class, 'image_media_id');
  }

  public function orderItems(): HasMany
  {
    return $this->hasMany(CommerceOrderItem::class, 'product_id');
  }

  public function translations(): HasMany
  {
    return $this->hasMany(CommerceProductTranslation::class, 'product_id');
  }

  public function isActive(): bool
  {
    return $this->status === self::STATUS_ACTIVE;
  }

  public function taxClass(): string
  {
    $taxClass = $this->tax_class;

    return is_string($taxClass) && $taxClass !== '' ? $taxClass : self::TAX_CLASS_STANDARD;
  }

  public function tracksInventory(): bool
  {
    return $this->inventory_quantity !== null;
  }

  public function isAvailableForCheckout(): bool
  {
    if (! $this->isActive()) {
      return false;
    }

    return ! $this->tracksInventory() || $this->inventory_quantity > 0;
  }
}
