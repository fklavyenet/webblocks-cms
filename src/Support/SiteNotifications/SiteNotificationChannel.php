<?php

namespace WebBlocks\Cms\Support\SiteNotifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use WebBlocks\Cms\Models\Site;

interface SiteNotificationChannel
{
  public function source(Site $site, int $id): ?Model;

  public function enabled(Model $source): bool;

  public function group(Model $source): string;

  public function recipient(Model $source): ?string;

  public function fullMail(array $sources): Mailable;

  public function record(Model $source, string $status): void;

  /** @return array<string, array{unread: int, awaiting: int}> */
  public function summaries(Site $site): array;

  /** Translation key for this channel, resolved in the notification locale. */
  public function label(): string;

  public function inbox(Site $site): string;
}
