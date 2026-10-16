@php
  $apLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $ap = fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('automatic-pages.'.$key, $apLocale, $replace);
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $ap('transition'), 'heading' => $ap('transition')])
@section('content')
@include('webblocks-cms::admin.partials.page-header', ['title' => $automaticPage->name, 'description' => $ap('transition_intro')])
<div class="wb-alert wb-alert-info">{{ $ap('transition_notice') }}</div>
<div class="wb-cluster wb-cluster-2 wb-mb-4"><span class="wb-badge">{{ $ap('retained_pages', ['count' => $plan['pages']]) }}</span><span class="wb-badge">{{ $ap('detail_urls', ['count' => $plan['urls']]) }}</span><code>{{ $automaticPage->base_path }}/{slug}</code></div>
@foreach ($plan['errors'] as $error)<div class="wb-alert wb-alert-danger">{{ $ap($error) }}</div>@endforeach
<section class="wb-card"><div class="wb-card-header">{{ $ap('transition') }}</div><div class="wb-card-body"><div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>{{ $ap('key') }}</th><th>{{ $ap('retained_design') }}</th><th>{{ $ap('proposed_url') }}</th><th>{{ $ap('preview') }}</th></tr></thead><tbody>
@foreach ($plan['rows'] as $row)<tr><td><strong>{{ $row['key'] }}</strong><br>{{ $ap('available') }} · EN / DE / TR / ES / IT / FR</td><td>{{ $row['page'] ? '#'.$row['page']->id : $ap('shared_design') }}</td><td><code>{{ $automaticPage->base_path }}/{{ $row['slug'] }}</code></td><td><div class="wb-cluster wb-cluster-2">@foreach ($automaticPage->site->enabledLocales()->get() as $locale)<a class="wb-btn wb-btn-secondary wb-btn-sm" target="_blank" rel="noopener" href="{{ route('admin.automatic-pages.preview', ['automaticPage' => $automaticPage, 'record' => $row['key'], 'locale' => $locale->code]) }}">{{ strtoupper($locale->code) }}</a>@endforeach</div></td></tr>@endforeach
</tbody></table></div></div><div class="wb-card-footer"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.edit', $automaticPage) }}">{{ $ap('back') }}</a></div></section>
@if ($plan['errors'] === [])
@can('activate', $automaticPage)<form method="POST" action="{{ route('admin.automatic-pages.activate', $automaticPage) }}" class="wb-card wb-mt-4">@csrf<input type="hidden" name="digest" value="{{ $plan['digest'] }}"><div class="wb-card-body"><p>{{ $ap('activation_help') }}</p></div><div class="wb-card-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ $ap('activate') }}</button></div></form>@endcan
@endif
@endsection
