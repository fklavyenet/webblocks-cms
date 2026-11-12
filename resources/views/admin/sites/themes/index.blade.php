@php
    $text = static fn (string $key) => __('webblocks-cms::themes.'.$key);
    $title = __('webblocks-cms::themes.site_heading', ['site' => $site->name]);
    $preset = $site->resolvedPublicThemePreset();
    $label = static fn ($change) => $change?->theme_version_id ? $change->name.' · '.$change->version : $text('builtin').' · '.str($preset)->headline();
    $back = '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme'])).'">'.e($text('back')).'</a>';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $title, 'actions' => $back])
    @include('webblocks-cms::admin.system.themes.feedback')

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-body wb-cluster wb-cluster-between wb-flex-wrap">
                <div><strong>{{ $text('current') }}</strong> {{ $label($current) }}</div>
                @if ($current?->theme_version_id)
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#theme-restore-builtin">{{ $text('restore_builtin') }}</button>
                @endif
            </div>
        </div>

        @if ($canPreview)
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $text('versions') }}</strong></div>
                <div class="wb-card-body">
                    @if ($versions->isEmpty())
                        <p class="wb-text-muted">{{ $text('empty') }}</p>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table">
                                <thead><tr><th scope="col">{{ $text('theme') }}</th><th scope="col">{{ $text('layouts') }}</th><th scope="col">{{ $text('site_status') }}</th><th scope="col" class="wb-table-actions">{{ $text('actions') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($versions as $version)
                                        @php
                                            $active = (int) $version->id === (int) $current?->theme_version_id;
                                            $issue = match (true) {
                                                ! $version->compatible => $text('incompatible'),
                                                ! $version->intact => $text('damaged'),
                                                $version->missing_layouts !== [] => __('webblocks-cms::themes.missing_site_layouts', ['layouts' => implode(', ', $version->missing_layouts)]),
                                                default => null,
                                            };
                                        @endphp
                                        <tr>
                                            <th scope="row">{{ $version->name }} · {{ $version->version }}</th>
                                            <td>{{ implode(', ', $version->layouts) }}</td>
                                            <td>
                                                @if ($active)
                                                    <span class="wb-badge wb-badge-success">{{ $text('site_status_active') }}</span>
                                                @endif
                                                @if ($issue !== null)
                                                    <span class="wb-badge wb-badge-warning">{{ $text('site_status_incompatible') }}</span>
                                                    <div class="wb-text-sm wb-text-muted">{{ $issue }}</div>
                                                @elseif (! $active)
                                                    @if ($version->has_pages)
                                                        <span class="wb-badge">{{ $text('site_status_available') }}</span>
                                                    @else
                                                        <span class="wb-text-sm wb-text-muted">{{ $text('no_pages') }}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="wb-table-actions">
                                                @if ($version->intact)
                                                    <div class="wb-action-group">
                                                        <a class="wb-action-btn wb-action-btn-view"
                                                            href="{{ route('admin.sites.themes.comparison', ['site' => $site, 'version' => $version->id]) }}"
                                                            title="{{ $text('preview') }}" aria-label="{{ $text('preview') }}">
                                                            <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                        </a>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $versions->withQueryString(), 'ariaLabel' => $text('versions'), 'compact' => true])
            </div>
        @endif

        @if ($history->isNotEmpty())
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $text('history') }}</strong></div>
                <div class="wb-card-body wb-table-wrap">
                    <table class="wb-table">
                        <thead><tr><th scope="col">{{ $text('theme') }}</th><th scope="col">{{ $text('button_corners') }}</th><th scope="col">{{ $text('changed_at') }}</th><th scope="col">{{ $text('actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($history as $change)
                                <tr>
                                    <th scope="row">{{ $label($change) }}</th>
                                    <td>{{ $text($change->button_corners) }}</td>
                                    <td>{{ $change->created_at }}</td>
                                    <td>
                                        @if ((int) $change->id === (int) $current?->id)
                                            {{ $text('current') }}
                                        @else
                                            <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-toggle="modal" data-wb-target="#theme-restore-{{ $change->id }}">{{ $text('restore') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $history->withQueryString(), 'ariaLabel' => $text('history'), 'compact' => true])
            </div>
        @endif
    </div>
@endsection

@push('overlays')
    @if ($current?->theme_version_id)
        @include('webblocks-cms::admin.sites.themes.confirm', [
            'id' => 'theme-restore-builtin', 'operation' => 'restore', 'description' => $text('restore_help'),
            'target' => $label(null), 'revision' => null,
        ])
    @endif
    @foreach ($history as $change)
        @continue((int) $change->id === (int) $current?->id)
        @include('webblocks-cms::admin.sites.themes.confirm', [
            'id' => 'theme-restore-'.$change->id, 'operation' => 'restore', 'description' => $text('restore_help'),
            'target' => $label($change), 'revision' => $change->id,
        ])
    @endforeach
@endpush
