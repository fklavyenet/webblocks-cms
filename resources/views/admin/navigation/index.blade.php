@extends('layouts.admin', ['title' => 'Navigation Items', 'heading' => 'Navigation Items'])

@php
    $baseQuery = ['site_id' => $site->id, 'menu_key' => $activeMenuKey];
    $requestedModal = request('modal');
    $requestedNavigationId = request()->integer('navigation');
    $editModalItem = $editableItems->firstWhere('id', $requestedNavigationId);
    $showDocsGroupHelp = $activeMenuKey === \App\Models\NavigationItem::MENU_DOCS;
    $contextLabel = 'Site: '.$site->name.' · '.$menuOptions[$activeMenuKey];

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
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('admin.partials.listing-filters', [
                'action' => route('admin.navigation.index'),
                'selects' => [
                    [
                        'id' => 'navigation_site_id',
                        'name' => 'site_id',
                        'label' => 'Site',
                        'selected' => (string) $site->id,
                        'placeholder' => null,
                        'submitOnChange' => true,
                        'options' => collect($sites)->mapWithKeys(fn ($candidate) => [$candidate->id => $candidate->name])->all(),
                    ],
                    [
                        'id' => 'navigation_menu_key',
                        'name' => 'menu_key',
                        'label' => 'Menu',
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

    <div class="wb-card" data-navigation-tree-editor data-site-id="{{ $site->id }}" data-menu-key="{{ $activeMenuKey }}" data-reorder-url="{{ route('admin.navigation.reorder') }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-gap-1">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $contextLabel }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $allItems->count() }}</span>
                </div>

                @if ($showDocsGroupHelp)
                    <div class="wb-text-sm wb-text-muted">
                        Use <code>Add Group</code> for collapsible docs sidebar groups. Then use <code>Parent Group</code> in item modals to nest child links inside that group.
                    </div>
                @endif
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.navigation.index', array_merge($baseQuery, ['modal' => 'create-item'])) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog" aria-controls="navigationCreateItemModal">Add Item</a>
                <a href="{{ route('admin.navigation.index', array_merge($baseQuery, ['modal' => 'create-group'])) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog" aria-controls="navigationCreateGroupModal">Add Group</a>
            </div>
        </div>

        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                <span class="wb-text-sm wb-text-muted wb-navigation-toolbar-copy">Drag items by the handle. Changes save automatically.</span>
                <div class="wb-cluster wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted" data-navigation-save-status aria-live="polite" hidden></span>
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
    @include('admin.navigation.partials.modal', [
        'modalId' => 'navigationCreateItemModal',
        'modalTitle' => 'Add Navigation Item',
        'modalDescription' => 'Create a normal navigation link for this menu.',
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

    @include('admin.navigation.partials.modal', [
        'modalId' => 'navigationCreateGroupModal',
        'modalTitle' => 'Add Navigation Group',
        'modalDescription' => 'Create a collapsible parent section that can contain child navigation items.',
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
        @include('admin.navigation.partials.modal', [
            'modalId' => 'navigationEditModal-'.$editModalItem->id,
            'modalTitle' => 'Edit Navigation Item: '.$editModalItem->resolvedTitle(),
            'modalDescription' => 'Update the menu, parent group, and link settings for this item.',
            'item' => $editModalItem,
            'pages' => $pages,
            'parents' => app(\App\Support\Navigation\NavigationTree::class)->parentOptions($editModalItem->menu_key, $editModalItem->site_id, $editModalItem->id),
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

                    if (!text) {
                        status.textContent = '';
                        status.hidden = true;
                        status.className = 'wb-text-sm';

                        return;
                    }

                    status.hidden = false;
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
                                setStatus('', 'muted');
                            }, 1500);
                        })
                        .catch(function () {
                            setStatus('Could not save order.', 'error');
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

                            if (targetParent && targetParent.getAttribute('data-item-link-type') !== 'group') {
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
                                setStatus('Could not save order.', 'error');
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
