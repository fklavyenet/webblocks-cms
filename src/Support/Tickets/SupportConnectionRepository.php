<?php

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SupportConnection;
use WebBlocks\Cms\Support\Database\SupportConnectionSchema;

final class SupportConnectionRepository
{
  public function __construct(
    private readonly SupportConnectionSchema $schema,
  ) {}

  public function current(): ?SupportConnection
  {
    if (! Schema::hasTable('wbcms_support_connections')) {
      return null;
    }

    return SupportConnection::query()->latest('id')->first();
  }

  public function replace(array $attributes): SupportConnection
  {
    // The support connection is an optional feature first used after an
    // update. Repair its package-owned table at the write boundary so a host
    // whose update-migration step was skipped is not sent to a terminal.
    $this->schema->ensure();

    SupportConnection::query()->delete();

    return SupportConnection::query()->create($attributes);
  }

  public function disconnect(): void
  {
    SupportConnection::query()->delete();
  }
}
