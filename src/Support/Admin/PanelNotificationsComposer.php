<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\View\View;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogUpdates;

class PanelNotificationsComposer
{
  public function __construct(
    private readonly PanelNotificationQuery $notifications,
    private readonly PluginCatalogUpdates $pluginUpdates,
  ) {}

  public function compose(View $view): void
  {
    $user = request()->user();
    if (! array_key_exists('panelNotifications', $view->getData())) {
      $view->with('panelNotifications', $this->notifications->forUser($user));
    }
    $view->with('panelPendingComments', $this->notifications->pendingCommentsForUser($user));
    $view->with('panelPluginNotifications', $this->notifications->pluginItemsForUser($user));
    if ($user && app(CmsAuthorization::class)->isSuperAdmin($user)) {
      $status = $view->getData()['pluginCatalogUpdateStatus'] ?? $this->pluginUpdates->snapshot();
      $view->with('panelPluginUpdateCount', count($status['updates']));
      $view->with('checkPanelPluginUpdates', $status['due'] && ! $status['retry_pending']);
    }
  }
}
