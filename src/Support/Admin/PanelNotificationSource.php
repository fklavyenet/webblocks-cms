<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Contracts\Auth\Authenticatable;

interface PanelNotificationSource
{
  /**
   * Return a read-only aggregate for the same permitted records shown by the
   * destination route, or null when the user has no matching work.
   *
   * Apply the plugin's own permission and site rules within the CMS panel scope.
   *
   * @param  list<int>  $siteIds  Sites where the user can access the CMS panel.
   */
  public function notificationFor(Authenticatable $user, array $siteIds): ?PanelNotification;
}
