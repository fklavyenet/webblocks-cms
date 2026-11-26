@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::themes.'.$key, $replace);
    $indexUrl = route('admin.system.themes.index');
    $searchQuery = $search === '' ? [] : ['search' => $search];
    $uploadError = $errors->has('package') && old('theme_operation') === 'upload';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('title')])
        @slot('actions')
            @if (app(\WebBlocks\Cms\Support\Theme\ThemeCatalog::class)->enabled())
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.catalog.index') }}">
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i> {{ $text('browse_catalog') }}
                </a>
            @endif
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.themes.feedback', ['inlineErrors' => $uploadError ? ['package'] : []])

    @include('webblocks-cms::admin.partials.extension-list-filter', [
        'id' => 'theme-search',
        'indexUrl' => $indexUrl,
        'term' => $search,
    ])

    <section class="wb-card" aria-labelledby="installed-themes-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div>
                <h2 id="installed-themes-title" class="wb-card-title">{{ $text('installed_themes') }}</h2>
                <p class="wb-card-description">{{ __('webblocks-cms::admin_lists.themes') }}</p>
            </div>
            @include('webblocks-cms::admin.partials.extension-upload-button', ['target' => 'theme-install'])
        </div>
        <div class="wb-card-body">
            @if ($versions->isEmpty())
                <div class="wb-empty">
                    <p class="wb-empty-title">{{ $search === '' ? $text('empty') : __('webblocks-cms::admin.extensions_ui.no_matches') }}</p>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('webblocks-cms::admin.extensions_ui.name') }}</th>
                                <th scope="col">{{ $text('status') }}</th>
                                <th scope="col">{{ __('webblocks-cms::admin.system_plugins_index.version') }}</th>
                                <th scope="col">{{ $text('layouts') }}</th>
                                <th scope="col">{{ $text('installed_at') }}</th>
                                <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versions as $version)
                                @php
                                    $uploaded = \Illuminate\Support\Carbon::parse($version->created_at);
                                    $detailsId = 'theme-details-'.$version->id;
                                    $deleteLabel = $text('delete_named', ['name' => $version->name, 'version' => $version->version]);
                                @endphp
                                <tr>
                                    <td class="wb-table-break">
                                        @include('webblocks-cms::admin.partials.extension-row-name', [
                                            'name' => $version->name,
                                            'identifier' => $version->handle,
                                            'target' => $detailsId,
                                        ])
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $version->compatible ? 'wb-status-active' : 'wb-status-danger' }}">
                                            {{ $text($version->compatible ? 'compatible' : 'incompatible_short') }}
                                        </span>
                                        @unless ($version->compatible)
                                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $text('incompatible') }}</p>
                                        @endunless
                                    </td>
                                    <td>{{ $version->version }}</td>
                                    <td>{{ implode(', ', $version->layouts) }}</td>
                                    <td><time datetime="{{ $uploaded->toIso8601String() }}">{{ $uploaded->format('Y-m-d H:i') }}</time></td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            @if ($version->uses)
                                                <span class="wb-text-sm wb-text-muted">{{ $text('retained') }}</span>
                                            @else
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-theme-{{ $version->id }}" data-wb-tooltip="{{ $deleteLabel }}" aria-label="{{ $deleteLabel }}" aria-haspopup="dialog">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @push('overlays')
                                    @include('webblocks-cms::admin.partials.extension-details-modal', [
                                        'id' => $detailsId,
                                        'name' => $version->name,
                                        'details' => [
                                            __('webblocks-cms::admin.extensions_ui.identifier') => $version->handle,
                                            __('webblocks-cms::admin.system_plugins_index.version') => $version->version,
                                            $text('compatibility') => $text($version->compatible ? 'compatible' : 'incompatible_short'),
                                            $text('layouts') => implode(', ', $version->layouts),
                                            $text('installed_at') => $uploaded->format('Y-m-d H:i'),
                                        ],
                                    ])
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $versions->appends($searchQuery), 'ariaLabel' => $text('installed_themes'), 'compact' => true])
    </section>
@endsection

@push('overlays')
    @include('webblocks-cms::admin.partials.extension-upload-modal', [
        'id' => 'theme-install',
        'title' => $text('manual_install'),
        'action' => route('admin.system.themes.store'),
        'fieldId' => 'theme-package',
        'field' => 'package',
        'fileLabel' => $text('package').' (.zip)',
        'help' => $text('upload_help'),
        'error' => $uploadError ? $errors->first('package') : '',
        'hidden' => ['theme_operation' => 'upload'],
    ])

    @foreach ($versions as $version)
        @if (! $version->uses)
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'delete-theme-'.$version->id,
                'title' => $text('delete'),
                'description' => $text('delete_help'),
                'action' => route('admin.system.themes.destroy', ['version' => $version->id] + $searchQuery),
                'submitLabel' => $text('delete'),
                'nativeDialog' => true,
            ])
                <p>{{ $version->name }} · {{ $version->version }}</p>
            @endcomponent
        @endif
    @endforeach
@endpush
