@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    $locale = app(AdminLocaleResolver::class)->locale();
    $translator = app(CmsTranslator::class);
    $text = static fn (string $key, array $replace = []) => $translator->admin('profile.api_tokens.'.$key, $locale, $replace);
    $systemText = static fn (string $key, array $replace = []) => $translator->get('admin.api_tokens.index.'.$key, $locale, $replace);
    $selectedCapabilities = old('capabilities', ['content.read', 'content.validate', 'content.apply', 'media.read', 'media.upload']);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description'), 'count' => $tokens->total(), 'actions' => '<a href="'.e(route('admin.profile.edit')).'" class="wb-btn wb-btn-secondary">'.e($text('back')).'</a>'])
    @include('webblocks-cms::admin.partials.flash')

    @if ($createdToken)
        <div class="wb-alert wb-alert-success"><div class="wb-stack wb-gap-3">
            <div><strong>{{ $text('created') }}</strong><div class="wb-text-sm">{{ $text('copy_now') }}</div></div>
            <div class="wb-stack wb-gap-2"><div class="wb-cluster wb-gap-2"><label class="wb-label" for="created_personal_api_token">{{ $systemText('full_token') }}</label><button type="button" class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon" data-wb-copy-target="created_personal_api_token" aria-label="{{ $systemText('copy_full_token') }}"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></button></div><textarea id="created_personal_api_token" class="wb-textarea" rows="2" readonly>{{ $createdToken }}</textarea></div>
            <div class="wb-stack wb-gap-2"><div class="wb-cluster wb-gap-2"><label class="wb-label" for="created_personal_api_token_env">{{ $systemText('env_example') }}</label><button type="button" class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon" data-wb-copy-target="created_personal_api_token_env" aria-label="{{ $systemText('copy_env_example') }}"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></button></div><textarea id="created_personal_api_token_env" class="wb-textarea" rows="3" readonly>WEBBLOCKS_CMS_API_URL={{ $apiBaseUrl }}
