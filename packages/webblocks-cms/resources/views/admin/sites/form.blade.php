@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@php
    $canManageSiteSettings = $canManageSiteSettings ?? true;
    $canManageDomains = $canManageDomains ?? false;
    $siteTab = in_array(($siteTab ?? old('_site_tab', 'site')), ['site', 'locales', 'branding', 'seo-defaults', 'variables'], true)
        ? ($siteTab ?? old('_site_tab', 'site'))
        : 'site';
    $isReadOnly = ! $canManageSiteSettings;
    $selectedLocaleIds = collect(old('locale_ids', $site->exists ? $site->locales->pluck('id') : $locales->where('is_default', true)->pluck('id')))
        ->map(fn ($id) => (int) $id)
        ->values();
    $selectedFavicon = old('favicon_media_id', old('favicon_asset_id'))
        ? $assetPickerAssets->firstWhere('id', (int) old('favicon_media_id', old('favicon_asset_id')))
        : $site->faviconAsset;
    $selectedSocialImage = old('social_image_media_id', old('social_image_asset_id'))
        ? $assetPickerAssets->firstWhere('id', (int) old('social_image_media_id', old('social_image_asset_id')))
        : $site->socialImageAsset;
    $siteVariablesUi = $siteVariablesUi ?? ['requestedModal' => '', 'selectedVariable' => null, 'closeUrl' => $site->exists ? route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']) : route('admin.sites.create', ['tab' => 'variables'])];
    $tabUrl = fn (string $tab) => $site->exists
        ? route('admin.sites.edit', ['site' => $site, 'tab' => $tab])
        : route('admin.sites.create', ['tab' => $tab]);
    $actions = [];

    if ($site->exists && $canManageDomains) {
        $actions[] = '<a href="'.route('admin.sites.domains.index', $site).'" class="wb-btn wb-btn-secondary">Manage Domains</a>';
    }

    if ($site->exists) {
        $actions[] = '<a href="'.route('admin.pages.index', ['site' => $site->id]).'" class="wb-btn wb-btn-secondary">Open Pages</a>';
    }

    $pageHeaderActions = $actions !== []
        ? '<div class="wb-cluster wb-cluster-2">'.implode('', $actions).'</div>'
        : '';
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Keep technical site setup separate from public branding, locale assignment, SEO fallbacks, and reusable site variables for public content.',
        'actions' => $pageHeaderActions,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <input type="hidden" name="_site_tab" value="{{ $siteTab }}">

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-stack wb-gap-1">
                    <strong>Site Settings</strong>
                    <span class="wb-text-sm wb-text-muted">Edit the site record in focused sections without mixing in domain management.</span>
                </div>

                @if ($isReadOnly)
                    <span class="wb-status-pill wb-status-info">Read only</span>
                @endif
            </div>

            <div class="wb-card-body wb-stack wb-gap-4">
                @if ($isReadOnly)
                    <div class="wb-alert wb-alert-info">
                        <div>
                            <div class="wb-alert-title">View only</div>
                            <div>Editors can review site settings and variables for assigned sites, but only site admins and super admins can save changes.</div>
                        </div>
                    </div>
                @endif

                <div class="wb-tabs">
                    <div class="wb-tabs-nav" role="tablist" aria-label="Site settings sections">
                        @foreach ([
                            'site' => 'Site',
                            'locales' => 'Locales',
                            'branding' => 'Branding',
                            'seo-defaults' => 'SEO Defaults',
                            'variables' => 'Variables',
                        ] as $tabKey => $tabLabel)
                            <a
                                href="{{ $tabUrl($tabKey) }}"
                                class="wb-tabs-btn {{ $siteTab === $tabKey ? 'is-active' : '' }}"
                                aria-selected="{{ $siteTab === $tabKey ? 'true' : 'false' }}"
                            >{{ $tabLabel }}</a>
                        @endforeach
                    </div>

                    <div class="wb-tabs-panels">
                        <div class="wb-tabs-panel {{ $siteTab === 'site' ? 'is-active' : '' }}">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Site</strong></div>

                                <div class="wb-card-body wb-stack wb-gap-3">
                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_name">Name</label>
                                        <input id="site_name" name="name" class="wb-input" type="text" value="{{ old('name', $site->name) }}" required data-site-name-input @disabled($isReadOnly)>
                                        <div class="wb-text-sm wb-text-muted">Internal admin name for this site record.</div>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_handle">Handle</label>
                                        <input id="site_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $site->handle) }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" inputmode="text" data-site-handle-input data-site-handle-autosuggest="{{ $site->exists ? 'off' : 'on' }}" @disabled($isReadOnly)>
                                        <div class="wb-text-sm wb-text-muted">Lowercase letters, numbers, and hyphens only. New sites auto-suggest from Name until you edit Handle manually.</div>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_domain">Domain</label>
                                        <input id="site_domain" name="domain" class="wb-input" type="text" value="{{ old('domain', $site->domain) }}" @disabled($isReadOnly)>
                                        <div class="wb-text-sm wb-text-muted">This remains the canonical primary domain. Use the Domains screen for aliases, activation state, and redirect-to-primary behavior.</div>
                                    </div>

                                    <label class="wb-nowrap">
                                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $site->is_primary)) @disabled($isReadOnly)>
                                        <span>Primary</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $siteTab === 'locales' ? 'is-active' : '' }}">
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
                                                @disabled($isReadOnly || $locale->is_default || ! $locale->is_enabled)
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

                        <div class="wb-tabs-panel {{ $siteTab === 'branding' ? 'is-active' : '' }}">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Branding</strong></div>

                                <div class="wb-card-body wb-stack wb-gap-3">
                                    <div class="wb-text-sm wb-text-muted">These values affect the public site only. They do not change the fixed WebBlocks CMS admin product identity.</div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_display_name">Public display name</label>
                                        <input id="site_display_name" name="display_name" class="wb-input" type="text" value="{{ old('display_name', $site->display_name) }}" @disabled($isReadOnly)>
                                        <div class="wb-text-sm wb-text-muted">Optional public-facing name override. Falls back to the internal site name when empty.</div>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_tagline">Tagline</label>
                                        <input id="site_tagline" name="tagline" class="wb-input" type="text" value="{{ old('tagline', $site->tagline) }}" @disabled($isReadOnly)>
                                    </div>

                                    <div class="wb-grid wb-grid-2 wb-gap-4">
                                        <div class="wb-stack wb-gap-2 wb-field">
                                            <label for="favicon_media_id">Favicon</label>
                                            @if ($canManageSiteSettings)
                                                @include('webblocks-cms::admin.media.asset-picker-panel', [
                                                    'name' => 'site-favicon',
                                                    'title' => 'Favicon',
                                                    'inputId' => 'favicon_media_id',
                                                    'fieldName' => 'favicon_media_id',
                                                    'selectedAsset' => $selectedFavicon,
                                                    'assetPickerAssets' => $assetPickerAssets,
                                                    'assetPickerFolders' => $assetPickerFolders,
                                                    'accept' => 'image',
                                                    'buttonLabel' => 'Choose favicon',
                                                    'replaceLabel' => 'Replace favicon',
                                                    'clearLabel' => 'Remove favicon',
                                                ])
                                            @else
                                                <div class="wb-card wb-card-muted">
                                                    <div class="wb-card-body wb-text-sm">
                                                        {{ $selectedFavicon?->original_name ?? 'No favicon selected.' }}
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="wb-text-sm wb-text-muted">Used for public favicon link tags when the selected image has a public URL.</div>
                                        </div>

                                        <div class="wb-stack wb-gap-2 wb-field">
                                            <label for="social_image_media_id">Social image</label>
                                            @if ($canManageSiteSettings)
                                                @include('webblocks-cms::admin.media.asset-picker-panel', [
                                                    'name' => 'site-social-image',
                                                    'title' => 'Social image',
                                                    'inputId' => 'social_image_media_id',
                                                    'fieldName' => 'social_image_media_id',
                                                    'selectedAsset' => $selectedSocialImage,
                                                    'assetPickerAssets' => $assetPickerAssets,
                                                    'assetPickerFolders' => $assetPickerFolders,
                                                    'accept' => 'image',
                                                    'buttonLabel' => 'Choose social image',
                                                    'replaceLabel' => 'Replace social image',
                                                    'clearLabel' => 'Remove social image',
                                                ])
                                            @else
                                                <div class="wb-card wb-card-muted">
                                                    <div class="wb-card-body wb-text-sm">
                                                        {{ $selectedSocialImage?->original_name ?? 'No social image selected.' }}
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="wb-text-sm wb-text-muted">Used as fallback sharing artwork for the public site.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $siteTab === 'seo-defaults' ? 'is-active' : '' }}">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>SEO Defaults</strong></div>

                                <div class="wb-card-body wb-stack wb-gap-3">
                                    <div class="wb-text-sm wb-text-muted">These are site-level public metadata fallbacks. Page-level SEO overrides are intentionally not part of this phase.</div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_seo_title">Default meta title</label>
                                        <input id="site_seo_title" name="seo_title" class="wb-input" type="text" value="{{ old('seo_title', $site->seo_title) }}" @disabled($isReadOnly)>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_seo_description">Default meta description</label>
                                        <textarea id="site_seo_description" name="seo_description" class="wb-input" rows="5" @disabled($isReadOnly)>{{ old('seo_description', $site->seo_description) }}</textarea>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="site_seo_keywords">Meta keywords</label>
                                        <input id="site_seo_keywords" name="seo_keywords" class="wb-input" type="text" value="{{ old('seo_keywords', $site->seo_keywords) }}" @disabled($isReadOnly)>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $siteTab === 'variables' ? 'is-active' : '' }}">
                            @include('webblocks-cms::admin.sites.partials.variables-tab', [
                                'site' => $site,
                                'canManageSiteSettings' => $canManageSiteSettings,
                                'siteVariablesUi' => $siteVariablesUi,
                            ])
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions
                    :cancel-url="route('admin.sites.index')"
                    :show-submit="$canManageSiteSettings"
                    :submit-label="$site->exists ? 'Save Changes' : 'Create'"
                    :delete-href="$site->exists && isset($siteDeleteReport) && $canManageDomains ? route('admin.sites.delete', $site) : null"
                    :delete-disabled="$site->exists && isset($siteDeleteReport) ? ! $siteDeleteReport->canDelete : false"
                />
            </div>
        </div>
    </form>

    @if ($site->exists)
        @include('webblocks-cms::admin.sites.partials.site-variable-modals', [
            'site' => $site,
            'canManageSiteSettings' => $canManageSiteSettings,
            'siteVariablesUi' => $siteVariablesUi,
        ])
    @endif
@endsection
