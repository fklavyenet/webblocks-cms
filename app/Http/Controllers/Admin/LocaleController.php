<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocaleRequest;
use App\Models\Locale;
use App\Support\Locales\LocaleLifecycleGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocaleController extends Controller
{
    public function __construct(
        private readonly LocaleLifecycleGuard $lifecycleGuard,
    ) {}

    public function index(): View
    {
        $locales = Locale::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.locales.index', [
            'locales' => $locales,
            'reports' => $this->lifecycleGuard->inspectMany(collect($locales->items())),
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
            'report' => $this->lifecycleGuard->inspect($locale),
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

    public function enable(Locale $locale): RedirectResponse
    {
        $report = $this->lifecycleGuard->inspect($locale);

        if (! $report->canEnable()) {
            return redirect()->route('admin.locales.index')
                ->withErrors(['locale_lifecycle' => $locale->is_default
                    ? 'Default locale remains enabled automatically.'
                    : 'Locale is already enabled.']);
        }

        $locale->forceFill(['is_enabled' => true])->save();

        return redirect()->route('admin.locales.index')->with('status', 'Locale enabled successfully.');
    }

    public function disable(Locale $locale): RedirectResponse
    {
        $report = $this->lifecycleGuard->inspect($locale);

        if (! $report->canDisable()) {
            return redirect()->route('admin.locales.index')
                ->withErrors(['locale_lifecycle' => $report->disableBlockedReason() ?? 'Locale cannot be disabled.']);
        }

        $locale->forceFill(['is_enabled' => false])->save();

        return redirect()->route('admin.locales.index')->with('status', 'Locale disabled successfully.');
    }

    public function destroy(Locale $locale): RedirectResponse
    {
        $report = $this->lifecycleGuard->inspect($locale);

        if (! $report->canDelete()) {
            return redirect()->route('admin.locales.index')
                ->withErrors(['locale_lifecycle' => $report->deleteBlockedReason() ?? 'Locale cannot be deleted safely.']);
        }

        $locale->delete();

        return redirect()->route('admin.locales.index')->with('status', 'Locale deleted successfully.');
    }
}