WEBBLOCKS_CMS_API_TOKEN={{ $createdToken }}</textarea></div>
            <div class="wb-text-sm" data-wb-api-token-copy-feedback data-copy-success="{{ $systemText('copied') }}" data-copy-failed="{{ $systemText('copy_failed') }}" role="status" aria-live="polite"></div>
        </div></div>
    @endif

    <section class="wb-card">
        <div class="wb-card-header"><strong>{{ $systemText('quick_start') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3"><div class="wb-grid wb-grid-2 wb-gap-3"><div class="wb-stack wb-gap-1"><span class="wb-text-sm wb-text-muted">{{ $systemText('api_base_url') }}</span><code>/webadmin/api</code></div><div class="wb-stack wb-gap-1"><span class="wb-text-sm wb-text-muted">{{ $systemText('first_request') }}</span><code>{{ $systemText('discovery_request') }}</code></div></div><div class="wb-text-sm wb-text-muted">{{ $systemText('quick_start_help') }}</div></div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header"><div><strong>{{ $text('create_title') }}</strong><div class="wb-text-sm wb-text-muted">{{ $text('create_description') }}</div></div></div>
        <form method="POST" action="{{ route('admin.profile.api-tokens.store') }}">@csrf
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-field"><label class="wb-label" for="personal_api_token_name">{{ $text('name') }}</label><input id="personal_api_token_name" class="wb-input" name="name" value="{{ old('name') }}" placeholder="{{ $systemText('name_placeholder') }}" required maxlength="120">@error('name')<div class="wb-field-error">{{ $message }}</div>@enderror</div>
                <div class="wb-field"><div class="wb-label">{{ $text('sites') }}</div><div class="wb-text-sm wb-text-muted">{{ $text('sites_help') }}</div><div class="wb-api-token-capability-groups"><details class="wb-api-token-capability-group" open><summary class="wb-api-token-capability-summary"><span class="wb-api-token-capability-summary-copy"><strong>{{ $text('sites') }}</strong><span class="wb-text-sm wb-text-muted">{{ $text('sites_help') }}</span></span><span class="wb-status-pill wb-status-active">{{ $sites->count() }}</span></summary><div class="wb-api-token-capability-list">@foreach ($sites as $site)<label class="wb-check wb-api-token-capability-option" for="personal_api_token_site_{{ $site->id }}"><input id="personal_api_token_site_{{ $site->id }}" type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, old('site_ids', $sites->pluck('id')->all())))><span class="wb-api-token-capability-copy"><strong>{{ $site->name }}</strong><span class="wb-text-sm wb-text-muted">{{ $site->handle }}</span></span></label>@endforeach</div></details></div>@error('site_ids')<div class="wb-field-error">{{ $message }}</div>@enderror</div>
                @include('webblocks-cms::admin.system.api-tokens.partials.capability-checkboxes', ['fieldPrefix' => 'personal_api_token_capability', 'selectedCapabilities' => $selectedCapabilities, 'showErrors' => true])
                <div class="wb-field"><label class="wb-label" for="expires_in_days">{{ $text('expires') }}</label><select id="expires_in_days" name="expires_in_days" class="wb-select">@foreach ([30, 90, 365] as $days)<option value="{{ $days }}" @selected((int) old('expires_in_days', 90) === $days)>{{ $days }} {{ $text('days') }}</option>@endforeach</select></div>
            </div>
            <div class="wb-card-footer"><button class="wb-btn wb-btn-primary">{{ $text('create') }}</button></div>
        </form>
    </section>

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap"><div class="wb-cluster wb-cluster-2"><strong>{{ $text('existing_title') }}</strong><span class="wb-status-pill wb-status-info">{{ $tokens->total() }}</span></div></div>
        <div class="wb-card-body">@if ($tokens->isEmpty())<div class="wb-empty"><div class="wb-empty-title">{{ $text('empty') }}</div><div class="wb-empty-text">{{ $text('api_base', ['url' => $apiBaseUrl]) }}</div></div>@else<div class="wb-table-wrap"><table class="wb-table wb-table-striped wb-table-hover"><thead><tr><th>{{ $text('name') }}</th><th>{{ $systemText('status') }}</th><th>{{ $systemText('preview') }}</th><th>{{ $text('sites') }}</th><th>{{ $text('expires') }}</th><th>{{ $systemText('last_used') }}</th><th>{{ $systemText('actions') }}</th></tr></thead><tbody>
            @foreach ($tokens as $token)<tr><td><div class="wb-stack wb-gap-1"><strong>{{ $token->name }}</strong><span class="wb-text-sm wb-text-muted">{{ $capabilitiesPresenter->summary($token) }}</span></div></td><td><span class="wb-status-pill {{ $token->statusBadgeClass() }}">{{ $token->statusLabel() }}</span></td><td><code>{{ $token->token_preview }}</code></td><td>{{ $sites->whereIn('id', $token->allowed_site_ids ?? [])->pluck('name')->implode(', ') }}</td><td>{{ $token->expires_at?->format('Y-m-d') }}</td><td><div class="wb-stack wb-gap-1"><span>{{ $token->lastUsedAtLabel() }}</span>@if ($token->last_used_ip)<span class="wb-text-sm wb-text-muted">{{ $token->last_used_ip }}</span>@endif</div></td><td class="wb-table-actions"><div class="wb-action-group">@if (! $token->isRevoked())<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#revoke-personal-api-token-{{ $token->id }}" title="{{ $text('revoke') }}" aria-label="{{ $text('revoke') }}"><i class="wb-icon wb-icon-ban" aria-hidden="true"></i></button>@endif<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-personal-api-token-{{ $token->id }}" title="{{ $text('delete') }}" aria-label="{{ $text('delete') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button></div></td></tr>@endforeach
        </tbody></table></div>@endif</div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $tokens, 'ariaLabel' => $systemText('pagination'), 'compact' => true])
    </section>
@endsection

@push('overlays')
    @foreach ($tokens as $token)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'revoke-personal-api-token-'.$token->id, 'title' => $text('revoke'), 'description' => $text('revoke_confirm'), 'action' => route('admin.profile.api-tokens.revoke', $token), 'method' => 'POST', 'submitLabel' => $text('revoke')])<p><strong>{{ $token->name }}</strong></p>@endcomponent
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'delete-personal-api-token-'.$token->id, 'title' => $text('delete'), 'description' => $text('delete_confirm'), 'action' => route('admin.profile.api-tokens.destroy', $token), 'method' => 'DELETE', 'submitLabel' => $text('delete')])<p><strong>{{ $token->name }}</strong></p>@endcomponent
    @endforeach
@endpush

@push('scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-copy.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-capabilities.js'])
@endpush
