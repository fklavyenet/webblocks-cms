@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@php
    $selectedLocaleIds = collect(old('locale_ids', $site->exists ? $site->locales->pluck('id') : $locales->where('is_default', true)->pluck('id')))
        ->map(fn ($id) => (int) $id)
        ->values();
    $selectedFavicon = old('favicon_asset_id')
        ? $assetPickerAssets->firstWhere('id', (int) old('favicon_asset_id'))
        : $site->faviconAsset;
    $selectedSocialImage = old('social_image_asset_id')
        ? $assetPickerAssets->firstWhere('id', (int) old('social_image_asset_id'))
        : $site->socialImageAsset;
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Keep technical site setup separate from public branding. Each site can map a canonical primary host plus optional aliases from the Domains screen, its enabled locales, and public metadata fallbacks.',
        'actions' => $site->exists ? '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.sites.domains.index', $site).'" class="wb-btn wb-btn-secondary">Manage Domains</a><a href="'.route('admin.pages.index', ['site' => $site->id]).'" class="wb-btn wb-btn-secondary">Open Pages</a></div>' : '',
    ])

    @include('admin.partials.flash')

    <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header"><strong>Site</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <div class="wb-stack-2 wb-field">
                        <label for="site_name">Name</label>
                        <input id="site_name" name="name" class="wb-input" type="text" value="{{ old('name', $site->name) }}" required>
                        <div class="wb-text-sm wb-text-muted">Internal admin name for this site record.</div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_handle">Handle</label>
                        <input id="site_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $site->handle) }}" required>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_domain">Domain</label>
                        <input id="site_domain" name="domain" class="wb-input" type="text" value="{{ old('domain', $site->domain) }}">
                        <div class="wb-text-sm wb-text-muted">This remains the site's canonical primary domain. Use the dedicated Domains screen to manage aliases, activation state, and redirect-to-primary behavior.</div>
                    </div>

                    <label class="wb-nowrap">
                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $site->is_primary))>
                        <span>Primary</span>
                    </label>
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Locales</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-text-sm wb-text-muted">Each site must keep at least one locale enabled. The system default locale is always forced on.</div>
                    @foreach ($locales as $locale)
                        @if ($locale->is_default)
                            <input type="hidden" name="locale_ids[]" value="{{ $locale->id }}">
                        @endif

                        @if (! $locale->is_enabled && $selectedLocaleIds->contains($locale->id))
                            <input type="hidden" name="locale_ids[]" value="{{ $locale->id }}">
                        @endif

                        <label class="wb-nowrap">
                            <input
                                type="checkbox"
                                name="locale_ids[]"
                                value="{{ $locale->id }}"
                                @checked($selectedLocaleIds->contains($locale->id))
                                @disabled($locale->is_default || ! $locale->is_enabled)
                            >
                            <span>{{ strtoupper($locale->code) }} - {{ $locale->name }}@if ($locale->is_default) (Default) @elseif (! $locale->is_enabled) (Disabled) @endif</span>
                        </label>
                    @endforeach

                    @if ($locales->contains(fn ($locale) => ! $locale->is_default && ! $locale->is_enabled))
                        <div class="wb-text-sm wb-text-muted">Disabled locales stay unavailable for new site assignments until they are enabled again from the Locales screen.</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($site->exists)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Domains</strong></div>
                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-text-sm wb-text-muted">Public DNS, Nginx, SSL, and server routing are handled outside CMS by Herne Panel or the server operator. CMS only resolves hosts that already point to this install.</div>
                    <div class="wb-text-sm wb-text-muted">Primary domain: <strong>{{ $site->canonicalDomain() ?: 'Not set' }}</strong></div>
                    <div class="wb-text-sm wb-text-muted">Assigned domain records: {{ $site->siteDomains->count() }}</div>
                    <div>
                        <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-btn wb-btn-secondary">Open Domains</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header"><strong>Branding</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">These values affect the public site only. They do not change the fixed WebBlocks CMS admin product identity.</div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_display_name">Public display name</label>
                        <input id="site_display_name" name="display_name" class="wb-input" type="text" value="{{ old('display_name', $site->display_name) }}">
                        <div class="wb-text-sm wb-text-muted">Optional public-facing name override. Falls back to the internal site name when empty.</div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_tagline">Tagline</label>
                        <input id="site_tagline" name="tagline" class="wb-input" type="text" value="{{ old('tagline', $site->tagline) }}">
                    </div>

                    <div class="wb-stack wb-gap-2 wb-field">
                        <label for="favicon_asset_id">Favicon</label>
                        @include('admin.media.asset-picker-panel', [
                            'name' => 'site-favicon',
                            'title' => 'Favicon',
                            'inputId' => 'favicon_asset_id',
                            'fieldName' => 'favicon_asset_id',
                            'selectedAsset' => $selectedFavicon,
                            'assetPickerAssets' => $assetPickerAssets,
                            'assetPickerFolders' => $assetPickerFolders,
                            'accept' => 'image',
                            'buttonLabel' => 'Choose favicon',
                            'replaceLabel' => 'Replace favicon',
                            'clearLabel' => 'Remove favicon',
                        ])
                        <div class="wb-text-sm wb-text-muted">Used for public favicon link tags when the selected image has a public URL.</div>
                    </div>

                    <div class="wb-stack wb-gap-2 wb-field">
                        <label for="social_image_asset_id">Social image</label>
                        @include('admin.media.asset-picker-panel', [
                            'name' => 'site-social-image',
                            'title' => 'Social image',
                            'inputId' => 'social_image_asset_id',
                            'fieldName' => 'social_image_asset_id',
                            'selectedAsset' => $selectedSocialImage,
                            'assetPickerAssets' => $assetPickerAssets,
                            'assetPickerFolders' => $assetPickerFolders,
                            'accept' => 'image',
                            'buttonLabel' => 'Choose social image',
                            'replaceLabel' => 'Replace social image',
                            'clearLabel' => 'Remove social image',
                        ])
                        <div class="wb-text-sm wb-text-muted">Used as fallback sharing artwork for the public site.</div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>SEO Defaults</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">These are site-level public metadata fallbacks. Page-level SEO overrides are intentionally not part of this phase.</div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_seo_title">Default meta title</label>
                        <input id="site_seo_title" name="seo_title" class="wb-input" type="text" value="{{ old('seo_title', $site->seo_title) }}">
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_seo_description">Default meta description</label>
                        <textarea id="site_seo_description" name="seo_description" class="wb-input" rows="5">{{ old('seo_description', $site->seo_description) }}</textarea>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_seo_keywords">Meta keywords</label>
                        <input id="site_seo_keywords" name="seo_keywords" class="wb-input" type="text" value="{{ old('seo_keywords', $site->seo_keywords) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-body">
                <x-admin.form-actions
                    :cancel-url="route('admin.sites.index')"
                    :delete-href="$site->exists && isset($siteDeleteReport) ? route('admin.sites.delete', $site) : null"
                    :delete-disabled="$site->exists && isset($siteDeleteReport) ? ! $siteDeleteReport->canDelete : false"
                />
            </div>
        </div>
    </form>
@endsection
