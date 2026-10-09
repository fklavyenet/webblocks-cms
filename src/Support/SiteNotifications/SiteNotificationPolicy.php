<?php

namespace WebBlocks\Cms\Support\SiteNotifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Site;

final class SiteNotificationPolicy
{
  public const DEFAULTS = ['notification_mode' => 'alert_only', 'notification_frequency' => 'batched', 'batch_minutes' => 10, 'daily_summary' => true, 'summary_hour' => 9];

  public const LEGACY = ['notification_mode' => 'full', 'notification_frequency' => 'immediate', 'batch_minutes' => 10, 'daily_summary' => false, 'summary_hour' => 9];

  public static function forSite(Site $site): array
  {
    $raw = $site->notification_settings;
    if ($raw === null) {
      return $site->exists && ! Schema::hasColumn('wbcms_sites', 'notification_settings') ? self::LEGACY : self::DEFAULTS;
    }
    $raw = is_array($raw) ? $raw : [];

    return [
      // Corrupt values never reopen detailed delivery.
      'notification_mode' => ($raw['notification_mode'] ?? null) === 'full' ? 'full' : 'alert_only',
      'notification_frequency' => in_array($raw['notification_frequency'] ?? null, ['immediate', 'batched', 'daily'], true) ? $raw['notification_frequency'] : 'batched',
      'batch_minutes' => max(1, min(60, (int) ($raw['batch_minutes'] ?? 10))),
      'daily_summary' => filter_var($raw['daily_summary'] ?? false, FILTER_VALIDATE_BOOLEAN),
      'summary_hour' => max(0, min(23, (int) ($raw['summary_hour'] ?? 9))),
    ];
  }

  public static function rules(string $prefix = ''): array
  {
    return [
      $prefix.'notification_mode' => ['sometimes', 'required', Rule::in(['full', 'alert_only'])],
      $prefix.'notification_frequency' => ['sometimes', 'required', Rule::in(['immediate', 'batched', 'daily'])],
      $prefix.'batch_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
      $prefix.'daily_summary' => ['sometimes', 'required', 'boolean'],
      $prefix.'summary_hour' => ['sometimes', 'required', 'integer', 'min:0', 'max:23'],
    ];
  }

  public static function deliveryStatus(Site $site): array
  {
    if (! Schema::hasTable('wbcms_site_notification_events')) {
      return [];
    }

    return DB::table('wbcms_site_notification_events')->where('site_id', $site->id)
      ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all();
  }

  public static function summaryDeliveryStatus(Site $site): array
  {
    if (! Schema::hasTable('wbcms_site_notification_states')) {
      return [];
    }

    return DB::table('wbcms_site_notification_states')->where('site_id', $site->id)
      ->where('channel', 'summary')->whereNotNull('status')->selectRaw('status, COUNT(*) as total')
      ->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all();
  }

  public static function inbox(string $path): string
  {
    // A visitor's Host, referrer and source URL never determine admin links.
    return rtrim((string) config('app.url'), '/').'/webadmin/'.ltrim($path, '/');
  }
}
