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

        @if (! $configured)
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
