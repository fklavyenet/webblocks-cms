@php
  $pageTitle = 'Edit Page #'.$page->id.': '.$page->title;
  $settingsTab = old('_page_settings_tab', match (request('tab')) {
    'page-assets' => 'assets',
    'layout-slots' => 'layout-slots',
    'overview' => 'overview',
    default => 'settings',
  });
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
  $siteName = $page->site?->name ?? 'Site';
  $domainName = $page->site?->canonicalDomain() ?: 'Not set';
  $headerActions = collect([
    $pageDuplicateUrl ? '<a href="'.$pageDuplicateUrl.'" class="wb-btn wb-btn-secondary">Duplicate page</a>' : null,
    $pageMoveUrl ? '<a href="'.$pageMoveUrl.'" class="wb-btn wb-btn-secondary">Move to another site</a>' : null,
    $pageRevisionsUrl ? '<a href="'.$pageRevisionsUrl.'" class="wb-btn wb-btn-secondary">Revision History</a>' : null,
    '<a href="'.$pagePreviewUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i> <span>Preview</span></a>',
    $pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : null,
  ])->filter()->implode('');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.$siteName.'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">Pages</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.$page->title.'</span></li></ol></nav>',
    'title' => $pageTitle,
    'description' => 'Manage the canonical page, English base fields, and translation routing from one compact screen.',
    'actions' => $headerActions,
  ])

  @include('webblocks-cms::admin.partials.flash')

  <div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>Page Management</strong>
      <span class="wb-text-sm wb-text-muted">Manage page status, settings, page-specific assets, and layout slot alignment.</span>
    </div>
    <div class="wb-card-body">
      <div class="wb-tabs" data-wb-tabs data-wb-page-settings-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="Page management sections">
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'overview' ? 'is-active' : '' }}" data-wb-tab="page-management-overview-panel" aria-selected="{{ $settingsTab === 'overview' ? 'true' : 'false' }}" @if ($settingsTab !== 'overview') tabindex="-1" @endif>Overview</button>
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'settings' ? 'is-active' : '' }}" data-wb-tab="page-management-settings-panel" aria-selected="{{ $settingsTab === 'settings' ? 'true' : 'false' }}" @if ($settingsTab !== 'settings') tabindex="-1" @endif>Settings</button>
          @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
            <button type="button" class="wb-tabs-btn {{ $settingsTab === 'assets' ? 'is-active' : '' }}" data-wb-tab="page-management-assets-panel" aria-selected="{{ $settingsTab === 'assets' ? 'true' : 'false' }}" @if ($settingsTab !== 'assets') tabindex="-1" @endif>Assets</button>
          @endif
          <button type="button" class="wb-tabs-btn {{ $settingsTab === 'layout-slots' ? 'is-active' : '' }}" data-wb-tab="page-management-layout-slots-panel" aria-selected="{{ $settingsTab === 'layout-slots' ? 'true' : 'false' }}" @if ($settingsTab !== 'layout-slots') tabindex="-1" @endif>Layout Slots</button>
        </div>

        <div class="wb-tabs-panels">
          <div class="wb-tabs-panel {{ $settingsTab === 'overview' ? 'is-active' : '' }}" id="page-management-overview-panel">
            <div class="wb-card wb-card-muted">
              <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Overview</strong>
                <span class="wb-text-sm wb-text-muted">Only published pages are visible on the public site.</span>
              </div>
              <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                  <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">Site</span>
                      <strong>{{ $siteName }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">Domain</span>
                      <span>{{ $domainName }}</span>
                    </div>
                  </div>

                  <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">Status</span>
                      <div>
                        <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                      </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <span class="wb-text-sm wb-text-muted">Published</span>
                      <span>{{ $page->published_at ? $page->published_at->format('Y-m-d H:i') : 'Not published' }}</span>
                    </div>

                    @if ($page->review_requested_at)
                      <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Review requested</span>
                        <span>{{ $page->review_requested_at->format('Y-m-d H:i') }}</span>
                      </div>
                    @endif

                    @if ($workflowActions !== [])
                      <div class="wb-stack wb-gap-2">
                        <span class="wb-text-sm wb-text-muted">Actions</span>
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
                  <strong>Unpublished page content</strong>
                  @if ($canPublishPageOwnedBlocks)
                    <form method="POST" action="{{ route('admin.pages.publish-page-owned-blocks', $page) }}">
                      @csrf
                      <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                      <button type="submit" class="wb-btn wb-btn-secondary">Publish page-owned blocks</button>
                    </form>
                  @endif
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                  <p class="wb-text-sm wb-text-muted">{{ $pageOwnedBlockPublishingSummary['total'] }} page-owned {{ \Illuminate\Support\Str::plural('block', $pageOwnedBlockPublishingSummary['total']) }} are still draft or in review.</p>
                  @if ($hasExcludedSharedSlotBlocks)
                    <div class="wb-alert wb-alert-info">
                      Shared Slot content is not included. Review and publish Shared Slots separately.
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
                  <strong>Settings</strong>
                  <span class="wb-text-sm wb-text-muted">Update the page fields and default English routing settings.</span>
                </div>

                <div class="wb-card-body">
                  @include('webblocks-cms::admin.pages._form', ['canEditContent' => $canEditContent])
                </div>

                <div class="wb-card-footer">
                  <x-webblocks-cms::admin.form-actions :cancel-url="$pageReturnUrl" :show-submit="$canEditContent" submit-label="Save Changes" />
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
            <h2 class="wb-modal-title" id="publish-page-modal-title">Publish page</h2>
            <p class="wb-text-sm wb-text-muted" id="publish-page-modal-description">Choose whether page-owned blocks should publish with this page.</p>
          </div>
          <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close publish page modal">
            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('admin.pages.workflow', $page) }}">
          @csrf
          <input type="hidden" name="action" value="{{ \WebBlocks\Cms\Support\Pages\PageWorkflowManager::ACTION_PUBLISH }}">
          <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
          <div class="wb-modal-body wb-stack wb-gap-4">
            <div class="wb-alert wb-alert-info">
              Publishing the page alone keeps existing draft and in-review blocks unpublished unless you choose the option below.
            </div>

            <div class="wb-table-wrap">
              <table class="wb-table wb-table-striped">
                <thead>
                  <tr>
                    <th>Page-owned slot</th>
                    <th>Draft</th>
                    <th>In Review</th>
                    <th>Total</th>
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
                Shared Slot content is not included. Review and publish Shared Slots separately.
              </div>
              <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                  <thead>
                    <tr>
                      <th>Shared Slot-backed slot</th>
                      <th>Shared Slot</th>
                      <th>Unpublished</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($pageOwnedBlockPublishingSummary['shared_slots_excluded'] as $slotSummary)
                      <tr>
                        <td>{{ $slotSummary['label'] }}</td>
                        <td>{{ $slotSummary['shared_slot_label'] ?? 'Shared Slot' }}</td>
                        <td>{{ $slotSummary['total'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            <label class="wb-cluster wb-cluster-2" for="include_page_owned_blocks">
              <input id="include_page_owned_blocks" type="checkbox" name="include_page_owned_blocks" value="1">
              <span>Also publish all unpublished page-owned blocks</span>
            </label>
          </div>
          <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Cancel</button>
            <button type="submit" class="wb-btn wb-btn-primary">Publish</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>Translations</strong>
      <span class="wb-text-sm wb-text-muted">Page title and routing only</span>
    </div>
    <div class="wb-card-body">
      <div class="wb-table-wrap">
        <table class="wb-table wb-table-striped wb-table-hover">
          <thead>
            <tr>
              <th>Locale</th>
              <th>Status</th>
              <th>Slug</th>
              <th>Path</th>
              <th>Open</th>
              <th>Action</th>
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
                      <span class="wb-status-pill wb-status-info">Default</span>
                    @endif
                  </div>
                </td>
                <td>
                  <span class="wb-status-pill {{ $translationStatus['is_missing'] ? 'wb-status-pending' : 'wb-status-active' }}">
                    {{ $translationStatus['is_missing'] ? 'Missing' : 'Ready' }}
                  </span>
                </td>
                <td>{{ $translation?->slug ?? 'Missing' }}</td>
                <td>{{ $translationStatus['public_path'] ?? 'Missing' }}</td>
                <td>
                  @if ($page->isPublished() && $translationStatus['public_url'])
                    <a href="{{ $translationStatus['public_url'] }}" target="_blank" rel="noopener noreferrer" class="wb-action-btn wb-action-btn-view" title="Open translation" aria-label="Open translation">
                      <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                    </a>
                  @else
                    <span class="wb-action-btn" aria-disabled="true"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i></span>
                  @endif
                </td>
                <td>
                  @if (! $canEditContent)
                    <span class="wb-text-sm wb-text-muted">Locked by workflow</span>
                  @elseif ($translation)
                    <a href="{{ route('admin.pages.translations.edit', ['page' => $page, 'translation' => $translation, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">Edit translation</a>
                  @else
                    <a href="{{ route('admin.pages.translations.create', ['page' => $page, 'locale' => $locale, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">Add translation</a>
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
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/page-assets.js'])
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/page-slot-source-modals.js'])
@endpush
