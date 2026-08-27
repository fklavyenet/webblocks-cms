<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\System\SystemInformation;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemInformationController extends Controller
{
  public function __invoke(SystemInformation $systemInformation): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.information', [
      'information' => $systemInformation->rows(),
    ]);
  }
}
