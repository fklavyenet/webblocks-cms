<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\View\View;
use WebBlocks\Cms\Queries\PanelNotificationQuery;

class PanelNotificationsComposer
{
  public function __construct(private readonly PanelNotificationQuery $notifications) {}

  public function compose(View $view): void
  {
    $user = request()->user();
    if (! array_key_exists('panelNotifications', $view->getData())) {
      $view->with('panelNotifications', $this->notifications->forUser($user));
    }
    $view->with('panelPendingComments', $this->notifications->pendingCommentsForUser($user));
  }
}
