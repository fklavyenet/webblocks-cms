@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('site_form.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $site->exists ? $adminText('edit_title', ['name' => $site->name]) : $adminText('create_title');
  $canManageSiteSettings = $canManageSiteSettings ?? true;
  $canManageDomains = $canManageDomains ?? false;
  $siteTab = in_array(($siteTab ?? old('_site_tab', 'site')), \WebBlocks\Cms\Models\Site::ADMIN_FORM_TABS, true)
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
  $publicThemePresets = collect(\WebBlocks\Cms\Models\Site::PUBLIC_THEME_PRESETS)
    ->mapWithKeys(fn (string $preset) => [$preset => str($preset)->headline()->toString()]);
  $selectedPublicThemePreset = old('public_theme_preset', $site->resolvedPublicThemePreset());
  $tabUrl = fn (string $tab) => $site->exists
    ? route('admin.sites.edit', ['site' => $site, 'tab' => $tab])
    : route('admin.sites.create', ['tab' => $tab]);
  $actions = [];

  if ($site->exists && $canManageDomains) {
    $actions[] = '<a href="'.route('admin.sites.domains.index', $site).'" class="wb-btn wb-btn-secondary">'.e($adminText('manage_domains')).'</a>';
  }

  if ($site->exists) {
    $actions[] = '<a href="'.route('admin.pages.index', ['site' => $site->id]).'" class="wb-btn wb-btn-secondary">'.e($adminText('open_pages')).'</a>';
  }

  $pageHeaderActions = $actions !== []
    ? '<div class="wb-cluster wb-cluster-2">'.implode('', $actions).'</div>'
    : '';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $localizedPageTitle,
    'description' => $adminText('description'),
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
          <strong>{{ $adminText('site_settings') }}</strong>
          <span class="wb-text-sm wb-text-muted">{{ $adminText('site_settings_help') }}</span>
        </div>

        @if ($isReadOnly)
          <span class="wb-status-pill wb-status-info">{{ $adminText('read_only') }}</span>
        @endif
      </div>

      <div class="wb-card-body wb-stack wb-gap-4">
        @if ($isReadOnly)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">{{ $adminText('view_only') }}</div>
              <div>{{ $adminText('view_only_help') }}</div>
            </div>
          </div>
        @endif

        <div class="wb-tabs">
          <div class="wb-tabs-nav" role="tablist" aria-label="{{ $adminText('site_settings_sections') }}">
            @foreach (collect(\WebBlocks\Cms\Models\Site::ADMIN_FORM_TABS)
              ->mapWithKeys(fn (string $tab) => [$tab => $adminText(match ($tab) {
                'seo-defaults' => 'seo_defaults',
                'head' => 'head_code',
                'theme' => 'appearance',
                default => $tab,
              })]) as $tabKey => $tabLabel)
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
                <div class="wb-card-header"><strong>{{ $adminText('site') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                  <div class="wb-stack-2 wb-field">
                    <label for="site_name">{{ $adminText('name') }}</label>
                    <input id="site_name" name="name" class="wb-input" type="text" value="{{ old('name', $site->name) }}" required data-site-name-input @disabled($isReadOnly)>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('name_help') }}</div>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_handle">{{ $adminText('handle') }}</label>
                    <input id="site_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $site->handle) }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" inputmode="text" data-site-handle-input data-site-handle-autosuggest="{{ $site->exists ? 'off' : 'on' }}" @disabled($isReadOnly)>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('handle_help') }}</div>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_domain">{{ $adminText('domain') }}</label>
                    <input id="site_domain" name="domain" class="wb-input" type="text" value="{{ old('domain', $site->domain) }}" @disabled($isReadOnly)>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('domain_help') }}</div>
                  </div>

                  <label class="wb-nowrap">
                    <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $site->is_primary)) @disabled($isReadOnly)>
                    <span>{{ $adminText('primary') }}</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'locales' ? 'is-active' : '' }}">
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('locales') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                  <div class="wb-text-sm wb-text-muted">{{ $adminText('locales_help') }}</div>
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
                      <span>{{ strtoupper($locale->code) }} - {{ $locale->name }}@if ($locale->is_default) ({{ $adminText('default') }}) @elseif (! $locale->is_enabled) ({{ $adminText('disabled') }}) @endif</span>
                    </label>
                  @endforeach

                  @if ($locales->contains(fn ($locale) => ! $locale->is_default && ! $locale->is_enabled))
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('disabled_locales_help') }}</div>
                  @endif
                </div>
              </div>
            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'branding' ? 'is-active' : '' }}">
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('branding') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                  <div class="wb-text-sm wb-text-muted">{{ $adminText('branding_help') }}</div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_display_name">{{ $adminText('public_display_name') }}</label>
                    <input id="site_display_name" name="display_name" class="wb-input" type="text" value="{{ old('display_name', $site->display_name) }}" @disabled($isReadOnly)>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('public_display_name_help') }}</div>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_tagline">{{ $adminText('tagline') }}</label>
                    <input id="site_tagline" name="tagline" class="wb-input" type="text" value="{{ old('tagline', $site->tagline) }}" @disabled($isReadOnly)>
                  </div>

                  <div class="wb-grid wb-grid-2 wb-gap-4">
                    <div class="wb-stack wb-gap-2 wb-field">
                      <label for="favicon_media_id">{{ $adminText('favicon') }}</label>
                      @if ($canManageSiteSettings)
                        @include('webblocks-cms::admin.media.asset-picker-panel', [
                          'name' => 'site-favicon',
                          'title' => $adminText('favicon'),
                          'inputId' => 'favicon_media_id',
                          'fieldName' => 'favicon_media_id',
                          'selectedAsset' => $selectedFavicon,
                          'assetPickerAssets' => $assetPickerAssets,
                          'assetPickerFolders' => $assetPickerFolders,
                          'accept' => 'image',
                          'buttonLabel' => $adminText('choose_favicon'),
                          'replaceLabel' => $adminText('replace_favicon'),
                          'clearLabel' => $adminText('remove_favicon'),
                        ])
                      @else
                        <div class="wb-card wb-card-muted">
                          <div class="wb-card-body wb-text-sm">
                            {{ $selectedFavicon?->original_name ?? $adminText('no_favicon_selected') }}
                          </div>
                        </div>
                      @endif
                      <div class="wb-text-sm wb-text-muted">{{ $adminText('favicon_help') }}</div>
                    </div>

                    <div class="wb-stack wb-gap-2 wb-field">
                      <label for="social_image_media_id">{{ $adminText('social_image') }}</label>
                      @if ($canManageSiteSettings)
                        @include('webblocks-cms::admin.media.asset-picker-panel', [
                          'name' => 'site-social-image',
                          'title' => $adminText('social_image'),
                          'inputId' => 'social_image_media_id',
                          'fieldName' => 'social_image_media_id',
                          'selectedAsset' => $selectedSocialImage,
                          'assetPickerAssets' => $assetPickerAssets,
                          'assetPickerFolders' => $assetPickerFolders,
                          'accept' => 'image',
                          'buttonLabel' => $adminText('choose_social_image'),
                          'replaceLabel' => $adminText('replace_social_image'),
                          'clearLabel' => $adminText('remove_social_image'),
                        ])
                      @else
                        <div class="wb-card wb-card-muted">
                          <div class="wb-card-body wb-text-sm">
                            {{ $selectedSocialImage?->original_name ?? $adminText('no_social_image_selected') }}
                          </div>
                        </div>
                      @endif
                      <div class="wb-text-sm wb-text-muted">{{ $adminText('social_image_help') }}</div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'seo-defaults' ? 'is-active' : '' }}">
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('seo_defaults') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                  <div class="wb-text-sm wb-text-muted">{{ $adminText('seo_defaults_help') }}</div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_seo_title">{{ $adminText('default_meta_title') }}</label>
                    <input id="site_seo_title" name="seo_title" class="wb-input" type="text" value="{{ old('seo_title', $site->seo_title) }}" @disabled($isReadOnly)>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_seo_description">{{ $adminText('default_meta_description') }}</label>
                    <textarea id="site_seo_description" name="seo_description" class="wb-input" rows="5" @disabled($isReadOnly)>{{ old('seo_description', $site->seo_description) }}</textarea>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_seo_keywords">{{ $adminText('meta_keywords') }}</label>
                    <input id="site_seo_keywords" name="seo_keywords" class="wb-input" type="text" value="{{ old('seo_keywords', $site->seo_keywords) }}" @disabled($isReadOnly)>
                  </div>
                </div>
              </div>
            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'head' ? 'is-active' : '' }}">
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('head_code') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                  <div class="wb-text-sm wb-text-muted">{{ $adminText('head_code_help') }}</div>
                  <div class="wb-alert wb-alert-warning" role="note">
                    <div>{{ $adminText('head_code_warning') }}</div>
                  </div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_custom_head_html">{{ $adminText('custom_head_html') }}</label>
                    <textarea id="site_custom_head_html" name="custom_head_html" class="wb-input" rows="10" spellcheck="false" placeholder="{{ $adminText('custom_head_html_placeholder') }}" @disabled($isReadOnly)>{{ old('custom_head_html', $site->custom_head_html) }}</textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'contact' ? 'is-active' : '' }}">
              <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('contact_forms') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                  <div class="wb-text-sm wb-text-muted">{{ $adminText('contact_forms_help') }}</div>

                  <div class="wb-stack-2 wb-field">
                    <label for="site_contact_recipient_email">{{ $adminText('default_recipient_email') }}</label>
                    <input id="site_contact_recipient_email" name="contact_recipient_email" class="wb-input" type="email" value="{{ old('contact_recipient_email', $site->contact_recipient_email) }}" autocomplete="email" @disabled($isReadOnly)>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('default_recipient_email_help') }}</div>
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

            <div class="wb-tabs-panel {{ $siteTab === 'assets' ? 'is-active' : '' }}">
              @include('webblocks-cms::admin.sites.partials.assets-tab', [
                'site' => $site,
                'canManageSiteSettings' => $canManageSiteSettings,
                'siteAssets' => $siteAssets ?? collect(),
              ])
            </div>

            <div class="wb-tabs-panel {{ $siteTab === 'theme' ? 'is-active' : '' }}">
              <div class="wb-text-sm wb-text-muted wb-mb-4">{{ $adminText('appearance_help') }}</div>

              @include('webblocks-cms::admin.sites.partials.theme-tab', [
                'site' => $site,
                'canManageSiteSettings' => $canManageSiteSettings,
                'publicThemePresets' => $publicThemePresets,
                'selectedPublicThemePreset' => $selectedPublicThemePreset,
              ])

                <div class="wb-card wb-card-muted">
                  <div class="wb-card-header"><strong>{{ $adminText('brand_palette') }}</strong></div>
                  <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('brand_palette_help') }}</div>
                    <div class="wb-grid wb-grid-2 wb-gap-4">
                      @foreach ([
                        'brand_accent' => 'brand_accent',
                        'brand_accent_secondary' => 'brand_accent_secondary',
                        'brand_surface' => 'brand_surface',
                        'brand_text' => 'brand_text',
                      ] as $brandField => $brandLabelKey)
                        <div class="wb-stack wb-gap-2 wb-field">
                          <label for="site_{{ $brandField }}">{{ $adminText($brandLabelKey) }}</label>
                          <div class="wb-brand-colour">
                            <input
                              id="site_{{ $brandField }}_picker"
                              class="wb-brand-colour-swatch"
                              type="color"
                              value="{{ old($brandField, $site->{$brandField}) ?: '#ffffff' }}"
                              data-wb-brand-picker="site_{{ $brandField }}"
                              aria-label="{{ $adminText($brandLabelKey) }}"
                              @disabled($isReadOnly)
                            >
                            <input
                              id="site_{{ $brandField }}"
                              name="{{ $brandField }}"
                              class="wb-input wb-brand-colour-hex"
                              type="text"
                              inputmode="text"
                              spellcheck="false"
                              placeholder="#6a0f25"
                              value="{{ old($brandField, $site->{$brandField}) }}"
                              @disabled($isReadOnly)
                            >
                          </div>
                          <div class="wb-text-sm wb-text-muted">{{ $adminText($brandLabelKey.'_help') }}</div>
                        </div>
                      @endforeach
                    </div>
                    @php($brandContrast = $site->brandPalette()->accentContrast())
                    @if ($brandContrast !== null && $brandContrast < 4.5)
                      <div class="wb-alert wb-alert-warning">
                        {{ $adminText('brand_contrast_warning', ['ratio' => number_format($brandContrast, 2)]) }}
                      </div>
                    @endif
                    @php
                      $siteCssContents = collect($siteAssets ?? [])->firstWhere('type', 'css')['contents'] ?? null;
                      $fontOptions = \WebBlocks\Cms\Support\Theme\InstalledFonts::options($siteCssContents);
                      $installedFontCount = count(\WebBlocks\Cms\Support\Theme\InstalledFonts::fromCss($siteCssContents));
                    @endphp
                    @if ($installedFontCount === 0)
                      <div class="wb-alert wb-alert-info">{{ $adminText('brand_fonts_none') }}</div>
                    @endif
                    <div class="wb-grid wb-grid-2 wb-gap-4">
                      @foreach (['brand_font_heading', 'brand_font_body'] as $fontField)
                        @php($fontValue = (string) old($fontField, $site->{$fontField}))
                        @php($isCustomFont = $fontValue !== '' && ! array_key_exists($fontValue, $fontOptions))
                        <div class="wb-stack wb-gap-2 wb-field">
                          <label for="site_{{ $fontField }}_choice">{{ $adminText($fontField) }}</label>
                          <select
                            id="site_{{ $fontField }}_choice"
                            class="wb-input"
                            data-wb-font-choice="site_{{ $fontField }}"
                            @disabled($isReadOnly)
                          >
                            <option value="">{{ $adminText('brand_font_inherit') }}</option>
                            @foreach ($fontOptions as $stack => $label)
                              <option value="{{ $stack }}" @selected($fontValue === $stack)>{{ $label }}</option>
                            @endforeach
                            <option value="__custom" @selected($isCustomFont)>{{ $adminText('brand_font_custom') }}</option>
                          </select>
                          <input
                            id="site_{{ $fontField }}"
                            name="{{ $fontField }}"
                            class="wb-input @unless ($isCustomFont) wb-hidden @endunless"
                            type="text"
                            spellcheck="false"
                            placeholder="Libre Baskerville, Georgia, serif"
                            value="{{ $fontValue }}"
                            data-wb-font-stack
                            @disabled($isReadOnly)
                          >
                          <div class="wb-text-sm wb-text-muted">{{ $adminText($fontField.'_help') }}</div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                </div>
            </div>

          </div>
        </div>
      </div>

      <div class="wb-card-footer">
        <x-webblocks-cms::admin.form-actions
          :cancel-url="route('admin.sites.index')"
          :show-submit="$canManageSiteSettings"
          :submit-label="$site->exists ? $adminText('save_changes') : $adminText('create')"
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

    @foreach (($siteAssets ?? collect()) as $asset)
      <form id="site-asset-{{ $asset['type'] }}-form" method="POST" action="{{ route('admin.sites.assets.update', ['site' => $site, 'type' => $asset['type']]) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="expected_checksum" value="{{ $asset['checksum'] }}">
        <input type="hidden" name="_site_asset_type" value="{{ $asset['type'] }}">
      </form>
    @endforeach
  @endif
@endsection

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/brand-palette.js'])
@endpush
