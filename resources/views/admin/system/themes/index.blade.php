@php
    $text = static fn (string $key) => __('webblocks-cms::themes.'.$key);
    $indexUrl = route('admin.system.themes.index');
    $searchQuery = $search === '' ? [] : ['search' => $search];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title')])
    @include('webblocks-cms::admin.system.themes.feedback')

    <div class="wb-stack wb-gap-4">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-filters', [
                    'action' => $indexUrl,
                    'search' => [
                        'id' => 'theme-search', 'name' => 'search',
                        'label' => __('webblocks-cms::admin.common.search'),
                        'value' => $search, 'placeholder' => $text('search_placeholder'),
                    ],
                    'showReset' => $search !== '',
                    'resetUrl' => $indexUrl,
                ])
            </div>
        </div>

        <section class="wb-card" aria-labelledby="installed-themes-title">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-flex-wrap">
                <strong id="installed-themes-title">{{ $text('versions') }}</strong>
                <button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#upload-theme" aria-haspopup="dialog">{{ $text('upload_new') }}</button>
            </div>
            <div class="wb-card-body">
                @if ($versions->isEmpty())
                    <p class="wb-text-muted">{{ $text($search === '' ? 'empty' : 'no_matches') }}</p>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $text('theme') }}</th>
                                    <th scope="col">{{ $text('compatibility') }}</th>
                                    <th scope="col">{{ $text('layouts') }}</th>
                                    <th scope="col">{{ $text('uploaded_at') }}</th>
                                    <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($versions as $version)
                                    @php($uploaded = \Illuminate\Support\Carbon::parse($version->created_at))
                                    <tr>
                                        <th scope="row">{{ $version->name }} · {{ $version->version }}</th>
                                        <td>{{ $text($version->compatible ? 'compatible' : 'incompatible') }}</td>
                                        <td>{{ implode(', ', $version->layouts) }}</td>
                                        <td><time datetime="{{ $uploaded->toIso8601String() }}">{{ $uploaded->format('Y-m-d H:i') }}</time></td>
                                        <td class="wb-table-actions">
                                            <div class="wb-action-group">
                                                @if ($version->uses)
                                                    <button type="button" class="wb-action-btn wb-action-btn-delete" title="{{ $text('retained') }}" aria-label="{{ $text('retained') }}" disabled>
                                                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-theme-{{ $version->id }}" title="{{ $text('delete') }}" aria-label="{{ $text('delete') }}" aria-haspopup="dialog">
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
    </div>
@endsection

@push('overlays')
    <dialog class="wb-modal" id="upload-theme" aria-labelledby="upload-theme-title" @if ($errors->has('package') && old('theme_operation') === 'upload') data-wb-admin-autoload-overlay @endif>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="upload-theme-title">{{ $text('upload_new') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close_named', ['name' => $text('upload_new')]) }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </button>
            </div>
            <form method="post" action="{{ route('admin.system.themes.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="theme_operation" value="upload">
                <div class="wb-modal-body wb-field">
                    <label class="wb-label" for="theme-package">{{ $text('package') }}</label>
                    <input id="theme-package" name="package" type="file" class="wb-file" accept=".zip,application/zip"
                        aria-describedby="theme-package-help{{ $errors->has('package') && old('theme_operation') === 'upload' ? ' theme-package-error' : '' }}"
                        @if ($errors->has('package') && old('theme_operation') === 'upload') aria-invalid="true" @endif required>
                    <p id="theme-package-help" class="wb-text-sm wb-text-muted">{{ $text('upload_help') }}</p>
                    @if (old('theme_operation') === 'upload')
                        @error('package')
                            <p id="theme-package-error" class="wb-alert wb-alert-danger" role="alert">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
                <div class="wb-modal-footer">
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $text('cancel') }}</button>
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $text('upload') }}</button>
                </div>
            </form>
        </div>
    </dialog>

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
