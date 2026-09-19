@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('system_plugins_show.'.$key, $adminLocale, $replace);
    $pluginSetupText = fn (string $key, array $replace = []) => $adminTranslator->admin('plugin_setup.'.$key, $adminLocale, $replace);
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
    @php(ob_start())
    <div class="wb-cluster wb-cluster-2">
        <a href="{{ route('admin.system.plugins.index') }}" class="wb-btn wb-btn-secondary">
            <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i>
            {{ $adminText('back_to_plugins') }}
        </a>

        @if ($plugin['settings_url'])
            <a class="wb-btn wb-btn-primary" href="{{ $plugin['settings_url'] }}">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                {{ $adminText('open_settings') }}
            </a>
        @endif

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
                <button type="submit" class="wb-btn {{ $plugin['migrations_pending'] ? 'wb-btn-primary' : 'wb-btn-secondary' }}" @disabled(! $plugin['migrations_pending'])>
                    <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                    {{ $plugin['migrations_pending'] ? $adminText('run_plugin_migrations') : $adminText('migrations_up_to_date') }}
                </button>
            </form>
        @endif
    </div>
    @php($pageActions = ob_get_clean())

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin['label'],
        'description' => $plugin['description'] ?? $adminText('description'),
        'actions' => $pageActions,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $adminText('overview') }}</strong>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('handle') }}</th><td><code>{{ $plugin['handle'] }}</code></td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('version') }}</th><td>{{ $plugin['version'] ?? $adminText('not_declared') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('source') }}</th><td>{{ $plugin['source'] }}</td></tr>
                    <tr>
                        <th scope="row" class="wb-table-key">{{ $adminText('lifecycle') }}</th>
                        <td>
                            <span class="wb-status {{ $statusClass }}">{{ $plugin['lifecycle_label'] }}</span>
                            @if (! $plugin['compatible'])
                                <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                            @elseif ($plugin['setup_required'])
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('setup_required_help') }}</div>
                            @elseif (! $plugin['enabled'])
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('disabled_help') }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('health') }}</th><td><span class="wb-status {{ $healthClass }}">{{ $plugin['health']['status'] === 'inactive' ? $adminText('inactive') : ucfirst($plugin['health']['status']) }}</span> {{ $plugin['health']['message'] !== '' ? $plugin['health']['message'] : $adminText('no_health_details') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <details class="wb-card">
        <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('capabilities') }}</strong>
            <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
        </summary>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('routes') }}</th><td>{{ $plugin['enabled'] ? $plugin['admin_routes_count'] : $adminText('available_after_enabling') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('commands') }}</th><td>{{ $plugin['enabled'] ? $plugin['commands_count'] : $adminText('available_after_enabling') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('permissions') }}</th><td>{{ $plugin['permissions_count'] }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('menu_items') }}</th><td>{{ $plugin['enabled'] ? $plugin['menu_items_count'] : $adminText('available_after_enabling') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('settings') }}</th><td>{{ $plugin['settings'] ? ($plugin['enabled'] ? $adminText('declared') : $adminText('available_after_enabling')) : $adminText('not_declared') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('migrations') }}</th><td>{{ $plugin['migrations_count'] ?? $adminText('manual_plugin_owned') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('assets') }}</th><td>{{ $plugin['public_assets_count'] }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard_cards') }}</th><td>{{ $plugin['enabled'] ? $plugin['dashboard_widgets_count'] + $plugin['system_cards_count'] : $adminText('available_after_enabling') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('blocks') }}</th><td>{{ $plugin['block_types_count'] + $plugin['block_packs_count'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <details class="wb-card">
        <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('technical_details') }}</strong>
            <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
        </summary>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('provider') }}</th><td>{{ $plugin['provider'] ?? $adminText('not_declared') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('required_cms') }}</th><td>{{ $plugin['required_cms_version'] ?? $adminText('not_declared') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('requires') }}</th><td>
                        @forelse ($plugin['requires'] ?? [] as $requirement => $constraint)
                            <div><code>{{ $requirement }}</code> {{ $constraint }}</div>
                        @empty
                            {{ $adminText('not_declared') }}
                        @endforelse
                    </td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('settings_namespace') }}</th><td><code>{{ $plugin['settings_namespace'] }}</code></td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('database_prefix') }}</th><td><code>{{ $plugin['database_prefix'] }}</code></td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('route_namespace') }}</th><td><code>{{ $plugin['route_name_prefix'] }}</code></td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('install_path') }}</th><td>{{ $plugin['install_path'] ?? $adminText('not_installed_manual') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    @if ($plugin['settings'])
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('settings') }}</strong>
            </div>

            <div class="wb-card-body">
                <div class="wb-table-wrap"><table class="wb-table"><tbody>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('description_label') }}</th><td>{{ $plugin['settings']['description'] ?? $adminText('settings_surface_declared') }}</td></tr>
                    <tr><th scope="row" class="wb-table-key">{{ $adminText('route_label') }}</th><td>{{ $plugin['settings_route'] ?? $adminText('available_after_enabling') }}</td></tr>
                </tbody></table></div>
            </div>
        </div>
    @endif

    @if (($plugin['health']['checks'] ?? []) !== [])
        <details class="wb-card">
            <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>{{ $adminText('health') }}</strong>
                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
            </summary>

            <div class="wb-card-body wb-stack wb-gap-2">
                @include('webblocks-cms::admin.system.plugins.partials.health-checks', [
                    'health' => $plugin['health'],
                    'healthText' => static fn (string $key) => $adminText('health_'.$key),
                ])
            </div>
        </details>
    @endif

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
