@php
  $modalTitleId = $modalId.'Title';
  $modalDescriptionId = $modalId.'Description';
  $openModalId = old('_navigation_modal');
  $isOpen = $openModalId === $modalId || ($show ?? false);
  $closeUrl = $closeUrl ?? route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => $activeMenuKey]);
  $modalParents = $parents instanceof \Illuminate\Support\Collection ? $parents : collect($parents);
  $draftItem = $isOpen ? tap(clone $item, function ($draft) {
    $draft->menu_key = old('menu_key', $draft->menu_key);
    $draft->title = old('title', $draft->title);
    $draft->link_type = old('link_type', $draft->link_type);
    $draft->page_id = old('page_id', $draft->page_id);
    $draft->url = old('url', $draft->url);
    $draft->target = old('target', $draft->target);
    $draft->icon = old('icon', $draft->icon);
    $draft->visibility = old('visibility', $draft->visibility);
    $draft->parent_id = old('parent_id', $draft->parent_id);
    $draft->position = old('position', $draft->position);
  }) : $item;
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-wb-admin-close-url="{{ $closeUrl }}" @if ($isOpen) data-wb-admin-autoload-overlay hidden @else hidden @endif>
    <div class="wb-modal-dialog">
      <div class="wb-modal-header">
        <div class="wb-stack wb-gap-1">
          <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $modalTitle }}</h2>
          <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $modalDescription }}</span>
        </div>
        <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close navigation item modal">
          <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
        </a>
      </div>

      <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="Discard navigation item changes?">
        @csrf
        @if ($formMethod !== 'POST')
          @method($formMethod)
        @endif

        <input type="hidden" name="_navigation_modal" value="{{ $modalId }}">

        <div class="wb-modal-body wb-stack wb-gap-4">
          @if ($errors->any() && $isOpen)
            <div class="wb-alert wb-alert-danger">
              <div>
                <div class="wb-alert-title">Validation Error</div>
                <div>{{ $errors->first() }}</div>
              </div>
            </div>
          @endif

          @include('webblocks-cms::admin.navigation._form', [
            'item' => $draftItem,
            'pages' => $pages,
            'parents' => $modalParents,
            'menuOptions' => $menuOptions,
            'site' => $site,
            'cancelType' => 'link',
            'cancelUrl' => $closeUrl,
            'formActionsContainerClass' => 'wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap',
          ])
        </div>
      </form>
    </div>
</div>
