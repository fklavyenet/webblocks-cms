@extends('webblocks-cms::layouts.admin', ['title' => 'CMS API Tokens', 'heading' => 'CMS API Tokens'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'CMS API Tokens',
        'description' => 'Create and revoke bearer tokens for trusted local AI and operator tools.',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

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
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Create Token</strong>
        </div>

        <form method="POST" action="{{ route('admin.system.api-tokens.store') }}">
            @csrf
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-field">
                    <label class="wb-label" for="api_token_name">Name</label>
                    <input id="api_token_name" name="name" type="text" class="wb-input" value="{{ old('name') }}" placeholder="Local AI - Osman MacBook" required maxlength="120">
                    @error('name')
                        <div class="wb-field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="wb-card-footer">
                <button type="submit" class="wb-btn wb-btn-primary">Create Token</button>
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
                                        </div>
                                    </td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
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
    @endforeach
@endpush
