<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceSetting extends Model
{
  protected $table = 'webblocks_commerce_settings';

  protected $fillable = [
    'key',
    'value',
  ];

  protected function casts(): array
  {
    return [
      'value' => 'encrypted',
    ];
  }
}
