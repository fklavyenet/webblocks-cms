@php
    $mainBlocks = $slot['blocks'];

    // Article Layout pulls a top-level toc block out of the normal flow and
    // into a narrow sticky rail beside the rest of main, reusing the exact
    // wb-settings-shell.wb-docs-layout grid already shipped for the Settings
    // Shell pattern (no new CSS). It only activates when the page is on that
    // layout and main actually has a toc block as one of its direct
    // children -- a nested toc, or a page on any other layout, renders
    // exactly as before, one column, in document order.
    $articleToc = null;

    if (($page->publicShellPreset() ?? null) === 'article') {
        $articleToc = $mainBlocks->first(fn ($block) => $block->typeSlug() === 'toc');

        if ($articleToc) {
            $mainBlocks = $mainBlocks->reject(fn ($block) => $block->id === $articleToc->id)->values();
        }
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

@if ($articleToc)
    <div class="wb-settings-shell wb-docs-layout">
        <div class="wb-settings-body">
            <div class="wb-stack wb-gap-6">
                @foreach ($mainBlocks as $block)
                    {!! $renderBlock($block) !!}
                @endforeach
            </div>
        </div>
        <div class="wb-settings-nav wb-docs-rail">
            {!! $renderBlock($articleToc) !!}
        </div>
    </div>
@else
    <div class="wb-stack wb-gap-6">
        @foreach ($mainBlocks as $block)
            {!! $renderBlock($block) !!}
        @endforeach
    </div>
@endif
