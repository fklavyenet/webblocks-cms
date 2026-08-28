<?php

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SupportConnection;

final class SupportConnectionRepository
{
  public function current(): ?SupportConnection
  {
    if (! Schema::hasTable('wbcms_support_connections')) {
      return null;
    }

    return SupportConnection::query()->latest('id')->first();
  }

  public function replace(array $attributes): SupportConnection
  {
    SupportConnection::query()->delete();

    return SupportConnection::query()->create($attributes);
  }

  public function disconnect(): void
  {
    SupportConnection::query()->delete();
  }
}
