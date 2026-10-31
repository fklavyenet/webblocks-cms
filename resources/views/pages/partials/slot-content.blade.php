@php
    $contentBlocks = $slot['blocks'];
    // One direct-child TOC controls this slot's rail. Nested TOCs stay inline.
    $slotToc = $contentBlocks->first(fn ($block) => $block->typeSlug() === 'toc' && $block->publicTocHeadingBlocks($block->renderLocaleCode())->isNotEmpty());
    if ($slotToc) {
        $contentBlocks = $contentBlocks->reject(fn ($block) => $block->id === $slotToc->id)->values();
    }
    $renderBlock = function ($block) {
        if ($block->ownsPublicRoot()) {
            return view('webblocks-cms::pages.partials.block', ['block' => $block])->render();
        }
        return '<div class="wb-public-block" data-wb-public-block-type="'.e($block->publicBlockTypeAttribute()).'">'
            .view('webblocks-cms::pages.partials.block', ['block' => $block])->render()
            .'</div>';
    };
@endphp

@if ($slotToc)
    <div class="wb-cms-toc-scope">
        <div class="wb-cms-toc-layout wb-cms-toc-layout-{{ $slotToc->tocPosition() }}">
            <div class="wb-cms-toc-rail">
                {!! $renderBlock($slotToc) !!}
            </div>
            <div class="wb-cms-toc-body wb-stack wb-gap-6">
                @foreach ($contentBlocks as $block)
                    {!! $renderBlock($block) !!}
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="wb-stack wb-gap-6">
        @foreach ($contentBlocks as $block)
            {!! $renderBlock($block) !!}
        @endforeach
    </div>
@endif
