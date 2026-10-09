<?php

namespace WebBlocks\Cms\Policies;

class PanelNotificationPolicy
{
  public function view($user): bool
  {
    return (bool) $user?->can('manage-site-operations');
  }
}
