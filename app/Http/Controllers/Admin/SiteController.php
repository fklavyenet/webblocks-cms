<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteCloneRequest;
use App\Http\Requests\Admin\SiteDeleteRequest;
use App\Http\Requests\Admin\SiteRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Locale;
use App\Models\Site;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use App\Support\Sites\SiteDeleteService;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class SiteController extends Controller
{
    public function __construct(
        private readonly SiteCloneService $siteCloneService,
        private readonly SiteDeleteService $siteDeleteService,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(): View
    {
        $siteCount = Site::query()->count();
        $user = request()->user();
        $requestedModal = trim((string) request()->query('modal', old('_site_export_modal', '')));
        $selectedExportSiteId = (int) request()->integer('export_site', old('site_id'));
        $selectedExportSite = $selectedExportSiteId > 0
            ? Site::query()->find($selectedExportSiteId)
            : null;
        $selectedDetailSiteId = (int) request()->integer('details_site');
        $selectedDetailSite = $selectedDetailSiteId > 0
            ? Site::query()
                ->with(['locales' => fn ($query) => $query->orderBy('name')])
                ->withCount(['pages' => fn ($query) => $query->visibleInAdmin()])
                ->find($selectedDetailSiteId)
            : null;

        return view('admin.sites.index', [
            'sites' => Site::query()
                ->with(['locales' => fn ($query) => $query->orderBy('name')])
                ->withCount(['pages' => fn ($query) => $query->visibleInAdmin()])
                ->primaryFirst()
                ->orderBy('name')
                ->paginate(15),
            'siteDeleteReports' => Site::query()
                ->get()
                ->keyBy('id')
                ->map(fn (Site $site) => $this->siteDeleteService->inspect($site)),
            'siteCount' => $siteCount,
            'canExportSites' => $user?->isSuperAdmin() ?? false,
            'siteExportUi' => [
                'requestedModal' => $requestedModal,
                'selectedSite' => $selectedExportSite,
                'closeUrl' => route('admin.sites.index'),
            ],
            'siteDetailsUi' => [
                'requestedModal' => $requestedModal,
                'selectedSite' => $selectedDetailSite,
                'closeUrl' => route('admin.sites.index'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.sites.form', [
            'site' => new Site,
            'locales' => Locale::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'pageTitle' => 'Add Site',
            'formAction' => route('admin.sites.store'),
            'formMethod' => 'POST',
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'canManageSiteSettings' => true,
            'canManageDomains' => false,
            'siteTab' => trim((string) request()->query('tab', old('_site_tab', 'site'))),
            'siteVariablesUi' => [
                'requestedModal' => '',
                'selectedVariable' => null,
                'closeUrl' => route('admin.sites.create', ['tab' => 'variables']),
            ],
        ]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $site = DB::transaction(function () use ($request): Site {
            $data = $request->validated();
            $localeIds = $data['locale_ids'];
            unset($data['locale_ids']);

            $site = Site::query()->create($data);

            Site::enforcePrimaryInvariant($site);
            $this->syncLocales($site, $localeIds);

            return $site;
        });

        return redirect()->route('admin.sites.edit', $site)->with('status', 'Site created successfully.');
    }

    public function edit(Site $site): View
    {
        $this->authorization->abortUnlessSiteSettingsView(request()->user(), $site);

        $site->loadMissing(['locales', 'faviconAsset', 'socialImageAsset', 'siteDomains', 'siteVariables']);

        $deleteReport = $this->siteDeleteService->inspect($site);
        $canManageSiteSettings = $this->authorization->canMutateSiteSettings(request()->user(), $site);
        $canManageDomains = request()->user()?->isSuperAdmin() ?? false;

        $requestedTab = trim((string) request()->query('tab', old('_site_tab', 'site')));
        $siteTab = in_array($requestedTab, ['site', 'locales', 'branding', 'seo-defaults', 'variables'], true)
            ? $requestedTab
            : 'site';
        $requestedModal = trim((string) request()->query('modal', old('_site_variable_modal', '')));
        $selectedVariable = null;

        if (request()->filled('site_variable')) {
            $selectedVariable = $site->siteVariables->firstWhere('id', (int) request()->integer('site_variable'));
        }

        if (! $selectedVariable && old('_site_variable_id')) {
            $selectedVariable = $site->siteVariables->firstWhere('id', (int) old('_site_variable_id'));
        }

        return view('admin.sites.form', [
            'site' => $site,
            'locales' => Locale::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'pageTitle' => 'Edit Site: '.$site->name,
            'formAction' => route('admin.sites.update', $site),
            'formMethod' => 'PUT',
            'siteDeleteReport' => $deleteReport,
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'canManageSiteSettings' => $canManageSiteSettings,
            'canManageDomains' => $canManageDomains,
            'siteTab' => $siteTab,
            'siteVariablesUi' => [
                'requestedModal' => $requestedModal,
                'selectedVariable' => $selectedVariable,
                'closeUrl' => route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']),
            ],
        ]);
    }

    public function deleteConfirm(Site $site): View
    {
        return view('admin.sites.delete', [
            'site' => $site->loadMissing('locales'),
            'report' => $this->siteDeleteService->inspect($site),
        ]);
    }

    public function destroy(SiteDeleteRequest $request, Site $site): RedirectResponse
    {
        $report = $this->siteDeleteService->delete($site);

        if (! $report->deleted) {
            return redirect()
                ->route('admin.sites.delete', $site)
                ->withErrors(['site_delete' => $report->firstBlocker() ?? 'Site could not be deleted safely.']);
        }

        $summary = sprintf(
            'Site deleted. Pages: %d, blocks: %d, navigation: %d, locale assignments: %d.',
            $report->count('pages'),
            $report->count('blocks'),
            $report->count('navigation_items'),
            $report->count('site_locales'),
        );

        return redirect()->route('admin.sites.index')->with('status', $summary);
    }

    public function cloneForm(?Site $site = null): View
    {
        return view('admin.sites.clone', [
            'sourceSite' => $site,
            'sites' => Site::query()->withCount(['pages' => fn ($query) => $query->visibleInAdmin()])->primaryFirst()->orderBy('name')->get(),
        ]);
    }

    public function cloneStore(SiteCloneRequest $request): RedirectResponse
    {
        try {
            $result = $this->siteCloneService->clone(
                source: $request->integer('source_site_id'),
                target: (string) $request->string('target_identifier'),
                options: SiteCloneOptions::fromArray($request->validated()),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['clone' => $exception->getMessage()]);
        }

        $summary = sprintf(
            'Clone %s. Pages: %d, translations: %d, blocks: %d, navigation: %d.',
            $result->dryRun ? 'validated successfully' : 'completed successfully',
            $result->count('pages_cloned'),
            $result->count('page_translations_cloned') + $result->count('block_translation_rows_cloned'),
            $result->count('blocks_cloned'),
            $result->count('navigation_items_cloned'),
        );

        if ($result->dryRun) {
            return back()->with('status', $summary);
        }

        return redirect()->route('admin.sites.edit', $result->targetSite)->with('status', $summary);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $this->authorization->abortUnlessSiteSettingsMutation($request->user(), $site);

        DB::transaction(function () use ($request, $site): void {
            $data = $request->validated();
            $localeIds = $data['locale_ids'];
            unset($data['locale_ids']);

            $site->update($data);

            Site::enforcePrimaryInvariant($site);
            $this->syncLocales($site, $localeIds);
        });

        return redirect()->route('admin.sites.edit', ['site' => $site, 'tab' => $request->input('_site_tab', 'site')])->with('status', 'Site updated successfully.');
    }

    private function syncLocales(Site $site, array $localeIds): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if ($defaultLocaleId && ! in_array($defaultLocaleId, $localeIds, true)) {
            $localeIds[] = (int) $defaultLocaleId;
        }

        if ($localeIds === []) {
            $localeIds[] = (int) $defaultLocaleId;
        }

        $site->locales()->sync(collect($localeIds)
            ->unique()
            ->mapWithKeys(fn (int $localeId) => [$localeId => ['is_enabled' => true]])
            ->all());
    }

    private function assetPickerAssets()
    {
        return $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())
            ->with('folder')
            ->latest()
            ->get();
    }

    private function assetPickerFolders()
    {
        return AssetFolder::query()
            ->withCount('assets')
            ->with('parent')
            ->orderBy('name')
            ->get();
    }
}
