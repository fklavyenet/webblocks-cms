@extends('webblocks-cms::layouts.admin', ['title' => $plugin['label'], 'heading' => $plugin['label']])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin['label'],
        'description' => $plugin['description'] ?? 'Review this plugin registration, lifecycle status, and enabled runtime contributions.',
    ])

    <p><a href="{{ route('admin.system.plugins.index') }}">Back to Plugins</a></p>

    @if ($plugin['source'] === 'manual upload' && ! $plugin['enabled'] && $plugin['compatible'])
        <form method="POST" action="{{ route('admin.system.plugins.enable', $plugin['handle']) }}" class="wb-mb-4">
            @csrf
            <button type="submit" class="wb-button wb-button-primary">Enable Plugin</button>
        </form>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Plugin Status</strong>
        </div>

        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div>
                    <strong>Handle</strong>
                    <div><code>{{ $plugin['handle'] }}</code></div>
                </div>
                <div>
                    <strong>Lifecycle</strong>
                    <div>
                        <span class="wb-status {{ $plugin['lifecycle_status'] === 'enabled' ? 'wb-status-active' : 'wb-status-pending' }}">
                            {{ ucfirst($plugin['lifecycle_status']) }}
                        </span>
                        @if (! $plugin['compatible'])
                            <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                        @endif
                    </div>
                </div>
                <div>
                    <strong>Health</strong>
                    <div>
                        <span class="wb-status {{ $plugin['health']['status'] === 'healthy' ? 'wb-status-active' : 'wb-status-pending' }}">
                            {{ ucfirst($plugin['health']['status']) }}
                        </span>
                        <span>{{ $plugin['health']['message'] }}</span>
                    </div>
                </div>
                <div>
                    <strong>Source</strong>
                    <div>{{ $plugin['source'] }}</div>
                </div>
                <div>
                    <strong>Version</strong>
                    <div>{{ $plugin['version'] ?? 'Not declared' }}</div>
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
                    <strong>Provider</strong>
                    <div>{{ $plugin['provider'] ?? 'Not declared' }}</div>
                </div>
                <div>
                    <strong>Install Path</strong>
                    <div>{{ $plugin['install_path'] ?? 'Not installed from a manual package' }}</div>
                </div>
                <div>
                    <strong>Admin Routes</strong>
                    <div>{{ $plugin['admin_routes_count'] }}</div>
                </div>
                <div>
                    <strong>Commands</strong>
                    <div>{{ $plugin['commands_count'] }}</div>
                </div>
                <div>
                    <strong>Permissions</strong>
                    <div>{{ $plugin['permissions_count'] }}</div>
                </div>
            </div>
        </div>
    </div>

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
                <div><strong>Route:</strong> {{ $plugin['settings_route'] ?? 'Unavailable while disabled' }}</div>
            @else
                <div class="wb-empty">
                    <div class="wb-empty-title">No settings declared.</div>
                    <div class="wb-empty-text">This plugin has not registered a settings surface.</div>
                </div>
            @endif
        </div>
    </div>
@endsection
