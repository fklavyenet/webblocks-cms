<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\ProjectLayerServiceProvider;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    ProjectLayerServiceProvider::class,
    WebBlocksCmsServiceProvider::class,
];
