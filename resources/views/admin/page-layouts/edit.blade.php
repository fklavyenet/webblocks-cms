@php
    $indexUrl = route('admin.page-layouts.index');
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $pageLayoutsText = fn (string $key, array $replace = []) => $adminTranslator->admin('page_layouts.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageLayoutsText('edit_page_layout'), 'heading' => $pageLayoutsText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($pageLayoutsText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$indexUrl.'">'.e($pageLayoutsText('title')).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($pageLayout->name).'</span></li></ol></nav>',
        'title' => $pageLayoutsText('edit_page_layout'),
        'context' => '<span><code>'.e($pageLayout->handle).'</code></span>',
        'actions' => '<a href="'.$indexUrl.'" class="wb-btn wb-btn-secondary">'.e($pageLayoutsText('back_to_page_layouts')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <form method="POST" action="{{ route('admin.page-layouts.update', $pageLayout) }}" class="wb-stack wb-gap-0">
                    @csrf
                    @method('PUT')

                    <div class="wb-card-header"><strong>{{ $pageLayoutsText('layout_settings') }}</strong></div>

                    <div class="wb-card-body">
                        @include('webblocks-cms::admin.page-layouts._form', ['pageLayout' => $pageLayout])
                    </div>

                    <div class="wb-card-footer">
                        <x-webblocks-cms::admin.form-actions :cancel-url="$indexUrl" :submit-label="$pageLayoutsText('save_changes')" />
                    </div>
                </form>
            </div>

            <div class="wb-stack wb-gap-4">
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header"><strong>{{ $pageLayoutsText('usage') }}</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                        <div><strong>{{ $pageLayoutsText('handle_label') }}</strong> <code>{{ $pageLayout->handle }}</code></div>
                        <div><strong>{{ $pageLayoutsText('status_label') }}</strong> <span class="wb-status-pill {{ $pageLayout->statusBadgeClass() }}">{{ $pageLayout->is_active ? $pageLayoutsText('active') : $pageLayoutsText('inactive') }}</span></div>
                        <div><strong>{{ $pageLayoutsText('ownership_label') }}</strong> {{ $pageLayout->is_system ? $pageLayoutsText('system') : $pageLayoutsText('custom') }}</div>
                        <div><strong>{{ $pageLayoutsText('body_class_label') }}</strong> <code>{{ $pageLayout->body_class ?: '-' }}</code></div>
                    </div>
                </div>

                @if ($pageLayout->is_system)
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>{{ $pageLayoutsText('system_layout') }}</strong></div>
                        <div class="wb-card-body wb-text-sm wb-text-muted">
                            {{ $pageLayoutsText('system_layout_help') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @include('webblocks-cms::admin.page-layouts.partials.slots-card', ['pageLayout' => $pageLayout])
    </div>
@endsection
