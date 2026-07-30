@php
    $headingBlocks = $block->publicTocHeadingBlocks($block->renderLocaleCode());
@endphp

{{--
    wb-section-nav, not wb-link-list. It is a self-contained WebBlocks UI
    primitive (its own border/radius/background/padding, no dependency on the
    Settings Shell docs pattern it is normally seen inside), and the exact
    same shipped webblocks-ui.js the public layout already loads ships a
    WBSectionNav scrollspy that auto-initializes on any `.wb-section-nav` and
    live-updates `.is-active`/aria-current as the reader scrolls -- for free,
    with no JS owned by this package. The wb-docs-rail/wb-settings-nav
    modifier classes are deliberately not added: they pin the element into a
    two-column docs-shell grid and cap it to viewport height with its own
    internal scrollbar, both wrong for a block sitting inline in a normal
    content flow.
--}}
@if ($headingBlocks->isNotEmpty())
    <nav class="wb-section-nav"@if ($block->title) aria-label="{{ $block->title }}"@endif>
        @if ($block->title)
            <div class="wb-section-nav-title">{{ $block->title }}</div>
        @endif

        <ul class="wb-section-nav-list">
            @foreach ($headingBlocks as $headingBlock)
                <li class="wb-section-nav-item">
                    <a class="wb-section-nav-link" href="#{{ $headingBlock->headerAnchor() }}">{{ $headingBlock->tocEligibleHeadingText() }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
