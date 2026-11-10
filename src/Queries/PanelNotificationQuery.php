<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\EngagementPolicy;
use WebBlocks\Cms\Policies\PanelNotificationPolicy;
use WebBlocks\Cms\Support\Admin\PanelNotificationSources;

class PanelNotificationQuery
{
  public function __construct(
    private readonly PanelNotificationPolicy $policy,
    private readonly SiteNotificationHealthQuery $health,
    private readonly EngagementPolicy $engagementPolicy,
    private readonly EngagementQuery $engagement,
  ) {}

  public function pendingCommentsForUser($user): int
  {
    if (! $this->engagementPolicy->viewAny($user) || ! Schema::hasTable((new CommentEntry)->getTable())) {
      return 0;
    }

    return $this->engagement->comments($user, ['status' => 'pending', 'moderatable' => true])->count();
  }

  /** @return list<array{count: int, labelKey: string, url: string}> */
  public function pluginItemsForUser(?Authenticatable $user): array
  {
    if ($user === null) {
      return [];
    }

    $items = [];
    $siteIds = null;
    foreach (app(PanelNotificationSources::class)->active() as [$plugin, $source]) {
      $siteIds ??= $this->policy->pluginSiteIds($user);
      if ($siteIds === []) {
        return [];
      }

      try {
        $notification = app($source)->notificationFor($user, $siteIds);
        if ($notification === null || $notification->count <= 0 || trim($notification->labelKey) === '') {
          continue;
        }

        $route = Route::getRoutes()->getByName($notification->route);
        $prefix = trim($plugin->adminRoutePrefix(), '/');
        if (! $route || ! str_starts_with($notification->route, $plugin->routeNamePrefix().'.')
          || ! in_array('GET', $route->methods(), true)
          || ($route->uri() !== $prefix && ! str_starts_with($route->uri(), $prefix.'/'))) {
          continue;
        }

        $items[] = [
          'count' => $notification->count,
          'labelKey' => $notification->labelKey,
          'url' => route($notification->route, $notification->routeParameters, absolute: false),
        ];
      } catch (Throwable) {
        Log::warning('Panel notification source failed.', ['plugin' => $plugin->handle(), 'source' => $source]);
      }
    }

    return $items;
  }

  public function forUser($user): ?array
  {
    if (! $this->policy->view($user)) {
      return null;
    }

    $scheduler = $this->health->dashboardForUser($user);
    $sites = $scheduler['sites']->pluck('site');
    $available = Schema::hasTable((new ContactMessage)->getTable());
    $counts = collect();
    if ($available && $sites->isNotEmpty()) {
      // Read only aggregate state. Mail opt-in, recipients and scheduler runs
      // must not determine whether an operator can see stored work.
      $counts = DB::table((new ContactMessage)->getTable().' as messages')
        ->join((new Page)->getTable().' as pages', 'pages.id', '=', 'messages.page_id')
        ->whereIn('pages.site_id', $sites->pluck('id'))
        ->whereIn('messages.status', ['new', 'read'])
        ->selectRaw("pages.site_id, COUNT(*) as awaiting, SUM(CASE WHEN messages.status = 'new' THEN 1 ELSE 0 END) as unread")
        ->groupBy('pages.site_id')->get()->keyBy('site_id');
    }
    $inboxes = $sites->map(fn ($site) => [
      'site' => $site,
      'unread' => (int) ($counts->get($site->id)?->unread ?? 0),
      'awaiting' => (int) ($counts->get($site->id)?->awaiting ?? 0),
      'url' => route('admin.contact-messages.index', ['site' => $site->id]),
    ]);
    $health = $scheduler['health'];
    $warnings = (int) (! $available) + (int) ($health && $health['required'] && in_array($health['status'], ['unverified', 'delayed', 'failed', 'unavailable'], true));

    return [
      'inbox_available' => $available,
      'inboxes' => $inboxes,
      'unread' => $inboxes->sum('unread'),
      'awaiting' => $inboxes->sum('awaiting'),
      'warnings' => $warnings,
      'scheduler' => $scheduler,
    ];
  }
}
