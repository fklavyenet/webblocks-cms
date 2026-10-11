<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class SystemInformationController extends Controller
{
  public function __invoke(): RedirectResponse
  {
    return redirect()->route('admin.system.health.index');
  }
}
