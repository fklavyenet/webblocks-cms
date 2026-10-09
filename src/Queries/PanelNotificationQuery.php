<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\PanelNotificationPolicy;

class PanelNotificationQuery
{
  public function __construct(private readonly PanelNotificationPolicy $policy, private readonly SiteNotificationHealthQuery $health) {}

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
      // Unread is a subset of awaiting; do not count the same message twice.
      'attention' => $inboxes->sum('awaiting') + $warnings,
      'scheduler' => $scheduler,
    ];
  }
}
