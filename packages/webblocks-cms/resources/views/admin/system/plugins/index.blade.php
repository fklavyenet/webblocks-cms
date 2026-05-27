@extends('webblocks-cms::layouts.admin', ['title' => 'Plugins', 'heading' => 'Plugins'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Plugins',
        'description' => 'Manage manually installed WebBlocks CMS plugins and review the plugin host status.',
    ])

    @if ($canInstallPlugins)
        <div class="wb-card wb-mb-4">
            <div class="wb-card-header">
                <strong>Manual Plugin Install</strong>
            </div>
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.system.plugins.upload') }}" enctype="multipart/form-data" class="wb-stack wb-gap-3">
                    @csrf
                    <div>
                        <label for="plugin_zip">Plugin ZIP</label>
                        <input id="plugin_zip" type="file" name="plugin_zip" accept=".zip,application/zip" required>
                        @error('plugin_zip')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <button type="submit" class="wb-button wb-button-primary">Upload Plugin ZIP</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if (! empty($pluginSystemCards))
        <div class="wb-grid wb-grid-2 wb-mb-4">
            @foreach ($pluginSystemCards as $card)
                <div class="wb-card" data-plugin-system-card="{{ $card->key() }}" data-plugin-handle="{{ $card->pluginHandle() }}">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                        <strong>{{ $card->titleText() }}</strong>
                        <span class="wb-text-sm wb-text-muted">{{ $card->pluginHandle() }}</span>
                    </div>
                    <div class="wb-card-body wb-stack wb-gap-2">
                        @if ($card->descriptionText() !== null)
                            <div class="wb-text-sm wb-text-muted">{{ $card->descriptionText() }}</div>
                        @endif
                        @if ($card->urlValue() !== null)
                            <a href="{{ $card->urlValue() }}" class="wb-btn wb-btn-secondary">{{ $card->linkLabel() ?? 'Open' }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Registered Plugins</strong>
        </div>

        <div class="wb-card-body">
            @if (count($plugins) === 0)
                <div class="wb-empty">
                    <div class="wb-empty-title">No plugins registered yet.</div>
                    <div class="wb-empty-text">Plugin discovery and enablement will appear here as the plugin host lifecycle expands.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Version</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Health</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plugins as $plugin)
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
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.system.plugins.show', $plugin['handle']) }}"><strong>{{ $plugin['label'] }}</strong></a>
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $plugin['handle'] }}</code></div>
                                    </td>
                                    <td>{{ $plugin['version'] ?? 'Not declared' }}</td>
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
                                            {{ $plugin['health']['status'] === 'inactive' ? 'Inactive' : ucfirst($plugin['health']['status']) }}
                                        </span>
                                        @if ($plugin['health']['message'] !== '')
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin['health']['message'] }}</div>
                                        @endif
                                    </td>
                                    <td class="wb-table-actions wb-whitespace-nowrap">
                                        <div class="wb-action-group wb-whitespace-nowrap" aria-label="Plugin actions for {{ $plugin['label'] }}">
                                            <a href="{{ route('admin.system.plugins.show', $plugin['handle']) }}" class="wb-action-btn" title="View details" aria-label="View details">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>

                                            @if ($plugin['can_enable'])
                                                <form method="POST" action="{{ route('admin.system.plugins.enable', $plugin['handle']) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="Enable plugin" aria-label="Enable plugin">
                                                        <i class="wb-icon wb-icon-play" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($plugin['can_disable'])
                                                <form method="POST" action="{{ route('admin.system.plugins.disable', $plugin['handle']) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="Disable plugin" aria-label="Disable plugin">
                                                        <i class="wb-icon wb-icon-pause" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($plugin['can_uninstall'])
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" title="Uninstall plugin" aria-label="Uninstall plugin" data-wb-toggle="modal" data-wb-target="#{{ $uninstallModalId }}">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>

                                        @if ($plugin['can_uninstall'])
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
