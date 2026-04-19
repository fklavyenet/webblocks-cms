<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishUpdateReleaseRequest;
use App\Support\Updates\UpdatesServerPublisher;
use App\Support\Updates\UpdatesServerPublishException;
use Illuminate\Http\RedirectResponse;

class SystemUpdatePublishController extends Controller
{
    public function __invoke(PublishUpdateReleaseRequest $request, UpdatesServerPublisher $publisher): RedirectResponse
    {
        try {
            $result = $publisher->publish($request->validated());

            return redirect()
                ->route('admin.system.updates.index')
                ->with('status', 'Release published to Updates Server.')
                ->with('publish_result', $result);
        } catch (UpdatesServerPublishException $exception) {
            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['release_publish' => $exception->getMessage()])
                ->withInput();
        }
    }
}
