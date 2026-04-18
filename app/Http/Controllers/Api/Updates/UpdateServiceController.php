<?php

namespace App\Http\Controllers\Api\Updates;

use App\Http\Controllers\Controller;
use App\Http\Resources\Updates\UpdateServiceResource;
use App\Support\System\Updates\ReleaseCatalog;
use App\Support\System\Updates\UpdateApiResponse;

class UpdateServiceController extends Controller
{
    public function __invoke(ReleaseCatalog $releaseCatalog)
    {
        return UpdateApiResponse::success(UpdateServiceResource::make([
            'products' => $releaseCatalog->productCatalog(),
        ])->resolve());
    }
}
