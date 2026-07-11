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

                    <form method="POST" action="{{ route('admin.navigation.destroy', $item) }}" onsubmit="return confirm(@js($navigationItemsText('delete_confirm')));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $navigationItemsText('delete_item') }}" aria-label="{{ $navigationItemsText('delete_item') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                    </form>
                </div>
            </div>

            @if ($item->children->isNotEmpty())
                @include('webblocks-cms::admin.navigation.partials.tree-list', ['items' => $item->children, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
