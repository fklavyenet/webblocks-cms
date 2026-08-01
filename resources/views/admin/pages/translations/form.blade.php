@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_translations.'.$key, $adminLocale, $replace);
  $translationPublicUrl = $translation->exists ? $page->publicUrl($locale->code) : null;
  $pagesIndexUrl = $pagesIndexUrl ?? session('page_return_url') ?? route('admin.pages.index', ['site' => $page->site_id]);
  $pageReturnUrl = $pageReturnUrl ?? $pagesIndexUrl;
  $siteName = $page->site?->name ?? $adminText('site');
  $localizedPageTitle = $adminText($translation->exists ? 'edit_title' : 'create_title', ['title' => $page->title, 'locale' => strtoupper($locale->code)]);
  $currentPath = old('path', $translation->path ?: ($translation->slug ? \WebBlocks\Cms\Models\PageTranslation::pathFromSlug($translation->slug) : ($locale->is_default ? '/' : '/'.$locale->code)));
  $selectedOgImage = old('og_image_media_id', old('og_image_asset_id'))
    ? $assetPickerAssets->firstWhere('id', (int) old('og_image_media_id', old('og_image_asset_id')))
    : $translation->ogImage;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($adminText('pages')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($siteName).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]).'">'.e($page->title).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.strtoupper($locale->code).'</span></li></ol></nav>',
        'title' => $localizedPageTitle,
        'description' => $adminText('description'),
        'actions' => $translationPublicUrl ? '<a href="'.$translationPublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>'.e($adminText('open')).'</span></a>' : '',
    ])

    @include('webblocks-cms::admin.partials.flash')

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
                            <label>{{ $adminText('site') }}</label>
                            <input class="wb-input" type="text" value="{{ $page->site?->name }}{{ $page->site?->canonicalDomain() ? ' | '.$page->site->canonicalDomain() : '' }}" disabled>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label>{{ $adminText('locale') }}</label>
                            <input class="wb-input" type="text" value="{{ $locale->name }} ({{ strtoupper($locale->code) }})" disabled>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_name">{{ $adminText('name') }}</label>
                            <input id="translation_name" name="name" class="wb-input" type="text" value="{{ old('name', $translation->name) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_slug">{{ $adminText('slug') }}</label>
                            <input id="translation_slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $translation->slug) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_path">{{ $adminText('path') }}</label>
                            <input id="translation_path" name="path" class="wb-input" type="text" value="{{ $currentPath }}" required placeholder="/games/fruit-train">
                            <div class="wb-text-sm wb-text-muted">{!! $adminText('path_help') !!}</div>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong>{{ $adminText('routing') }}</strong>
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('default_locale_help') }}</div>
                            <div><strong>{{ $adminText('current_public_path') }}</strong><br>{{ $translation->exists ? $page->publicPath($locale->code) : $currentPath }}</div>
                            @if ($locale->is_default)
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('default_public_route_help') }}</div>
                            @else
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('prefixed_public_route_help', ['prefix' => '/'.$locale->code]) }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('seo') }}</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">{{ $adminText('seo_help') }}</div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_title">{{ $adminText('seo_title') }}</label>
                            <input id="translation_seo_title" name="seo_title" class="wb-input" type="text" value="{{ old('seo_title', $translation->seo_title) }}">
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_description">{{ $adminText('seo_description') }}</label>
                            <textarea id="translation_seo_description" name="seo_description" class="wb-input" rows="5">{{ old('seo_description', $translation->seo_description) }}</textarea>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_seo_keywords">{{ $adminText('seo_keywords') }}</label>
                            <input id="translation_seo_keywords" name="seo_keywords" class="wb-input" type="text" value="{{ old('seo_keywords', $translation->seo_keywords) }}">
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="translation_og_title">{{ $adminText('og_title') }}</label>
                            <input id="translation_og_title" name="og_title" class="wb-input" type="text" value="{{ old('og_title', $translation->og_title) }}">
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="translation_og_description">{{ $adminText('og_description') }}</label>
                            <textarea id="translation_og_description" name="og_description" class="wb-input" rows="5">{{ old('og_description', $translation->og_description) }}</textarea>
                        </div>

                        <div class="wb-stack wb-gap-2 wb-field">
                            <label for="og_image_media_id_open">{{ $adminText('og_image') }}</label>
                            @include('webblocks-cms::admin.media.asset-picker-panel', [
                                'name' => 'translation-og-image',
                                'title' => $adminText('og_image'),
                                'inputId' => 'og_image_media_id',
                                'fieldName' => 'og_image_media_id',
                                'selectedAsset' => $selectedOgImage,
                                'assetPickerAssets' => $assetPickerAssets,
                                'assetPickerFolders' => $assetPickerFolders,
                                'accept' => 'image',
                                'buttonLabel' => $adminText('choose_social_image'),
                                'replaceLabel' => $adminText('replace_social_image'),
                                'clearLabel' => $adminText('remove_social_image'),
                            ])
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('og_image_help') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card-footer">
            <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl])" />
        </div>
    </form>
@endsection
