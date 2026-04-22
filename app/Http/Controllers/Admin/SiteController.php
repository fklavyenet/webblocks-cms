<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteCloneRequest;
use App\Http\Requests\Admin\SiteRequest;
use App\Models\Locale;
use App\Models\Site;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class SiteController extends Controller
{
    public function __construct(
        private readonly SiteCloneService $siteCloneService,
    ) {}

    public function index(): View
    {
        return view('admin.sites.index', [
            'sites' => Site::query()
                ->with(['locales' => fn ($query) => $query->orderBy('name')])
                ->withCount('pages')
                ->primaryFirst()
                ->orderBy('name')
                ->paginate(15),
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
        return view('admin.sites.form', [
            'site' => $site->loadMissing('locales'),
            'locales' => Locale::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'pageTitle' => 'Edit Site: '.$site->name,
            'formAction' => route('admin.sites.update', $site),
            'formMethod' => 'PUT',
        ]);
    }

    public function cloneForm(?Site $site = null): View
    {
        return view('admin.sites.clone', [
            'sourceSite' => $site,
            'sites' => Site::query()->withCount('pages')->primaryFirst()->orderBy('name')->get(),
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
        DB::transaction(function () use ($request, $site): void {
            $data = $request->validated();
            $localeIds = $data['locale_ids'];
            unset($data['locale_ids']);

            $site->update($data);

            Site::enforcePrimaryInvariant($site);
            $this->syncLocales($site, $localeIds);
        });

        return redirect()->route('admin.sites.edit', $site)->with('status', 'Site updated successfully.');
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
}
