<?php

namespace WebBlocks\Cms\Http\Controllers\Diagnostics;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageDiagnosticsController extends Controller
{
    public function show(): View
    {
        return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::diagnostics.package-status', [
            'viewNamespace' => WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
            'packageBasePath' => base_path('packages/webblocks-cms'),
        ]);
    }
}
