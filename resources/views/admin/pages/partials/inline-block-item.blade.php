@php
    $selectedType = $blockTypes->firstWhere('id', $block->block_type_id) ?? $blockTypes->firstWhere('slug', $block->type);
    $inlineBlocksLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $inlineBlocksText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('inline_blocks.'.$key, $inlineBlocksLocale, $replace);
@endphp

<div class="wb-card wb-card-muted" data-wb-inline-block>
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $selectedType?->name ?? $block->typeName() }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $block->title ?: $inlineBlocksText('no_title') }}</span>
        </div>

        <div class="wb-action-group">
            <button type="button" class="wb-action-btn" data-wb-inline-move="up" title="{{ $inlineBlocksText('move_up') }}" aria-label="{{ $inlineBlocksText('move_up') }}"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-inline-move="down" title="{{ $inlineBlocksText('move_down') }}" aria-label="{{ $inlineBlocksText('move_down') }}"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-inline-toggle title="{{ $inlineBlocksText('collapse') }}" aria-label="{{ $inlineBlocksText('collapse') }}"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-inline-remove title="{{ $inlineBlocksText('remove') }}" aria-label="{{ $inlineBlocksText('remove') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="wb-card-body" data-wb-inline-body>
        @include('webblocks-cms::admin.pages.partials.inline-block-fields', [
            'block' => $block,
            'index' => $index,
            'blockTypes' => $blockTypes,
            'slotTypes' => $slotTypes,
            'selectedBlockType' => $selectedType,
        ])
    </div>
</div>
