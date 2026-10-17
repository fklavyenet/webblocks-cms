@php
    $dcLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $a = fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.'.$key, $dcLocale, $replace);
    $d = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-content.'.$key, $dcLocale);
    $isDraft = $page?->status === \WebBlocks\Cms\Models\Page::STATUS_DRAFT;
    $configuration = (array) $block?->setting('content_collection', []);
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $a('title'), 'heading' => $a('title')])
@section('content')
@include('webblocks-cms::admin.partials.flash')
@include('webblocks-cms::admin.partials.page-header', ['title' => $a('title'), 'description' => $a('intro')])
<p><a href="{{ route('admin.dynamic-content.index', ['source' => $source->handle(), 'site' => $siteId]) }}">{{ $d('source_details') }}</a></p>
<div class="wb-alert wb-alert-info"><strong>{{ $source->labelText() }}</strong><p>{{ $source->contract()['origin'] }}</p><p>{{ $d('source_translation_help') }}</p><p>{{ $a('provider_help') }}</p></div>
<section class="wb-card wb-mb-4"><div class="wb-card-header"><strong>{{ $a('destination') }}</strong></div><div class="wb-card-body">
<form method="GET" action="{{ route('admin.dynamic-content.author') }}" class="wb-stack wb-gap-3">
    <input type="hidden" name="source" value="{{ $source->handle() }}">
    <label for="author-site">{{ $d('site') }}</label><select id="author-site" data-wb-author-site name="site" class="wb-select">@foreach ($sites as $item)<option value="{{ $item->id }}" @selected($siteId === $item->id)>{{ $item->name }}</option>@endforeach</select>
    <label for="author-page">{{ $a('page') }}</label><select id="author-page" name="page_id" class="wb-select"><option value="">{{ $a('choose') }}</option>@foreach ($pages as $item)<option value="{{ $item->id }}" @selected($page?->id === $item->id)>{{ $item->defaultTranslation()?->name ?: $item->title ?: $item->slug }} · {{ $d('workflow_'.$item->status) }}</option>@endforeach</select>
    @if ($page)
    <label for="author-block">{{ $source->isCollection() ? $a('loop_block') : $a('field_block') }}</label><select id="author-block" name="block_id" class="wb-select"><option value="">{{ $a('choose') }}</option>@foreach ($destinations as $item)<option value="{{ $item->id }}" @selected($block?->id === $item->id)>{{ $item->slotType?->name }} · {{ $design->label($item) }}</option>@endforeach</select>
    @endif
    @if ($block && $source->isCollection())
    <label for="author-template">{{ $a('template') }}</label><select id="author-template" name="template_id" class="wb-select"><option value="">{{ $a('choose') }}</option>@foreach ($block->children as $child)@if ($editor->collectionTemplateIsAllowed($block, $child->typeSlug()))<option value="{{ $child->id }}" @selected($templateId === $child->id)>{{ $design->label($child) }}</option>@endif @endforeach</select><p class="wb-text-sm">{{ $a('template_help') }}</p>
    @endif
    <button class="wb-btn wb-btn-secondary" type="submit">{{ $a('continue') }}</button>
