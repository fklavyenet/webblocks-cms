<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocaleRequest;
use App\Models\Locale;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocaleController extends Controller
{
    public function index(): View
    {
        return view('admin.locales.index', [
            'locales' => Locale::query()
                ->withCount(['pageTranslations', 'sites'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.locales.form', [
            'locale' => new Locale(['is_enabled' => true]),
            'pageTitle' => 'Add Locale',
            'formAction' => route('admin.locales.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(LocaleRequest $request): RedirectResponse
    {
        $locale = DB::transaction(function () use ($request): Locale {
            $locale = Locale::query()->create($request->validated());
            $this->applyInvariants($locale);

            return $locale;
        });

        return redirect()->route('admin.locales.edit', $locale)->with('status', 'Locale created successfully.');
    }

    public function edit(Locale $locale): View
    {
        return view('admin.locales.form', [
            'locale' => $locale,
            'pageTitle' => 'Edit Locale: '.$locale->name,
            'formAction' => route('admin.locales.update', $locale),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(LocaleRequest $request, Locale $locale): RedirectResponse
    {
        DB::transaction(function () use ($request, $locale): void {
            $locale->update($request->validated());
            $this->applyInvariants($locale);
        });

        return redirect()->route('admin.locales.edit', $locale)->with('status', 'Locale updated successfully.');
    }

    private function applyInvariants(Locale $locale): void
    {
        $defaultLocale = Locale::query()->where('is_default', true)->first();

        if ($locale->is_default) {
            Locale::query()->whereKeyNot($locale->id)->update(['is_default' => false]);
            $locale->forceFill(['is_enabled' => true])->saveQuietly();
        }

        if (! $defaultLocale && Locale::query()->where('is_default', true)->doesntExist()) {
            $locale->forceFill(['is_default' => true, 'is_enabled' => true])->saveQuietly();
        }

        if (! $locale->fresh()->is_default && ! $locale->fresh()->is_enabled && Locale::query()->whereKeyNot($locale->id)->where('is_enabled', true)->doesntExist()) {
            $locale->forceFill(['is_enabled' => true])->saveQuietly();
        }

        if ($locale->fresh()->is_default) {
            Site::query()->with('locales')->get()->each(function (Site $site) use ($locale): void {
                $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
            });
        }
    }
}
