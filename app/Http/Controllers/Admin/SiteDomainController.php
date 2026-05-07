<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteDomainStoreRequest;
use App\Http\Requests\Admin\SiteDomainUpdateRequest;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Support\Sites\SiteDomainManager;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteDomainController extends Controller
{
    public function __construct(
        private readonly SiteDomainManager $siteDomainManager,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(Site $site): View
    {
        $this->authorization->abortUnlessSystem(request()->user());

        return view('admin.sites.domains.index', [
            'site' => $site->loadMissing('siteDomains'),
            'domains' => $site->siteDomains()->orderByDesc('is_primary')->orderBy('domain')->get(),
        ]);
    }

    public function store(SiteDomainStoreRequest $request, Site $site): RedirectResponse
    {
        $this->authorization->abortUnlessSystem($request->user());

        $this->siteDomainManager->addDomain(
            $site,
            (string) $request->string('domain'),
            $request->boolean('is_primary'),
            $request->boolean('redirect_to_primary'),
            (string) $request->string('status'),
        );

        return redirect()
            ->route('admin.sites.domains.index', $site)
            ->with('status', 'Domain added successfully.');
    }

    public function update(SiteDomainUpdateRequest $request, Site $site, SiteDomain $domain): RedirectResponse
    {
        $this->authorization->abortUnlessSystem($request->user());

        $this->siteDomainManager->updateDomain($site, $domain, $request->validated());

        return redirect()
            ->route('admin.sites.domains.index', $site)
            ->with('status', 'Domain updated successfully.');
    }

    public function destroy(Site $site, SiteDomain $domain): RedirectResponse
    {
        $this->authorization->abortUnlessSystem(request()->user());

        $this->siteDomainManager->deleteDomain($site, $domain);

        return redirect()
            ->route('admin.sites.domains.index', $site)
            ->with('status', 'Domain removed successfully.');
    }

    public function setPrimary(Site $site, SiteDomain $domain): RedirectResponse
    {
        $this->authorization->abortUnlessSystem(request()->user());

        $this->siteDomainManager->setPrimaryDomain($site, $domain);

        return redirect()
            ->route('admin.sites.domains.index', $site)
            ->with('status', 'Primary domain updated successfully.');
    }
}