</form></div></section>
@if ($page && ! $isDraft)
<div class="wb-alert wb-alert-warning">{{ $a('published_help') }}</div>
@if ($page->status === \WebBlocks\Cms\Models\Page::STATUS_PUBLISHED)
<form method="POST" action="{{ route('admin.dynamic-content.stage') }}" class="wb-mb-4">@csrf<input type="hidden" name="source" value="{{ $source->handle() }}"><input type="hidden" name="page_id" value="{{ $page->id }}"><button class="wb-btn wb-btn-primary" type="submit">{{ $a('prepare_draft') }}</button></form>
@endif
@endif
@if ($page && $isDraft && $source->isCollection())
<details class="wb-card wb-mb-4"><summary class="wb-card-header">{{ $a('starter') }}</summary><div class="wb-card-body"><p>{{ $a('starter_help') }}</p><form method="POST" action="{{ route('admin.dynamic-content.starter') }}" class="wb-stack wb-gap-2">@csrf<input type="hidden" name="source" value="{{ $source->handle() }}"><input type="hidden" name="page_id" value="{{ $page->id }}"><label for="author-slot">{{ $d('slot') }}</label><select id="author-slot" name="slot_id" class="wb-select" required>@foreach ($slots as $slot)<option value="{{ $slot->id }}">{{ $slot->slotType?->name }}</option>@endforeach</select><button class="wb-btn wb-btn-secondary" type="submit" @disabled($slots->isEmpty())>{{ $a('create_starter') }}</button></form></div></details>
@endif
@if ($block && (! $source->isCollection() || $templateId))
<form method="POST" action="{{ route('admin.dynamic-content.save') }}" class="wb-stack wb-gap-4">
    @csrf
    <input type="hidden" name="source" value="{{ $source->handle() }}"><input type="hidden" name="site" value="{{ $siteId }}"><input type="hidden" name="page_id" value="{{ $page->id }}"><input type="hidden" name="block_id" value="{{ $block->id }}"><input type="hidden" name="template_id" value="{{ $templateId }}"><input type="hidden" name="version" value="{{ $design->fingerprint($page) }}">
    @if ($source->isCollection())
    <section class="wb-card"><div class="wb-card-header"><strong>{{ $a('loop') }}</strong></div><div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ $a('loop_summary', ['template' => $design->label($block->children->firstWhere('id', $templateId))]) }}</p>
        <input type="hidden" name="collection[source]" value="{{ $source->handle() }}"><input type="hidden" name="collection[template_block_id]" value="{{ $templateId }}">
        <div class="wb-grid wb-grid-2">
        @foreach (['limit' => 12, 'per_page' => 12] as $key => $default)<div><label for="author-{{ $key }}">{{ $a($key) }}</label><input id="author-{{ $key }}" type="number" class="wb-input" min="1" max="50" name="collection[{{ $key }}]" value="{{ old('collection.'.$key, $configuration[$key] ?? $default) }}"></div>@endforeach
        @foreach (['filter_field', 'sort_field'] as $key)<div><label for="author-{{ $key }}">{{ $a($key) }}</label><select id="author-{{ $key }}" class="wb-select" name="collection[{{ $key }}]"><option value="">{{ $a('none') }}</option>@foreach ($source->fieldDefinitions() as $field => $definition)<option value="{{ $field }}" @selected(old('collection.'.$key, $configuration[$key] ?? '') === $field)>{{ $definition['label'] }}</option>@endforeach</select></div>@endforeach
        <div><label for="author-filter-value">{{ $a('filter_value') }}</label><input id="author-filter-value" class="wb-input" name="collection[filter_value]" value="{{ old('collection.filter_value', $configuration['filter_value'] ?? '') }}"></div>
        <div><label for="author-sort-direction">{{ $a('sort_direction') }}</label><select id="author-sort-direction" class="wb-select" name="collection[sort_direction]">@foreach (['asc', 'desc'] as $direction)<option value="{{ $direction }}" @selected(old('collection.sort_direction', $configuration['sort_direction'] ?? 'asc') === $direction)>{{ $d('sort_'.$direction) }}</option>@endforeach</select></div>
        @foreach (['empty_behavior', 'error_behavior'] as $key)<div><label for="author-{{ $key }}">{{ $a($key) }}</label><select id="author-{{ $key }}" class="wb-select" name="collection[{{ $key }}]">@foreach (['hide_template', 'keep_template'] as $behavior)<option value="{{ $behavior }}" @selected(old('collection.'.$key, $configuration[$key] ?? ($key === 'empty_behavior' ? 'hide_template' : 'keep_template')) === $behavior)>{{ $d('behavior_'.$behavior) }}</option>@endforeach</select></div>@endforeach
        </div>
        <label class="wb-check"><input type="hidden" name="collection[paginate]" value="0"><input type="checkbox" name="collection[paginate]" value="1" @checked(old('collection.paginate', $configuration['paginate'] ?? false))><span>{{ $a('paginate') }}</span></label>
    </div></section>
    @endif
    <section class="wb-card"><div class="wb-card-header"><strong>{{ $a('mappings') }}</strong></div><div class="wb-card-body wb-stack wb-gap-4"><p>{{ $a('mapping_help') }}</p>
        @forelse ($mappingBlocks as $mappingBlock)
            <section id="mapping-{{ $mappingBlock->id }}" class="wb-card"><div class="wb-card-header"><strong>{{ $mappingBlock->layoutAdminName() ?: $mappingBlock->typeName() }}</strong> <small>#{{ $mappingBlock->id }}</small> <a href="{{ route('admin.pages.slots.blocks', ['page' => $page->id, 'slot' => $page->slots->firstWhere('slot_type_id', $mappingBlock->slot_type_id)?->id ?? $mappingBlock->slot_type_id, 'edit' => $mappingBlock->id, 'block_tab' => 'dynamic-content']) }}">{{ $d('open_block') }}</a></div><div class="wb-card-body wb-stack wb-gap-3">
                @foreach ($editor->targets($mappingBlock) as $target => $types)
                    @include('webblocks-cms::admin.blocks.partials.binding-picker', ['pickerId' => 'mapping-'.$mappingBlock->id.'-'.$target, 'pickerName' => 'mappings['.$mappingBlock->id.']['.$target.']', 'pickerLabel' => $d('target_'.($mappingBlock->typeSlug() === 'image' ? match ($target) {'title' => 'caption', 'subtitle' => 'alt_text', default => $target} : $target)), 'pickerSelected' => old('mappings.'.$mappingBlock->id.'.'.$target, $editor->selectedValue($mappingBlock, $target)), 'pickerChoices' => $editor->choices($mappingBlock, $types)])
                @endforeach
            </div></section>
        @empty <p>{{ $a('no_mapping_blocks') }}</p>@endforelse
    </div></section>
    <section class="wb-card"><div class="wb-card-header"><strong>{{ $a('review') }}</strong></div><div class="wb-card-body wb-stack wb-gap-3">
    <label for="author-locale">{{ $a('preview_locale') }}</label><select id="author-locale" name="locale" class="wb-select">@foreach ($page->site->enabledLocales()->get() as $item)<option value="{{ $item->code }}" @selected($locale === $item->code)>{{ strtoupper($item->code) }}</option>@endforeach</select>
    <p>{{ $a('preview_help') }}</p><div class="wb-cluster wb-gap-2"><button class="wb-btn wb-btn-secondary" type="submit" formaction="{{ route('admin.dynamic-content.preview') }}" formtarget="_blank">{{ $a('preview') }}</button>@if ($isDraft)<button class="wb-btn wb-btn-primary" type="submit">{{ $a('save_draft') }}</button>@endif</div><p class="wb-text-sm wb-text-muted">{{ $a('save_help') }}</p>
    </div></section>
</form>
@endif
@endsection
@push('admin-scripts')
@include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/dynamic-content-authoring.js'])
@endpush
