@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);

    $statusBadges = [
        'new' => 'wb-badge-primary',
        'triaged' => 'wb-badge-warning',
        'converted' => 'wb-badge-success',
        'rejected' => 'wb-badge-danger',
        'closed' => 'wb-badge',
    ];

    // A status or type Workbench adds later falls back to its raw value
    // rather than rendering a missing translation key.
    $statusLabel = static fn (string $status): string => $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode) === 'support.statuses.'.$status
        ? $status
        : $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode);
    $typeLabel = static fn (string $type): string => $adminTranslator->admin('support.types.'.$type, $adminLocaleCode) === 'support.types.'.$type
        ? $type
        : $adminTranslator->admin('support.types.'.$type, $adminLocaleCode);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('support.title'), 'heading' => $adminText('support.title')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('support.title'),
            'description' => $adminText('support.intro'),
        ])

        @include('webblocks-cms::admin.partials.flash')

        @if ($canManageConnection)
            <section class="wb-card">
                <div class="wb-card-header">
                    <h2 class="wb-card-title">{{ $adminText('support.connection_title') }}</h2>
                </div>
                <div class="wb-card-body wb-stack wb-gap-4">
                    @if (! $connection)
                        <p class="wb-text-muted">{{ $adminText('support.connection_intro') }}</p>
                        <form method="POST" action="{{ route('admin.support.connection.store') }}" class="wb-stack wb-gap-3">
                            @csrf
                            <div class="wb-field">
                                <label class="wb-label" for="supportProviderUrl">{{ $adminText('support.provider_url') }}</label>
                                <input id="supportProviderUrl" class="wb-input" type="url" name="provider_url" value="{{ old('provider_url', 'https://workbench.webblocksui.com') }}" required>
                                <p class="wb-text-muted wb-text-sm">{{ $adminText('support.provider_url_help') }}</p>
                                @error('provider_url')<div class="wb-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div><button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.connect') }}</button></div>
                        </form>
                    @elseif ($connection->status === 'pending')
                        <p>{{ $adminText('support.activation_instructions', ['provider' => $connection->provider_name]) }}</p>
                        <div class="wb-alert wb-alert-info">
                            <div><strong>{{ $adminText('support.activation_code') }}:</strong> {{ $connection->activation_user_code }}</div>
                        </div>
                        <div class="wb-cluster">
                            <a class="wb-btn wb-btn-primary" href="{{ $connection->activation_url }}" target="_blank" rel="noopener noreferrer">{{ $adminText('support.open_activation') }}</a>
                            <form method="POST" action="{{ route('admin.support.connection.refresh') }}">@csrf<button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('support.check_activation') }}</button></form>
                            <button class="wb-btn wb-btn-danger" type="button" data-wb-toggle="modal" data-wb-target="#support-disconnect-modal" aria-haspopup="dialog">{{ $adminText('support.disconnect') }}</button>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table">
                                <tbody>
                                    <tr><th>{{ $adminText('support.provider') }}</th><td>{{ $connection->provider_name }}</td></tr>
                                    <tr><th>{{ $adminText('support.connection_status') }}</th><td>{{ $connection->status }}</td></tr>
                                    @if ($connection->plan_name)<tr><th>{{ $adminText('support.plan') }}</th><td>{{ $connection->plan_name }}</td></tr>@endif
                                </tbody>
                            </table>
                        </div>
                        <button class="wb-btn wb-btn-danger" type="button" data-wb-toggle="modal" data-wb-target="#support-disconnect-modal" aria-haspopup="dialog">{{ $adminText('support.disconnect') }}</button>
                    @endif
                </div>
            </section>
        @endif

        @if (! $configured && ! $canManageConnection)
            <div class="wb-alert wb-alert-warning">
                <div>{{ $adminText('support.not_configured') }}</div>
            </div>
        @elseif ($error)
            <div class="wb-alert wb-alert-danger">
                <div>{{ $error }}</div>
            </div>
        @endif

        <section class="wb-card">
            <div class="wb-card-header wb-cluster">
                <h2 class="wb-card-title">{{ $adminText('support.description') }}</h2>
                @if ($configured)
                    <a class="wb-btn wb-btn-primary wb-ms-auto" href="{{ route('admin.support.create') }}">
                        <i class="wb-icon wb-icon-plus" aria-hidden="true"></i>{{ $adminText('support.new') }}
                    </a>
                @endif
            </div>
            <div class="wb-card-body">
                @if (count($tickets) > 0)
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ $adminText('support.col_subject') }}</th>
                                    <th>{{ $adminText('support.col_type') }}</th>
                                    <th>{{ $adminText('support.col_status') }}</th>
                                    <th>{{ $adminText('support.col_submitted') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>{{ $ticket['number'] }}</td>
                                        <td>
                                            <a href="{{ route('admin.support.show', ['ticket' => $ticket['id']]) }}">{{ $ticket['title'] }}</a>
                                        </td>
                                        <td>{{ $typeLabel($ticket['type']) }}</td>
                                        <td>
                                            <span class="wb-badge {{ $statusBadges[$ticket['status']] ?? 'wb-badge' }}">
                                                {{ $statusLabel($ticket['status']) }}
                                            </span>
                                        </td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($ticket['created_at'])->isoFormat('LLL') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($configured && ! $error)
                    <p class="wb-text-muted">{{ $adminText('support.empty') }}</p>
                @endif
            </div>
        </section>
    </div>
@endsection

@if ($canManageConnection && $connection)
    @push('overlays')
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'support-disconnect-modal',
            'title' => $adminText('support.disconnect_title'),
            'description' => $adminText('support.disconnect_description'),
            'action' => route('admin.support.connection.destroy'),
            'method' => 'DELETE',
            'submitLabel' => $adminText('support.disconnect'),
            'cancelLabel' => $adminText('common.cancel'),
        ])
            <p>{{ $adminText('support.disconnect_description') }}</p>
        @endcomponent
    @endpush
@endif
