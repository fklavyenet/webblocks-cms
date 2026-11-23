@php
    $text = static fn (string $key) => __('webblocks-cms::panel-languages.'.$key);
    $defaultErrors = $errors->getBag('panelLanguageDefault');
    $importError = old('panel_language_operation') === 'import' && $errors->has('catalog');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('language_packs'), 'heading' => $text('language_packs')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('language_packs')])
        @slot('actions')
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#panel-language-default-modal"
                    aria-controls="panel-language-default-modal" aria-haspopup="dialog">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i> {{ $text('default') }}
            </button>
            @if ($extensionsAvailable)
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.extensions.index') }}">
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i> {{ $text('extensions_browse') }}
                </a>
            @endif
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.panel-languages.feedback', ['inlineErrors' => $importError ? ['catalog'] : []])

    <section class="wb-card" aria-labelledby="panel-languages-import-title">
        <div class="wb-card-header">
            <h2 id="panel-languages-import-title" class="wb-card-title">{{ $text('manual_import') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.system.panel-languages.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="panel_language_operation" value="import">
            <div class="wb-card-body">
                <div class="wb-field">
                    <label class="wb-label" for="panel-language-catalog">{{ $text('file') }} (.json)</label>
                    <input class="wb-file" type="file" id="panel-language-catalog" name="catalog" accept=".json,application/json"
                        aria-describedby="panel-language-file-help{{ $importError ? ' panel-language-file-error' : '' }}"
                        @if ($importError) aria-invalid="true" @endif required>
                    <p class="wb-text-sm wb-text-muted wb-m-0" id="panel-language-file-help">{{ $text('help') }}</p>
                    @if ($importError)
                        <p class="wb-alert wb-alert-danger" id="panel-language-file-error" role="alert">{{ $errors->first('catalog') }}</p>
                    @endif
                </div>
            </div>
            <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.template') }}">
                    <i class="wb-icon wb-icon-download" aria-hidden="true"></i> {{ $text('template') }}
                </a>
                <button type="submit" class="wb-btn wb-btn-primary" data-wb-busy data-wb-busy-label="{{ __('webblocks-cms::modal-submit.uploading') }}">
                    <span data-wb-busy-text>{{ $text('upload') }}</span>
                </button>
            </div>
        </form>
    </section>

    <section class="wb-card" aria-labelledby="panel-languages-catalogs-title">
        <div class="wb-card-header">
            <h2 id="panel-languages-catalogs-title" class="wb-card-title">{{ $text('imported_catalogs') }}</h2>
        </div>
        <div class="wb-card-body">
            @if ($catalogs->isEmpty())
                <div class="wb-empty"><div class="wb-empty-title">{{ $text('empty') }}</div></div>
            @else
                @include('webblocks-cms::admin.system.panel-languages.catalogs')
            @endif
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $catalogs, 'ariaLabel' => $text('imported_catalogs'), 'compact' => true])
    </section>
@endsection

@push('overlays')
    <dialog class="wb-modal" id="panel-language-default-modal" aria-labelledby="panel-language-default-title"
            @if ($defaultErrors->any() || request()->query('tab') === 'settings') data-wb-admin-autoload-overlay @endif>
        <form method="POST" action="{{ route('admin.system.panel-languages.default') }}" class="wb-modal-dialog">
            @csrf
            @method('PUT')
            <div class="wb-modal-header">
                <h2 id="panel-language-default-title" class="wb-modal-title">{{ $text('default') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="wb-modal-body">
                <div class="wb-field">
                    <label class="wb-label" for="panel-language-default">{{ $text('language') }}</label>
                    <select class="wb-select" id="panel-language-default" name="code"
                            aria-describedby="panel-language-default-help{{ $defaultErrors->any() ? ' panel-language-default-error' : '' }}"
                            @if ($defaultErrors->any()) aria-invalid="true" @endif required>
                        @foreach ($options as $code => $label)
                            <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="wb-text-sm wb-text-muted wb-m-0" id="panel-language-default-help">{{ $text('default_help') }}</p>
                    @if ($defaultErrors->any())
                        <p class="wb-alert wb-alert-danger" id="panel-language-default-error" role="alert">{{ $defaultErrors->first() }}</p>
                    @endif
                </div>
            </div>
            <div class="wb-modal-footer">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::admin.common.cancel') }}</button>
                <button type="submit" class="wb-btn wb-btn-primary">{{ $text('save_default') }}</button>
            </div>
        </form>
    </dialog>

    @foreach ($catalogs as $catalog)
        @if ((int) $catalog->active_catalog_id !== (int) $catalog->id)
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'delete-panel-catalog-'.$catalog->id,
                'title' => $text('delete'),
                'description' => $text('delete_description'),
                'action' => route('admin.system.panel-languages.destroy', $catalog->id),
                'submitLabel' => $text('delete'),
                'nativeDialog' => true,
            ])
                <p><strong lang="{{ $catalog->language_code }}">{{ $catalog->name }}</strong> <code>{{ $catalog->language_code }}</code></p>
            @endcomponent
        @endif
    @endforeach
@endpush
