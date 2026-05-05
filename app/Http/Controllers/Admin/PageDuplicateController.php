<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DuplicatePageRequest;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Pages\PageDuplicator;
use App\Support\Pages\PageDuplicateValidator;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageDuplicateController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly PageDuplicator $duplicator,
        private readonly PageDuplicateValidator $validator,
    ) {}

    public function create(Request $request, Page $page): View
    {
        $this->authorizeDuplicate($request, $page);

        $page->loadMissing(['site', 'translations.locale', 'slots.sharedSlot', 'slots.slotType', 'navigationItems']);
        $sites = $this->authorization->scopeSitesForUser(Site::query(), $request->user())
            ->primaryFirst()
            ->orderBy('name')
            ->get();
        $selectedTargetSite = $sites->firstWhere('id', (int) old('target_site_id', $page->site_id))
            ?? $sites->firstWhere('id', $page->site_id)
            ?? $sites->first();
        $sharedSlotValidation = $selectedTargetSite
            ? $this->validator->inspect($page, $selectedTargetSite)
            : null;

        return view('admin.pages.duplicate', [
            'page' => $page,
            'sites' => $sites,
            'defaultTranslation' => $page->defaultTranslation(),
            'secondaryTranslations' => $this->secondaryTranslations($page),
            'sharedSlotHandles' => $page->slots
                ->filter(fn ($slot) => $slot->runtimeSourceType() === $slot::SOURCE_TYPE_SHARED_SLOT)
                ->map(fn ($slot) => $slot->sharedSlot?->handle)
                ->filter()
                ->unique()
                ->values(),
            'selectedTargetSite' => $selectedTargetSite,
            'sharedSlotValidation' => $sharedSlotValidation,
        ]);
    }

    public function store(DuplicatePageRequest $request, Page $page): RedirectResponse
    {
        $this->authorizeDuplicate($request, $page);

        $targetSite = Site::query()->findOrFail((int) $request->validated('target_site_id'));
        $disableIncompatibleSharedSlots = $request->boolean('disable_incompatible_shared_slots');

        try {
            $result = $this->duplicator->duplicate(
                $page,
                $targetSite,
                $request->user(),
                $request->validatedTranslations(),
                $disableIncompatibleSharedSlots,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $status = 'Page duplicated as "'.$result->page->title.'".';

        if ($result->sourceNavigationCount > 0) {
            $status .= ' Navigation was not duplicated.';
        }

        if ($result->remappedSharedSlotCount > 0) {
            $status .= ' Shared Slot references were remapped for the target site.';
        }

        if ($result->disabledSharedSlotCount > 0) {
            $status .= ' Incompatible Shared Slot-backed slots were disabled on the duplicate.';
        }

        return redirect()
            ->route('admin.pages.edit', $result->page)
            ->with('status', $status);
    }

    private function authorizeDuplicate(Request $request, Page $page): void
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($request->user()?->canAccessAdmin(), 403);
    }

    private function secondaryTranslations(Page $page): Collection
    {
        $defaultLocaleId = $page->defaultTranslation()?->locale_id;

        return $page->translations
            ->sortBy(fn (PageTranslation $translation) => $translation->locale_id)
            ->reject(fn (PageTranslation $translation) => $translation->locale_id === $defaultLocaleId)
            ->values();
    }
}
