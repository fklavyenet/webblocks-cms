@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('system_plugins_show.'.$key, $adminLocale, $replace);
    $pluginSetupText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.plugin_setup.'.$key, $replace);
    $statusClass = match ($plugin['lifecycle_label']) {
        $pluginSetupText('enabled') => 'wb-status-active',
        $pluginSetupText('incompatible'), $pluginSetupText('missing_files'), $pluginSetupText('error') => 'wb-status-danger',
        default => 'wb-status-pending',
    };
    $healthClass = match ($plugin['health']['status']) {
        'healthy' => 'wb-status-active',
        'warning', 'error', 'incompatible' => 'wb-status-danger',
        default => 'wb-status-pending',
    };
    $uninstallModalId = 'plugin-uninstall-'.$plugin['handle'];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $plugin['label'], 'heading' => $plugin['label']])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin['label'],
        'description' => $plugin['description'] ?? $adminText('description'),
    ])

    <p><a href="{{ route('admin.system.plugins.index') }}">{{ $adminText('back_to_plugins') }}</a></p>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('overview') }}</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $plugin['label'] }}</strong>
                    <div class="wb-text-sm wb-text-muted"><code>{{ $plugin['handle'] }}</code></div>
                </div>
                <div>{{ $plugin['description'] ?? $adminText('no_description') }}</div>
                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>{{ $adminText('version') }}</strong>
                        <div>{{ $plugin['version'] ?? $adminText('not_declared') }}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('source') }}</strong>
                        <div>{{ $plugin['source'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('lifecycle') }}</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <span class="wb-status {{ $statusClass }}">{{ $plugin['lifecycle_label'] }}</span>
                    @if (! $plugin['compatible'])
                        <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                    @elseif ($plugin['setup_required'])
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('setup_required_help') }}</div>
                    @elseif (! $plugin['enabled'])
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('disabled_help') }}</div>
                    @endif
                </div>

                <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                    @if ($plugin['can_enable'])
                        <form method="POST" action="{{ route('admin.system.plugins.enable', $plugin['handle']) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary">
                                <i class="wb-icon wb-icon-play" aria-hidden="true"></i>
                                {{ $adminText('enable_plugin') }}
                            </button>
                        </form>
                    @endif

                    @if ($plugin['can_disable'])
                        <form method="POST" action="{{ route('admin.system.plugins.disable', $plugin['handle']) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-secondary">
                                <i class="wb-icon wb-icon-pause" aria-hidden="true"></i>
                                {{ $adminText('disable_plugin') }}
                            </button>
                        </form>
                    @endif

                    @if ($plugin['can_setup'])
                        <form method="POST" action="{{ route('admin.system.plugins.setup', $plugin['handle']) }}">
                            @csrf
                            <button type="submit" class="wb-btn {{ $plugin['setup_required'] ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                                {{ $adminText('run_plugin_migrations') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $adminText('capabilities') }}</strong>
        </div>

        <div class="wb-card-body">
            <div class="wb-grid wb-grid-4">
                <div>
                    <strong>{{ $adminText('routes') }}</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['admin_routes_count'] : $adminText('available_after_enabling') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('commands') }}</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['commands_count'] : $adminText('available_after_enabling') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('permissions') }}</strong>
                    <div>{{ $plugin['permissions_count'] }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('menu_items') }}</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['menu_items_count'] : $adminText('available_after_enabling') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('settings') }}</strong>
                    <div>{{ $plugin['settings'] ? ($plugin['enabled'] ? $adminText('declared') : $adminText('available_after_enabling')) : $adminText('not_declared') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('migrations') }}</strong>
                    <div>{{ $plugin['migrations_count'] ?? $adminText('manual_plugin_owned') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('assets') }}</strong>
                    <div>{{ $plugin['public_assets_count'] }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('dashboard_cards') }}</strong>
                    <div>{{ $plugin['enabled'] ? $plugin['dashboard_widgets_count'] + $plugin['system_cards_count'] : $adminText('available_after_enabling') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('blocks') }}</strong>
                    <div>{{ $plugin['block_types_count'] + $plugin['block_packs_count'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <details class="wb-card">
        <summary class="wb-card-header">
            <strong>{{ $adminText('technical_details') }}</strong>
        </summary>

        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div>
                    <strong>{{ $adminText('provider') }}</strong>
                    <div>{{ $plugin['provider'] ?? $adminText('not_declared') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('required_cms') }}</strong>
                    <div>{{ $plugin['required_cms_version'] ?? $adminText('not_declared') }}</div>
                </div>
                <div>
                    <strong>{{ $adminText('settings_namespace') }}</strong>
                    <div><code>{{ $plugin['settings_namespace'] }}</code></div>
                </div>
                <div>
                    <strong>{{ $adminText('database_prefix') }}</strong>
                    <div><code>{{ $plugin['database_prefix'] }}</code></div>
                </div>
                <div>
                    <strong>{{ $adminText('route_namespace') }}</strong>
                    <div><code>{{ $plugin['route_name_prefix'] }}</code></div>
                </div>
                <div>
                    <strong>{{ $adminText('install_path') }}</strong>
                    <div>{{ $plugin['install_path'] ?? $adminText('not_installed_manual') }}</div>
                </div>
            </div>
        </div>
    </details>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('settings') }}</strong>
            </div>

            <div class="wb-card-body">
                @if ($plugin['settings'])
                    <p>{{ $plugin['settings']['description'] ?? $adminText('settings_surface_declared') }}</p>
                    <div><strong>{{ $adminText('route_label') }}</strong> {{ $plugin['settings_route'] ?? $adminText('available_after_enabling') }}</div>
                @else
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_settings_declared') }}</div>
                        <div class="wb-empty-text">{{ $adminText('no_settings_help') }}</div>
                    </div>
                @endif
            </div>
            @if ($plugin['settings_url'])
                <div class="wb-card-footer">
                    <a class="wb-btn wb-btn-secondary" href="{{ $plugin['settings_url'] }}">
                        <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                        {{ $adminText('open_settings') }}
                    </a>
                </div>
            @endif
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('health') }}</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-2">
                <div>
                    <span class="wb-status {{ $healthClass }}">{{ $plugin['health']['status'] === 'inactive' ? $adminText('inactive') : ucfirst($plugin['health']['status']) }}</span>
                </div>
                <div>{{ $plugin['health']['message'] !== '' ? $plugin['health']['message'] : $adminText('no_health_details') }}</div>
            </div>
        </div>
    </div>

    @if ($plugin['can_uninstall'])
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('danger_zone') }}</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-danger">
                    {{ $adminText('database_cleanup_warning') }}
                </div>
            </div>
            <div class="wb-card-footer">
                <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#{{ $uninstallModalId }}">
                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                    {{ $adminText('uninstall_plugin') }}
                </button>
            </div>
        </div>

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => $uninstallModalId,
            'title' => $adminText('uninstall_title', ['plugin' => $plugin['label']]),
            'description' => $adminText('uninstall_description'),
            'action' => route('admin.system.plugins.uninstall', $plugin['handle']),
            'method' => 'DELETE',
            'submitLabel' => $adminText('uninstall_plugin'),
            'submitAttributes' => $plugin['enabled'] ? ['disabled' => true] : [],
        ])
            @if ($plugin['enabled'])
                <div class="wb-alert wb-alert-danger">{{ $adminText('disable_before_uninstall') }}</div>
            @else
                <p>{{ $adminText('remove_version_help', ['version' => $plugin['version'] ?? $adminText('unknown')]) }}</p>
                <p class="wb-text-sm wb-text-muted">{{ $adminText('database_cleanup_warning') }}</p>
            @endif
        @endcomponent
    @endif
@endsection
