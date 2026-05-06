@php
    $headingBlocks = $block->publicTocHeadingBlocks($block->renderLocaleCode());
@endphp

@if ($headingBlocks->isNotEmpty())
    <div class="wb-stack wb-gap-2">
        @if ($block->title)
            <strong>{{ $block->title }}</strong>
        @endif

            <div class="wb-link-list">
                @foreach ($headingBlocks as $headingBlock)
                    <a class="wb-link-list-item" href="#{{ $headingBlock->headerAnchor() }}">
                        <div class="wb-link-list-main">
                            <span class="wb-link-list-title">{{ $headingBlock->tocEligibleHeadingText() }}</span>
                            @if ($headingBlock->variant === 'h3')
                                <span class="wb-link-list-meta">Section detail</span>
                            @endif
                    </div>

                    <div class="wb-link-list-desc">{{ $headingBlock->variant === 'h3' ? 'Jump to subsection' : 'Jump to section' }}</div>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
