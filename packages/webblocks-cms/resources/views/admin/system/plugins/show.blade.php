@extends('webblocks-cms::layouts.admin', ['title' => $plugin['label'], 'heading' => $plugin['label']])

@php
    $statusClass = match ($plugin['lifecycle_label']) {
        'Enabled' => 'wb-status-active',
        'Incompatible', 'Missing files', 'Error' => 'wb-status-danger',
        default => 'wb-status-pending',
    };
    $healthClass = match ($plugin['health']['status']) {
        'healthy' => 'wb-status-active',
        'warning', 'error', 'incompatible' => 'wb-status-danger',
        default => 'wb-status-pending',
    };
    $uninstallModalId = 'plugin-uninstall-'.$plugin['handle'];
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin['label'],
        'description' => $plugin['description'] ?? 'Review this plugin lifecycle, capabilities, and manual package details.',
    ])

    <p><a href="{{ route('admin.system.plugins.index') }}">Back to Plugins</a></p>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Overview</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $plugin['label'] }}</strong>
                    <div class="wb-text-sm wb-text-muted"><code>{{ $plugin['handle'] }}</code></div>
                </div>
                <div>{{ $plugin['description'] ?? 'No description provided.' }}</div>
                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>Version</strong>
                        <div>{{ $plugin['version'] ?? 'Not declared' }}</div>
                    </div>
                    <div>
                        <strong>Source</strong>
                        <div>{{ $plugin['source'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Lifecycle</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <span class="wb-status {{ $statusClass }}">{{ $plugin['lifecycle_label'] }}</span>
                    @if (! $plugin['compatible'])
                        <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                    @elseif (! $plugin['enabled'])
                        <div class="wb-text-sm wb-text-muted">This plugin is installed but disabled. Enable it to register its routes, commands, menus, settings, health checks, and contributions.</div>
                    @endif
                </div>

                <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                    @if ($plugin['can_enable'])
                        <form method="POST" action="{{ route('admin.system.plugins.enable', $plugin['handle']) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary">
                                <i class="wb-icon wb-icon-play" aria-hidden="true"></i>
                                Enable Plugin
                            </button>
                        </form>
                    @endif

                    @if ($plugin['can_disable'])
                        <form method="POST" action="{{ route('admin.system.plugins.disable', $plugin['handle']) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-secondary">
                                <i class="wb-icon wb-icon-pause" aria-hidden="true"></i>
                                Disable Plugin
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Capabilities</strong>
        </div>

        <div class="wb-card-body">
            <div class="wb-grid wb-grid-4">
                <div>
                    <strong>Routes</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['admin_routes_count'] : 'Available after enabling' }}</div>
                </div>
                <div>
                    <strong>Commands</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['commands_count'] : 'Available after enabling' }}</div>
                </div>
                <div>
                    <strong>Permissions</strong>
                    <div>{{ $plugin['permissions_count'] }}</div>
                </div>
                <div>
                    <strong>Menu Items</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['menu_items_count'] : 'Available after enabling' }}</div>
                </div>
                <div>
                    <strong>Settings</strong>
                    <div>{{ $plugin['settings'] ? ($plugin['enabled'] ? 'Declared' : 'Available after enabling') : 'Not declared' }}</div>
                </div>
                <div>
                    <strong>Migrations</strong>
                    <div>{{ $plugin['migrations_count'] ?? 'Manual/plugin-owned' }}</div>
                </div>
                <div>
                    <strong>Assets</strong>
                    <div>{{ $plugin['public_assets_count'] }}</div>
                </div>
                <div>
                    <strong>Dashboard Cards</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['dashboard_widgets_count'] + $plugin['system_cards_count'] : 'Available after enabling' }}</div>
                </div>
                <div>
                    <strong>Blocks</strong>
                    <div>{{ $plugin['block_types_count'] + $plugin['block_packs_count'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <details class="wb-card">
        <summary class="wb-card-header">
            <strong>Technical Details</strong>
        </summary>

        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div>
                    <strong>Provider</strong>
                    <div>{{ $plugin['provider'] ?? 'Not declared' }}</div>
                </div>
                <div>
                    <strong>Required CMS</strong>
                    <div>{{ $plugin['required_cms_version'] ?? 'Not declared' }}</div>
                </div>
                <div>
                    <strong>Settings Namespace</strong>
                    <div><code>{{ $plugin['settings_namespace'] }}</code></div>
                </div>
                <div>
                    <strong>Database Prefix</strong>
                    <div><code>{{ $plugin['database_prefix'] }}</code></div>
                </div>
                <div>
                    <strong>Route Namespace</strong>
                    <div><code>{{ $plugin['route_name_prefix'] }}</code></div>
                </div>
                <div>
                    <strong>Install Path</strong>
                    <div>{{ $plugin['install_path'] ?? 'Not installed from a manual package' }}</div>
                </div>
            </div>
        </div>
    </details>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Settings</strong>
                @if ($plugin['settings_url'])
                    <a class="wb-button wb-button-secondary wb-button-small" href="{{ $plugin['settings_url'] }}">Open Settings</a>
                @endif
            </div>

            <div class="wb-card-body">
                @if ($plugin['settings'])
                    <p>{{ $plugin['settings']['description'] ?? 'This plugin declares a settings surface.' }}</p>
                    <div><strong>Route:</strong> {{ $plugin['settings_route'] ?? 'Available after enabling' }}</div>
                @else
                    <div class="wb-empty">
                        <div class="wb-empty-title">No settings declared.</div>
                        <div class="wb-empty-text">This plugin has not registered a settings surface.</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Health</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-2">
                <div>
                    <span class="wb-status {{ $healthClass }}">{{ $plugin['health']['status'] === 'inactive' ? 'Inactive' : ucfirst($plugin['health']['status']) }}</span>
                </div>
                <div>{{ $plugin['health']['message'] !== '' ? $plugin['health']['message'] : 'No health details reported.' }}</div>
            </div>
        </div>
    </div>

    @if ($plugin['can_uninstall'])
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Danger Zone</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-danger">
                    Database cleanup is not automatic. Plugin-owned tables are preserved unless a future explicit cleanup tool is added.
                </div>
                <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#{{ $uninstallModalId }}">
                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                    Uninstall Plugin
                </button>
            </div>
        </div>

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => $uninstallModalId,
            'title' => 'Uninstall '.$plugin['label'],
            'description' => 'This removes the uploaded plugin package from storage. Plugin-owned database tables are preserved.',
            'action' => route('admin.system.plugins.uninstall', $plugin['handle']),
            'method' => 'DELETE',
            'submitLabel' => 'Uninstall Plugin',
            'submitAttributes' => $plugin['enabled'] ? ['disabled' => true] : [],
        ])
            @if ($plugin['enabled'])
                <div class="wb-alert wb-alert-danger">Disable this plugin before uninstalling it.</div>
            @else
                <p>This will remove version {{ $plugin['version'] ?? 'unknown' }} from the manual plugin install directory.</p>
                <p class="wb-text-sm wb-text-muted">Database cleanup is not automatic. Plugin-owned tables are preserved unless a future explicit cleanup tool is added.</p>
            @endif
        @endcomponent
    @endif
@endsection
