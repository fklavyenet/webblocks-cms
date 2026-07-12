@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.api_tokens.index.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $schemaReady)
        <div class="wb-alert wb-alert-warning">
            <div class="wb-stack wb-gap-2">
                <strong>{{ $adminText('storage_not_ready') }}</strong>
                <div>{{ $adminText('storage_not_ready_help') }}</div>
            </div>
        </div>
    @endif

    @if ($createdToken)
        <div class="wb-alert wb-alert-success">
            <div class="wb-stack wb-gap-3">
                <div>
                    <strong>{{ $adminText('token_created', ['name' => $createdTokenName]) }}</strong>
                    <div class="wb-text-sm">{{ $adminText('token_created_help') }}</div>
                </div>

                <div class="wb-stack wb-gap-2">
                    <div class="wb-cluster wb-gap-2">
                        <label class="wb-label" for="created_cms_api_token">{{ $adminText('full_token') }}</label>
                        <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon" data-wb-copy-target="created_cms_api_token" aria-label="{{ $adminText('copy_full_token') }}" title="{{ $adminText('copy_full_token') }}">
                            <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                        </button>
                    </div>
                    <textarea id="created_cms_api_token" class="wb-textarea" rows="2" readonly>{{ $createdToken }}</textarea>
                </div>

                <div class="wb-stack wb-gap-2">
                    <div class="wb-cluster wb-gap-2">
                        <label class="wb-label" for="created_cms_api_token_env">{{ $adminText('env_example') }}</label>
                        <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon" data-wb-copy-target="created_cms_api_token_env" aria-label="{{ $adminText('copy_env_example') }}" title="{{ $adminText('copy_env_example') }}">
                            <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                        </button>
                    </div>
                    <textarea id="created_cms_api_token_env" class="wb-textarea" rows="3" readonly>WEBBLOCKS_CMS_API_URL={{ $apiBaseUrl }}
