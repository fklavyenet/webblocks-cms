@php
  $apLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $ap = fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('automatic-pages.'.$key, $apLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $ap('title'), 'heading' => $ap('title')])
@section('content')
@include('webblocks-cms::admin.partials.page-header', ['title' => $ap('title'), 'description' => $ap('intro')])
<div class="wb-alert wb-alert-info">{{ $ap('draft_notice') }}</div>
<nav class="wb-cluster wb-cluster-2 wb-flex-wrap wb-mb-4" aria-label="{{ $ap('title') }}">
  <a class="wb-btn wb-btn-secondary" href="{{ route('admin.dynamic-content.index', ['site' => $siteId]) }}">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-content.title', $apLocale) }}</a>
  <a class="wb-btn wb-btn-primary" href="{{ route('admin.automatic-pages.index', ['site' => $siteId]) }}" aria-current="page">{{ $ap('title') }}</a>
</nav>
<form method="GET" class="wb-filter-bar" action="{{ route('admin.automatic-pages.index') }}">
  <div class="wb-field"><label for="ap-site">{{ $ap('site') }}</label><select id="ap-site" name="site" class="wb-select"><option value="">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-content.all_sites', $apLocale) }}</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected($siteId === $site->id)>{{ $site->name }}</option>@endforeach</select></div>
  <button type="submit" class="wb-btn wb-btn-secondary">{{ $ap('choose_site') }}</button>
  @if ($ready && $sites->isNotEmpty())<a class="wb-btn wb-btn-primary" href="{{ route('admin.automatic-pages.create', ['site' => $siteId]) }}">{{ $ap('create') }}</a>@endif
</form>
@if (! $ready)<div class="wb-alert wb-alert-warning">{{ $ap('schema_missing') }}</div>@else
<section class="wb-card"><div class="wb-card-body"><div class="wb-table-wrap"><table class="wb-table">
<thead><tr><th>{{ $ap('name') }}</th><th>{{ $ap('site') }}</th><th>{{ $ap('source') }}</th><th>{{ $ap('base_path') }}</th><th>{{ $ap('status') }}</th><th>{{ $ap('inspect') }}</th></tr></thead>
<tbody>@forelse ($rules as $rule)<tr><td><a href="{{ route('admin.automatic-pages.edit', $rule) }}">{{ $rule->name }}</a></td><td>{{ $rule->site->name }}</td><td><code>{{ $rule->source_handle }}</code></td><td><code>{{ $rule->base_path }}/{slug}</code></td><td><span class="wb-status-pill wb-status-info">{{ $ap('draft') }}</span></td><td><a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ route('admin.automatic-pages.records', $rule) }}">{{ $ap('inspect') }}</a></td></tr>@empty<tr><td colspan="6">{{ $ap('no_rules') }}</td></tr>@endforelse</tbody>
</table></div></div>@include('webblocks-cms::admin.partials.pagination', ['paginator' => $rules])</section>
@endif
@endsection
