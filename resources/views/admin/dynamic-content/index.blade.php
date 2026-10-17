@php
    $dcLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $dc = fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-content.'.$key, $dcLocale, $replace);
    $link = fn ($query = []) => route('admin.dynamic-content.index', array_filter(array_merge(['site' => $siteId, 'source' => $handle, 'search' => $search, 'state' => $state], $query), fn ($value) => $value !== null && $value !== ''));
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $dc('title'), 'heading' => $dc('title')])
@section('content')
@include('webblocks-cms::admin.partials.page-header', ['title' => $dc('title'), 'description' => $dc('intro')])
<div class="wb-alert wb-alert-info">{{ $dc('source_translation_help') }}</div>
<nav class="wb-cluster wb-cluster-2 wb-flex-wrap wb-mb-4" aria-label="{{ $dc('views') }}">
    @foreach (['sources', 'loops', 'bindings', 'issues'] as $view)
        <a class="wb-btn {{ $tab === $view ? 'wb-btn-primary' : 'wb-btn-secondary' }}" href="{{ $link(['tab' => $view]) }}" @if ($tab === $view) aria-current="page" @endif>{{ $dc($view) }}</a>
    @endforeach
    <a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.index', ['site' => $siteId]) }}">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('automatic-pages.title', $dcLocale) }}</a>
</nav>
<form method="GET" class="wb-filter-bar wb-filter-bar--fields" action="{{ route('admin.dynamic-content.index') }}">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if ($handle !== '')<input type="hidden" name="source" value="{{ $handle }}">@endif
    <div class="wb-filter-bar-fields">
        <div class="wb-field"><label for="dc-site">{{ $dc('site') }}</label><select id="dc-site" name="site" class="wb-select"><option value="">{{ $dc('all_sites') }}</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected($siteId === $site->id)>{{ $site->name }}</option>@endforeach</select></div>
        <div class="wb-field wb-filter-bar-search"><label for="dc-search">{{ $dc('search') }}</label><input id="dc-search" name="search" class="wb-input" value="{{ $search }}"></div>
        <div class="wb-field"><label for="dc-state">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.state_filter', $dcLocale) }}</label><select class="wb-select" id="dc-state" name="state"><option value="">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.all_states', $dcLocale) }}</option>@foreach (['draft', 'published', 'in_review', 'scheduled', 'archived'] as $workflow)<option value="{{ $workflow }}" @selected($state === $workflow)>{{ $dc('workflow_'.$workflow) }}</option>@endforeach</select></div>
        <div class="wb-filter-bar-actions"><button class="wb-btn wb-btn-primary" type="submit">{{ $dc('filter') }}</button>@if ($siteId || $search !== '' || $handle !== '')<a class="wb-btn wb-btn-secondary" href="{{ route('admin.dynamic-content.index', ['tab' => $tab]) }}">{{ $dc('clear') }}</a>@endif</div>
    </div>
</form>
@if ($selected && $tab !== 'sources')
<p>{{ $dc('source') }}: <a href="{{ $link(['tab' => 'sources']) }}">{{ $selected['label'] }}</a></p>
@endif
@if ($selected && $tab === 'sources')
<section class="wb-card wb-mb-4">
    <div class="wb-card-header"><strong>{{ $selected['label'] }}</strong> <code>{{ $selected['handle'] }}</code></div>
    <div class="wb-card-body wb-stack wb-stack-3">
        <p>{{ $selected['contract']['description'] ?? $dc('legacy_help') }}</p>
        <p><strong>{{ $dc('provider') }}:</strong> {{ $selected['provider_label'] ?: $dc('unknown') }} · <strong>{{ $dc('state') }}:</strong> {{ $dc('state_'.$selected['state']) }}</p>
        <p><strong>{{ $dc('origin') }}:</strong> {{ $selected['contract']['origin'] ?? $dc('unknown') }}</p>
        <p><strong>{{ $dc('languages') }}:</strong> {{ isset($selected['contract']['locales']) ? strtoupper(implode(' · ', $selected['contract']['locales'])) : $dc('unknown') }} · {{ $dc('declared_only') }}</p>
        <p><strong>{{ $dc('record_key') }}:</strong> {{ $selected['contract']['record_key'] ?? $dc('unknown') }}</p>
        @if ($selected['capabilities']['collection'] ?? false)<p><strong>{{ $dc('query_execution') }}:</strong> {{ $dc('execution_'.$selected['capabilities']['query_execution']) }}</p>@endif
        <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>{{ $dc('field') }}</th><th>{{ $dc('key') }}</th><th>{{ $dc('type') }}</th></tr></thead><tbody>@forelse ($selected['fields'] as $key => $field)<tr><td>{{ $field['label'] }}</td><td><code>{{ $key }}</code></td><td>{{ $dc('type_'.$field['type']) }}</td></tr>@empty<tr><td colspan="3">{{ $dc('no_fields') }}</td></tr>@endforelse</tbody></table></div>
        <p class="wb-text-sm wb-text-muted">{{ $dc('passive_inventory') }}</p>
        @if ($selected['publication'] ?? null)<a class="wb-btn wb-btn-secondary" href="{{ route('admin.automatic-pages.create', ['site' => $siteId, 'source' => $selected['handle']]) }}">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('automatic-pages.create', $dcLocale) }}</a>@endif
        @if ($selected['state'] === 'declared')<a class="wb-btn wb-btn-primary" href="{{ route('admin.dynamic-content.author', ['source' => $selected['handle'], 'site' => $siteId]) }}">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.use') }}</a>@endif
        <a class="wb-btn wb-btn-secondary" href="{{ $link(['tab' => 'bindings']) }}">{{ $dc('used_here') }}</a>
    </div>
