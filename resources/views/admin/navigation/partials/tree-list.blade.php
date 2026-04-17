@php($listAttribute = $depth === 1 ? 'data-navigation-tree' : 'data-navigation-children')

<ul class="{{ $depth === 1 ? 'wb-navigation-tree' : 'wb-navigation-children' }}" {{ $listAttribute }}>
    @foreach ($items as $item)
        <li class="wb-navigation-tree-item" data-navigation-item data-item-id="{{ $item->id }}" data-depth="{{ $depth }}">
            <div class="wb-navigation-row">
                <button type="button" class="wb-navigation-handle" data-navigation-handle title="Drag to reorder" aria-label="Drag to reorder">
                    <span aria-hidden="true">⋮⋮</span>
                </button>

                <div class="wb-navigation-meta">
                    <div class="wb-navigation-title-row">
                        <strong>{{ $item->resolvedTitle() }}</strong>
                        <span class="wb-navigation-badge">{{ $item->typeLabel() }}</span>
                        @if ($item->is_system)
                            <span class="wb-navigation-badge">System</span>
                        @endif
                        <span class="wb-status-pill {{ $item->isVisible() ? 'wb-status-active' : 'wb-status-pending' }}">{{ $item->visibilityLabel() }}</span>
                    </div>
                    <div class="wb-navigation-sub-row wb-text-sm wb-text-muted">
                        <span>{{ $item->metaLabel() }}</span>
                    </div>
                </div>

                <div class="wb-navigation-actions">
                    <button
                        type="button"
                        class="wb-action-btn wb-action-btn-edit"
                        data-wb-toggle="drawer"
                        data-wb-target="#navigationEditDrawer-{{ $item->id }}"
                        aria-controls="navigationEditDrawer-{{ $item->id }}"
                        title="Edit navigation item"
                        aria-label="Edit navigation item"
                    ><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></button>

                    <form method="POST" action="{{ route('admin.navigation.visibility', $item) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="wb-action-btn" title="{{ $item->isVisible() ? 'Hide item' : 'Show item' }}" aria-label="{{ $item->isVisible() ? 'Hide item' : 'Show item' }}"><i class="wb-icon {{ $item->isVisible() ? 'wb-icon-eye-off' : 'wb-icon-eye' }}" aria-hidden="true"></i></button>
                    </form>

                    <form method="POST" action="{{ route('admin.navigation.destroy', $item) }}" onsubmit="return confirm('Delete this navigation item?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete navigation item" aria-label="Delete navigation item"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                    </form>
                </div>
            </div>

            @if ($item->children->isNotEmpty())
                @include('admin.navigation.partials.tree-list', ['items' => $item->children, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
