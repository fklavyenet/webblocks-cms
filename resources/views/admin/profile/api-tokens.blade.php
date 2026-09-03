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
            @foreach ($tokens as $token)<tr><td><div class="wb-stack wb-gap-1"><strong>{{ $token->name }}</strong><span class="wb-text-sm wb-text-muted">{{ $capabilitiesPresenter->summary($token) }}</span></div></td><td><span class="wb-status-pill {{ $token->statusBadgeClass() }}">{{ $token->statusLabel() }}</span></td><td><code>{{ $token->token_preview }}</code></td><td>{{ $sites->whereIn('id', $token->allowed_site_ids ?? [])->pluck('name')->implode(', ') }}</td><td>{{ $token->expires_at?->format('Y-m-d') }}</td><td><div class="wb-stack wb-gap-1"><span>{{ $token->lastUsedAtLabel() }}</span>@if ($token->last_used_ip)<span class="wb-text-sm wb-text-muted">{{ $token->last_used_ip }}</span>@endif</div></td><td class="wb-table-actions"><div class="wb-action-group"><button type="button" class="wb-action-btn" data-wb-toggle="modal" data-wb-target="#edit-personal-api-token-{{ $token->id }}" title="{{ $systemText('edit_token') }}" aria-label="{{ $systemText('edit_token') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></button><button type="button" class="wb-action-btn" data-wb-toggle="modal" data-wb-target="#activity-personal-api-token-{{ $token->id }}" title="{{ $systemText('view_activity') }}" aria-label="{{ $systemText('view_activity') }}"><i class="wb-icon wb-icon-history" aria-hidden="true"></i></button>@if (! $token->isRevoked())<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#revoke-personal-api-token-{{ $token->id }}" title="{{ $text('revoke') }}" aria-label="{{ $text('revoke') }}"><i class="wb-icon wb-icon-ban" aria-hidden="true"></i></button>@endif<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-personal-api-token-{{ $token->id }}" title="{{ $text('delete') }}" aria-label="{{ $text('delete') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button></div></td></tr>@endforeach
        </tbody></table></div>@endif</div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $tokens, 'ariaLabel' => $systemText('pagination'), 'compact' => true])
    </section>
@endsection

