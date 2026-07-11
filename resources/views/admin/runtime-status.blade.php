@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $adminText = fn (string $key) => $adminTranslator->get('admin.runtime_status.'.$key, $adminLocale);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
      'title' => $adminText('title'),
      'description' => $adminText('description'),
  ])

  <div class="wb-stack wb-stack-4" data-webblocks-cms-package-admin-slice="status">
    <div class="wb-card">
      <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <strong>{{ $adminText('slice_title') }}</strong>
        <span class="wb-status-pill wb-status-info">{{ $adminText('guarded') }}</span>
      </div>

      <div class="wb-card-body">
        <div class="wb-stack wb-gap-3">
          <p class="wb-m-0">{{ $adminText('availability') }}</p>
          <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <span>{{ $adminText('named_route') }}</span>
            <code>{{ $packageRouteName }}</code>
          </div>
          <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <span>{{ $adminText('reserved_path') }}</span>
            <code>{{ $packageRoutePath }}</code>
          </div>
          <div class="wb-text-sm wb-text-muted">{{ $adminText('boundary_help') }}</div>
        </div>
      </div>
    </div>
  </div>
@endsection
