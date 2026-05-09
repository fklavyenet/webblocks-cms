@php
    $translationPublicUrl = $translation->exists ? $page->publicUrl($locale->code) : null;
    $pagesIndexUrl = $pagesIndexUrl ?? session('page_return_url') ?? route('admin.pages.index', ['site' => $page->site_id]);
    $pageReturnUrl = $pageReturnUrl ?? $pagesIndexUrl;
    $siteName = $page->site?->name ?? 'Site';
    $selectedOgImage = old('og_image_asset_id')
        ? $assetPickerAssets->firstWhere('id', (int) old('og_image_asset_id'))
        : $translation->ogImage;
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">Pages</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.$siteName.'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]).'">'.$page->title.'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.strtoupper($locale->code).'</span></li></ol></nav>',
        'title' => $pageTitle,
        'description' => 'Edit page name, routing, and SEO overrides for this locale. Block content stays shared in this phase.',
        'actions' => $translationPublicUrl ? '<a href="'.$translationPublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>Open</span></a>' : '',
    ])

    @include('admin.partials.flash')

    <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif
        <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label>Site</label>
                            <input class="wb-input" type="text" value="{{ $page->site?->name }}{{ $page->site?->canonicalDomain() ? ' | '.$page->site->canonicalDomain() : '' }}" disabled>
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
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>SEO</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">These fields override the site-level SEO defaults for this locale only. Leave any field blank to fall back to the page title or site default where available.</div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_title">SEO title</label>
                            <input id="translation_seo_title" name="seo_title" class="wb-input" type="text" value="{{ old('seo_title', $translation->seo_title) }}">
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_description">SEO description</label>
                            <textarea id="translation_seo_description" name="seo_description" class="wb-input" rows="5">{{ old('seo_description', $translation->seo_description) }}</textarea>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_keywords">SEO keywords</label>
                            <input id="translation_seo_keywords" name="seo_keywords" class="wb-input" type="text" value="{{ old('seo_keywords', $translation->seo_keywords) }}">
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="translation_og_title">Open Graph title</label>
                            <input id="translation_og_title" name="og_title" class="wb-input" type="text" value="{{ old('og_title', $translation->og_title) }}">
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_og_description">Open Graph description</label>
                            <textarea id="translation_og_description" name="og_description" class="wb-input" rows="5">{{ old('og_description', $translation->og_description) }}</textarea>
                        </div>

                        <div class="wb-stack wb-gap-2 wb-field">
                            <label for="og_image_asset_id">Open Graph image</label>
                            @include('admin.media.asset-picker-panel', [
                                'name' => 'translation-og-image',
                                'title' => 'Open Graph image',
                                'inputId' => 'og_image_asset_id',
                                'fieldName' => 'og_image_asset_id',
                                'selectedAsset' => $selectedOgImage,
                                'assetPickerAssets' => $assetPickerAssets,
                                'assetPickerFolders' => $assetPickerFolders,
                                'accept' => 'image',
                                'buttonLabel' => 'Choose social image',
                                'replaceLabel' => 'Replace social image',
                                'clearLabel' => 'Remove social image',
                            ])
                            <div class="wb-text-sm wb-text-muted">Overrides the site-level social image for this locale when the selected image has a public URL.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-admin.form-actions :cancel-url="route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl])" />
    </form>
@endsection
