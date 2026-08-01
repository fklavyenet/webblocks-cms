@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_edit.'.$key, $adminLocale, $replace);
  $pageTitle = $adminText('title', ['id' => $page->id, 'title' => $page->title]);
  // wb-tabs syncs its active tab into the hidden field below via
  // data-wb-tabs-field, but it writes the tab button's panel id
  // ("page-management-{key}-panel"), not the bare key read here and by
  // request('tab') — unwrap it before folding both sources through the
  // same known-value match.
  $resolvePageSettingsTab = static fn (?string $value): string => match ($value) {
    'overview' => 'overview',
    'settings' => 'settings',
    'assets', 'page-assets' => 'assets',
    'layout-slots' => 'layout-slots',
    default => 'settings',
  };
  $rawPageSettingsTab = old('_page_settings_tab');
  $settingsTab = $rawPageSettingsTab !== null
    ? $resolvePageSettingsTab(str_replace(['page-management-', '-panel'], '', $rawPageSettingsTab))
    : $resolvePageSettingsTab(request('tab'));
  $pagePublicUrl = $page->isPublished() ? $page->publicUrl() : null;
  $pagePreviewUrl = route('admin.pages.preview', $page);
  $pagesIndexUrl = $pagesIndexUrl ?? session('page_return_url') ?? route('admin.pages.index', ['site' => $page->site_id]);
  $pageReturnUrl = $pageReturnUrl ?? $pagesIndexUrl;
  $pageRevisionsUrl = $canViewRevisions ? route('admin.pages.revisions.index', $page) : null;
  $pageDuplicateUrl = $canDuplicatePage ? route('admin.pages.duplicate.create', ['page' => $page, 'return_url' => $pageReturnUrl]) : null;
  $pageMoveUrl = $canMoveToAnotherSite ? route('admin.pages.move-site.create', ['page' => $page, 'return_url' => $pageReturnUrl]) : null;
  $pageOwnedBlockPublishingSummary = $pageOwnedBlockPublishingSummary ?? ['total' => 0, 'by_slot' => [], 'shared_slots_excluded' => [], 'shared_slots_excluded_total' => 0];
  $hasUnpublishedPageOwnedBlocks = ($pageOwnedBlockPublishingSummary['total'] ?? 0) > 0;
  $hasExcludedSharedSlotBlocks = ($pageOwnedBlockPublishingSummary['shared_slots_excluded_total'] ?? 0) > 0;
  $siteName = $page->site?->name ?? $adminText('site_fallback');
  $domainName = $page->site?->canonicalDomain() ?: $adminText('not_set');
  $headerActions = collect([
    $pageDuplicateUrl ? '<a href="'.$pageDuplicateUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('duplicate_page')).'</a>' : null,
    $pageMoveUrl ? '<a href="'.$pageMoveUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('move_to_another_site')).'</a>' : null,
    $pageRevisionsUrl ? '<a href="'.$pageRevisionsUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('revision_history')).'</a>' : null,
    '<a href="'.$pagePreviewUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i> <span>'.e($adminText('preview')).'</span></a>',
    $pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>'.e($adminText('view_page')).'</span></a>' : null,
  ])->filter()->implode('');
  $pageBreadcrumb = '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($siteName).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($adminText('pages')).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">#'.$page->id.' '.e($page->title).'</span></li></ol></nav>';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle, 'breadcrumb' => $pageBreadcrumb])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $pageTitle,
    'description' => $adminText('description'),
    'actions' => $headerActions,
  ])

  @include('webblocks-cms::admin.partials.flash')

  <div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>{{ $adminText('page_management') }}</strong>
      <span class="wb-text-sm wb-text-muted">{{ $adminText('page_management_help') }}</span>
    </div>
    <div class="wb-card-body">
      <div class="wb-tabs" data-wb-tabs data-wb-tabs-field="[data-wb-page-settings-tab-input]">
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $adminText('page_management_sections') }}">
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'overview' ? 'is-active' : '' }}" data-wb-tab="page-management-overview-panel" aria-selected="{{ $settingsTab === 'overview' ? 'true' : 'false' }}" @if ($settingsTab !== 'overview') tabindex="-1" @endif>{{ $adminText('overview') }}</button>
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'settings' ? 'is-active' : '' }}" data-wb-tab="page-management-settings-panel" aria-selected="{{ $settingsTab === 'settings' ? 'true' : 'false' }}" @if ($settingsTab !== 'settings') tabindex="-1" @endif>{{ $adminText('settings') }}</button>
          @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
            <button type="button" class="wb-tabs-btn {{ $settingsTab === 'assets' ? 'is-active' : '' }}" data-wb-tab="page-management-assets-panel" aria-selected="{{ $settingsTab === 'assets' ? 'true' : 'false' }}" @if ($settingsTab !== 'assets') tabindex="-1" @endif>{{ $adminText('assets') }}</button>
          @endif
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'layout-slots' ? 'is-active' : '' }}" data-wb-tab="page-management-layout-slots-panel" aria-selected="{{ $settingsTab === 'layout-slots' ? 'true' : 'false' }}" @if ($settingsTab !== 'layout-slots') tabindex="-1" @endif>{{ $adminText('layout_slots') }}</button>
        </div>

        <div class="wb-tabs-panels">
          <div class="wb-tabs-panel {{ $settingsTab === 'overview' ? 'is-active' : '' }}" id="page-management-overview-panel">
            <div class="wb-card wb-card-muted">
              <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>{{ $adminText('overview') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('overview_help') }}</span>
              </div>
              <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                  <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">{{ $adminText('site') }}</span>
                      <strong>{{ $siteName }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">{{ $adminText('domain') }}</span>
                      <span>{{ $domainName }}</span>
                    </div>
                  </div>

                  <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">{{ $adminText('status') }}</span>
                      <div>
                        <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                      </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">{{ $adminText('published') }}</span>
                      <span>{{ $page->published_at ? $page->published_at->format('Y-m-d H:i') : $adminText('not_published') }}</span>
                    </div>

                    @if ($page->review_requested_at)
                      <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('review_requested') }}</span>
                        <span>{{ $page->review_requested_at->format('Y-m-d H:i') }}</span>
                      </div>
                    @endif

                    @if ($workflowActions !== [])
                      <div class="wb-stack wb-gap-2">
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('actions') }}</span>
                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                          @foreach ($workflowActions as $workflowAction)
                            @if ($workflowAction['value'] === \WebBlocks\Cms\Support\Pages\PageWorkflowManager::ACTION_PUBLISH && $hasUnpublishedPageOwnedBlocks)
                              <button type="button" class="{{ $workflowAction['class'] }}" data-wb-toggle="modal" data-wb-target="#publish-page-modal" aria-haspopup="dialog">{{ $workflowAction['label'] }}</button>
                            @else
                              <form method="POST" action="{{ route('admin.pages.workflow', $page) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $workflowAction['value'] }}">
                                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                                <button type="submit" class="{{ $workflowAction['class'] }}">{{ $workflowAction['label'] }}</button>
                              </form>
                            @endif
                          @endforeach
                        </div>
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            </div>

            @if ($hasUnpublishedPageOwnedBlocks)
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                  <strong>{{ $adminText('unpublished_page_content') }}</strong>
                  @if ($canPublishPageOwnedBlocks)
                    <form method="POST" action="{{ route('admin.pages.publish-page-owned-blocks', $page) }}">
                      @csrf
                      <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                      <button type="submit" class="wb-btn wb-btn-secondary">{{ $adminText('publish_page_owned_blocks') }}</button>
                    </form>
                  @endif
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                  <p class="wb-text-sm wb-text-muted">{{ $adminText($pageOwnedBlockPublishingSummary['total'] === 1 ? 'page_owned_block_unpublished_one' : 'page_owned_block_unpublished_many', ['count' => $pageOwnedBlockPublishingSummary['total']]) }}</p>
                  @if ($hasExcludedSharedSlotBlocks)
                    <div class="wb-alert wb-alert-info">
                      {{ $adminText('shared_slot_content_excluded') }}
                    </div>
                  @endif
                </div>
              </div>
            @endif
          </div>

          <div class="wb-tabs-panel {{ $settingsTab === 'settings' ? 'is-active' : '' }}" id="page-management-settings-panel">
            <div class="wb-card">
              <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')

                <input type="hidden" name="_page_settings_tab" value="{{ $settingsTab }}" data-wb-page-settings-tab-input>
                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                  <strong>{{ $adminText('settings') }}</strong>
                  <span class="wb-text-sm wb-text-muted">{{ $adminText('settings_help') }}</span>
                </div>

                <div class="wb-card-body">
                  @include('webblocks-cms::admin.pages._form', ['canEditContent' => $canEditContent])
                </div>

                <div class="wb-card-footer">
                  <x-webblocks-cms::admin.form-actions :cancel-url="$pageReturnUrl" :show-submit="$canEditContent" :submit-label="$adminText('save_changes')" />
                </div>
              </form>
            </div>
          </div>

          @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
            <div class="wb-tabs-panel {{ $settingsTab === 'assets' ? 'is-active' : '' }}" id="page-management-assets-panel">
              @include('webblocks-cms::admin.pages.partials.page-assets-tab', [
                'page' => $page,
                'canManagePageAssets' => $canManagePageAssets,
                'pageAssetsTab' => $pageAssetsTab,
                'pageReturnUrl' => $pageReturnUrl,
              ])
            </div>
          @endif

          <div class="wb-tabs-panel {{ $settingsTab === 'layout-slots' ? 'is-active' : '' }}" id="page-management-layout-slots-panel">
            @include('webblocks-cms::admin.pages.partials.layout-slot-summary-card', [
              'page' => $page,
              'layoutSlotComparison' => $layoutSlotComparison,
              'canEditContent' => $canEditContent,
              'pageReturnUrl' => $pageReturnUrl,
            ])
          </div>
        </div>
      </div>
    </div>
  </div>

  @include('webblocks-cms::admin.pages.partials.slots-card', [
    'page' => $page,
    'slotTypes' => $slotTypes,
    'slotBlockPreviews' => $slotBlockPreviews,
    'slotSharedSlotOptions' => $slotSharedSlotOptions,
    'sharedSlotSourcesAvailable' => $sharedSlotSourcesAvailable,
    'layoutSlotComparison' => $layoutSlotComparison,
    'canEditContent' => $canEditContent,
    'canCreateSharedSlots' => $canCreateSharedSlots,
    'pageReturnUrl' => $pageReturnUrl,
  ])

  @if ($hasUnpublishedPageOwnedBlocks)
    <div class="wb-modal wb-modal-lg" id="publish-page-modal" role="dialog" aria-modal="true" aria-labelledby="publish-page-modal-title" aria-describedby="publish-page-modal-description" hidden>
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <div>
            <h2 class="wb-modal-title" id="publish-page-modal-title">{{ $adminText('publish_page') }}</h2>
            <p class="wb-text-sm wb-text-muted" id="publish-page-modal-description">{{ $adminText('publish_page_help') }}</p>
          </div>
          <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close_publish_page_modal') }}">
            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('admin.pages.workflow', $page) }}">
          @csrf
          <input type="hidden" name="action" value="{{ \WebBlocks\Cms\Support\Pages\PageWorkflowManager::ACTION_PUBLISH }}">
          <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
          <div class="wb-modal-body wb-stack wb-gap-4">
            <div class="wb-alert wb-alert-info">
              {{ $adminText('publish_page_without_blocks_help') }}
            </div>

            <div class="wb-table-wrap">
              <table class="wb-table wb-table-striped">
                <thead>
                  <tr>
                    <th>{{ $adminText('page_owned_slot') }}</th>
                    <th>{{ $adminText('draft') }}</th>
                    <th>{{ $adminText('in_review') }}</th>
                    <th>{{ $adminText('total') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($pageOwnedBlockPublishingSummary['by_slot'] as $slotSummary)
                    <tr>
                      <td>{{ $slotSummary['label'] }}</td>
                      <td>{{ $slotSummary['status_counts']['draft'] ?? 0 }}</td>
                      <td>{{ $slotSummary['status_counts']['in_review'] ?? 0 }}</td>
                      <td>{{ $slotSummary['total'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            @if ($hasExcludedSharedSlotBlocks)
              <div class="wb-alert wb-alert-warning">
                {{ $adminText('shared_slot_content_excluded') }}
              </div>
              <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                  <thead>
                    <tr>
                      <th>{{ $adminText('shared_slot_backed_slot') }}</th>
                      <th>{{ $adminText('shared_slot') }}</th>
                      <th>{{ $adminText('unpublished') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($pageOwnedBlockPublishingSummary['shared_slots_excluded'] as $slotSummary)
                      <tr>
                        <td>{{ $slotSummary['label'] }}</td>
                        <td>{{ $slotSummary['shared_slot_label'] ?? $adminText('shared_slot') }}</td>
                        <td>{{ $slotSummary['total'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            <label class="wb-cluster wb-cluster-2" for="include_page_owned_blocks">
              <input id="include_page_owned_blocks" type="checkbox" name="include_page_owned_blocks" value="1">
              <span>{{ $adminText('also_publish_page_owned_blocks') }}</span>
            </label>
          </div>
          <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('cancel') }}</button>
            <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('publish') }}</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>{{ $adminText('translations') }}</strong>
      <span class="wb-text-sm wb-text-muted">{{ $adminText('translations_help') }}</span>
    </div>
    <div class="wb-card-body">
      <div class="wb-table-wrap">
        <table class="wb-table wb-table-striped wb-table-hover">
          <thead>
            <tr>
              <th>{{ $adminText('locale') }}</th>
              <th>{{ $adminText('status') }}</th>
              <th>{{ $adminText('slug') }}</th>
              <th>{{ $adminText('path') }}</th>
              <th>{{ $adminText('open') }}</th>
              <th>{{ $adminText('action') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($translationStatuses as $translationStatus)
              @php
                $locale = $translationStatus['locale'];
                $translation = $translationStatus['translation'];
              @endphp
              <tr>
                <td>
                  <div class="wb-cluster wb-cluster-2">
                    <strong>{{ strtoupper($locale->code) }}</strong>
                    <span>{{ $locale->name }}</span>
                    @if ($translationStatus['is_default'])
                      <span class="wb-status-pill wb-status-info">{{ $adminText('default') }}</span>
                    @endif
                  </div>
                </td>
                <td>
                  <span class="wb-status-pill {{ $translationStatus['is_missing'] ? 'wb-status-pending' : 'wb-status-active' }}">
                    {{ $translationStatus['is_missing'] ? $adminText('missing') : $adminText('ready') }}
                  </span>
                </td>
                <td>{{ $translation?->slug ?? $adminText('missing') }}</td>
                <td>{{ $translationStatus['public_path'] ?? $adminText('missing') }}</td>
                <td>
                  @if ($page->isPublished() && $translationStatus['public_url'])
                    <a href="{{ $translationStatus['public_url'] }}" target="_blank" rel="noopener noreferrer" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('open_translation') }}" aria-label="{{ $adminText('open_translation') }}">
                      <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                    </a>
                  @else
                    <span class="wb-action-btn" aria-disabled="true"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i></span>
                  @endif
                </td>
                <td>
                  @if (! $canEditContent)
                    <span class="wb-text-sm wb-text-muted">{{ $adminText('locked_by_workflow') }}</span>
                  @elseif ($translation)
                    <a href="{{ route('admin.pages.translations.edit', ['page' => $page, 'translation' => $translation, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">{{ $adminText('edit_translation') }}</a>
                  @else
                    <a href="{{ route('admin.pages.translations.create', ['page' => $page, 'locale' => $locale, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">{{ $adminText('add_translation') }}</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('overlays')
  @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
    @include('webblocks-cms::admin.pages.partials.page-assets-modals', [
      'page' => $page,
      'canManagePageAssets' => $canManagePageAssets,
      'pageAssetsTab' => $pageAssetsTab,
    ])
  @endif
@endpush

@push('admin-scripts')
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/page-slot-source-modals.js'])
@endpush
