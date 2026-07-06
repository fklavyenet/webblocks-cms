@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.content_header.'.$key, $adminLocale);
    $metaItems = old('meta_items', $block->metaItems()->all());
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

@once
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/builder-items.js'])
    @endpush
@endonce

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('title') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="intro_text">{{ $adminText('intro_text') }}</label>
        <textarea id="intro_text" name="intro_text" class="wb-textarea" rows="4">{{ old('intro_text', $block->subtitle) }}</textarea>
    </div>

    @include('webblocks-cms::admin.blocks.types.partials.icon-badge-fields')

    @if (! $isNonDefaultLocale)
        @include('webblocks-cms::admin.blocks.types.partials.background-media-fields')
    @endif

    <div class="wb-card wb-card-muted" data-wb-builder-items-editor="content-header-meta-items">
        <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
            <strong>{{ $adminText('meta_items') }}</strong>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-builder-item-add="content-header-meta-items">{{ $adminText('add_item') }}</button>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-text-sm wb-text-muted">{{ $adminText('meta_items_help') }}</div>

            <div class="wb-stack wb-gap-3" data-wb-builder-item-list="content-header-meta-items">
                @forelse ($metaItems as $index => $metaItem)
                    <div class="wb-card" data-wb-builder-item-row="content-header-meta-items">
                        <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <strong data-wb-builder-item-label="content-header-meta-items">{{ trim((string) $metaItem) !== '' ? $metaItem : $adminText('new_item') }}</strong>
                            <div class="wb-flex wb-items-center wb-gap-2">
                                <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="{{ $adminText('move_up') }}" aria-label="{{ $adminText('move_up') }}"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="{{ $adminText('move_down') }}" aria-label="{{ $adminText('move_down') }}"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="{{ $adminText('remove_item') }}" aria-label="{{ $adminText('remove_item') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="content-header-meta-items">
                            <div class="wb-stack wb-gap-1">
                                <label for="meta_items_{{ $index }}">{{ $adminText('item') }}</label>
                                <input id="meta_items_{{ $index }}" class="wb-input" type="text" name="meta_items[]" value="{{ $metaItem }}" data-wb-builder-item-title="content-header-meta-items">
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="wb-empty" data-wb-builder-item-empty="content-header-meta-items">
                        <div class="wb-empty-title">{{ $adminText('empty_title') }}</div>
                        <div class="wb-empty-text">{{ $adminText('empty_description') }}</div>
                    </div>
                @endforelse
            </div>
        </div>

        <template
            data-wb-builder-item-template="content-header-meta-items"
            data-empty-title="{{ $adminText('empty_title') }}"
            data-empty-description="{{ $adminText('empty_description') }}"
        >
            <div class="wb-card" data-wb-builder-item-row="content-header-meta-items">
                <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <strong data-wb-builder-item-label="content-header-meta-items">{{ $adminText('new_item') }}</strong>
                    <div class="wb-flex wb-items-center wb-gap-2">
                        <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="{{ $adminText('move_up') }}" aria-label="{{ $adminText('move_up') }}"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                        <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="{{ $adminText('move_down') }}" aria-label="{{ $adminText('move_down') }}"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                        <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="{{ $adminText('remove_item') }}" aria-label="{{ $adminText('remove_item') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="content-header-meta-items">
                    <div class="wb-stack wb-gap-1">
                        <label for="meta_items___INDEX__">{{ $adminText('item') }}</label>
                        <input id="meta_items___INDEX__" class="wb-input" type="text" name="meta_items[]" value="" data-wb-builder-item-title="content-header-meta-items">
                    </div>
                </div>
            </div>
        </template>

        <input type="hidden" data-wb-builder-item-next-index="content-header-meta-items" value="{{ count($metaItems) }}">
    </div>
</div>
