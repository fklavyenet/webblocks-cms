<form method="POST" action="{{ route('admin.system.settings.mcp.update') }}" class="wb-card wb-card-muted">
    @csrf
    @method('PUT')
    <div class="wb-card-header"><h2 class="wb-card-title">{{ __('webblocks-cms::mcp.settings') }}</h2></div>
    <div class="wb-card-body wb-stack wb-gap-4">
        <p>{{ __('webblocks-cms::mcp.settings_help') }}</p>
        @if (!$mcpReady)
            <div class="wb-alert wb-alert-warning" role="status">{{ __('webblocks-cms::mcp.setup_required') }}</div>
        @endif
        <div class="wb-field">
            <label class="wb-label" for="settings-mcp-enabled">{{ __('webblocks-cms::mcp.enabled') }}</label>
            <select class="wb-select" id="settings-mcp-enabled" name="mcp_enabled" required>
                <option value="0" @selected(!(bool) old('mcp_enabled', $mcpEnabled))>{{ __('webblocks-cms::mcp.off') }}</option>
                <option value="1" @selected((bool) old('mcp_enabled', $mcpEnabled)) @disabled(!$mcpReady)>{{ __('webblocks-cms::mcp.on') }}</option>
            </select>
        </div>
        @if ($mcpReady)
            <div class="wb-field">
                <label class="wb-label" for="settings-mcp-url">{{ __('webblocks-cms::mcp.server_url') }}</label>
                <div class="wb-cluster wb-gap-2">
                    <input class="wb-input" id="settings-mcp-url" value="{{ $mcpResource }}" readonly>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-copy data-wb-target="#settings-mcp-url"
                        data-wb-copy-status="#settings-mcp-copy-status" data-wb-copy-success="{{ __('webblocks-cms::mcp.copied') }}"
                        data-wb-copy-error="{{ __('webblocks-cms::mcp.copy_failed') }}">{{ __('webblocks-cms::mcp.copy') }}</button>
                </div>
                <span id="settings-mcp-copy-status" class="wb-text-sm" role="status" aria-live="polite"></span>
            </div>
        @endif
        <div class="wb-stack-3" role="group" aria-labelledby="mcp-allowed-scopes">
            <h3 id="mcp-allowed-scopes">{{ __('webblocks-cms::mcp.allowed_scopes') }}</h3>
            <p>{{ __('webblocks-cms::mcp.allowed_scopes_help') }}</p>
            <input type="hidden" name="manage_mcp_scopes" value="1">
            @foreach ($mcpScopes as $scope => $label)
                <label class="wb-check">
                    <input type="checkbox" name="mcp_scopes[]" value="{{ $scope }}"
                        @checked(in_array($scope, old('manage_mcp_scopes') ? (array) old('mcp_scopes', []) : $mcpAvailableScopes, true))>
                    <span>{{ __('webblocks-cms::mcp.'.$label) }}</span>
                </label>
            @endforeach
        </div>
        <p class="wb-text-muted">{{ __('webblocks-cms::mcp.disable_help') }}</p>
    </div>
    <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::mcp.save') }}</button></div>
</form>
