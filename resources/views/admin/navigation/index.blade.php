@extends('layouts.admin', ['title' => 'Navigation Items', 'heading' => 'Navigation Items'])

@php
    $menuSelector = '<form method="GET" action="'.route('admin.navigation.index').'" class="wb-cluster wb-cluster-2">'
        .'<select name="site_id" class="wb-select" onchange="this.form.submit()">'
        .collect($sites)->map(fn ($candidate) => '<option value="'.$candidate->id.'" '.($site->id === $candidate->id ? 'selected' : '').'>'.$candidate->name.'</option>')->implode('')
        .'</select>'
        .'<select name="menu_key" class="wb-select" onchange="this.form.submit()">'
        .collect($menuOptions)->map(fn ($label, $key) => '<option value="'.$key.'" '.($activeMenuKey === $key ? 'selected' : '').'>'.$label.'</option>')->implode('')
        .'</select>'
        .'<button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="drawer" data-wb-target="#navigationCreateItemDrawer">Add Item</button>'
        .'<button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="drawer" data-wb-target="#navigationCreateGroupDrawer">Add Group</button>'
        .'</form>';

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

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Navigation Items',
        'description' => 'Manage site menus, dropdowns, and footer links.',
        'count' => $allItems->count(),
        'actions' => $menuSelector,
    ])

    @include('admin.partials.flash')

    <div class="wb-card" data-navigation-tree-editor data-site-id="{{ $site->id }}" data-menu-key="{{ $activeMenuKey }}" data-reorder-url="{{ route('admin.navigation.reorder') }}">
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                <div class="wb-cluster wb-cluster-2">
                    <span class="wb-status-pill wb-status-info">{{ $site->name }}</span>
                    <span class="wb-status-pill wb-status-active">{{ $menuOptions[$activeMenuKey] }}</span>
                    <span class="wb-text-sm wb-text-muted wb-navigation-toolbar-copy">Drag items by the handle. Changes save automatically.</span>
                </div>
                <div class="wb-cluster wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted" data-navigation-save-status aria-live="polite">Idle</span>
                </div>
            </div>

            @if ($items->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No navigation items yet</div>
                    <div class="wb-empty-text">Create a page link, custom URL, or dropdown group for this menu.</div>
                </div>
            @else
                @include('admin.navigation.partials.tree-list', ['items' => $items, 'depth' => 1])
            @endif
        </div>
    </div>
@endsection

@push('overlays')
    @include('admin.navigation.partials.drawer', [
        'drawerId' => 'navigationCreateItemDrawer',
        'drawerTitle' => 'Add Navigation Item',
        'item' => $newItem,
        'pages' => $pages,
        'parents' => [],
        'menuOptions' => $menuOptions,
        'linkTypes' => \App\Models\NavigationItem::linkTypes(),
        'formAction' => route('admin.navigation.store'),
        'formMethod' => 'POST',
    ])

    @include('admin.navigation.partials.drawer', [
        'drawerId' => 'navigationCreateGroupDrawer',
        'drawerTitle' => 'Add Navigation Group',
        'item' => $newGroup,
        'pages' => $pages,
        'parents' => [],
        'menuOptions' => $menuOptions,
        'linkTypes' => \App\Models\NavigationItem::linkTypes(),
        'formAction' => route('admin.navigation.store'),
        'formMethod' => 'POST',
    ])

    @foreach ($editableItems as $editableItem)
        @include('admin.navigation.partials.drawer', [
            'drawerId' => 'navigationEditDrawer-'.$editableItem->id,
            'drawerTitle' => 'Edit Navigation Item: '.$editableItem->resolvedTitle(),
            'item' => $editableItem,
            'pages' => $pages,
            'parents' => app(\App\Support\Navigation\NavigationTree::class)->parentOptions($editableItem->menu_key, $editableItem->site_id, $editableItem->id),
            'menuOptions' => $menuOptions,
            'linkTypes' => \App\Models\NavigationItem::linkTypes(),
            'formAction' => route('admin.navigation.update', $editableItem),
            'formMethod' => 'PUT',
        ])
    @endforeach
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        (function () {
            function initNavigationTree(root) {
                if (!root || root.dataset.navigationTreeReady === '1') {
                    return;
                }

                root.dataset.navigationTreeReady = '1';

                var status = root.querySelector('[data-navigation-save-status]');
                var menuKey = root.getAttribute('data-menu-key');
                var siteId = Number(root.getAttribute('data-site-id'));
                var reorderUrl = root.getAttribute('data-reorder-url');
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var previousSnapshot = root.querySelector('[data-navigation-tree]') ? root.querySelector('[data-navigation-tree]').innerHTML : '';
                var saveTimer = null;

                function setStatus(text, tone) {
                    if (!status) {
                        return;
                    }

                    status.textContent = text;
                    status.className = 'wb-text-sm';

                    if (tone === 'error') {
                        status.classList.add('wb-text-danger');
                    } else if (tone === 'success') {
                        status.classList.add('wb-text-success');
                    } else {
                        status.classList.add('wb-text-muted');
                    }
                }

                function snapshot() {
                    var tree = root.querySelector('[data-navigation-tree]');

                    if (tree) {
                        previousSnapshot = tree.innerHTML;
                    }
                }

                function restore() {
                    var tree = root.querySelector('[data-navigation-tree]');

                    if (!tree) {
                        window.location.reload();
                        return;
                    }

                    tree.innerHTML = previousSnapshot;
                    initSortables(tree);
                }

                function buildPayload() {
                    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-navigation-item]'));
                    var siblingPositions = {};

                    return {
                        site_id: siteId,
                        menu_key: menuKey,
                        items: rows.map(function (row) {
                            var parentList = row.parentElement.closest('[data-navigation-item]');
                            var parentId = parentList ? Number(parentList.getAttribute('data-item-id')) : null;
                            var key = parentId === null ? 'root' : String(parentId);

                            siblingPositions[key] = (siblingPositions[key] || 0) + 1;

                            return {
                                id: Number(row.getAttribute('data-item-id')),
                                parent_id: parentId,
                                position: siblingPositions[key]
                            };
                        })
                    };
                }

                function save() {
                    setStatus('Saving...', 'muted');
                    root.querySelectorAll('.wb-navigation-row').forEach(function (row) {
                        row.classList.add('is-saving');
                    });

                    fetch(reorderUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
                        },
                        body: JSON.stringify(buildPayload())
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                return response.json().catch(function () {
                                    return {};
                                }).then(function (payload) {
                                    throw new Error(payload.message || (payload.errors && payload.errors.items ? payload.errors.items[0] : 'Navigation save failed.'));
                                });
                            }

                            return response.json();
                        })
                        .then(function () {
                            snapshot();
                            setStatus('Saved', 'success');

                            if (saveTimer) {
                                window.clearTimeout(saveTimer);
                            }

                            saveTimer = window.setTimeout(function () {
                                setStatus('Idle', 'muted');
                            }, 1500);
                        })
                        .catch(function (error) {
                            setStatus(error.message || 'Save failed', 'error');
                            restore();
                            window.setTimeout(function () {
                                window.location.reload();
                            }, 900);
                        })
                        .finally(function () {
                            root.querySelectorAll('.wb-navigation-row').forEach(function (row) {
                                row.classList.remove('is-saving');
                            });
                        });
                }

                function ensureChildList(item) {
                    var childList = item.querySelector(':scope > [data-navigation-children]');

                    if (childList) {
                        return childList;
                    }

                    childList = document.createElement('ul');
                    childList.className = 'wb-navigation-children';
                    childList.setAttribute('data-navigation-children', '');
                    item.appendChild(childList);
                    initSortable(childList, Number(item.getAttribute('data-depth')) + 1);

                    return childList;
                }

                function updateDepth(item, depth) {
                    item.setAttribute('data-depth', String(depth));
                    var childList = item.querySelector(':scope > [data-navigation-children]');

                    if (!childList) {
                        return;
                    }

                    Array.prototype.slice.call(childList.children).forEach(function (child) {
                        if (child.matches('[data-navigation-item]')) {
                            updateDepth(child, depth + 1);
                        }
                    });
                }

                function initSortable(list, depth) {
                    new Sortable(list, {
                        group: 'navigation-tree-'+menuKey,
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.65,
                        handle: '[data-navigation-handle]',
                        draggable: '[data-navigation-item]',
                        ghostClass: 'wb-navigation-tree-ghost',
                        chosenClass: 'wb-navigation-tree-chosen',
                        onMove: function (event) {
                            var dragged = event.dragged;
                            var related = event.related;
                            var toList = event.to;
                            var fromItem = related ? related.closest('[data-navigation-item]') : toList.closest('[data-navigation-item]');
                            var baseDepth = fromItem ? Number(fromItem.getAttribute('data-depth')) + 1 : 1;

                            if (baseDepth > 3) {
                                return false;
                            }

                            var draggedId = dragged.getAttribute('data-item-id');
                            var targetParent = toList.closest('[data-navigation-item]');

                            if (targetParent && targetParent.getAttribute('data-item-id') === draggedId) {
                                return false;
                            }

                            if (targetParent && targetParent.querySelector('[data-item-id="'+draggedId+'"]')) {
                                return false;
                            }

                            return true;
                        },
                        onEnd: function (event) {
                            var item = event.item;
                            var toList = event.to;
                            var parentItem = toList.closest('[data-navigation-item]');
                            var newDepth = parentItem ? Number(parentItem.getAttribute('data-depth')) + 1 : 1;

                            if (newDepth > 3) {
                                restore();
                                setStatus('Navigation depth cannot exceed 3 levels.', 'error');
                                return;
                            }

                            updateDepth(item, newDepth);

                            if (item.parentElement !== toList) {
                                toList.appendChild(item);
                            }

                            if (event.pullMode === 'clone') {
                                restore();
                                return;
                            }

                            save();
                        }
                    });
                }

                function initSortables(tree) {
                    if (!tree) {
                        return;
                    }

                    [tree].concat(Array.prototype.slice.call(tree.querySelectorAll('[data-navigation-children]'))).forEach(function (list) {
                        var parentItem = list.closest('[data-navigation-item]');
                        var depth = parentItem ? Number(parentItem.getAttribute('data-depth')) + 1 : 1;
                        initSortable(list, depth);
                    });
                }

                snapshot();
                initSortables(root.querySelector('[data-navigation-tree]'));

                root.addEventListener('pointermove', function (event) {
                    var dragging = document.querySelector('.sortable-chosen');

                    if (!dragging) {
                        return;
                    }

                    var treeRect = root.getBoundingClientRect();
                    var indent = 32;
                    var relative = Math.max(0, event.clientX - treeRect.left - 24);
                    var desiredDepth = Math.min(3, Math.max(1, Math.round(relative / indent) + 1));
                    var currentDepth = Number(dragging.getAttribute('data-depth')) || 1;

                    if (desiredDepth === currentDepth) {
                        return;
                    }

                    if (desiredDepth > currentDepth) {
                        var previous = dragging.previousElementSibling;

                        if (previous && previous.matches('[data-navigation-item]') && Number(previous.getAttribute('data-depth')) < 3) {
                            ensureChildList(previous).appendChild(dragging);
                            updateDepth(dragging, Number(previous.getAttribute('data-depth')) + 1);
                        }
                    } else {
                        var parentItem = dragging.parentElement.closest('[data-navigation-item]');

                        while (parentItem && Number(dragging.getAttribute('data-depth')) > desiredDepth) {
                            parentItem.after(dragging);
                            updateDepth(dragging, Number(parentItem.getAttribute('data-depth')));
                            parentItem = dragging.parentElement.closest('[data-navigation-item]');
                        }
                    }
                });
            }

            document.querySelectorAll('[data-navigation-tree-editor]').forEach(initNavigationTree);
        })();
    </script>
@endpush
