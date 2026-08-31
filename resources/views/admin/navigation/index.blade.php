@php
  $navigationLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $navigationItemsText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('navigation_items.'.$key, $navigationLocale, $replace);
  $navigationFormText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('navigation_form.'.$key, $navigationLocale, $replace);
  $baseQuery = ['site_id' => $site->id, 'menu_key' => $activeMenuKey];
  $requestedModal = request('modal');
  $requestedNavigationId = request()->integer('navigation');
  $editModalItem = $editableItems->firstWhere('id', $requestedNavigationId);
  $showDocsGroupHelp = $activeMenuKey === \WebBlocks\Cms\Models\NavigationItem::MENU_DOCS;
  $contextLabel = $navigationItemsText('context_label', ['site' => $site->name, 'menu' => $menuOptions[$activeMenuKey]]);

  $flattenTree = function ($items) use (&$flattenTree) {
    $flat = [];

    foreach ($items as $item) {
      $flat[] = $item;

      if ($item->children->isNotEmpty()) {
        foreach ($flattenTree($item->children) as $child) {
          $flat[] = $child;
        }
      }
    }

    return $flat;
  };

  $allItems = collect($flattenTree($items));
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $navigationItemsText('title'), 'heading' => $navigationItemsText('title')])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $navigationItemsText('title'),
    'description' => $navigationItemsText('description'),
  ])

  @include('webblocks-cms::admin.partials.flash')

  <div class="wb-card wb-card-muted">
    <div class="wb-card-body">
      @include('webblocks-cms::admin.partials.listing-filters', [
        'action' => route('admin.navigation.index'),
        'selects' => [
          [
            'id' => 'navigation_site_id',
            'name' => 'site_id',
            'label' => $navigationFormText('site'),
            'selected' => (string) $site->id,
            'placeholder' => null,
            'submitOnChange' => true,
            'options' => collect($sites)->mapWithKeys(fn ($candidate) => [$candidate->id => $candidate->name])->all(),
          ],
          [
            'id' => 'navigation_menu_key',
            'name' => 'menu_key',
            'label' => $navigationFormText('menu'),
            'selected' => $activeMenuKey,
            'placeholder' => null,
            'submitOnChange' => true,
            'options' => $menuOptions,
          ],
        ],
        'showActions' => false,
      ])
    </div>
  </div>

  <div
    class="wb-card"
    data-navigation-tree-editor
    data-site-id="{{ $site->id }}"
    data-menu-key="{{ $activeMenuKey }}"
    data-reorder-url="{{ route('admin.navigation.reorder') }}"
    data-saving-text="{{ $navigationItemsText('saving') }}"
    data-saved-text="{{ $navigationItemsText('saved') }}"
    data-save-failed-text="{{ $navigationItemsText('save_failed') }}"
    data-could-not-save-text="{{ $navigationItemsText('could_not_save_order') }}"
  >
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
      <div class="wb-stack wb-gap-1">
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
          <strong>{{ $contextLabel }}</strong>
          <span class="wb-status-pill wb-status-info">{{ $allItems->count() }}</span>
        </div>

        @if ($showDocsGroupHelp)
          <div class="wb-text-sm wb-text-muted">
            {!! $navigationItemsText('docs_group_help') !!}
          </div>
        @endif
      </div>

      <div class="wb-cluster wb-cluster-2">
        <a href="{{ route('admin.navigation.index', array_merge($baseQuery, ['modal' => 'create-item'])) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog" aria-controls="navigationCreateItemModal">{{ $navigationItemsText('add_item') }}</a>
        <a href="{{ route('admin.navigation.index', array_merge($baseQuery, ['modal' => 'create-group'])) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog" aria-controls="navigationCreateGroupModal">{{ $navigationItemsText('add_group') }}</a>
      </div>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
      <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
        <span class="wb-text-sm wb-text-muted wb-navigation-toolbar-copy">{{ $navigationItemsText('drag_help') }}</span>
        <div class="wb-cluster wb-cluster-2">
          <span class="wb-text-sm wb-text-muted" data-navigation-save-status aria-live="polite" hidden></span>
        </div>
      </div>

      @if ($items->isEmpty())
        <div class="wb-empty">
          <div class="wb-empty-title">{{ $navigationItemsText('empty_title') }}</div>
          <div class="wb-empty-text">{{ $navigationItemsText('empty_text') }}</div>
        </div>
      @else
        @include('webblocks-cms::admin.navigation.partials.tree-list', ['items' => $items, 'depth' => 1])
      @endif
    </div>
  </div>
@endsection

@push('overlays')
  @include('webblocks-cms::admin.navigation.partials.modal', [
    'modalId' => 'navigationCreateItemModal',
    'modalTitle' => $navigationItemsText('create_item_title'),
    'modalDescription' => $navigationItemsText('create_item_description'),
    'item' => $newItem,
    'pages' => $pages,
    'parents' => $parentOptions,
    'menuOptions' => $menuOptions,
    'site' => $site,
    'activeMenuKey' => $activeMenuKey,
    'formAction' => route('admin.navigation.store'),
    'formMethod' => 'POST',
    'closeUrl' => route('admin.navigation.index', $baseQuery),
    'show' => $requestedModal === 'create-item',
  ])

  @include('webblocks-cms::admin.navigation.partials.modal', [
    'modalId' => 'navigationCreateGroupModal',
    'modalTitle' => $navigationItemsText('create_group_title'),
    'modalDescription' => $navigationItemsText('create_group_description'),
    'item' => $newGroup,
    'pages' => $pages,
    'parents' => $parentOptions,
    'menuOptions' => $menuOptions,
    'site' => $site,
    'activeMenuKey' => $activeMenuKey,
    'formAction' => route('admin.navigation.store'),
    'formMethod' => 'POST',
    'closeUrl' => route('admin.navigation.index', $baseQuery),
    'show' => $requestedModal === 'create-group',
  ])

  @if ($editModalItem)
    @include('webblocks-cms::admin.navigation.partials.modal', [
      'modalId' => 'navigationEditModal-'.$editModalItem->id,
      'modalTitle' => $navigationItemsText('edit_title', ['title' => $editModalItem->resolvedTitle()]),
      'modalDescription' => $navigationItemsText('edit_description'),
      'item' => $editModalItem,
      'pages' => $pages,
      'parents' => app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)->parentOptions($editModalItem->menu_key, $editModalItem->site_id, $editModalItem->id),
      'menuOptions' => $menuOptions,
      'site' => $site,
      'activeMenuKey' => $activeMenuKey,
      'formAction' => route('admin.navigation.update', $editModalItem),
      'formMethod' => 'PUT',
      'closeUrl' => route('admin.navigation.index', $baseQuery),
      'show' => $requestedModal === 'edit-item',
    ])
  @endif
@endpush

@push('scripts')
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/navigation-tree.js'])
@endpush
