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

    <section class="wb-card" aria-labelledby="theme-install-title">
        <div class="wb-card-header">
            <h2 id="theme-install-title" class="wb-card-title">{{ $text('manual_install') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.system.themes.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="theme_operation" value="upload">
            <div class="wb-card-body">
                <div class="wb-field">
                    <label class="wb-label" for="theme-package">{{ $text('package') }} (.zip)</label>
                    <input id="theme-package" name="package" type="file" class="wb-file" accept=".zip,application/zip"
                        aria-describedby="theme-package-help{{ $uploadError ? ' theme-package-error' : '' }}"
                        @if ($uploadError) aria-invalid="true" @endif required>
                    <p id="theme-package-help" class="wb-text-sm wb-text-muted wb-m-0">{{ $text('upload_help') }}</p>
                    @if ($uploadError)
                        <p id="theme-package-error" class="wb-alert wb-alert-danger" role="alert">{{ $errors->first('package') }}</p>
                    @endif
                </div>
            </div>
            <div class="wb-card-footer wb-cluster wb-cluster-end">
                <button type="submit" class="wb-btn wb-btn-primary" data-wb-busy data-wb-busy-label="{{ __('webblocks-cms::modal-submit.uploading') }}">
                    <span data-wb-busy-text>{{ $text('upload') }}</span>
                </button>
            </div>
        </form>
    </section>

    <section class="wb-card" aria-labelledby="installed-themes-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <h2 id="installed-themes-title" class="wb-card-title">{{ $text('versions') }}</h2>
            <form method="GET" action="{{ $indexUrl }}" class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <div class="wb-input-wrap">
                    <label for="theme-search" class="wb-sr-only">{{ __('webblocks-cms::admin.common.search') }}</label>
                    <i class="wb-icon wb-icon-search wb-input-icon" aria-hidden="true"></i>
                    <input id="theme-search" name="search" class="wb-input" type="search" value="{{ $search }}" placeholder="{{ $text('search_placeholder') }}">
                </div>
                <button type="submit" class="wb-btn wb-btn-primary">{{ __('webblocks-cms::admin.common.filter') }}</button>
                @if ($search !== '')
                    <a href="{{ $indexUrl }}" class="wb-btn wb-btn-secondary">{{ __('webblocks-cms::admin.common.reset') }}</a>
                @endif
            </form>
        </div>
        <div class="wb-card-body">
            @if ($versions->isEmpty())
                <div class="wb-empty">
                    <p class="wb-empty-title">{{ $text($search === '' ? 'empty' : 'no_matches') }}</p>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ $text('theme') }}</th>
                                <th scope="col">{{ __('webblocks-cms::admin.system_plugins_index.version') }}</th>
                                <th scope="col">{{ $text('compatibility') }}</th>
                                <th scope="col">{{ $text('layouts') }}</th>
                                <th scope="col">{{ $text('installed_at') }}</th>
                                <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versions as $version)
                                @php
                                    $uploaded = \Illuminate\Support\Carbon::parse($version->created_at);
                                    $deleteLabel = $text('delete_named', ['name' => $version->name, 'version' => $version->version]);
                                @endphp
                                <tr>
                                    <th scope="row" class="wb-table-break">{{ $version->name }}</th>
                                    <td>{{ $version->version }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $version->compatible ? 'wb-status-active' : 'wb-status-danger' }}">
                                            {{ $text($version->compatible ? 'compatible' : 'incompatible_short') }}
                                        </span>
                                        @unless ($version->compatible)
                                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $text('incompatible') }}</p>
                                        @endunless
                                    </td>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $versions->appends($searchQuery), 'ariaLabel' => $text('versions'), 'compact' => true])
    </section>
@endsection

@push('overlays')
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
