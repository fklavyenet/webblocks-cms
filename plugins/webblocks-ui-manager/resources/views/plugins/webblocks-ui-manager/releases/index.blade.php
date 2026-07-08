@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $uiManagerText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->plugin('webblocks-ui-manager', 'admin.'.$key, $adminLocale, $replace, $fallback);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $uiManagerText('releases.title'), 'heading' => $uiManagerText('releases.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $uiManagerText('releases.title'),
        'description' => $uiManagerText('releases.description'),
        'actions' => auth()->user()?->can('webblocks-ui-manager.manage')
            ? '<a href="'.route('webblocks.plugins.webblocks_ui_manager.releases.create').'" class="wb-btn wb-btn-primary">'.e($uiManagerText('releases.new_release')).'</a>'
            : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $uiManagerText('releases.tracked_releases') }}</strong>
        </div>
        <div class="wb-card-body">
            @if ($releases->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $uiManagerText('releases.empty_title') }}</div>
                    <div class="wb-empty-text">{{ $uiManagerText('releases.empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>{{ $uiManagerText('releases.version') }}</th>
                                <th>{{ $uiManagerText('releases.status') }}</th>
                                <th>{{ $uiManagerText('releases.artifacts') }}</th>
                                <th>{{ $uiManagerText('releases.cdn_target') }}</th>
                                <th>{{ $uiManagerText('releases.prepared') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($releases as $release)
                                <tr>
                                    <td><a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}"><strong>{{ $release->label ?: $release->version }}</strong></a></td>
                                    <td><span class="wb-status {{ $release->statusBadgeClass() }}">{{ $uiManagerText('statuses.'.$release->statusLabel(), fallback: ucfirst($release->statusLabel())) }}</span></td>
                                    <td>{{ $release->artifacts_count }}</td>
                                    <td><code>{{ $release->cdn_base_path ?: '-' }}</code></td>
                                    <td>{{ $release->prepared_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td><a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $uiManagerText('releases.open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('webblocks-cms::admin.partials.pagination', ['paginator' => $releases])
@endsection
