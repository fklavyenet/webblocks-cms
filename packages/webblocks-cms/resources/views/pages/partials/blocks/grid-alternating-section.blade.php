@php
    $mediaLeft = $parentGrid->gridSectionMediaLeft($sectionIndex);
    $class = collect(['wb-section', $block->sectionSpacingClass(), 'wb-stack', $block->publicBackgroundMediaClass()])->filter()->implode(' ');
    $backgroundStyle = $block->publicBackgroundMediaStyle();
    $children = $block->children->values();
    $visualBlocks = $children->filter(fn ($child) => $child->isMediaTextVisualBlock())->values();
    $textBlocks = $children->reject(fn ($child) => $child->isMediaTextVisualBlock())->values();
@endphp

<section class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-3">
            @foreach ($mediaLeft ? $visualBlocks : $textBlocks as $child)
                @include('webblocks-cms::pages.partials.block', ['block' => $child])
            @endforeach
        </div>
        <div class="wb-stack wb-gap-3">
            @foreach ($mediaLeft ? $textBlocks : $visualBlocks as $child)
                @include('webblocks-cms::pages.partials.block', ['block' => $child])
            @endforeach
        </div>
    </div>
</section>
