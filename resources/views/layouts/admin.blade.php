<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $adminCssPath = public_path('site/css/admin.css');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($adminCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/admin.css') }}?v={{ filemtime($adminCssPath) }}">
        @endif
        @stack('styles')
    </head>
        <body>
        @php
            $user = auth()->user();
            $userInitials = collect(preg_split('/\s+/', trim($user?->name ?? 'User')))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('');

            $menuItems = [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'wb-icon-layout-dashboard'],
            ];

            $sidebarGroups = [
                [
                    'label' => 'Content',
                    'icon' => 'wb-icon-file-text',
                    'items' => [
                        ['label' => 'Pages', 'route' => 'admin.pages.index', 'active' => 'admin.pages.*'],
                        ['label' => 'Navigation', 'route' => 'admin.navigation.index', 'active' => 'admin.navigation.*'],
                        ['label' => 'Media', 'route' => 'admin.media.index', 'active' => 'admin.media.*'],
                    ],
                ],
                [
                    'label' => 'Reports',
                    'icon' => 'wb-icon-list',
                    'items' => [
                        ['label' => 'Visitor Reports', 'route' => 'admin.reports.visitors.index', 'active' => 'admin.reports.visitors.*'],
                    ],
                ],
            ];

            if ($user?->can('access-system')) {
                $sidebarGroups[] = [
                    'label' => 'Sites',
                    'icon' => 'wb-icon-globe',
                    'items' => [
                        ['label' => 'Sites', 'route' => 'admin.sites.index', 'active' => 'admin.sites.*'],
                        ['label' => 'Locales', 'route' => 'admin.locales.index', 'active' => 'admin.locales.*'],
                    ],
                ];

                $sidebarGroups[] = [
                    'label' => 'Access',
                    'icon' => 'wb-icon-user',
                    'items' => [
                        ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                    ],
                ];

                $sidebarGroups[] = [
                    'label' => 'Structure',
                    'icon' => 'wb-icon-layers',
                    'items' => [
                        ['label' => 'Block Types', 'route' => 'admin.block-types.index', 'active' => 'admin.block-types.*'],
                        ['label' => 'Slot Types', 'route' => 'admin.slot-types.index', 'active' => 'admin.slot-types.*'],
                    ],
                ];

                $sidebarGroups[] = [
                    'label' => 'System',
                    'icon' => 'wb-icon-palette',
                    'items' => [
                        ['label' => 'Settings', 'route' => 'admin.system.settings.edit', 'active' => 'admin.system.settings.*'],
                        ['label' => 'Updates', 'route' => 'admin.system.updates.index', 'active' => 'admin.system.updates.*'],
                    ],
                ];

                $sidebarGroups[] = [
                    'label' => 'Maintenance',
                    'icon' => 'wb-icon-file',
                    'items' => [
                        ['label' => 'Backups', 'route' => 'admin.system.backups.index', 'active' => 'admin.system.backups.*'],
                        ['label' => 'Export / Import', 'route' => 'admin.site-transfers.exports.index', 'active' => 'admin.site-transfers.*'],
                    ],
                ];
            }
        @endphp

        <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>

        <div class="wb-dashboard-shell">
            <aside class="wb-sidebar" id="admin-sidebar">
                <a href="{{ route('admin.dashboard') }}" class="wb-sidebar-brand">
                    <img src="{{ asset('brand/logo-64.png') }}" alt="{{ config('app.name') }} logo" class="wb-sidebar-brand-logo">
                    <span class="wb-sidebar-brand-copy">
                        <x-brand-copy slogan-class="wb-sidebar-brand-note" />
                    </span>
                </a>

                <nav class="wb-sidebar-nav" aria-label="Admin navigation">
                    <div class="wb-stack wb-stack-1">
                        @foreach ($menuItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="wb-sidebar-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                                @if (request()->routeIs($item['active'])) aria-current="page" @endif
                            >
                                <i class="wb-icon {{ $item['icon'] }} wb-sidebar-icon" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <hr class="wb-divider">

                    @foreach ($sidebarGroups as $group)
                        @php($groupIsActive = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['active'])))
                        <div class="wb-nav-group {{ $groupIsActive ? 'is-open' : '' }}">
                            <button type="button" class="wb-nav-group-toggle {{ $groupIsActive ? 'is-active' : '' }}" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}" data-wb-nav-group-toggle>
                                <i class="wb-icon {{ $group['icon'] }} wb-nav-group-icon" aria-hidden="true"></i>
                                <span class="wb-nav-group-label">{{ $group['label'] }}</span>
                                <span class="wb-nav-group-arrow" aria-hidden="true"></span>
                            </button>

                            <div class="wb-nav-group-items">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="wb-nav-group-item {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                                        @if (request()->routeIs($item['active'])) aria-current="page" @endif
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="wb-sidebar-footer">
                    <div class="wb-text-sm wb-text-muted">{{ config('app.name') }} v{{ $installedVersionDisplay ?? config('app.version') }}</div>
                </div>
            </aside>

            <div class="wb-dashboard-body">
                <header class="wb-navbar">
                    <button
                        class="wb-navbar-toggle"
                        type="button"
                        data-wb-toggle="sidebar"
                        data-wb-target="#admin-sidebar"
                        aria-expanded="false"
                        aria-controls="admin-sidebar"
                        aria-label="Toggle navigation"
                    >
                        <span></span><span></span><span></span>
                    </button>

                    <div class="wb-navbar-identity">
                        <span class="wb-navbar-brand">
                            <span>{{ config('app.name') }}</span>
                        </span>
                        <span class="wb-navbar-context">{{ $heading ?? config('app.slogan') }}</span>
                    </div>

                    <span class="wb-navbar-spacer"></span>

                    <div class="wb-navbar-end">
                        <div class="wb-navbar-iconbar">
                            <button type="button" class="wb-navbar-icon-trigger" data-wb-mode-cycle aria-label="Color mode" title="Color mode">
                                <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown wb-dropdown-end">
                                <button class="wb-navbar-icon-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-theme-menu" aria-expanded="false" aria-label="Theme settings" title="Theme settings">
                                    <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
                                </button>

                                <div class="wb-dropdown-menu" id="admin-theme-menu">
                                    <div class="wb-dropdown-label">Presets</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="modern">Modern</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="minimal">Minimal</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="editorial">Editorial</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="playful">Playful</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="corporate">Corporate</button>
                                    <hr class="wb-dropdown-divider">
                                    <div class="wb-dropdown-label">Accent</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="ocean">Ocean</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="forest">Forest</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="sunset">Sunset</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="royal">Royal</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="mint">Mint</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="amber">Amber</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="rose">Rose</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="slate-fire">Slate Fire</button>
                                </div>
                            </div>

                        </div>

                        <div class="wb-dropdown wb-dropdown-end">
                            <button class="wb-navbar-avatar-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-user-menu" aria-expanded="false" aria-label="User menu" title="{{ $user?->name }}">
                                <span class="wb-navbar-avatar" aria-hidden="true">{{ $userInitials }}</span>
                                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown-menu" id="admin-user-menu">
                                <a href="{{ route('profile.edit') }}" class="wb-dropdown-item">Profile</a>
                                <hr class="wb-dropdown-divider">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="wb-dropdown-item wb-dropdown-item-danger">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="wb-dashboard-main">
                    <div class="wb-stack wb-stack-6">
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>

        <div id="wb-overlay-root" class="wb-overlay-root">
            @stack('overlays')
        </div>

        <script src="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.js"></script>
        @stack('scripts')
        <script>
            function resetAdminTransientUiState() {
                if (document.body) {
                    document.body.classList.remove('wb-overlay-lock', 'overflow-y-hidden');
                    document.body.style.overflow = '';
                }

                var sidebar = document.getElementById('admin-sidebar');

                if (sidebar) {
                    sidebar.classList.remove('is-open');
                }

                document.querySelectorAll('[data-wb-sidebar-backdrop]').forEach(function (backdrop) {
                    backdrop.classList.remove('is-open');
                });

                document.querySelectorAll('[data-wb-toggle="sidebar"]').forEach(function (trigger) {
                    trigger.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                });

                var overlayRoot = document.getElementById('wb-overlay-root');

                if (!overlayRoot) {
                    return;
                }

                var dialogBackdrop = overlayRoot.querySelector('.wb-overlay-layer--dialog > .wb-overlay-backdrop');

                if (dialogBackdrop) {
                    dialogBackdrop.hidden = true;
                    dialogBackdrop.className = 'wb-overlay-backdrop';
                    delete dialogBackdrop.dataset.wbOverlayOwner;
                }
            }

            function bindAdminTransientUiReset() {
                resetAdminTransientUiState();

                window.addEventListener('pageshow', function () {
                    resetAdminTransientUiState();
                });
            }

            function redirectToLoginFromAdmin() {
                resetAdminTransientUiState();
                window.location.assign('{{ route('login') }}');
            }

            bindAdminTransientUiReset();

            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-password-toggle]');

                if (! button) {
                    return;
                }

                var wrapper = button.closest('[data-password-field]');
                var input = wrapper ? wrapper.querySelector('[data-password-input]') : null;

                if (! input) {
                    return;
                }

                var isHidden = input.type === 'password';
                var label = button.querySelector('[data-password-toggle-label]');
                var icon = button.querySelector('[data-password-toggle-icon]');

                input.type = isHidden ? 'text' : 'password';
                button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

                if (label) {
                    label.textContent = isHidden ? 'Hide password' : 'Show password';
                }

                if (icon) {
                    icon.classList.remove('wb-icon-eye', 'wb-icon-eye-off');
                    icon.classList.add(isHidden ? 'wb-icon-eye-off' : 'wb-icon-eye');
                }
            });

            document.querySelectorAll('[data-wb-nav-group-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var group = toggle.closest('.wb-nav-group');

                    if (!group) {
                        return;
                    }

                    var isOpen = group.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            });

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function parseAssetPayload(value) {
                try {
                    return JSON.parse(value || '{}');
                } catch (error) {
                    return {};
                }
            }

            function updatePickerSummary(root) {
                var summary = root.querySelector('[data-wb-picker-summary]');
                var clearButton = root.querySelector('[data-wb-picker-clear]');
                var mode = root.getAttribute('data-wb-picker-mode');
                var inputs = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]'));

                if (!summary) {
                    return;
                }

                if (mode === 'multiple') {
                    var previews = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-preview]'));
                    var labels = previews.map(function (preview) {
                        var title = preview.querySelector('strong');
                        return title ? title.textContent.trim() : '';
                    }).filter(Boolean);

                    if (inputs.length === 0) {
                        summary.innerHTML = '<strong>No assets selected</strong><div class="wb-text-sm wb-text-muted">Choose internal assets from the shared media library.</div>';
                    } else {
                        summary.innerHTML = '<strong>' + inputs.length + ' assets selected</strong><div class="wb-text-sm wb-text-muted">' + escapeHtml(labels.join(', ')) + '</div>';
                    }

                    if (clearButton) {
                        clearButton.disabled = inputs.length === 0;
                    }

                    return;
                }

                var input = root.querySelector('[data-wb-picker-selected-input]');
                var previewCard = root.querySelector('[data-wb-picker-preview]');

                if (!input || !input.value || !previewCard) {
                    summary.innerHTML = '<strong>No asset selected</strong><div class="wb-text-sm wb-text-muted">Choose an internal asset from the shared media library.</div>';

                    if (clearButton) {
                        clearButton.disabled = true;
                    }

                    return;
                }

                var titleElement = previewCard.querySelector('strong');
                var metaElement = previewCard.querySelector('[data-wb-picker-preview-meta]');
                var image = previewCard.querySelector('img');
                var html = '';

                if (image) {
                    html += '<img src="' + escapeHtml(image.getAttribute('src')) + '" alt="' + escapeHtml(image.getAttribute('alt')) + '" width="96" height="64">';
                }

                html += '<strong>' + escapeHtml(titleElement ? titleElement.textContent.trim() : 'Selected asset') + '</strong>';

                if (metaElement) {
                    html += '<div class="wb-text-sm wb-text-muted">' + escapeHtml(metaElement.textContent.trim()) + '</div>';
                }

                summary.innerHTML = html;

                if (clearButton) {
                    clearButton.disabled = false;
                }
            }

            function buildSinglePreview(asset) {
                var preview = document.createElement('div');
                preview.className = 'wb-card';
                preview.setAttribute('data-wb-picker-preview', '');
                preview.setAttribute('data-wb-picker-preview-id', String(asset.id || ''));

                var html = '<div class="wb-card-body wb-stack wb-gap-2">';

                if (asset.previewable && asset.url) {
                    html += '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '" width="120" height="84">';
                }

                html += '<strong>' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '</strong>';
                html += '<div class="wb-text-sm wb-text-muted" data-wb-picker-preview-meta>' + escapeHtml([asset.kind, asset.original_name].filter(Boolean).join(' | ')) + '</div>';
                html += '</div>';
                preview.innerHTML = html;

                return preview;
            }

            function buildMultiPreview(asset) {
                var preview = document.createElement('div');
                preview.className = 'wb-card';
                preview.setAttribute('data-wb-picker-preview', '');
                preview.setAttribute('data-wb-picker-preview-id', String(asset.id || ''));

                var html = '<div class="wb-card-body wb-stack wb-gap-2">';

                if (asset.previewable && asset.url) {
                    html += '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '" width="120" height="84">';
                }

                html += '<strong>' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '</strong>';
                html += '<button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-remove-preview data-asset-id="' + escapeHtml(asset.id) + '">Remove</button>';
                html += '</div>';
                preview.innerHTML = html;

                return preview;
            }

            function setSinglePickerSelection(root, asset) {
                var input = root.querySelector('[data-wb-picker-selected-input]');
                var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

                if (!input || !previewGrid) {
                    return;
                }

                input.value = asset && asset.id ? asset.id : '';
                previewGrid.innerHTML = '';

                if (asset && asset.id) {
                    previewGrid.appendChild(buildSinglePreview(asset));
                }

                updatePickerSummary(root);
            }

            function clearSinglePickerSelection(root) {
                var input = root.querySelector('[data-wb-picker-selected-input]');
                var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

                if (input) {
                    input.value = '';
                }

                if (previewGrid) {
                    previewGrid.innerHTML = '';
                }

                updatePickerSummary(root);
            }

            function appendMultiSelection(root, asset) {
                var selectedList = root.querySelector('[data-wb-picker-selected-list]');
                var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');
                var existing = selectedList ? selectedList.querySelector('[value="' + String(asset.id) + '"]') : null;
                var fieldName = root.getAttribute('data-wb-picker-field-name') || 'gallery_asset_ids';

                if (!selectedList || !previewGrid || existing) {
                    return;
                }

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName + '[]';
                input.value = String(asset.id);
                input.setAttribute('data-wb-picker-selected-input', '');
                selectedList.appendChild(input);
                previewGrid.appendChild(buildMultiPreview(asset));
                updatePickerSummary(root);
            }

            function removeMultiSelection(root, assetId) {
                root.querySelectorAll('[data-wb-picker-selected-input]').forEach(function (input) {
                    if (input.value === String(assetId)) {
                        input.remove();
                    }
                });

                root.querySelectorAll('[data-wb-picker-preview]').forEach(function (preview) {
                    if (preview.getAttribute('data-wb-picker-preview-id') === String(assetId)) {
                        preview.remove();
                    }
                });

                root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                    var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));

                    if (String(asset.id) === String(assetId)) {
                        button.textContent = 'Select';
                    }
                });

                updatePickerSummary(root);
            }

            function filterPickerAssets(root) {
                var searchValue = String((root.querySelector('[data-wb-picker-search]') || {}).value || '').toLowerCase().trim();
                var folderValue = String((root.querySelector('[data-wb-picker-folder]') || {}).value || '');
                var kindValue = String((root.querySelector('[data-wb-picker-kind]') || {}).value || '');
                var visibleCount = 0;

                root.querySelectorAll('[data-wb-asset-card]').forEach(function (card) {
                    var text = String(card.getAttribute('data-wb-asset-search') || '');
                    var folderId = String(card.getAttribute('data-wb-asset-folder-id') || '');
                    var kind = String(card.getAttribute('data-wb-asset-kind') || '');
                    var matchesSearch = searchValue === '' || text.indexOf(searchValue) !== -1;
                    var matchesFolder = folderValue === '' || folderId === folderValue;
                    var matchesKind = kindValue === '' || kind === kindValue;
                    var visible = matchesSearch && matchesFolder && matchesKind;

                    card.hidden = !visible;

                    if (visible) {
                        visibleCount += 1;
                    }
                });

                var emptyState = root.querySelector('[data-wb-picker-empty]');

                if (emptyState) {
                    emptyState.hidden = visibleCount !== 0;
                }
            }

            function closePickerPanel(root) {
                var panel = root.querySelector('[data-wb-picker-panel]');

                if (panel) {
                    panel.hidden = true;
                }
            }

            function openPickerPanel(root) {
                var panel = root.querySelector('[data-wb-picker-panel]');

                if (panel) {
                    panel.hidden = false;
                }

                filterPickerAssets(root);
            }

            function resetPickerSelection(root) {
                var mode = root.getAttribute('data-wb-picker-mode');

                if (mode === 'multiple') {
                    root.querySelectorAll('[data-wb-picker-selected-input]').forEach(function (input) {
                        input.remove();
                    });

                    var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

                    if (previewGrid) {
                        previewGrid.innerHTML = '';
                    }

                    root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                        button.textContent = 'Select';
                    });
                    updatePickerSummary(root);
                    return;
                }

                clearSinglePickerSelection(root);
            }

            document.querySelectorAll('[data-wb-asset-picker-panel]').forEach(function (root) {
                updatePickerSummary(root);
                filterPickerAssets(root);

                if (root.getAttribute('data-wb-picker-mode') === 'multiple') {
                    var selectedIds = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]')).map(function (input) {
                        return input.value;
                    });

                    root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                        var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));
                        button.textContent = selectedIds.indexOf(String(asset.id)) !== -1 ? 'Selected' : 'Select';
                    });
                }
            });

            document.addEventListener('click', function (event) {
                var openButton = event.target.closest('[data-wb-picker-open]');
                var closeButton = event.target.closest('[data-wb-picker-close]');
                var clearButton = event.target.closest('[data-wb-picker-clear]');
                var selectButton = event.target.closest('[data-wb-asset-select]');
                var toggleButton = event.target.closest('[data-wb-asset-toggle]');
                var removePreviewButton = event.target.closest('[data-wb-picker-remove-preview]');
                var applyButton = event.target.closest('[data-wb-picker-apply]');

                if (openButton) {
                    openPickerPanel(openButton.closest('[data-wb-asset-picker-panel]'));
                    return;
                }

                if (closeButton) {
                    closePickerPanel(closeButton.closest('[data-wb-asset-picker-panel]'));
                    return;
                }

                if (clearButton) {
                    resetPickerSelection(clearButton.closest('[data-wb-asset-picker-panel]'));
                    return;
                }

                if (selectButton) {
                    var pickerRoot = selectButton.closest('[data-wb-asset-picker-panel]');
                    var asset = parseAssetPayload(selectButton.getAttribute('data-wb-asset'));

                    setSinglePickerSelection(pickerRoot, asset);
                    closePickerPanel(pickerRoot);
                    return;
                }

                if (toggleButton) {
                    var multiRoot = toggleButton.closest('[data-wb-asset-picker-panel]');
                    var multiAsset = parseAssetPayload(toggleButton.getAttribute('data-wb-asset'));
                    var isSelected = toggleButton.textContent.trim() === 'Selected';

                    if (isSelected) {
                        removeMultiSelection(multiRoot, multiAsset.id);
                        toggleButton.textContent = 'Select';
                    } else {
                        appendMultiSelection(multiRoot, multiAsset);
                        toggleButton.textContent = 'Selected';
                    }

                    return;
                }

                if (removePreviewButton) {
                    removeMultiSelection(removePreviewButton.closest('[data-wb-asset-picker-panel]'), removePreviewButton.getAttribute('data-asset-id'));
                    return;
                }

                if (applyButton) {
                    closePickerPanel(applyButton.closest('[data-wb-asset-picker-panel]'));
                }
            });

            document.addEventListener('input', function (event) {
                var pickerSearch = event.target.closest('[data-wb-picker-search]');

                if (pickerSearch) {
                    filterPickerAssets(pickerSearch.closest('[data-wb-asset-picker-panel]'));
                }
            });

            document.addEventListener('change', function (event) {
                var pickerFolder = event.target.closest('[data-wb-picker-folder]');
                var pickerKind = event.target.closest('[data-wb-picker-kind]');

                if (pickerFolder || pickerKind) {
                    filterPickerAssets(event.target.closest('[data-wb-asset-picker-panel]'));
                    return;
                }

                var uploadInput = event.target.closest('[data-wb-picker-upload-input]');

                if (!uploadInput) {
                    return;
                }

                var pickerRoot = uploadInput.closest('[data-wb-asset-picker-panel]');
                var status = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-status]') : null;

                if (status) {
                    status.textContent = uploadInput.files && uploadInput.files[0]
                        ? uploadInput.files[0].name + ' ready to upload.'
                        : 'Select a file to upload it to the shared media library.';
                }
            });

            document.addEventListener('click', function (event) {
                var uploadButton = event.target.closest('[data-wb-picker-upload-submit]');

                if (!uploadButton) {
                    return;
                }

                var pickerRoot = uploadButton.closest('[data-wb-asset-picker-panel]');
                var fileInput = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-input]') : null;
                var titleInput = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-title]') : null;
                var status = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-status]') : null;

                if (!pickerRoot || !fileInput || !fileInput.files || !fileInput.files[0]) {
                    if (status) {
                        status.textContent = 'Choose a file before uploading.';
                    }
                    return;
                }

                var formData = new FormData();
                formData.append('file', fileInput.files[0]);

                if (titleInput && titleInput.value.trim() !== '') {
                    formData.append('title', titleInput.value.trim());
                }

                var token = document.querySelector('meta[name="csrf-token"]');

                fetch('/admin/media', {
                    method: 'POST',
                    headers: token ? { 'X-CSRF-TOKEN': token.getAttribute('content') } : {},
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (response.redirected) {
                            redirectToLoginFromAdmin();
                            return;
                        }

                        if (response.status === 401 || response.status === 403 || response.status === 419) {
                            redirectToLoginFromAdmin();
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Upload failed');
                        }

                        window.location.reload();
                    })
                    .catch(function () {
                        if (status) {
                            status.textContent = 'Upload failed. Refresh and try again.';
                        }
                    });
            });

            document.addEventListener('click', function (event) {
                var addButton = event.target.closest('[data-wb-inline-add]');
                var moveButton = event.target.closest('[data-wb-inline-move]');
                var removeButton = event.target.closest('[data-wb-inline-remove]');
                var toggleButton = event.target.closest('[data-wb-inline-toggle]');

                if (addButton) {
                    var builder = addButton.closest('[data-wb-inline-builder]');

                    if (!builder) {
                        return;
                    }

                    var payload = {};

                    try {
                        payload = JSON.parse(addButton.getAttribute('data-block-type') || '{}');
                    } catch (error) {
                        payload = {};
                    }

                    addInlineBlock(builder, payload);
                    return;
                }

                if (moveButton) {
                    var block = moveButton.closest('[data-wb-inline-block]');

                    if (!block) {
                        return;
                    }

                    var direction = moveButton.getAttribute('data-wb-inline-move');
                    var sibling = direction === 'up' ? block.previousElementSibling : block.nextElementSibling;

                    if (!sibling || !sibling.matches('[data-wb-inline-block]')) {
                        return;
                    }

                    if (direction === 'up') {
                        block.parentNode.insertBefore(block, sibling);
                    } else {
                        block.parentNode.insertBefore(sibling, block);
                    }

                    syncInlineBuilder(block.closest('[data-wb-inline-builder]'));
                    return;
                }

                if (removeButton) {
                    var inlineBlock = removeButton.closest('[data-wb-inline-block]');

                    if (!inlineBlock) {
                        return;
                    }

                    var deleteInput = inlineBlock.querySelector('[data-wb-inline-delete]');
                    var builderRoot = inlineBlock.closest('[data-wb-inline-builder]');

                    if (deleteInput && deleteInput.closest('form') && deleteInput.value !== undefined) {
                        deleteInput.value = '1';
                    }

                    inlineBlock.remove();
                    syncInlineBuilder(builderRoot);
                    return;
                }

                if (toggleButton) {
                    var body = toggleButton.closest('[data-wb-inline-block]').querySelector('[data-wb-inline-body]');

                    if (!body) {
                        return;
                    }

                    body.hidden = !body.hidden;
                    return;
                }
            });

            function addInlineBlock(builder, payload) {
                var template = builder.querySelector('[data-wb-inline-template]');
                var list = builder.querySelector('[data-wb-inline-list]');
                var emptyState = list.querySelector('[data-wb-inline-empty]');
                var defaultSlotType = builder.querySelector('[data-wb-default-slot-type]');
                var nextIndexInput = builder.querySelector('[data-wb-inline-next-index]');

                if (!template || !list) {
                    return;
                }

                if (emptyState) {
                    emptyState.remove();
                }

                var index = nextIndexInput ? Number(nextIndexInput.value || '0') : list.querySelectorAll('[data-wb-inline-block]').length;
                var wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.trim();
                var block = wrapper.firstElementChild;
                var defaultSlotTypeId = defaultSlotType ? defaultSlotType.value : '';

                if (nextIndexInput) {
                    nextIndexInput.value = String(index + 1);
                }

                block.querySelector('[data-wb-inline-label]').textContent = payload.name || 'New Block';
                block.querySelector('[data-wb-inline-body]').innerHTML = buildInlineFields(index, payload, defaultSlotTypeId);
                list.appendChild(block);
                syncInlineBuilder(builder);
            }

            function buildInlineFields(index, payload, defaultSlotTypeId) {
                var typeId = payload.id || '';
                var title = payload.name || 'Block';
                var contentFields = '';

                switch (payload.slug) {
                    case 'heading':
                        contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Heading Text</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Heading Level</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="h1">H1</option><option value="h2">H2</option><option value="h3">H3</option><option value="h4">H4</option><option value="h5">H5</option><option value="h6">H6</option></select></div></div>';
                        break;
                    case 'section':
                        contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Section Title</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Section Variant</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="default">default</option><option value="muted">muted</option><option value="accent">accent</option><option value="wide">wide</option></select></div></div><div class="wb-stack wb-gap-1"><label>Section Intro</label><textarea class="wb-textarea" rows="5" name="blocks[' + index + '][content]"></textarea></div>';
                        break;
                    case 'callout':
                        contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>CTA Title</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Tone</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="info">info</option><option value="success">success</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div><div class="wb-stack wb-gap-1"><label>CTA Content</label><textarea class="wb-textarea" rows="5" name="blocks[' + index + '][content]"></textarea></div>';
                        break;
                    case 'rich-text':
                    case 'text':
                    case 'html':
                        contentFields = '<div class="wb-stack wb-gap-1"><label>Content</label><textarea class="wb-textarea" rows="8" name="blocks[' + index + '][content]"></textarea></div>';
                        break;
                    case 'button':
                        contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Button Label</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>URL</label><input class="wb-input" type="text" name="blocks[' + index + '][url]"></div></div>';
                        break;
                    case 'download':
                        contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Download Label</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Document Asset ID</label><input class="wb-input" type="number" min="1" name="blocks[' + index + '][asset_id]"></div></div>';
                        break;
                    default:
                        contentFields = '<div class="wb-stack wb-gap-1"><label>Content</label><textarea class="wb-textarea" rows="6" name="blocks[' + index + '][content]"></textarea></div>';
                        break;
                }

                return '' +
                    '<input type="hidden" name="blocks[' + index + '][id]" value="">' +
                    '<input type="hidden" name="blocks[' + index + '][_delete]" value="0" data-wb-inline-delete>' +
                    '<input type="hidden" name="blocks[' + index + '][sort_order]" value="' + index + '" data-wb-inline-sort>' +
                    '<input type="hidden" name="blocks[' + index + '][block_type_id]" value="' + typeId + '">' +
                    '<input type="hidden" name="blocks[' + index + '][is_system]" value="' + (payload.is_system ? '1' : '0') + '">' +
                    '<div class="wb-grid wb-grid-4"><div class="wb-stack wb-gap-1"><label>Block Type</label><div class="wb-card wb-card-muted"><div class="wb-card-body"><strong>' + title + '</strong><div>' + (payload.description || 'Inline block editor') + '</div></div></div></div><div class="wb-stack wb-gap-1"><label>Slot Type ID</label><input class="wb-input" type="number" min="1" name="blocks[' + index + '][slot_type_id]" value="' + defaultSlotTypeId + '"></div><div class="wb-stack wb-gap-1"><label>Status</label><select class="wb-select" name="blocks[' + index + '][status]"><option value="published">published</option><option value="draft">draft</option></select></div><div class="wb-stack wb-gap-1"><label>Kind</label><div class="wb-card wb-card-muted"><div class="wb-card-body"><strong>' + (payload.is_system ? 'System Block' : 'Content Block') + '</strong></div></div></div></div>' +
                    '<div class="wb-card wb-card-accent"><div class="wb-card-body">' + contentFields + '</div></div>';
            }

            function syncInlineBuilder(builder) {
                if (!builder) {
                    return;
                }

                var list = builder.querySelector('[data-wb-inline-list]');

                if (!list) {
                    return;
                }

                var blocks = Array.prototype.slice.call(list.querySelectorAll('[data-wb-inline-block]'));

                blocks.forEach(function (block, index) {
                    var sortInput = block.querySelector('[data-wb-inline-sort]');
                    var up = block.querySelector('[data-wb-inline-move="up"]');
                    var down = block.querySelector('[data-wb-inline-move="down"]');

                    if (sortInput) {
                        sortInput.value = index;
                    }

                    if (up) {
                        up.disabled = index === 0;
                    }

                    if (down) {
                        down.disabled = index === blocks.length - 1;
                    }
                });

                if (blocks.length === 0 && !list.querySelector('[data-wb-inline-empty]')) {
                    var empty = document.createElement('div');
                    empty.className = 'wb-empty';
                    empty.setAttribute('data-wb-inline-empty', '');
                    empty.innerHTML = '<div class="wb-empty-title">No blocks yet</div><div class="wb-empty-text">Add the first block to start composing this page inline.</div>';
                    list.appendChild(empty);
                }
            }

            document.querySelectorAll('[data-wb-inline-builder]').forEach(function (builder) {
                syncInlineBuilder(builder);
            });

            document.addEventListener('wb:tabs:change', function (event) {
                var container = event.target;

                if (!container || !container.matches('[data-wb-slot-block-tabs]')) {
                    return;
                }

                var hiddenInput = container.querySelector('[data-wb-slot-block-tab-input]');

                if (hiddenInput && event.detail && event.detail.tabId) {
                    hiddenInput.value = event.detail.tabId === 'slot-block-info-panel' ? 'block-info' : 'block-fields';
                }
            });

            function syncColumnItems(editor) {
                if (!editor) {
                    return;
                }

                var list = editor.querySelector('[data-wb-column-item-list]');

                if (!list) {
                    return;
                }

                var rows = Array.prototype.slice.call(list.querySelectorAll('[data-wb-column-item-row]'));

                rows.forEach(function (row, index) {
                    var sortInput = row.querySelector('[data-wb-column-item-sort]');
                    var up = row.querySelector('[data-wb-column-item-move="up"]');
                    var down = row.querySelector('[data-wb-column-item-move="down"]');

                    if (sortInput) {
                        sortInput.value = index;
                    }

                    if (up) {
                        up.disabled = index === 0;
                    }

                    if (down) {
                        down.disabled = index === rows.length - 1;
                    }
                });

                var emptyState = list.querySelector('[data-wb-column-item-empty]');

                if (rows.length === 0 && !emptyState) {
                    var empty = document.createElement('div');
                    empty.className = 'wb-empty';
                    empty.setAttribute('data-wb-column-item-empty', '');
                    empty.innerHTML = '<div class="wb-empty-title">No column items yet</div><div class="wb-empty-text">Add the first item to build the visible columns for this section.</div>';
                    list.appendChild(empty);
                }
            }

            function updateColumnItemLabel(row) {
                if (!row) {
                    return;
                }

                var label = row.querySelector('[data-wb-column-item-label]');
                var titleInput = row.querySelector('[data-wb-column-item-title]');

                if (label && titleInput) {
                    label.textContent = titleInput.value.trim() || 'New Column Item';
                }
            }

            function addColumnItem(editor) {
                var template = editor.querySelector('[data-wb-column-item-template]');
                var list = editor.querySelector('[data-wb-column-item-list]');
                var nextIndexInput = editor.querySelector('[data-wb-column-item-next-index]');

                if (!template || !list || !nextIndexInput) {
                    return;
                }

                var index = Number(nextIndexInput.value || '0');
                var wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
                var row = wrapper.firstElementChild;
                var emptyState = list.querySelector('[data-wb-column-item-empty]');

                if (emptyState) {
                    emptyState.remove();
                }

                list.appendChild(row);
                nextIndexInput.value = String(index + 1);
                syncColumnItems(editor);
            }

            document.querySelectorAll('[data-wb-column-items-editor]').forEach(function (editor) {
                syncColumnItems(editor);
            });

            document.addEventListener('click', function (event) {
                var addColumnItemButton = event.target.closest('[data-wb-column-item-add]');
                var moveColumnItemButton = event.target.closest('[data-wb-column-item-move]');
                var toggleColumnItemButton = event.target.closest('[data-wb-column-item-toggle]');
                var removeColumnItemButton = event.target.closest('[data-wb-column-item-remove]');
                var addSlotButton = event.target.closest('[data-wb-slot-add]');
                var moveSlotButton = event.target.closest('[data-wb-slot-move]');
                var removeSlotButton = event.target.closest('[data-wb-slot-remove]');

                if (addColumnItemButton) {
                    addColumnItem(addColumnItemButton.closest('[data-wb-column-items-editor]'));
                    return;
                }

                if (moveColumnItemButton) {
                    var row = moveColumnItemButton.closest('[data-wb-column-item-row]');
                    var editor = moveColumnItemButton.closest('[data-wb-column-items-editor]');

                    if (!row || !editor) {
                        return;
                    }

                    var direction = moveColumnItemButton.getAttribute('data-wb-column-item-move');
                    var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;

                    while (sibling && !sibling.matches('[data-wb-column-item-row]')) {
                        sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
                    }

                    if (!sibling) {
                        return;
                    }

                    if (direction === 'up') {
                        row.parentNode.insertBefore(row, sibling);
                    } else {
                        row.parentNode.insertBefore(sibling, row);
                    }

                    syncColumnItems(editor);
                    return;
                }

                if (toggleColumnItemButton) {
                    var body = toggleColumnItemButton.closest('[data-wb-column-item-row]').querySelector('[data-wb-column-item-body]');

                    if (body) {
                        body.hidden = !body.hidden;
                    }

                    return;
                }

                if (removeColumnItemButton) {
                    var removableRow = removeColumnItemButton.closest('[data-wb-column-item-row]');
                    var removableEditor = removeColumnItemButton.closest('[data-wb-column-items-editor]');

                    if (!removableRow || !removableEditor) {
                        return;
                    }

                    var deleteInput = removableRow.querySelector('[data-wb-column-item-delete]');
                    var idInput = removableRow.querySelector('input[name$="[id]"]');

                    if (deleteInput && idInput && idInput.value) {
                        deleteInput.value = '1';
                        removableRow.hidden = true;
                    } else {
                        removableRow.remove();
                    }

                    syncColumnItems(removableEditor);
                    return;
                }

                if (addSlotButton) {
                    var slotBuilder = addSlotButton.closest('[data-wb-slot-builder]');

                    if (!slotBuilder) {
                        slotBuilder = document.querySelector('[data-wb-slot-builder]');
                    }

                    if (!slotBuilder) {
                        return;
                    }

                    var payload = {};

                    try {
                        payload = JSON.parse(addSlotButton.getAttribute('data-slot-type') || '{}');
                    } catch (error) {
                        payload = {};
                    }

                    addSlot(slotBuilder, payload);
                    return;
                }

                if (moveSlotButton) {
                    var slotItem = moveSlotButton.closest('[data-wb-slot-item]');

                    if (!slotItem) {
                        return;
                    }

                    var direction = moveSlotButton.getAttribute('data-wb-slot-move');
                    var sibling = direction === 'up' ? slotItem.previousElementSibling : slotItem.nextElementSibling;

                    while (sibling && !sibling.matches('[data-wb-slot-item]')) {
                        sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
                    }

                    if (!sibling) {
                        return;
                    }

                    if (direction === 'up') {
                        slotItem.parentNode.insertBefore(slotItem, sibling);
                    } else {
                        slotItem.parentNode.insertBefore(sibling, slotItem);
                    }

                    syncSlotBuilder(slotItem.closest('[data-wb-slot-builder]'));
                    return;
                }

                if (removeSlotButton) {
                    var removableSlot = removeSlotButton.closest('[data-wb-slot-item]');

                    if (!removableSlot) {
                        return;
                    }

                    removableSlot.remove();
                    syncSlotBuilder(removeSlotButton.closest('[data-wb-slot-builder]'));
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target.matches('[data-wb-column-item-title]')) {
                    updateColumnItemLabel(event.target.closest('[data-wb-column-item-row]'));
                }
            });

            function currentExpandedSlotBlocks() {
                return Array.prototype.slice.call(document.querySelectorAll('[data-wb-slot-block-toggle][aria-expanded="true"]'))
                    .map(function (button) {
                        var controls = button.getAttribute('aria-controls') || '';
                        return controls.replace('slot-block-children-', '');
                    })
                    .filter(function (value) {
                        return value !== '';
                    });
            }

            function syncSlotBlockExpandedState() {
                var expanded = currentExpandedSlotBlocks().join(',');

                document.querySelectorAll('[data-wb-slot-block-expanded-input]').forEach(function (input) {
                    var current = input.value || '';
                    var forced = input.closest('tbody[id^="slot-block-children-"]') ? input.closest('tbody[id^="slot-block-children-"]').id.replace('slot-block-children-', '') : null;
                    var values = expanded === '' ? [] : expanded.split(',').filter(Boolean);

                    if (forced && values.indexOf(forced) === -1) {
                        values.push(forced);
                    }

                    input.value = values.join(',');
                });

                document.querySelectorAll('[data-wb-slot-block-link]').forEach(function (link) {
                    var baseUrl = link.getAttribute('data-base-url');

                    if (!baseUrl) {
                        return;
                    }

                    var url = new URL(baseUrl, window.location.origin);

                    if (expanded !== '') {
                        url.searchParams.set('expanded', expanded);
                    }

                    link.href = url.toString();
                });

                var currentUrl = new URL(window.location.href);

                if (expanded !== '') {
                    currentUrl.searchParams.set('expanded', expanded);
                } else {
                    currentUrl.searchParams.delete('expanded');
                }

                window.history.replaceState({}, '', currentUrl.toString());
            }

            document.addEventListener('click', function (event) {
                var slotBlockToggle = event.target.closest('[data-wb-slot-block-toggle]');

                if (!slotBlockToggle) {
                    return;
                }

                var target = slotBlockToggle.getAttribute('data-wb-target');
                var group = target ? document.getElementById(target) : null;
                var expanded = slotBlockToggle.getAttribute('aria-expanded') === 'true';

                if (!group) {
                    return;
                }

                slotBlockToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                slotBlockToggle.setAttribute('aria-label', expanded ? 'Expand child blocks' : 'Collapse child blocks');
                slotBlockToggle.setAttribute('title', expanded ? 'Expand child blocks' : 'Collapse child blocks');
                group.hidden = expanded;
                syncSlotBlockExpandedState();
            });

            syncSlotBlockExpandedState();

            function addSlot(builder, payload) {
                var list = builder.querySelector('[data-wb-slot-list]');
                var tableWrap = builder.querySelector('[data-wb-slot-table-wrap]');
                var emptyState = builder.querySelector('[data-wb-slot-empty]');

                if (!list || !payload.id || !payload.slug) {
                    return;
                }

                var existingSlotTypeIds = Array.prototype.slice.call(list.querySelectorAll('[data-wb-slot-type-id]')).map(function (input) {
                    return input.value;
                });

                if (existingSlotTypeIds.indexOf(String(payload.id)) !== -1) {
                    return;
                }

                if (emptyState) {
                    emptyState.remove();
                }

                if (tableWrap) {
                    tableWrap.hidden = false;
                }

                var wrapper = document.createElement('tbody');
                wrapper.innerHTML = buildSlotItemHtml(payload);
                list.appendChild(wrapper.firstElementChild);
                syncSlotBuilder(builder);
            }

            function buildSlotItemHtml(payload) {
                return '' +
                    '<tr data-wb-slot-item>' +
                        '<td>' +
                            '<strong>' + escapeHtml(payload.name || 'Slot') + '</strong>' +
                            '<input type="hidden" name="" value="" data-wb-slot-id>' +
                            '<input type="hidden" name="" value="' + escapeHtml(String(payload.id)) + '" data-wb-slot-type-id>' +
                            '<input type="hidden" name="" value="0" data-wb-slot-sort>' +
                            '<input type="hidden" name="" value="0" data-wb-slot-delete>' +
                            '<input type="hidden" value="' + escapeHtml(payload.slug) + '" data-wb-slot-slug>' +
                            '<input type="hidden" value="' + escapeHtml(payload.name || 'Slot') + '" data-wb-slot-name>' +
                        '</td>' +
                        '<td>' +
                            '<div class="wb-action-group">' +
                                '<span class="wb-action-btn" aria-disabled="true" title="Save the page before editing slot blocks"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></span>' +
                                '<button type="button" class="wb-action-btn" data-wb-slot-move="up" title="Move slot up" aria-label="Move slot up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>' +
                                '<button type="button" class="wb-action-btn" data-wb-slot-move="down" title="Move slot down" aria-label="Move slot down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>' +
                                '<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-slot-remove title="Delete slot" aria-label="Delete slot"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';
            }

            function syncSlotBuilder(builder) {
                if (!builder) {
                    return;
                }

                var list = builder.querySelector('[data-wb-slot-list]');
                var tableWrap = builder.querySelector('[data-wb-slot-table-wrap]');
                var menu = builder.querySelector('#page-slot-menu');
                var addButton = builder.querySelector('[data-wb-target="#page-slot-menu"]');

                if (!list) {
                    return;
                }

                var items = Array.prototype.slice.call(list.querySelectorAll('[data-wb-slot-item]'));

                items.forEach(function (item, index) {
                    var inputs = item.querySelectorAll('input');
                    var sortInput = item.querySelector('[data-wb-slot-sort]');
                    var up = item.querySelector('[data-wb-slot-move="up"]');
                    var down = item.querySelector('[data-wb-slot-move="down"]');

                    inputs.forEach(function (input) {
                        if (input.hasAttribute('data-wb-slot-id')) {
                            input.name = 'slots[' + index + '][id]';
                        } else if (input.hasAttribute('data-wb-slot-type-id')) {
                            input.name = 'slots[' + index + '][slot_type_id]';
                        } else if (input.hasAttribute('data-wb-slot-sort')) {
                            input.name = 'slots[' + index + '][sort_order]';
                        } else if (input.hasAttribute('data-wb-slot-delete')) {
                            input.name = 'slots[' + index + '][_delete]';
                        }
                    });

                    if (sortInput) {
                        sortInput.value = index;
                    }

                    if (up) {
                        up.disabled = index === 0;
                    }

                    if (down) {
                        down.disabled = index === items.length - 1;
                    }
                });

                if (tableWrap) {
                    tableWrap.hidden = items.length === 0;
                }

                if (items.length === 0 && !builder.querySelector('[data-wb-slot-empty]')) {
                    var empty = document.createElement('div');
                    empty.className = 'wb-empty';
                    empty.setAttribute('data-wb-slot-empty', '');
                    empty.innerHTML = '<div class="wb-empty-title">No slots yet</div><div class="wb-empty-text">Add Header, Main, Sidebar, or Footer to start defining the page structure.</div>';
                    if (tableWrap) {
                        tableWrap.parentNode.insertBefore(empty, tableWrap);
                    } else {
                        list.appendChild(empty);
                    }
                }

                if (items.length > 0) {
                    var existingEmpty = builder.querySelector('[data-wb-slot-empty]');

                    if (existingEmpty) {
                        existingEmpty.remove();
                    }
                }

                if (menu) {
                    var optionButtons = Array.prototype.slice.call(menu.querySelectorAll('[data-wb-slot-add]'));
                    var selectedIds = items.map(function (item) {
                        var input = item.querySelector('[data-wb-slot-type-id]');

                        return input ? String(input.value) : null;
                    }).filter(Boolean);

                    optionButtons.forEach(function (button) {
                        var payload = {};

                        try {
                            payload = JSON.parse(button.getAttribute('data-slot-type') || '{}');
                        } catch (error) {
                            payload = {};
                        }

                        button.hidden = selectedIds.indexOf(String(payload.id || '')) !== -1;
                    });

                    var hasVisibleOptions = optionButtons.some(function (button) {
                        return !button.hidden;
                    });
                    var emptyLabel = menu.querySelector('[data-wb-slot-menu-empty]');

                    if (!hasVisibleOptions) {
                        if (!emptyLabel) {
                            emptyLabel = document.createElement('div');
                            emptyLabel.className = 'wb-dropdown-label';
                            emptyLabel.setAttribute('data-wb-slot-menu-empty', '');
                            emptyLabel.textContent = 'All slot types already added';
                            menu.appendChild(emptyLabel);
                        }
                    } else if (emptyLabel) {
                        emptyLabel.remove();
                    }

                    if (addButton) {
                        addButton.disabled = !hasVisibleOptions;
                    }
                }
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            document.querySelectorAll('[data-wb-slot-builder]').forEach(function (builder) {
                syncSlotBuilder(builder);
            });
        </script>
    </body>
</html>
