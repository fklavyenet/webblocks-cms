@php
    $drawerTitleId = $drawerId.'Title';
    $drawerParents = $parents instanceof \Illuminate\Support\Collection ? $parents : collect($parents);
@endphp

<div class="wb-drawer wb-drawer-right wb-drawer-sm" id="{{ $drawerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $drawerTitleId }}">
    <div class="wb-drawer-header">
        <h2 class="wb-drawer-title" id="{{ $drawerTitleId }}">{{ $drawerTitle }}</h2>
        <button class="wb-drawer-close" data-wb-dismiss="drawer" aria-label="Close navigation editor">
            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
        </button>
    </div>

    <div class="wb-drawer-body">
        @if ($errors->any() && old('_navigation_drawer') === $drawerId)
            <div class="wb-alert wb-alert-danger">
                <div>
                    <div class="wb-alert-title">Validation Error</div>
                    <div>{{ $errors->first() }}</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <input type="hidden" name="_navigation_drawer" value="{{ $drawerId }}">

            @include('admin.navigation._form', [
                'item' => old('_navigation_drawer') === $drawerId ? tap(clone $item, function ($draft) {
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
                }) : $item,
                'pages' => $pages,
                'parents' => $drawerParents,
                'menuOptions' => $menuOptions,
                'linkTypes' => $linkTypes,
                'cancelType' => 'button',
                'cancelUrl' => null,
                'cancelAttributes' => ['data-wb-dismiss' => 'drawer'],
            ])
        </form>
    </div>
</div>
