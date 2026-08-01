<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\SiteCloneRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteDeleteRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteRequest;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Sites\ExportImport\ExportablePages;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Sites\SiteCloneOptions;
use WebBlocks\Cms\Support\Sites\SiteCloneService;
use WebBlocks\Cms\Support\Sites\SiteDeleteService;
use WebBlocks\Cms\Support\Sites\SitePublicDirectoryManager;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteController extends Controller
{
  public function __construct(
    private readonly SiteCloneService $siteCloneService,
    private readonly SiteDeleteService $siteDeleteService,
    private readonly AdminAuthorization $authorization,
    private readonly SiteAssetStore $siteAssetStore,
    private readonly SitePublicDirectoryManager $sitePublicDirectories,
    private readonly SystemSettings $systemSettings,
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

    return view('webblocks-cms::admin.sites.index', [
      'exportablePages' => app(ExportablePages::class)->grouped(),
      'sites' => Site::query()
        ->with(['locales' => fn ($query) => $query->orderBy('name')])
        ->withCount(['pages' => fn ($query) => $query->visibleInAdmin()])
        ->primaryFirst()
        ->orderBy('name')
        ->paginate(AdminPagination::perPage()),
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
      'totalCount' => $siteCount,
    ]);
  }

  public function create(): View
  {
    return view('webblocks-cms::admin.sites.form', [
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
      'timezoneOptions' => $this->systemSettings->timezoneOptions(),
      'systemTimezone' => $this->systemSettings->timezone(),
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
      $data = $this->runtimeSafeSiteData($data);

      $site = Site::query()->create($data);

      Site::enforcePrimaryInvariant($site);
      $this->syncLocales($site, $localeIds);

      return $site;
    });

    $warnings = $this->sitePublicDirectories->ensureAssetDirectories($site);

    return redirect()
      ->route('admin.sites.edit', $site)
      ->with('status', 'Site created successfully.'.($warnings ? ' '.implode(' ', $warnings) : ''));
  }

  public function edit(Site $site): View
  {
    $this->authorization->abortUnlessSiteSettingsView(request()->user(), $site);

    $site->loadMissing(['locales', 'faviconAsset', 'socialImageAsset', 'siteDomains', 'siteVariables']);

    $deleteReport = $this->siteDeleteService->inspect($site);
    $canManageSiteSettings = $this->authorization->canMutateSiteSettings(request()->user(), $site);
    $canManageDomains = request()->user()?->isSuperAdmin() ?? false;

    $requestedTab = trim((string) request()->query('tab', old('_site_tab', 'site')));
    $siteTab = in_array($requestedTab, Site::ADMIN_FORM_TABS, true)
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

    return view('webblocks-cms::admin.sites.form', [
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
      'timezoneOptions' => $this->systemSettings->timezoneOptions(),
      'systemTimezone' => $this->systemSettings->timezone(),
      'siteVariablesUi' => [
        'requestedModal' => $requestedModal,
        'selectedVariable' => $selectedVariable,
        'closeUrl' => route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']),
      ],
      'siteAssets' => collect(SiteAssetStore::TYPES)
        ->map(fn (string $type) => $this->siteAssetStore->read($site, $type))
        ->values(),
    ]);
  }

  public function deleteConfirm(Site $site): View
  {
    return view('webblocks-cms::admin.sites.delete', [
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
    return view('webblocks-cms::admin.sites.clone', [
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

    $warnings = $this->sitePublicDirectories->ensureAssetDirectories($result->targetSite);

    return redirect()
      ->route('admin.sites.edit', $result->targetSite)
      ->with('status', $summary.($warnings ? ' '.implode(' ', $warnings) : ''));
  }

  public function update(SiteRequest $request, Site $site): RedirectResponse
  {
    $this->authorization->abortUnlessSiteSettingsMutation($request->user(), $site);

    $previousHandle = $site->handle;

    DB::transaction(function () use ($request, $site): void {
      $data = $request->validated();
      $localeIds = $data['locale_ids'];
      unset($data['locale_ids']);
      $data = $this->runtimeSafeSiteData($data);

      $site->update($data);

      Site::enforcePrimaryInvariant($site);
      $this->syncLocales($site, $localeIds);
    });

    $freshSite = $site->fresh();
    $warnings = $this->sitePublicDirectories->relocateAssetDirectory($previousHandle, $freshSite);
    $warnings = array_merge($warnings, $this->sitePublicDirectories->ensureAssetDirectories($freshSite));

    return redirect()
      ->route('admin.sites.edit', ['site' => $site, 'tab' => $request->input('_site_tab', 'site')])
      ->with('status', 'Site updated successfully.'.($warnings ? ' '.implode(' ', $warnings) : ''));
  }

  private function syncLocales(Site $site, array $localeIds): void
  {
    $defaultLocaleId = (int) Locale::query()->where('is_default', true)->value('id');

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

  private function runtimeSafeSiteData(array $data): array
  {
    if (! Schema::hasColumn('wbcms_sites', 'public_theme_preset')) {
      unset($data['public_theme_preset']);
    }

    if (! Schema::hasColumn('wbcms_sites', 'timezone')) {
      unset($data['timezone']);
    } elseif (array_key_exists('timezone', $data)) {
      // Blank stores null, which reads as "follow the system timezone".
      $data['timezone'] = trim((string) $data['timezone']) === '' ? null : $data['timezone'];
    }

    if (! Schema::hasColumn('wbcms_sites', 'custom_head_html')) {
      unset($data['custom_head_html']);
    } elseif (array_key_exists('custom_head_html', $data)) {
      // Blank clears it, matching the API endpoint rather than storing an empty string.
      $data['custom_head_html'] = trim((string) $data['custom_head_html']) === '' ? null : $data['custom_head_html'];
    }

    foreach ([
      'brand_accent',
      'brand_accent_secondary',
      'brand_surface',
      'brand_text',
      'brand_font_heading',
      'brand_font_body',
    ] as $brandColumn) {
      if (! Schema::hasColumn('wbcms_sites', $brandColumn)) {
        unset($data[$brandColumn]);
      }
    }

    return $data;
  }

  private function assetPickerAssets()
  {
    return $this->authorization->scopeMediaForUser(Media::query(), request()->user())
      ->with('folder')
      ->latest()
      ->get();
  }

  private function assetPickerFolders()
  {
    return MediaFolder::query()
      ->withCount('assets')
      ->with('parent')
      ->orderBy('name')
      ->get();
  }
}
