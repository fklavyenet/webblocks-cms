@php
    $translationPublicUrl = $translation->exists ? $page->publicUrl($locale->code) : null;
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.index').'">Pages</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', $page).'">'.$page->title.'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.strtoupper($locale->code).'</span></li></ol></nav>',
        'title' => $pageTitle,
        'description' => 'Edit page name and routing for this locale. Block content stays shared in this phase.',
        'actions' => $translationPublicUrl ? '<a href="'.$translationPublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>Open</span></a>' : '',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label>Site</label>
                            <input class="wb-input" type="text" value="{{ $page->site?->name }}" disabled>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label>Locale</label>
                            <input class="wb-input" type="text" value="{{ $locale->name }} ({{ strtoupper($locale->code) }})" disabled>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_name">Name</label>
                            <input id="translation_name" name="name" class="wb-input" type="text" value="{{ old('name', $translation->name) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_slug">Slug</label>
                            <input id="translation_slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $translation->slug) }}" required>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong>Routing</strong>
                            <div class="wb-text-sm wb-text-muted">Default locale stays prefixless. Non-default locales use a prefixed public URL.</div>
                            <div><strong>Path</strong><br>{{ $translation->slug ? $page->publicPath($locale->code) : ($locale->is_default ? '/' : '/'.$locale->code) }}</div>
                            @if ($locale->is_default)
                                <div class="wb-text-sm wb-text-muted">This locale uses the canonical prefixless public route.</div>
                            @else
                                <div class="wb-text-sm wb-text-muted">This locale uses the `{{ '/'.$locale->code }}` public prefix.</div>
                            @endif
                        </div>
                    </div>
                </div>

                <x-admin.form-actions :cancel-url="route('admin.pages.edit', $page)" />
            </form>
        </div>
    </div>
@endsection
