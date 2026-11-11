@php
    $text = static fn (string $key) => __('webblocks-cms::themes.'.$key);
    $preset = $site->resolvedPublicThemePreset();
    $label = static fn ($change) => $change?->theme_version_id ? $change->name.' · '.$change->version : $text('builtin').' · '.str($preset)->headline();
    $back = '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme'])).'">'.e($text('back')).'</a>';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('site_title'), 'heading' => $text('site_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('site_title'), 'description' => $site->name, 'actions' => $back])
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
                                <thead><tr><th scope="col">{{ $text('theme') }}</th><th scope="col">{{ $text('layouts') }}</th><th scope="col">{{ $text('actions') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($versions as $version)
                                        <tr>
                                            <th scope="row">{{ $version->name }} · {{ $version->version }}</th>
                                            <td>{{ implode(', ', $version->layouts) }}</td>
                                            <td>
                                                @if ($version->compatible)
                                                    <a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ route('admin.sites.themes.index', ['site' => $site, 'version' => $version->id]) }}#theme-preview">{{ $text('preview') }}</a>
                                                @else
                                                    {{ $text('incompatible') }}
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

        @if ($candidate)
            @include('webblocks-cms::admin.sites.themes.preview')
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
    @if ($confirmation)
        @include('webblocks-cms::admin.sites.themes.confirm', [
            'id' => 'theme-activate', 'operation' => 'activate', 'description' => $text('activate_help'),
            'target' => $candidate->name.' · '.$candidate->version,
        ])
    @endif
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
