@extends('webblocks-cms::layouts.admin', ['title' => 'Plugins', 'heading' => 'Plugins'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Plugins',
        'description' => 'Review installed WebBlocks CMS plugins and their registry-owned menu and permission contributions.',
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
                                <th>Handle</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Health</th>
                                <th>Source</th>
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
                                        <span class="wb-status {{ $plugin['lifecycle_status'] === 'enabled' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ ucfirst($plugin['lifecycle_status']) }}
                                        </span>
                                        @if (! $plugin['compatible'])
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin['incompatibility_message'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="wb-status {{ $plugin['health']['status'] === 'healthy' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ ucfirst($plugin['health']['status']) }}
                                        </span>
                                    </td>
                                    <td>{{ $plugin['source'] }}</td>
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
