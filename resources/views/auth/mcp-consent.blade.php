@extends('webblocks-cms::layouts.guest', ['title' => __('webblocks-cms::mcp.connect')])

@section('content')
    <div class="wb-auth-shell">
        <div class="wb-auth-card wb-stack wb-gap-4">
            <h1>{{ __('webblocks-cms::mcp.connect') }}</h1>
            <p>{{ __('webblocks-cms::mcp.consent_read', ['client' => $clientName]) }}</p>
            <p>{{ __('webblocks-cms::mcp.callback_host', ['host' => $callbackAddress]) }}</p>
            @if ($write)
                <p>{{ __('webblocks-cms::mcp.consent_write') }}</p>
            @endif
            <p>{{ __('webblocks-cms::mcp.consent_duration') }}</p>

            <form method="post" action="{{ route('webblocks.mcp.authorize', absolute: false) }}" class="wb-stack wb-gap-4">
                @csrf
                @foreach ($data as $key => $value)
                    @if ($value !== null && ! in_array($key, ['site_id', 'decision'], true))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="wb-field">
                    <label for="mcp-site">{{ __('webblocks-cms::mcp.site') }}</label>
                    <select id="mcp-site" name="site_id" class="wb-select" required>
                        <option value="">{{ __('webblocks-cms::mcp.select_site') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wb-cluster wb-gap-2">
                    <button class="wb-btn wb-btn-primary" name="decision" value="allow">{{ __('webblocks-cms::mcp.allow') }}</button>
                    <button class="wb-btn wb-btn-secondary" name="decision" value="deny" formnovalidate>{{ __('webblocks-cms::mcp.deny') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
