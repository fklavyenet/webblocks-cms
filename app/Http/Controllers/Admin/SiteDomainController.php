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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteDomainController extends Controller
{
    private const SITE_CONTEXT_SESSION_KEY = 'admin.domains.site';

    public function __construct(
        private readonly SiteDomainManager $siteDomainManager,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function landing(Request $request): View|RedirectResponse
    {
        $this->authorization->abortUnlessSystem($request->user());

        $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())->get();
        [$activeSite, $siteFilterValue] = $this->resolveSiteContext($request, $sites);

        if ($activeSite) {
            return redirect()->route('admin.sites.domains.index', $activeSite);
        }

        return view('admin.domains.index', [
            'sites' => $sites,
            'siteFilterValue' => $siteFilterValue,
        ]);
    }

    public function index(Site $site): View
    {
        $this->authorization->abortUnlessSystem(request()->user());

        if (request()->hasSession()) {
            request()->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $site->id);
        }

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

    private function resolveSiteContext(Request $request, Collection $sites): array
    {
        $requestedSite = null;
        $hasRequestedSite = false;

        if ($request->query->has('site')) {
            $requestedSite = $request->query('site');
            $hasRequestedSite = true;
        } elseif ($request->query->has('site_id')) {
            $requestedSite = $request->query('site_id');
            $hasRequestedSite = true;
        } elseif ($request->hasSession()) {
            foreach ([
                self::SITE_CONTEXT_SESSION_KEY,
                'admin.pages.site',
                'admin.shared-slots.site',
            ] as $sessionKey) {
                $candidate = $request->session()->get($sessionKey);

                if ($candidate !== null) {
                    $requestedSite = $candidate;
                    $hasRequestedSite = true;
                    break;
                }
            }
        }

        if ($hasRequestedSite) {
            $normalizedSite = is_string($requestedSite) ? trim($requestedSite) : (string) $requestedSite;

            if (Str::lower($normalizedSite) === 'all') {
                if ($request->hasSession()) {
                    $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, 'all');
                }

                return [null, 'all'];
            }

            if (ctype_digit($normalizedSite)) {
                $site = $sites->firstWhere('id', (int) $normalizedSite);

                if ($site) {
                    if ($request->hasSession()) {
                        $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $site->id);
                    }

                    return [$site, (string) $site->id];
                }
            }
        }

        if ($sites->count() === 1) {
            $site = $sites->first();

            if ($site && $request->hasSession()) {
                $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $site->id);
            }

            return [$site, $site ? (string) $site->id : 'all'];
        }

        if ($request->hasSession()) {
            $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, 'all');
        }

        return [null, 'all'];
    }
}