WEBBLOCKS_CMS_API_TOKEN={{ $createdToken }}</textarea>
                </div>
                <div class="wb-text-sm" data-wb-api-token-copy-feedback data-copy-success="{{ $adminText('copied') }}" data-copy-failed="{{ $adminText('copy_failed') }}" role="status" aria-live="polite"></div>
            </div>
        </div>

        <section class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('usage_title') }}</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('api_base_url') }}</span>
                        <code>{{ $apiBaseUrl }}</code>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('start_here') }}</span>
                        <code>{{ $adminText('discovery_request') }}</code>
                    </div>
                </div>
                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="cms_api_token_headers">{{ $adminText('headers') }}</label>
                    <textarea id="cms_api_token_headers" class="wb-textarea" rows="4" readonly>{{ $adminText('headers_example') }}</textarea>
                </div>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api" target="_blank" rel="noopener">{{ $adminText('discovery') }}</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/openapi.json" target="_blank" rel="noopener">OpenAPI</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/ai-guide" target="_blank" rel="noopener">{{ $adminText('ai_guide') }}</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/examples/contact-page" target="_blank" rel="noopener">{{ $adminText('contact_example') }}</a>
                </div>
                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="cms_api_setup_prompt">{{ $adminText('ai_setup_prompt') }}</label>
                    <textarea id="cms_api_setup_prompt" class="wb-textarea" rows="3" readonly>{{ $adminText('ai_setup_prompt_text') }}</textarea>
                </div>
            </div>
        </section>
    @endif

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $adminText('quick_start') }}</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-2 wb-gap-3">
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">{{ $adminText('api_base_url') }}</span>
                    <code>/webadmin/api</code>
                </div>
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">{{ $adminText('first_request') }}</span>
                    <code>{{ $adminText('discovery_request') }}</code>
                </div>
            </div>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('quick_start_help') }}</div>
        </div>
    </section>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $adminText('create_token') }}</strong>
        </div>

        @php($selectedCapabilities = old('capabilities', $defaultCapabilities))

        <form method="POST" action="{{ route('admin.system.api-tokens.store') }}">
            @csrf
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-field">
                    <label class="wb-label" for="api_token_name">{{ $adminText('name') }}</label>
                    <input id="api_token_name" name="name" type="text" class="wb-input" value="{{ old('name') }}" placeholder="{{ $adminText('name_placeholder') }}" required maxlength="120">
                    @error('name')
                        <div class="wb-field-error">{{ $message }}</div>
                    @enderror
                </div>

                @include('webblocks-cms::admin.system.api-tokens.partials.capability-checkboxes', [
                    'fieldPrefix' => 'api_token_capability',
                    'selectedCapabilities' => $selectedCapabilities,
                    'showErrors' => true,
                ])
            </div>

            <div class="wb-card-footer">
                <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $schemaReady)>{{ $adminText('create_token') }}</button>
            </div>
        </form>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('tokens') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $tokens->total() }}</span>
            </div>
        </div>

        <div class="wb-card-body">
            @if ($tokens->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('name') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('preview') }}</th>
                                <th>{{ $adminText('created') }}</th>
                                <th>{{ $adminText('last_used') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $token->name }}</strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $capabilitiesPresenter->summary($token) }}</span>
                                            @if ($token->creator)
                                                <span class="wb-text-sm wb-text-muted">{{ $adminText('created_by', ['name' => $token->creator->name]) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><span class="wb-status-pill {{ $token->statusBadgeClass() }}">{{ $token->statusLabel() }}</span></td>
                                    <td><code>{{ $token->token_preview }}</code></td>
                                    <td>{{ $token->createdAtLabel() }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $token->lastUsedAtLabel() }}</span>
                                            @if ($token->last_used_ip)
                                                <span class="wb-text-sm wb-text-muted">{{ $token->last_used_ip }}</span>
                                            @endif
                                            @if ($token->last_used_user_agent)
                                                <span class="wb-text-sm wb-text-muted">{{ Str::limit($token->last_used_user_agent, 80) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-edit"
                                                data-wb-toggle="modal"
                                                data-wb-target="#edit-cms-api-token-{{ $token->id }}"
                                                title="{{ $adminText('edit_token') }}"
                                                aria-label="{{ $adminText('edit_token') }}"
                                                aria-haspopup="dialog"
                                            >
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-view"
                                                data-wb-toggle="modal"
                                                data-wb-target="#activity-cms-api-token-{{ $token->id }}"
                                                title="{{ $adminText('view_activity') }}"
                                                aria-label="{{ $adminText('view_activity') }}"
                                                aria-haspopup="dialog"
                                                @disabled(! $activitySchemaReady)
                                            >
                                                <i class="wb-icon wb-icon-history" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                data-wb-toggle="modal"
                                                data-wb-target="#revoke-cms-api-token-{{ $token->id }}"
                                                title="{{ $token->isRevoked() ? $adminText('already_revoked') : $adminText('revoke_token') }}"
                                                aria-label="{{ $adminText('revoke_token') }}"
                                                @disabled($token->isRevoked())
                                            >
                                                <i class="wb-icon wb-icon-ban" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-cms-api-token-{{ $token->id }}"
                                                title="{{ $adminText('delete_token') }}"
                                                aria-label="{{ $adminText('delete_token') }}"
                                                aria-haspopup="dialog"
                                            >
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $tokens, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
    </div>
@endsection

@push('overlays')
    @foreach ($tokens as $token)
        <div class="wb-modal wb-modal-lg" id="edit-cms-api-token-{{ $token->id }}" role="dialog" aria-modal="true" aria-labelledby="edit-cms-api-token-{{ $token->id }}-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title" id="edit-cms-api-token-{{ $token->id }}-title">{{ $adminText('edit_title') }}</h2>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('edit_description') }}</p>
                    </div>

                    <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close_edit') }}">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.system.api-tokens.update', $token) }}">
                    @csrf
                    @method('PUT')

                    <div class="wb-modal-body wb-stack wb-gap-4 wb-api-token-modal-body">
                        <div class="wb-field">
                            <label class="wb-label" for="edit_cms_api_token_name_{{ $token->id }}">{{ $adminText('name') }}</label>
                            <input id="edit_cms_api_token_name_{{ $token->id }}" name="name" type="text" class="wb-input" value="{{ $token->name }}" required maxlength="120">
                        </div>

                        @include('webblocks-cms::admin.system.api-tokens.partials.capability-checkboxes', [
                            'fieldPrefix' => 'edit_cms_api_token_'.$token->id.'_capability',
                            'selectedCapabilities' => $capabilitiesPresenter->capabilitiesFor($token),
                            'showErrors' => false,
                        ])
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                            <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('save_changes') }}</button>
                            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('cancel') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-modal wb-modal-xl" id="activity-cms-api-token-{{ $token->id }}" role="dialog" aria-modal="true" aria-labelledby="activity-cms-api-token-{{ $token->id }}-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title" id="activity-cms-api-token-{{ $token->id }}-title">{{ $adminText('activity_title') }}</h2>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('activity_description', ['name' => $token->name]) }}</p>
                    </div>

                    <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close_activity') }}">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="wb-modal-body wb-api-token-activity-modal-body">
                    @if (! $activitySchemaReady)
                        <div class="wb-alert wb-alert-warning">
                            {{ $adminText('activity_storage_not_ready') }}
                        </div>
                    @elseif ($token->activityLogs->isEmpty())
                        <div class="wb-empty">
                            <div class="wb-empty-title">{{ $adminText('no_activity_title') }}</div>
                            <div class="wb-empty-text">{{ $adminText('no_activity_text') }}</div>
                        </div>
                    @else
                        <div class="wb-api-token-activity-list" role="list" aria-label="{{ $adminText('activity_entries') }}">
                            @foreach ($token->activityLogs as $activity)
                                <article class="wb-card wb-card-muted wb-api-token-activity-item" role="listitem">
                                    <div class="wb-card-body wb-stack wb-gap-3">
                                        <div class="wb-api-token-activity-header">
                                            <div class="wb-stack wb-gap-2">
                                                <div class="wb-cluster wb-gap-2 wb-flex-wrap">
                                                    <span class="wb-status-pill {{ $activity->statusBadgeClass() }}">{{ $activity->statusLabel() }}</span>
                                                    <span class="wb-text-sm wb-text-muted">{{ $activity->occurredAtLabel() }}</span>
                                                </div>

                                                <code class="wb-api-token-activity-request">
                                                    <span class="wb-api-token-activity-method">{{ $activity->method }}</span>
                                                    <span>{{ $activity->path }}</span>
                                                </code>

                                                @if ($activity->route_name)
                                                    <span class="wb-text-sm wb-text-muted wb-api-token-activity-route">{{ $activity->route_name }}</span>
                                                @endif
                                            </div>

                                            <div class="wb-api-token-activity-client">
                                                <span class="wb-text-xs wb-text-muted">{{ $adminText('client') }}</span>
                                                <strong>{{ $activity->ip ?? $adminText('unknown_ip') }}</strong>
                                            </div>
                                        </div>

                                        <dl class="wb-api-token-activity-meta">
                                            <div>
                                                <dt>{{ $adminText('capability') }}</dt>
                                                <dd>
                                                    @if ($activity->required_capability)
                                                        <code>{{ $activity->required_capability }}</code>
                                                    @else
                                                        <span class="wb-text-muted">{{ $adminText('none') }}</span>
                                                    @endif
                                                </dd>
                                            </div>

                                            @if ($activity->user_agent)
                                                <div class="wb-api-token-activity-user-agent">
                                                    <dt>{{ $adminText('user_agent') }}</dt>
                                                    <dd title="{{ $activity->user_agent }}">{{ Str::limit($activity->user_agent, 140) }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="wb-modal-footer">
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('close') }}</button>
                </div>
            </div>
        </div>

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'revoke-cms-api-token-'.$token->id,
            'title' => $adminText('revoke_title'),
            'description' => $adminText('revoke_description'),
            'action' => route('admin.system.api-tokens.revoke', $token),
            'method' => 'POST',
            'submitLabel' => $adminText('revoke_submit'),
        ])
            <p>{{ $adminText('revoke_confirm_prefix') }} <strong>{{ $token->name }}</strong>? {{ $adminText('revoke_confirm_suffix') }}</p>
        @endcomponent

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-cms-api-token-'.$token->id,
            'title' => $adminText('delete_title'),
            'description' => $token->isRevoked()
                ? $adminText('delete_revoked_description')
                : $adminText('delete_active_description'),
            'action' => route('admin.system.api-tokens.destroy', $token),
            'method' => 'DELETE',
            'submitLabel' => $adminText('delete_submit'),
        ])
            <p>{{ $adminText('delete_confirm_prefix') }} <strong>{{ $token->name }}</strong>? {{ $adminText('cannot_be_undone') }}</p>

            @if (! $token->isRevoked())
                <div class="wb-alert wb-alert-warning">
                    {{ $adminText('active_delete_warning') }}
                </div>
            @endif
        @endcomponent
    @endforeach
@endpush

@push('scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-copy.js'])
@endpush
