@extends('webblocks-cms::layouts.admin', ['title' => 'CMS API Tokens', 'heading' => 'CMS API Tokens'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'CMS API Tokens',
        'description' => 'Create and revoke bearer tokens for trusted local AI and operator tools.',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $schemaReady)
        <div class="wb-alert wb-alert-warning">
            <div class="wb-stack wb-gap-2">
                <strong>API token storage is not ready.</strong>
                <div>Run System Update again to apply the required CMS API token schema, then return to this page.</div>
            </div>
        </div>
    @endif

    @if ($createdToken)
        <div class="wb-alert wb-alert-success">
            <div class="wb-stack wb-gap-3">
                <div>
                    <strong>Token created: {{ $createdTokenName }}</strong>
                    <div class="wb-text-sm">Copy this token now. The full token is shown only once.</div>
                </div>

                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="created_cms_api_token">Full token</label>
                    <textarea id="created_cms_api_token" class="wb-textarea" rows="2" readonly>{{ $createdToken }}</textarea>
                </div>

                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="created_cms_api_token_env">Local .env example</label>
                    <textarea id="created_cms_api_token_env" class="wb-textarea" rows="3" readonly>WEBBLOCKS_CMS_URL={{ $currentCmsUrl }}
WEBBLOCKS_CMS_API_TOKEN={{ $createdToken }}</textarea>
                </div>
            </div>
        </div>

        <section class="wb-card">
            <div class="wb-card-header">
                <strong>How to use this token</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">API Base URL</span>
                        <code>{{ $apiBaseUrl }}</code>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Start here</span>
                        <code>GET /webadmin/api</code>
                    </div>
                </div>
                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="cms_api_token_headers">Headers</label>
                    <textarea id="cms_api_token_headers" class="wb-textarea" rows="4" readonly>Authorization: Bearer &lt;token&gt;
Accept: application/json
Content-Type: application/json</textarea>
                </div>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api" target="_blank" rel="noopener">Discovery</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/openapi.json" target="_blank" rel="noopener">OpenAPI</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/ai-guide" target="_blank" rel="noopener">AI Guide</a>
                    <a class="wb-btn wb-btn-secondary" href="/webadmin/api/examples/contact-page" target="_blank" rel="noopener">Contact Example</a>
                </div>
                <div class="wb-stack wb-gap-2">
                    <label class="wb-label" for="cms_api_setup_prompt">AI setup prompt</label>
                    <textarea id="cms_api_setup_prompt" class="wb-textarea" rows="3" readonly>Use this WebBlocks CMS API base URL and token. First call the API discovery endpoint. Follow the returned OpenAPI, content contract, examples, and recommended next steps. Do not use browser automation or admin UI clicks.</textarea>
                </div>
            </div>
        </section>
    @endif

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>API Discovery Quick Start</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-2 wb-gap-3">
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">API Base URL</span>
                    <code>/webadmin/api</code>
                </div>
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">First request</span>
                    <code>GET /webadmin/api</code>
                </div>
            </div>
            <div class="wb-text-sm wb-text-muted">Use Bearer auth with JSON headers. Discovery links point AI/operator tools to OpenAPI, the AI guide, content contract, examples, validate, and apply endpoints without exposing token values.</div>
        </div>
    </section>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Create Token</strong>
        </div>

        @php($selectedCapabilities = old('capabilities', $defaultCapabilities))

        <form method="POST" action="{{ route('admin.system.api-tokens.store') }}">
            @csrf
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-field">
                    <label class="wb-label" for="api_token_name">Name</label>
                    <input id="api_token_name" name="name" type="text" class="wb-input" value="{{ old('name') }}" placeholder="Example: Local AI, Homepage Builder, Operator Tool" required maxlength="120">
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
                <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $schemaReady)>Create Token</button>
            </div>
        </form>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Tokens</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $tokens->total() }}</span>
            </div>
        </div>

        <div class="wb-card-body">
            @if ($tokens->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No API tokens</div>
                    <div class="wb-empty-text">Create a token for a trusted local AI or operator tool.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Preview</th>
                                <th>Created</th>
                                <th>Last Used</th>
                                <th>Actions</th>
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
                                                <span class="wb-text-sm wb-text-muted">Created by {{ $token->creator->name }}</span>
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
                                                title="Edit token"
                                                aria-label="Edit token"
                                                aria-haspopup="dialog"
                                            >
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                data-wb-toggle="modal"
                                                data-wb-target="#revoke-cms-api-token-{{ $token->id }}"
                                                title="{{ $token->isRevoked() ? 'Token already revoked' : 'Revoke token' }}"
                                                aria-label="Revoke token"
                                                @disabled($token->isRevoked())
                                            >
                                                <i class="wb-icon wb-icon-ban" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-cms-api-token-{{ $token->id }}"
                                                title="Delete token"
                                                aria-label="Delete token"
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

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $tokens, 'ariaLabel' => 'CMS API tokens pagination', 'compact' => true])
    </div>
@endsection

@push('overlays')
    @foreach ($tokens as $token)
        <div class="wb-modal wb-modal-lg" id="edit-cms-api-token-{{ $token->id }}" role="dialog" aria-modal="true" aria-labelledby="edit-cms-api-token-{{ $token->id }}-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title" id="edit-cms-api-token-{{ $token->id }}-title">Edit API Token</h2>
                        <p class="wb-text-sm wb-text-muted">Update this token's name and capabilities. The token value and audit metadata are not shown or changed.</p>
                    </div>

                    <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close Edit API Token">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.system.api-tokens.update', $token) }}">
                    @csrf
                    @method('PUT')

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-field">
                            <label class="wb-label" for="edit_cms_api_token_name_{{ $token->id }}">Name</label>
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
                            <button type="submit" class="wb-btn wb-btn-primary">Save Changes</button>
                            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'revoke-cms-api-token-'.$token->id,
            'title' => 'Revoke API Token',
            'description' => 'Revoking this token immediately blocks API access for the connected tool.',
            'action' => route('admin.system.api-tokens.revoke', $token),
            'method' => 'POST',
            'submitLabel' => 'Revoke Token',
        ])
            <p>Revoke <strong>{{ $token->name }}</strong>? The audit record will remain visible.</p>
        @endcomponent

        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-cms-api-token-'.$token->id,
            'title' => 'Delete API Token',
            'description' => $token->isRevoked()
                ? 'Deleting this revoked token permanently removes its audit row from the token list.'
                : 'Deleting this active token immediately removes API access for the connected tool and permanently removes the token row.',
            'action' => route('admin.system.api-tokens.destroy', $token),
            'method' => 'DELETE',
            'submitLabel' => 'Delete Token',
        ])
            <p>Delete <strong>{{ $token->name }}</strong>? This cannot be undone.</p>

            @if (! $token->isRevoked())
                <div class="wb-alert wb-alert-warning">
                    This token is active. Any tool currently using it will lose API access immediately.
                </div>
            @endif
        @endcomponent
    @endforeach
@endpush
