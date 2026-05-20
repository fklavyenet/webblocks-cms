<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\Admin\SiteVariableRequest;
use WebBlocks\Cms\Models\SiteVariable;

class SiteVariableController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
    ) {}

    public function store(SiteVariableRequest $request, Site $site): RedirectResponse
    {
        $this->authorization->abortUnlessSiteVariableMutation($request->user(), $site);

        $site->siteVariables()->create($request->siteVariableData());

        return redirect()
            ->to($request->input('_site_variable_close_url', route('admin.sites.edit', ['site' => $site, 'tab' => 'variables'])))
            ->with('status', 'Site variable added.');
    }

    public function update(SiteVariableRequest $request, Site $site, SiteVariable $siteVariable): RedirectResponse
    {
        $this->authorization->abortUnlessSiteVariableMutation($request->user(), $site);
        abort_unless((int) $siteVariable->site_id === (int) $site->id, 404);

        $siteVariable->update($request->siteVariableData());

        return redirect()
            ->to($request->input('_site_variable_close_url', route('admin.sites.edit', ['site' => $site, 'tab' => 'variables'])))
            ->with('status', 'Site variable updated.');
    }

    public function destroy(Site $site, SiteVariable $siteVariable): RedirectResponse
    {
        $this->authorization->abortUnlessSiteVariableMutation(request()->user(), $site);
        abort_unless((int) $siteVariable->site_id === (int) $site->id, 404);

        $siteVariable->delete();

        return redirect()
            ->route('admin.sites.edit', ['site' => $site, 'tab' => 'variables'])
            ->with('status', 'Site variable deleted.');
    }
}
