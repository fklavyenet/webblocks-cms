@php
    $listAttribute = $depth === 1 ? 'data-navigation-tree' : 'data-navigation-children';
    $navigationItemsText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.navigation_items.'.$key, $replace);
@endphp

<ul class="{{ $depth === 1 ? 'wb-navigation-tree' : 'wb-navigation-children' }}" {{ $listAttribute }}>
    @foreach ($items as $item)
        <li class="wb-navigation-tree-item" data-navigation-item data-item-id="{{ $item->id }}" data-depth="{{ $depth }}" data-item-link-type="{{ $item->link_type }}">
            <div class="wb-navigation-row">
                <button type="button" class="wb-navigation-handle" data-navigation-handle title="{{ $navigationItemsText('drag_to_reorder') }}" aria-label="{{ $navigationItemsText('drag_to_reorder') }}">
                    <span aria-hidden="true">⋮⋮</span>
                </button>

                <div class="wb-navigation-meta">
                    <div class="wb-navigation-title-row">
                        <strong>{{ $item->resolvedTitle() }}</strong>
                        <span class="wb-navigation-badge">{{ $item->typeLabel() }}</span>
                        @if ($item->is_system)
                            <span class="wb-navigation-badge">{{ $navigationItemsText('system') }}</span>
                        @endif
                        <span class="wb-status-pill {{ $item->isVisible() ? 'wb-status-active' : 'wb-status-pending' }}">{{ $item->visibilityLabel() }}</span>
                    </div>
                    <div class="wb-navigation-sub-row wb-text-sm wb-text-muted">
                        <span>{{ $item->metaLabel() }}</span>
                    </div>
                </div>

                <div class="wb-navigation-actions">
                    <a
                        href="{{ route('admin.navigation.index', ['site_id' => $item->site_id, 'menu_key' => $item->menu_key, 'modal' => 'edit-item', 'navigation' => $item->id]) }}"
                        class="wb-action-btn wb-action-btn-edit"
                        aria-haspopup="dialog"
                        aria-controls="navigationEditModal-{{ $item->id }}"
                        title="{{ $navigationItemsText('edit_item') }}"
                        aria-label="{{ $navigationItemsText('edit_item') }}"
                    ><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>

                    <form method="POST" action="{{ route('admin.navigation.visibility', $item) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="site_id" value="{{ $item->site_id }}">
                        <button type="submit" class="wb-action-btn" title="{{ $item->isVisible() ? $navigationItemsText('hide_item') : $navigationItemsText('show_item') }}" aria-label="{{ $item->isVisible() ? $navigationItemsText('hide_item') : $navigationItemsText('show_item') }}"><i class="wb-icon {{ $item->isVisible() ? 'wb-icon-eye-off' : 'wb-icon-eye' }}" aria-hidden="true"></i></button>
                    </form>

                    <button
                        type="button"
                        class="wb-action-btn wb-action-btn-delete"
                        data-wb-toggle="modal"
                        data-wb-target="#delete-navigation-item-{{ $item->id }}"
                        title="{{ $navigationItemsText('delete_item') }}"
                        aria-label="{{ $navigationItemsText('delete_item') }}"
                        aria-haspopup="dialog"
                    ><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                </div>
            </div>

            {{-- The tree recurses, so each level pushes its own confirmations and a
                 nested item's modal is registered exactly once. --}}
            @push('overlays')
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'delete-navigation-item-'.$item->id,
                    'title' => $navigationItemsText('delete_title'),
                    'description' => $navigationItemsText('delete_description'),
                    'action' => route('admin.navigation.destroy', $item),
                    'method' => 'DELETE',
                    'submitLabel' => $navigationItemsText('delete_item'),
                ])
                    <p>{{ $navigationItemsText('delete_confirm_prefix') }} <strong>{{ $item->resolvedTitle() }}</strong>? {{ $navigationItemsText('cannot_be_undone') }}</p>

                    @if ($item->children->isNotEmpty())
                        <div class="wb-alert wb-alert-warning">
                            {{ $navigationItemsText('delete_children_warning', ['count' => $item->children->count()]) }}
                        </div>
                    @endif
                @endcomponent
            @endpush

            @if ($item->children->isNotEmpty())
                @include('webblocks-cms::admin.navigation.partials.tree-list', ['items' => $item->children, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
