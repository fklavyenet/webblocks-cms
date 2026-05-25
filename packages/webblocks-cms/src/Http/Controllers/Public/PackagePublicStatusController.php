<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackagePublicStatusController extends Controller
{
  public function __invoke(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status', [
      'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME,
      'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH,
    ]);
  }
}
