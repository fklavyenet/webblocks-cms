@extends('webblocks-cms::layouts.admin', ['title' => 'Plugins', 'heading' => 'Plugins'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Plugins',
        'description' => 'Review registered WebBlocks CMS plugins and their registry-owned menu and permission contributions.',
    ])

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
                                <th>Handle</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Health</th>
                                <th>Provider</th>
                                <th>Description</th>
                                <th>Permissions</th>
                                <th>Menu Items</th>
                                <th>Settings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plugins as $plugin)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.system.plugins.show', $plugin['handle']) }}"><strong>{{ $plugin['label'] }}</strong></a>
                                    </td>
                                    <td><code>{{ $plugin['handle'] }}</code></td>
                                    <td>{{ $plugin['version'] ?? 'Not declared' }}</td>
                                    <td>
                                        <span class="wb-status {{ $plugin['enabled'] ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $plugin['enabled'] ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="wb-status {{ $plugin['health']['status'] === 'healthy' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ ucfirst($plugin['health']['status']) }}
                                        </span>
                                    </td>
                                    <td>{{ $plugin['provider'] ?? 'Not declared' }}</td>
                                    <td>{{ $plugin['description'] ?? 'No description provided.' }}</td>
                                    <td>{{ $plugin['permissions_count'] }}</td>
                                    <td>{{ $plugin['menu_items_count'] }}</td>
                                    <td>
                                        @if ($plugin['settings_url'])
                                            <a href="{{ $plugin['settings_url'] }}">Open</a>
                                        @elseif ($plugin['settings'])
                                            Declared
                                        @else
                                            Not declared
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
