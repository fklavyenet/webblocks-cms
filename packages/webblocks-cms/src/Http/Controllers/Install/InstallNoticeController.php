<?php

namespace WebBlocks\Cms\Http\Controllers\Install;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class InstallNoticeController extends Controller
{
    public function __invoke(): View
    {
        return view('webblocks-cms::install.notice');
    }
}
