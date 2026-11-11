@php
    $text = static fn (string $key) => __('webblocks-cms::panel-languages.'.$key);
    $tabs = ['catalogs', 'import', 'settings'];
    $tab = old('panel_language_tab', request()->query('tab'));
    $tab = in_array($tab, $tabs, true) ? $tab : 'catalogs';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title')])
    @include('webblocks-cms::admin.system.panel-languages.feedback')

    <div class="wb-tabs" data-wb-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $text('title') }}">
            @foreach ($tabs as $section)
                <button type="button" role="tab" id="panel-languages-tab-{{ $section }}"
                    class="wb-tabs-btn{{ $tab === $section ? ' is-active' : '' }}"
                    data-wb-tab="panel-languages-{{ $section }}"
                    aria-controls="panel-languages-{{ $section }}" aria-selected="{{ $tab === $section ? 'true' : 'false' }}"
                    @if ($tab !== $section) tabindex="-1" @endif>{{ $text($section) }}</button>
            @endforeach
        </div>

        <div class="wb-tabs-panels">
            <div class="wb-tabs-panel{{ $tab === 'catalogs' ? ' is-active' : '' }}" id="panel-languages-catalogs" role="tabpanel" aria-labelledby="panel-languages-tab-catalogs">
                <div class="wb-card">
                    <div class="wb-card-body">
                        @if ($catalogs->isEmpty())
                            <div class="wb-empty">
                                <div class="wb-empty-title">{{ $text('empty') }}</div>
                                <div class="wb-empty-action">
                                    <a class="wb-btn wb-btn-primary" href="{{ route('admin.system.panel-languages.index', ['tab' => 'import']) }}">{{ $text('import') }}</a>
                                </div>
                            </div>
                        @else
                            @include('webblocks-cms::admin.system.panel-languages.catalogs')
                        @endif
                    </div>
                    @include('webblocks-cms::admin.partials.pagination', ['paginator' => $catalogs, 'ariaLabel' => $text('catalogs'), 'compact' => true])
                </div>
            </div>

            <div class="wb-tabs-panel{{ $tab === 'import' ? ' is-active' : '' }}" id="panel-languages-import" role="tabpanel" aria-labelledby="panel-languages-tab-import">
                <form method="POST" action="{{ route('admin.system.panel-languages.store') }}" enctype="multipart/form-data" class="wb-card">
                    @csrf
                    <input type="hidden" name="panel_language_tab" value="import">
                    <div class="wb-card-body">
                        <div class="wb-field">
                            <label class="wb-label" for="panel-language-catalog">{{ $text('file') }}</label>
                            <input class="wb-file" type="file" id="panel-language-catalog" name="catalog" accept=".json,application/json"
                                aria-describedby="panel-language-file-help" @if ($tab === 'import' && $errors->has('catalog')) aria-invalid="true" @endif required>
                            <p class="wb-text-sm wb-text-muted wb-m-0" id="panel-language-file-help">{{ $text('help') }}</p>
                        </div>
                    </div>
                    <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                        <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.template') }}">{{ $text('template') }}</a>
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $text('upload') }}</button>
                    </div>
                </form>
            </div>

            <div class="wb-tabs-panel{{ $tab === 'settings' ? ' is-active' : '' }}" id="panel-languages-settings" role="tabpanel" aria-labelledby="panel-languages-tab-settings">
                <form method="POST" action="{{ route('admin.system.panel-languages.default') }}" class="wb-card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="panel_language_tab" value="settings">
                    <div class="wb-card-body">
                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                            <label class="wb-label" for="panel-language-default">{{ $text('default') }}</label>
                            <div>
                                <select class="wb-select" id="panel-language-default" name="code"
                                    @if ($tab === 'settings' && $errors->any()) aria-invalid="true" @endif required>
                                    @foreach ($options as $code => $label)
                                        <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="wb-card-footer wb-cluster wb-cluster-end">
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $text('save_default') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('overlays')
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
                <input type="hidden" name="panel_language_tab" value="catalogs">
                <p><strong lang="{{ $catalog->language_code }}">{{ $catalog->name }}</strong> <code>{{ $catalog->language_code }}</code></p>
            @endcomponent
        @endif
    @endforeach
@endpush
