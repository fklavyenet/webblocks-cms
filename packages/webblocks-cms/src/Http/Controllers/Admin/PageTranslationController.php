<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PageTranslationRequest;
use WebBlocks\Cms\Support\Pages\PageIndexState;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

class PageTranslationController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly PageIndexState $pageIndexState,
        private readonly PageRevisionManager $revisionManager,
        private readonly PageWorkflowManager $workflowManager,
    ) {}

    public function create(Page $page, Locale $locale): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);
        $page->loadMissing('site');
        abort_if($page->site->enabledLocales()->where('locales.id', $locale->id)->doesntExist(), 404);

        $translation = new PageTranslation([
            'name' => $page->defaultTranslation()?->name,
            'slug' => $page->defaultTranslation()?->slug,
        ]);
        $translation->setRelation('locale', $locale);

        return view('webblocks-cms::admin.pages.translations.form', [
            'page' => $page->loadMissing(['site', 'translations.locale']),
            'translation' => $translation,
            'locale' => $locale,
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'formAction' => route('admin.pages.translations.store', [$page, $locale]),
            'formMethod' => 'POST',
            'pageTitle' => 'Add Translation: '.$page->title.' / '.strtoupper($locale->code),
            'pagesIndexUrl' => $this->pageIndexState->returnUrl(request(), $page->site_id),
            'pageReturnUrl' => $this->pageIndexState->returnUrl(request(), $page->site_id),
        ]);
    }

    public function store(PageTranslationRequest $request, Page $page, Locale $locale): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
        $page->loadMissing('site');
        abort_if($page->site->enabledLocales()->where('locales.id', $locale->id)->doesntExist(), 404);

        DB::transaction(function () use ($request, $page, $locale): void {
            $page->translations()->updateOrCreate(
                ['locale_id' => $locale->id],
                $request->validatedTranslation(),
            );

            $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Translation added',
                'Page translation was added for locale '.$locale->code.'.',
                event: 'page_updated',
            );
        });

        return redirect()
            ->route('admin.pages.edit', array_filter([
                'page' => $page,
                'return_url' => $this->pageIndexState->safeReturnUrlFromRequest($request),
            ], fn (mixed $value) => $value !== null && $value !== ''))
            ->with('status', 'Translation added successfully.');
    }

    public function edit(Page $page, PageTranslation $translation): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);
        abort_unless($translation->page_id === $page->id, 404);
        $page->loadMissing('site');
        abort_if($page->site->enabledLocales()->where('locales.id', $translation->locale_id)->doesntExist(), 404);

        return view('webblocks-cms::admin.pages.translations.form', [
            'page' => $page->loadMissing(['site', 'translations.locale']),
            'translation' => $translation->loadMissing(['locale', 'ogImage']),
            'locale' => $translation->locale,
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'formAction' => route('admin.pages.translations.update', [$page, $translation]),
            'formMethod' => 'PUT',
            'pageTitle' => 'Edit Translation: '.$page->title.' / '.strtoupper($translation->locale->code),
            'pagesIndexUrl' => $this->pageIndexState->returnUrl(request(), $page->site_id),
            'pageReturnUrl' => $this->pageIndexState->returnUrl(request(), $page->site_id),
        ]);
    }

    public function update(PageTranslationRequest $request, Page $page, PageTranslation $translation): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
        abort_unless($translation->page_id === $page->id, 404);
        $page->loadMissing('site');
        abort_if($page->site->enabledLocales()->where('locales.id', $translation->locale_id)->doesntExist(), 404);

        DB::transaction(function () use ($request, $page, $translation): void {
            $translation->update($request->validatedTranslation());
            $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Translation updated',
                'Page translation was updated for locale '.$translation->locale->code.'.',
                event: 'page_updated',
            );
        });

        return redirect()
            ->route('admin.pages.edit', array_filter([
                'page' => $page,
                'return_url' => $this->pageIndexState->safeReturnUrlFromRequest($request),
            ], fn (mixed $value) => $value !== null && $value !== ''))
            ->with('status', 'Translation updated successfully.');
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
