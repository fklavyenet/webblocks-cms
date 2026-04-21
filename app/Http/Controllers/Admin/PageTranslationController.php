<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageTranslationRequest;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PageTranslationController extends Controller
{
    public function create(Page $page, Locale $locale): View
    {
        $page->loadMissing('site');
        abort_if($page->site->locales()->where('locales.id', $locale->id)->wherePivot('is_enabled', true)->doesntExist(), 404);

        $translation = new PageTranslation([
            'name' => $page->defaultTranslation()?->name,
            'slug' => $page->defaultTranslation()?->slug,
        ]);
        $translation->setRelation('locale', $locale);

        return view('admin.pages.translations.form', [
            'page' => $page->loadMissing(['site', 'translations.locale']),
            'translation' => $translation,
            'locale' => $locale,
            'formAction' => route('admin.pages.translations.store', [$page, $locale]),
            'formMethod' => 'POST',
            'pageTitle' => 'Add Translation: '.$page->title.' / '.strtoupper($locale->code),
        ]);
    }

    public function store(PageTranslationRequest $request, Page $page, Locale $locale): RedirectResponse
    {
        $page->loadMissing('site');
        abort_if($page->site->locales()->where('locales.id', $locale->id)->wherePivot('is_enabled', true)->doesntExist(), 404);

        DB::transaction(function () use ($request, $page, $locale): void {
            $page->translations()->updateOrCreate(
                ['locale_id' => $locale->id],
                $request->validatedTranslation() + ['site_id' => $page->site_id],
            );
        });

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Translation added successfully.');
    }

    public function edit(Page $page, PageTranslation $translation): View
    {
        abort_unless($translation->page_id === $page->id, 404);

        return view('admin.pages.translations.form', [
            'page' => $page->loadMissing(['site', 'translations.locale']),
            'translation' => $translation->loadMissing('locale'),
            'locale' => $translation->locale,
            'formAction' => route('admin.pages.translations.update', [$page, $translation]),
            'formMethod' => 'PUT',
            'pageTitle' => 'Edit Translation: '.$page->title.' / '.strtoupper($translation->locale->code),
        ]);
    }

    public function update(PageTranslationRequest $request, Page $page, PageTranslation $translation): RedirectResponse
    {
        abort_unless($translation->page_id === $page->id, 404);

        DB::transaction(function () use ($request, $page, $translation): void {
            $translation->update($request->validatedTranslation() + ['site_id' => $page->site_id]);
        });

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Translation updated successfully.');
    }
}
