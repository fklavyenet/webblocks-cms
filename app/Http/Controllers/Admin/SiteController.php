<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteRequest;
use App\Models\Locale;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('admin.sites.index', [
            'sites' => Site::query()
                ->with(['locales' => fn ($query) => $query->orderBy('name')])
                ->withCount('pages')
                ->orderByDesc('is_primary')
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

            $site = Site::query()->create($data + [
                'handle' => Str::slug($data['handle']),
                'domain' => $data['domain'] ?: null,
            ]);

            $this->syncPrimarySite($site, (bool) $site->is_primary);
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

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $data = $request->validated();
            $localeIds = $data['locale_ids'];
            unset($data['locale_ids']);

            $site->update($data + [
                'handle' => Str::slug($data['handle']),
                'domain' => $data['domain'] ?: null,
            ]);

            $this->syncPrimarySite($site, (bool) $site->is_primary);
            $this->syncLocales($site, $localeIds);
        });

        return redirect()->route('admin.sites.edit', $site)->with('status', 'Site updated successfully.');
    }

    private function syncPrimarySite(Site $site, bool $isPrimary): void
    {
        if (! $isPrimary) {
            if (Site::query()->where('is_primary', true)->whereKeyNot($site->id)->doesntExist()) {
                $site->forceFill(['is_primary' => true])->saveQuietly();
            }

            return;
        }

        Site::query()->whereKeyNot($site->id)->update(['is_primary' => false]);
    }

    private function syncLocales(Site $site, array $localeIds): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if ($defaultLocaleId && ! in_array($defaultLocaleId, $localeIds, true)) {
            $localeIds[] = (int) $defaultLocaleId;
        }

        $site->locales()->sync(collect($localeIds)
            ->unique()
            ->mapWithKeys(fn (int $localeId) => [$localeId => ['is_enabled' => true]])
            ->all());
    }
}
