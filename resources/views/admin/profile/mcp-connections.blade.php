@php
    $text = static fn (string $key) => __('webblocks-cms::mcp.'.$key);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('connections'), 'heading' => $text('connections')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $text('connections'),
        'description' => $text('connections_help'),
        'actions' => '<a href="'.e(route('admin.profile.edit')).'" class="wb-btn wb-btn-secondary">'.e($text('back_profile')).'</a>',
    ])
    @include('webblocks-cms::admin.partials.flash')

    @if (!$enabled)
        <div class="wb-alert wb-alert-info" role="status">{{ $text('disabled_notice') }}</div>
    @endif

    <div class="wb-card">
        @if ($connections === null || $connections->isEmpty())
            <div class="wb-card-body">{{ $text('no_connections') }}</div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <thead><tr>
                        <th scope="col">{{ $text('client') }}</th>
                        <th scope="col">{{ $text('site') }}</th>
                        <th scope="col">{{ $text('connection_permissions') }}</th>
                        <th scope="col">{{ $text('expires') }}</th>
                        <th scope="col">{{ $text('actions') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($connections as $connection)
                            <tr>
                                <th scope="row">
                                    {{ $connection->client_name }}
                                    <div class="wb-text-sm wb-text-muted">{{ $text('client_id') }} {{ substr($connection->oauth_client_id, -8) }}</div>
                                </th>
                                <td>{{ $connection->site_name }}</td>
                                <td>
                                    @if ($connection->pending)
                                        <p class="wb-text-muted">{{ $text('connection_pending') }}</p>
                                    @endif
                                    <p>{{ $text($connection->drafts_created_only ? 'drafts_created' : 'drafts_site') }}</p>
                                    @if ($connection->drafts_created_only && $connection->draft_names->isNotEmpty())
                                        <p>{{ $text('authorized_drafts') }} {{ $connection->draft_names->join(', ') }}</p>
                                    @endif
                                    <ul>
                                        @foreach ($connection->permissions as $label => $available)
                                            <li>{{ $text($label) }}@if (!$available) <span class="wb-text-muted">({{ $text('permission_unavailable') }})</span>@endif</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::parse($connection->expires_at)->isoFormat('lll') }}</td>
                                <td>
                                    <button type="button" class="wb-btn wb-btn-danger wb-btn-sm" data-wb-toggle="modal"
                                        data-wb-target="#revoke-mcp-{{ $connection->row_id }}">{{ $text('revoke') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $connections, 'ariaLabel' => $text('connections'), 'compact' => true])
        @endif
    </div>
@endsection

@push('overlays')
    @foreach ($connections ?? [] as $connection)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'revoke-mcp-'.$connection->row_id,
            'title' => $text('revoke'),
            'description' => $text('revoke_confirm'),
            'action' => route('admin.profile.mcp-connections.destroy', $connection->row_id),
            'method' => 'DELETE',
            'submitLabel' => $text('revoke'),
        ])
            <p><strong>{{ $connection->client_name }}</strong></p>
            <p>{{ $connection->site_name }} · {{ \Illuminate\Support\Carbon::parse($connection->expires_at)->isoFormat('lll') }}</p>
        @endcomponent
    @endforeach
@endpush
