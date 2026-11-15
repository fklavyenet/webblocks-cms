<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Actions\Pages\WritePageSlotSource;
use WebBlocks\Cms\Http\Requests\Admin\StorePageSlotRequest;
use WebBlocks\Cms\Http\Requests\Admin\SyncPageLayoutSlotsRequest;
use WebBlocks\Cms\Http\Requests\Admin\UpdatePageSlotSourceRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageIndexState;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageSlotController extends Controller
{
  public function __construct(
    private readonly PageRevisionManager $revisionManager,
    private readonly PageIndexState $pageIndexState,
    private readonly PageLayoutSlotSyncer $pageLayoutSlotSyncer,
    private readonly PageWorkflowManager $workflowManager,
    private readonly AdminAuthorization $authorization,
  ) {}

  public function syncLayoutSlots(SyncPageLayoutSlotsRequest $request, Page $page): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

    $result = $this->pageLayoutSlotSyncer->syncMissingSlots($page, $request->user());

    if ($result['noop']) {
      return $this->redirectToEdit($page, 'This page already has all slots defined by the selected Page Layout.', $request->validatedReturnUrl(), 'layout-slots');
    }

    $addedCount = (int) $result['added_count'];

    return $this->redirectToEdit(
      $page,
      $addedCount === 1
        ? 'Added 1 missing Page Layout slot.'
        : 'Added '.$addedCount.' missing Page Layout slots.',
      $request->validatedReturnUrl(),
      'layout-slots',
    );
  }

  public function store(StorePageSlotRequest $request, Page $page): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

    DB::transaction(function () use ($request, $page): void {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);
      $type = SlotType::query()->findOrFail($request->validated('slot_type_id'));
      if (! PageLayoutCatalog::allowsSlot($page->publicShellPreset(), $type->slug) || $page->slots()->where('slot_type_id', $type->id)->exists()) {
        throw ValidationException::withMessages(['slot_type_id' => __('validation.unique', ['attribute' => __('webblocks-cms::admin.page_slots_card.slot')])]);
      }
      $nextSortOrder = (int) $page->slots()->max('sort_order') + 1;

      $page->slots()->create([
        'slot_type_id' => $type->id,
        'sort_order' => $nextSortOrder,
      ]);

      $page->forceFill([
        'updated_by_user_id' => $request->user()?->id,
      ])->save();

      $this->revisionManager->capture(
        $page->fresh(),
        $request->user(),
        'Slot added',
        'Page slot structure was updated by adding a slot.',
        event: 'slot_changed',
      );
    });

    return $this->redirectToEdit($page, 'Slot added successfully.');
  }

  public function destroy(Request $request, Page $page, PageSlot $slot): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
    abort_unless($slot->page_id === $page->id, 404);

    if ($slot->isProtectedMain($page)) {
      return $this->redirectToEditWithErrors($page, ['slot' => __('webblocks-cms::admin.page_layout_picker.main_protected')], $request->input('return_url'));
    }

    if (! $request->boolean('confirm_delete_slot')) {
      return $this->redirectToEditWithErrors($page, ['slot' => 'Confirm slot deletion before deleting the slot.'], $request->input('return_url'));
    }

    $error = DB::transaction(function () use ($request, $page, $slot): ?string {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);
      $slot = $page->slots()->with('slotType')->findOrFail($slot->id);
      if ($page->blocks()->where('slot_type_id', $slot->slot_type_id)->exists()) {
        return 'Slot cannot be deleted while it still contains blocks.';
      }
      if (in_array($slot->slotSlug(), PageLayoutCatalog::COLUMN_SIDEBARS, true) && $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
        return __('webblocks-cms::admin.page_layout_picker.incompatible_slot', ['slot' => $slot->slotType?->name]);
      }
      $slot->delete();
      $this->normalizeSortOrder($page);
      $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();

      $this->revisionManager->capture(
        $page->fresh(),
        $request->user(),
        'Slot deleted',
        'Page slot structure was updated by removing a slot.',
        event: 'slot_changed',
      );

      return null;
    });

    if ($error !== null) {
      return $this->redirectToEditWithErrors($page, ['slot' => $error], $request->input('return_url'));
    }

    return $this->redirectToEdit($page, 'Slot deleted successfully.');
  }

  public function updateSource(UpdatePageSlotSourceRequest $request, Page $page, PageSlot $slot): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
    abort_unless($slot->page_id === $page->id, 404);

    DB::transaction(function () use ($request, $page, $slot): void {
      $source = $request->validatedData();
      app(WritePageSlotSource::class)->execute($page, $slot, $source['source_type'], $source['shared_slot_id']);
      $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();

      $this->revisionManager->capture(
        $page->fresh(),
        $request->user(),
        'Slot source updated',
        'Page slot source was updated.',
        event: 'slot_changed',
      );
    });

    return $this->redirectToEdit($page, 'Slot source updated successfully.');
  }

  private function normalizeSortOrder(Page $page): void
  {
    $page->slots()
      ->orderBy('sort_order')
      ->orderBy('id')
      ->lockForUpdate()
      ->get()
      ->values()
      ->each(function (PageSlot $slot, int $index): void {
        if ($slot->sort_order === $index) {
          return;
        }

        $slot->update(['sort_order' => $index]);
      });
  }

  private function redirectToEdit(Page $page, string $status, ?string $returnUrl = null, ?string $tab = null): RedirectResponse
  {
    $parameters = ['page' => $page];
    $safeReturnUrl = $returnUrl ?? $this->pageIndexState->safeReturnUrlFromRequest(request());

    if ($tab !== null && $tab !== '') {
      $parameters['tab'] = $tab;
    }

    if ($safeReturnUrl !== null && $safeReturnUrl !== '') {
      $parameters['return_url'] = $safeReturnUrl;
    }

    $redirect = redirect()
      ->route('admin.pages.edit', $parameters)
      ->with('status', $status);

    if ($page->isPublished() && $page->publicUrl()) {
      $redirect->with('status_action', [
        'label' => 'View page',
        'url' => $page->publicUrl(),
      ]);
    }

    return $redirect;
  }

  private function redirectToEditWithErrors(Page $page, array $errors, ?string $returnUrl = null): RedirectResponse
  {
    $parameters = ['page' => $page];
    $safeReturnUrl = $returnUrl ?? $this->pageIndexState->safeReturnUrlFromRequest(request());

    if ($safeReturnUrl !== null && $safeReturnUrl !== '') {
      $parameters['return_url'] = $safeReturnUrl;
    }

    return redirect()
      ->route('admin.pages.edit', $parameters)
      ->withErrors($errors);
  }
}