</section>
@endif
<section class="wb-card">
    <div class="wb-card-header"><strong>{{ $dc($tab) }}</strong></div>
    <div class="wb-card-body"><div class="wb-table-wrap"><table class="wb-table">
    @if ($tab === 'sources')
        <thead><tr><th>{{ $dc('source') }}</th><th>{{ $dc('provider') }}</th><th>{{ $dc('type') }}</th><th>{{ $dc('state') }}</th><th>{{ $dc('blocks') }}</th><th>{{ $dc('actions') }}</th></tr></thead>
        <tbody>@forelse ($filteredSources as $source)<tr><td><strong>{{ $source['label'] }}</strong><div class="wb-text-sm"><code>{{ $source['handle'] }}</code></div></td><td>{{ $source['provider_label'] ?: $dc('unknown') }}</td><td>{{ $dc($source['kind']) }}</td><td><span class="wb-status-pill wb-status-info">{{ $dc('state_'.$source['state']) }}</span></td><td>{{ $source['blocks_count'] }}</td><td class="wb-table-actions"><div class="wb-action-group"><a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ $link(['source' => $source['handle']]) }}">{{ $dc('details') }}</a></div></td></tr>@empty<tr><td colspan="6">{{ $dc('no_sources') }}</td></tr>@endforelse</tbody>
    @else
        <thead><tr><th>{{ $dc('location') }}</th><th>{{ $dc('source') }}</th><th>{{ $dc('connection') }}</th><th>{{ $dc('publication') }}</th><th>{{ $dc('actions') }}</th></tr></thead>
        <tbody>@forelse ($rows as $row)<tr>
            <td>{{ $row['site']->name }}<div><strong>{{ $row['owner'] }}</strong> @if ($row['shared'])<span class="wb-status-pill wb-status-info">{{ $dc('shared') }}</span>@endif</div><div class="wb-text-sm">{{ $row['path'] }}</div><div class="wb-text-sm wb-text-muted">{{ $dc('slot') }}: {{ $row['block']->slotType?->name ?? $row['block']->slot }}</div></td>
            <td>{{ $sources->get($row['source'])['label'] ?? $row['source'] }}</td>
            <td>@if ($row['kind'] === 'loop')
                <div>{{ $dc('template_label', ['id' => $row['configuration']['template_block_id'] ?? '—']) }}</div>
                <div>{{ $dc('limit_label', ['limit' => $row['configuration']['limit'] ?? 12]) }}</div>
                @if ($row['configuration']['paginate'] ?? false)<div>{{ $dc('per_page_label', ['limit' => $row['configuration']['per_page'] ?? 12]) }}</div>@endif
                <div>{{ $dc('sort') }}: {{ ($row['configuration']['sort_field'] ?? '') ?: '—' }} {{ $dc('sort_'.($row['configuration']['sort_direction'] ?? 'asc')) }}</div>
                @if (! empty($row['configuration']['filter_field']))<div>{{ $dc('filter') }}: {{ $row['configuration']['filter_field'] }} = {{ $row['configuration']['filter_value'] ?? '' }}</div>@endif
                @if (! $row['template_valid'])<div class="wb-text-danger">{{ $dc('missing_template') }}</div>@endif
                <div>{{ $dc('empty') }}: {{ $dc('behavior_'.($row['configuration']['empty_behavior'] ?? 'hide_template')) }}</div>
                <div>{{ $dc('error') }}: {{ $dc('behavior_'.($row['configuration']['error_behavior'] ?? 'keep_template')) }}</div>
            @else
                <div>{{ $dc('target_'.$row['target_label_key']) }} ← {{ $sources->get($row['source'])['fields'][$row['field']]['label'] ?? $row['field'] }}</div>
                <div class="wb-text-sm">{{ $row['record'] === '@item' ? $dc('current_item') : $dc('record').': '.$row['record'] }}</div>
                @if ($row['record'] === '@item')<div class="wb-text-sm">{{ $row['loop_id'] ? $dc('loop_owner', ['id' => $row['loop_id']]) : $dc('missing_loop') }}</div>@endif
                @if (! isset($sources->get($row['source'])['fields'][$row['field']]))<div class="wb-text-danger">{{ $dc('missing_field') }}</div>@endif
            @endif</td>
            <td>{{ $dc($row['shared'] ? 'shared' : 'page_state') }}: {{ $dc('workflow_'.$row['status']) }}<div>{{ $dc('block_state') }}: {{ $dc('workflow_'.$row['block_status']) }}</div></td>
            <td class="wb-table-actions"><div class="wb-action-group"><a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ $row['edit_url'] }}">{{ $dc('open_block') }}</a></div></td>
        </tr>@empty<tr><td colspan="5">{{ $dc('no_usages') }}</td></tr>@endforelse</tbody>
    @endif
    </table></div></div>
    <div class="wb-card-footer wb-text-sm wb-text-muted">{{ $dc('configuration_only') }}</div>
    @include('webblocks-cms::admin.partials.pagination', ['paginator' => $paginator])
</section>
@endsection
