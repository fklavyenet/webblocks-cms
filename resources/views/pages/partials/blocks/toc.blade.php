@php
    $sections = [];
    foreach ($block->publicTocHeadingBlocks($block->renderLocaleCode()) as $heading) {
        $last = array_key_last($sections);
        if ($heading->getAttribute('toc_parent_id') !== null && $last !== null && $sections[$last]['heading']->id === $heading->getAttribute('toc_parent_id')) {
            $sections[$last]['children'][] = $heading;
        } else {
            $sections[] = ['heading' => $heading, 'children' => []];
        }
    }
@endphp

@if ($sections !== [])
    <nav class="wb-section-nav"@if ($block->title) aria-label="{{ $block->title }}"@endif>
        @if ($block->title)
            <div class="wb-section-nav-title">{{ $block->title }}</div>
        @endif
        <ul class="wb-section-nav-list">
            @foreach ($sections as $section)
                <li class="wb-section-nav-item">
                    <a class="wb-section-nav-link" href="#{{ $section['heading']->headerAnchor() }}">{{ $section['heading']->tocEligibleHeadingText() }}</a>
                    @if ($section['children'] !== [])
                        <ul class="wb-section-nav-list wb-cms-toc-sublist">
                            @foreach ($section['children'] as $heading)
                                <li class="wb-section-nav-item">
                                    <a class="wb-section-nav-link" href="#{{ $heading->headerAnchor() }}">{{ $heading->tocEligibleHeadingText() }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
