@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('site_form.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('site_assets');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $localizedPageTitle,
    'description' => $adminText('site_assets_help'),
  ])

  @include('webblocks-cms::admin.partials.flash')

  @if ($sites->isNotEmpty())
    <div class="wb-card wb-card-muted wb-mb-4">
      <div class="wb-card-body">
        @include('webblocks-cms::admin.partials.listing-filters', [
          'action' => route('admin.site-assets.index'),
          'selects' => [
            [
              'id' => 'site_asset_site',
              'name' => 'site',
              'label' => $adminText('site'),
              'selected' => (string) $site?->id,
              'placeholder' => null,
              'options' => $sites->mapWithKeys(fn ($siteOption) => [$siteOption->id => $siteOption->name])->all(),
            ],
          ],
          'applyLabel' => $adminText('select_site'),
        ])
      </div>
    </div>
  @endif

  @if ($site)
    @include('webblocks-cms::admin.sites.partials.assets-tab', [
      'site' => $site,
      'canManageSiteSettings' => $canManageSiteSettings,
      'siteAssets' => $siteAssets,
    ])
  @else
    <div class="wb-empty-state">
      <div class="wb-empty-title">{{ $adminText('save_site_first') }}</div>
      <div class="wb-empty-text">{{ $adminText('site_assets_existing_help') }}</div>
    </div>
  @endif
@endsection