@push('overlays')
    @foreach ($tokens as $token)
        <div class="wb-modal wb-modal-lg" id="edit-personal-api-token-{{ $token->id }}" role="dialog" aria-modal="true" aria-labelledby="edit-personal-api-token-{{ $token->id }}-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header"><div><h2 class="wb-modal-title" id="edit-personal-api-token-{{ $token->id }}-title">{{ $systemText('edit_title') }}</h2><p class="wb-text-sm wb-text-muted">{{ $systemText('edit_description') }}</p></div><button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $systemText('close_edit') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button></div>
                <form method="POST" action="{{ route('admin.profile.api-tokens.update', $token) }}">@csrf @method('PUT')
                    <div class="wb-modal-body wb-stack wb-gap-4 wb-api-token-modal-body">
                        <div class="wb-field"><label class="wb-label" for="edit_personal_api_token_name_{{ $token->id }}">{{ $text('name') }}</label><input id="edit_personal_api_token_name_{{ $token->id }}" name="name" class="wb-input" value="{{ $token->name }}" required maxlength="120"></div>
                        <div class="wb-field"><div class="wb-label">{{ $text('sites') }}</div><div class="wb-api-token-capability-list">@foreach ($sites as $site)<label class="wb-check wb-api-token-capability-option" for="edit_personal_api_token_{{ $token->id }}_site_{{ $site->id }}"><input id="edit_personal_api_token_{{ $token->id }}_site_{{ $site->id }}" type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, $token->allowed_site_ids ?? []))><span class="wb-api-token-capability-copy"><strong>{{ $site->name }}</strong><span class="wb-text-sm wb-text-muted">{{ $site->handle }}</span></span></label>@endforeach</div></div>
                        @include('webblocks-cms::admin.system.api-tokens.partials.capability-checkboxes', ['fieldPrefix' => 'edit_personal_api_token_'.$token->id.'_capability', 'selectedCapabilities' => $capabilitiesPresenter->capabilitiesFor($token), 'showErrors' => false])
                        <div class="wb-field"><label class="wb-label" for="edit_personal_api_token_expiry_{{ $token->id }}">{{ $text('expires') }}</label><select id="edit_personal_api_token_expiry_{{ $token->id }}" name="expires_in_days" class="wb-select">@foreach ([30, 90, 365] as $days)<option value="{{ $days }}">{{ $days }} {{ $text('days') }}</option>@endforeach</select></div>
                    </div>
                    <div class="wb-modal-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ $systemText('save_changes') }}</button><button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $systemText('cancel') }}</button></div>
                </form>
            </div>
        </div>

        <div class="wb-modal wb-modal-xl" id="activity-personal-api-token-{{ $token->id }}" role="dialog" aria-modal="true" aria-labelledby="activity-personal-api-token-{{ $token->id }}-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header"><div><h2 class="wb-modal-title" id="activity-personal-api-token-{{ $token->id }}-title">{{ $systemText('activity_title') }}</h2><p class="wb-text-sm wb-text-muted">{{ $systemText('activity_description', ['name' => $token->name]) }}</p></div><button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $systemText('close_activity') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button></div>
                <div class="wb-modal-body wb-api-token-activity-modal-body">
                    @if (! $activitySchemaReady)<div class="wb-alert wb-alert-warning">{{ $systemText('activity_storage_not_ready') }}</div>
                    @elseif ($token->activityLogs->isEmpty())<div class="wb-empty"><div class="wb-empty-title">{{ $systemText('no_activity_title') }}</div><div class="wb-empty-text">{{ $systemText('no_activity_text') }}</div></div>
                    @else<div class="wb-api-token-activity-list" role="list" aria-label="{{ $systemText('activity_entries') }}">@foreach ($token->activityLogs as $activity)<article class="wb-card wb-card-muted wb-api-token-activity-item" role="listitem"><div class="wb-card-body wb-stack wb-gap-3"><div class="wb-api-token-activity-header"><div class="wb-stack wb-gap-2"><div class="wb-cluster wb-gap-2 wb-flex-wrap"><span class="wb-status-pill {{ $activity->statusBadgeClass() }}">{{ $activity->statusLabel() }}</span><span class="wb-text-sm wb-text-muted">{{ $activity->occurredAtLabel() }}</span></div><code class="wb-api-token-activity-request"><span class="wb-api-token-activity-method">{{ $activity->method }}</span> <span>{{ $activity->path }}</span></code>@if ($activity->route_name)<span class="wb-text-sm wb-text-muted wb-api-token-activity-route">{{ $activity->route_name }}</span>@endif</div><div class="wb-api-token-activity-client"><span class="wb-text-xs wb-text-muted">{{ $systemText('client') }}</span><strong>{{ $activity->ip ?? $systemText('unknown_ip') }}</strong></div></div><dl class="wb-api-token-activity-meta"><div><dt>{{ $systemText('capability') }}</dt><dd>@if ($activity->required_capability)<code>{{ $activity->required_capability }}</code>@else<span class="wb-text-muted">{{ $systemText('none') }}</span>@endif</dd></div>@if ($activity->user_agent)<div class="wb-api-token-activity-user-agent"><dt>{{ $systemText('user_agent') }}</dt><dd title="{{ $activity->user_agent }}">{{ \Illuminate\Support\Str::limit($activity->user_agent, 140) }}</dd></div>@endif</dl></div></article>@endforeach</div>@endif
                </div>
                <div class="wb-modal-footer"><button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $systemText('close') }}</button></div>
            </div>
        </div>

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'revoke-personal-api-token-'.$token->id, 'title' => $text('revoke'), 'description' => $text('revoke_confirm'), 'action' => route('admin.profile.api-tokens.revoke', $token), 'method' => 'POST', 'submitLabel' => $text('revoke')])<p><strong>{{ $token->name }}</strong></p>@endcomponent
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'delete-personal-api-token-'.$token->id, 'title' => $text('delete'), 'description' => $text('delete_confirm'), 'action' => route('admin.profile.api-tokens.destroy', $token), 'method' => 'DELETE', 'submitLabel' => $text('delete')])<p><strong>{{ $token->name }}</strong></p>@endcomponent
    @endforeach
@endpush

@push('scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-copy.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-capabilities.js'])
@endpush
