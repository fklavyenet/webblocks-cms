@extends('webblocks-cms::layouts.guest', ['title' => __('webblocks-cms::mcp.connect')])

@section('content')
    <div class="wb-auth-shell">
        <section class="wb-auth-card wb-mcp-consent" aria-labelledby="mcp-consent-title">
            <header class="wb-auth-header">
                <h1 id="mcp-consent-title" class="wb-auth-header-title">{{ __('webblocks-cms::mcp.connect') }}</h1>
                <p class="wb-auth-header-subtitle">{{ __('webblocks-cms::mcp.consent_client', ['client' => $clientName]) }}</p>
            </header>
            <form method="post" action="{{ route('webblocks.mcp.authorize', absolute: false) }}">
                @csrf
                @foreach ($data as $key => $value)
                    @if ($value !== null && ! in_array($key, ['site_id', 'decision', 'draft_access', 'draft_page_id'], true))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="wb-auth-body wb-stack-4">
                    <div class="wb-field">
                        <label class="wb-label" for="mcp-site">{{ __('webblocks-cms::mcp.site') }}</label>
                        <select id="mcp-site" name="site_id" class="wb-select" required>
                            <option value="">{{ __('webblocks-cms::mcp.select_site') }}</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="mcp-draft-access">{{ __('webblocks-cms::mcp.draft_access') }}</label>
                        <select id="mcp-draft-access" name="draft_access" class="wb-select" aria-describedby="mcp-draft-access-help" required>
                            <option value="created">{{ __('webblocks-cms::mcp.drafts_created') }}</option>
                            <option value="site">{{ __('webblocks-cms::mcp.drafts_site') }}</option>
                        </select>
                        <p id="mcp-draft-access-help" class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::mcp.draft_access_help') }}</p>
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="mcp-draft-page">{{ __('webblocks-cms::mcp.existing_draft') }}</label>
                        <select id="mcp-draft-page" name="draft_page_id" class="wb-select" aria-describedby="mcp-draft-page-help">
                            <option value="">{{ __('webblocks-cms::mcp.no_existing_draft') }}</option>
                            @foreach ($sites as $site)
                                <optgroup label="{{ $site->name }}">
                                    @foreach ($drafts->where('site_id', $site->id) as $draft)
                                        <option value="{{ $draft->id }}">{{ $draft->translations->first()?->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p id="mcp-draft-page-help" class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::mcp.existing_draft_help') }}</p>
                    </div>

                    <section class="wb-alert wb-alert-info" aria-labelledby="mcp-consent-permissions">
                        <h2 class="wb-alert-title" id="mcp-consent-permissions">{{ __('webblocks-cms::mcp.consent_permissions') }}</h2>
                        <ul class="wb-stack-2">
                            @foreach ($permissions as $permission)
                                <li>{{ __('webblocks-cms::mcp.'.$permission) }}</li>
                            @endforeach
                        </ul>
                    </section>

                    <div class="wb-cluster wb-gap-2">
                        <button class="wb-btn wb-btn-primary" type="submit" name="decision" value="allow">{{ __('webblocks-cms::mcp.allow') }}</button>
                        <button class="wb-btn wb-btn-secondary" type="submit" name="decision" value="deny" formnovalidate>{{ __('webblocks-cms::mcp.deny') }}</button>
                    </div>
                </div>
            </form>
            <footer class="wb-auth-footer">
                <p>{{ __('webblocks-cms::mcp.consent_duration') }}</p>
                <p>{{ __('webblocks-cms::mcp.callback_host', ['host' => $callbackAddress]) }}</p>
            </footer>
        </section>
    </div>
@endsection
