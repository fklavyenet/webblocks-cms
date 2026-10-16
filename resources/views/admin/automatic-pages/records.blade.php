@php
  $apLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $ap = fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('automatic-pages.'.$key, $apLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $ap('records'), 'heading' => $ap('records')])
@section('content')
@include('webblocks-cms::admin.partials.page-header', ['title' => $automaticPage->name, 'description' => $ap('select_record')])
<div class="wb-alert wb-alert-info">{{ $ap(in_array(($rule ?? $automaticPage)->status, ['active', 'paused'], true) ? ($rule ?? $automaticPage)->status : 'draft_notice') }}</div>
<div class="wb-cluster wb-cluster-2 wb-mb-4"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.edit', $automaticPage) }}">{{ $ap('edit') }}</a><code>{{ $automaticPage->source_handle }}</code></div>
@if ($inspection['status'] !== 'available')<div class="wb-alert wb-alert-warning">{{ $ap($inspection['status']) }}</div>@else
<section class="wb-card wb-mb-4"><div class="wb-card-header">{{ $ap('records') }} @if ($inspection['page']->total !== null)· {{ $ap('total', ['count' => $inspection['page']->total]) }}@endif</div><div class="wb-card-body">
<form method="GET" action="{{ route('admin.automatic-pages.records', $automaticPage) }}" class="wb-stack wb-stack-3">
@if (request('cursor'))<input type="hidden" name="cursor" value="{{ request('cursor') }}">@endif
<div class="wb-field"><label for="ap-record">{{ $ap('key') }}</label><select class="wb-select" id="ap-record" name="record" required><option value="">{{ $ap('none') }}</option>@foreach ($inspection['page']->entries as $entry)<option value="{{ $entry['key'] }}" @selected(request('record') === $entry['key'])>{{ $entry['label'] }} · {{ $entry['key'] }}</option>@endforeach</select></div>
<div class="wb-cluster wb-cluster-2"><button type="submit" class="wb-btn wb-btn-primary">{{ $ap('inspect') }}</button>@if (request('cursor'))<a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.records', $automaticPage) }}">{{ $ap('first') }}</a>@endif @if ($inspection['page']->nextCursor !== null)<a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.records', ['automaticPage' => $automaticPage, 'cursor' => $inspection['page']->nextCursor]) }}">{{ $ap('next') }}</a>@endif</div>
</form></div></section>
@endif
@if ($selected)
<section class="wb-card"><div class="wb-card-header"><strong>{{ request('record') }}</strong> · {{ $ap($selected['status']) }}</div><div class="wb-card-body wb-stack wb-stack-3">
@if (! empty($selected['conflicts']))<div class="wb-alert wb-alert-warning">{{ $ap('conflicts', ['ids' => implode(', ', $selected['conflicts'])]) }}</div>@endif
<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>{{ $ap('language') }}</th><th>{{ $ap('status') }}</th><th>{{ $ap('record_title') }}</th><th>{{ $ap('proposed_url') }}</th><th>{{ $ap('preview') }}</th></tr></thead><tbody>
@foreach ($selected['locales'] as $code => $state)<tr><td>{{ strtoupper($code) }}</td><td>{{ $ap($state) }}</td><td>{{ data_get($selected['records'][$code] ?? [], $selected['configuration']['publication']['title_field'] ?? '') }}</td><td>@if ($selected['status'] === 'available')<code>{{ $inspector->url($automaticPage, $selected['slug'], $code) }}</code>@endif</td><td>@if ($selected['status'] === 'available' && $locales->contains('code', $code))<a class="wb-btn wb-btn-primary wb-btn-sm" target="_blank" rel="noopener" href="{{ route('admin.automatic-pages.preview', ['automaticPage' => $automaticPage, 'record' => request('record'), 'locale' => $code]) }}">{{ $ap('preview') }} · {{ strtoupper($code) }}</a>@endif</td></tr>@endforeach
</tbody></table></div>
@foreach ($selected['records'] as $code => $record)<details><summary>{{ $ap('values') }} · {{ strtoupper($code) }}</summary><dl>@foreach ($selected['configuration']['entity']->fieldDefinitions() as $field => $definition)<dt>{{ $definition['label'] }} <code>{{ $field }}</code></dt><dd>{{ data_get($record, $field) }}</dd>@endforeach</dl></details>@endforeach
</div></section>
@endif
@endsection
