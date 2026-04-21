<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocaleRequest;
use App\Models\Locale;
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
            Locale::enforceDefaultInvariant($locale);

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
            Locale::enforceDefaultInvariant($locale);
        });

        return redirect()->route('admin.locales.edit', $locale)->with('status', 'Locale updated successfully.');
    }
}
