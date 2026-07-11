<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageAdminStatusController extends Controller
{
  public function __invoke(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status', [
      'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME,
      'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH,
    ]);
  }
}
