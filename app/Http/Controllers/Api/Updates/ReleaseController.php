<?php

namespace App\Http\Controllers\Api\Updates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Updates\ReleaseIndexRequest;
use App\Http\Requests\Updates\ShowReleaseRequest;
use App\Http\Resources\Updates\ReleaseResource;
use App\Support\System\Updates\ReleaseCatalog;
use App\Support\System\Updates\UpdateApiResponse;

class ReleaseController extends Controller
{
    public function index(ReleaseIndexRequest $request, ReleaseCatalog $releaseCatalog)
    {
        $releases = $releaseCatalog->releaseHistory(
            $request->string('product')->toString(),
            $request->string('channel')->toString(),
            $request->integer('limit'),
        );

        return UpdateApiResponse::success([
            'product' => $request->string('product')->toString(),
            'channel' => $request->string('channel')->toString(),
            'releases' => ReleaseResource::collection($releases->getCollection())->resolve(),
        ], [
            'pagination' => [
                'current_page' => $releases->currentPage(),
                'last_page' => $releases->lastPage(),
                'per_page' => $releases->perPage(),
                'total' => $releases->total(),
            ],
        ]);
    }

    public function show(ShowReleaseRequest $request, ReleaseCatalog $releaseCatalog)
    {
        $release = $releaseCatalog->releaseByVersion(
            $request->string('product')->toString(),
            $request->string('version')->toString(),
        );

        if ($release === null) {
            return UpdateApiResponse::error('release_not_found', 'No published release was found for the requested product and version.', 404);
        }

        return UpdateApiResponse::success([
            'product' => $release->product,
            'release' => ReleaseResource::make($release)->resolve(),
        ]);
    }
}
