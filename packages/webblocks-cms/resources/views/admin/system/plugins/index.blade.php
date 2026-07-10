@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $systemPluginsIndexLocale = app(AdminLocaleResolver::class)->locale();
    $systemPluginsIndexTranslator = app(CmsTranslator::class);
    $systemPluginsIndexText = static fn (string $key, array $replace = []) => $systemPluginsIndexTranslator->admin('system_plugins_index.'.$key, $systemPluginsIndexLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $systemPluginsIndexText('plugins'), 'heading' => $systemPluginsIndexText('plugins')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $systemPluginsIndexText('plugins'),
        'description' => $systemPluginsIndexText('description'),
        'actions' => '<a href="'.e(route('admin.plugins.catalog.index')).'" class="wb-btn wb-btn-secondary"><i class="wb-icon wb-icon-search" aria-hidden="true"></i> '.$systemPluginsIndexText('browse_plugin_catalog').'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($canInstallPlugins)
        <div class="wb-card wb-mb-4">
            <div class="wb-card-header">
                <strong>{{ $systemPluginsIndexText('manual_plugin_install') }}</strong>
            </div>
            <form method="POST" action="{{ route('admin.system.plugins.upload') }}" enctype="multipart/form-data">
                <div class="wb-card-body">
                    @csrf
                    <div>
                        <label for="plugin_zip">{{ $systemPluginsIndexText('plugin_zip') }}</label>
                        <input id="plugin_zip" type="file" name="plugin_zip" accept=".zip,application/zip" required>
                        @error('plugin_zip')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted">{{ $systemPluginsIndexText('manual_install_help') }}</span>
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $systemPluginsIndexText('upload_plugin_zip') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $systemPluginsIndexText('registered_plugins') }}</strong>
        </div>

        <div class="wb-card-body">
            @if (count($plugins) === 0)
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $systemPluginsIndexText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $systemPluginsIndexText('empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>{{ $systemPluginsIndexText('plugin') }}</th>
                                <th>{{ $systemPluginsIndexText('version') }}</th>
                                <th>{{ $systemPluginsIndexText('source') }}</th>
                                <th>{{ $systemPluginsIndexText('status') }}</th>
                                <th>{{ $systemPluginsIndexText('health') }}</th>
                                <th>{{ $systemPluginsIndexText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plugins as $plugin)
                                @php
                                    $statusClass = ! $plugin['files_available'] || ! $plugin['compatible']
                                        ? 'wb-status-danger'
                                        : ($plugin['enabled'] ? 'wb-status-active' : 'wb-status-pending');
                                    $healthClass = match ($plugin['health']['status']) {
                                        'healthy' => 'wb-status-active',
                                        'warning', 'error', 'incompatible' => 'wb-status-danger',
                                        default => 'wb-status-pending',
                                    };
                                    $uninstallModalId = 'plugin-uninstall-'.$plugin['handle'];
                                    $updateModalId = 'plugin-update-'.$plugin['handle'];
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.system.plugins.show', $plugin['handle']) }}"><strong>{{ $plugin['label'] }}</strong></a>
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $plugin['handle'] }}</code></div>
                                    </td>
                                    <td>
                                        {{ $plugin['version'] ?? $systemPluginsIndexText('not_declared') }}
                                        @if ($plugin['catalog_update'])
                                            <div class="wb-text-sm wb-text-muted">{{ $systemPluginsIndexText('update_available', ['version' => $plugin['catalog_update']['version']]) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $plugin['source'] }}</td>
                                    <td>
                                        <span class="wb-status {{ $statusClass }}">
                                            {{ $plugin['lifecycle_label'] }}
                                        </span>
                                        @if (! $plugin['compatible'])
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="wb-status {{ $healthClass }}">
                                            {{ $plugin['health']['status'] === 'inactive' ? $systemPluginsIndexText('inactive') : ucfirst($plugin['health']['status']) }}
                                        </span>
                                        @if ($plugin['health']['message'] !== '')
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin['health']['message'] }}</div>
                                        @endif
                                    </td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group" aria-label="{{ $systemPluginsIndexText('plugin_actions_for', ['label' => $plugin['label']]) }}">
                                            <a href="{{ route('admin.system.plugins.show', $plugin['handle']) }}" class="wb-action-btn" title="{{ $systemPluginsIndexText('view_details') }}" aria-label="{{ $systemPluginsIndexText('view_details') }}">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>

                                            @if ($plugin['can_update_from_catalog'])
                                                <button type="button" class="wb-action-btn" title="{{ $systemPluginsIndexText('update_from_catalog') }}" aria-label="{{ $systemPluginsIndexText('update_from_catalog') }}" data-wb-toggle="modal" data-wb-target="#{{ $updateModalId }}">
                                                    <i class="wb-icon wb-icon-rotate-cw" aria-hidden="true"></i>
                                                </button>
                                            @endif

                                            @if ($plugin['can_enable'])
                                                <form method="POST" action="{{ route('admin.system.plugins.enable', $plugin['handle']) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="{{ $systemPluginsIndexText('enable_plugin') }}" aria-label="{{ $systemPluginsIndexText('enable_plugin') }}">
                                                        <i class="wb-icon wb-icon-play" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($plugin['can_disable'])
                                                <form method="POST" action="{{ route('admin.system.plugins.disable', $plugin['handle']) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="{{ $systemPluginsIndexText('disable_plugin') }}" aria-label="{{ $systemPluginsIndexText('disable_plugin') }}">
                                                        <i class="wb-icon wb-icon-pause" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($plugin['can_uninstall'])
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" title="{{ $systemPluginsIndexText('uninstall_plugin') }}" aria-label="{{ $systemPluginsIndexText('uninstall_plugin') }}" data-wb-toggle="modal" data-wb-target="#{{ $uninstallModalId }}">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>

                                        @if ($plugin['can_update_from_catalog'])
                                            <div class="wb-modal wb-modal-lg" id="{{ $updateModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $updateModalId }}-title" aria-describedby="{{ $updateModalId }}-body">
                                                <div class="wb-modal-dialog">
                                                    <div class="wb-modal-header">
                                                        <h2 class="wb-modal-title" id="{{ $updateModalId }}-title">{{ $systemPluginsIndexText('update_confirm_title', ['label' => $plugin['label']]) }}</h2>
                                                        <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $systemPluginsIndexText('update_confirm_cancel') }}">
                                                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                                                        </button>
                                                    </div>

                                                    <form method="POST" action="{{ route('admin.system.plugins.update-from-catalog', $plugin['handle']) }}">
                                                        @csrf

                                                        <div class="wb-modal-body wb-stack wb-gap-4">
                                                            <p id="{{ $updateModalId }}-body">{{ $systemPluginsIndexText('update_confirm_body', ['label' => $plugin['label'], 'current' => $plugin['version'] ?? $systemPluginsIndexText('unknown'), 'version' => $plugin['catalog_update']['version']]) }}</p>
                                                        </div>

                                                        <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                                                            <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                                                                <button type="submit" class="wb-btn wb-btn-primary" data-wb-busy data-wb-busy-label="{{ $systemPluginsIndexText('update_in_progress') }}">
                                                                    <span data-wb-busy-text>{{ $systemPluginsIndexText('update_confirm_submit') }}</span>
                                                                </button>
                                                                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $systemPluginsIndexText('update_confirm_cancel') }}</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($plugin['can_uninstall'])
                                            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                                'id' => $uninstallModalId,
                                                'title' => $systemPluginsIndexText('uninstall_title', ['label' => $plugin['label']]),
                                                'description' => $systemPluginsIndexText('uninstall_description'),
                                                'action' => route('admin.system.plugins.uninstall', $plugin['handle']),
                                                'method' => 'DELETE',
                                                'submitLabel' => $systemPluginsIndexText('uninstall_plugin'),
                                                'submitAttributes' => $plugin['enabled'] ? ['disabled' => true] : [],
                                            ])
                                                @if ($plugin['enabled'])
                                                    <div class="wb-alert wb-alert-danger">{{ $systemPluginsIndexText('disable_before_uninstall') }}</div>
                                                @else
                                                    <p>{{ $systemPluginsIndexText('uninstall_version_help', ['version' => $plugin['version'] ?? $systemPluginsIndexText('unknown')]) }}</p>
                                                    <p class="wb-text-sm wb-text-muted">{{ $systemPluginsIndexText('database_cleanup_help') }}</p>
                                                @endif
                                            @endcomponent
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
