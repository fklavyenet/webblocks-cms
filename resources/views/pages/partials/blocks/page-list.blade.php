@php
    $settings = $block->pageListSettings();
    $items = $block->pageListItems();

    /*
     * Deliberately no empty state. An unconfigured or currently-empty list
     * renders nothing at all, the same guard navigation-auto and toc use: an
     * editor-facing "no pages found" notice on a public page is an admin
     * artifact leaking to visitors.
     *
     * Every card links from its own title rather than a footer button, so the
     * block needs no call-to-action copy and therefore no public translation
     * of its own.
     */
@endphp

@if ($items->isNotEmpty())
    @if ($settings->rendersCards())
        <div class="wb-grid {{ $settings->gridColumnsClass() }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
            @foreach ($items as $item)
                @php($thumbnailUrl = $settings->showThumbnail ? $item->thumbnailUrl() : null)

                @if ($settings->clickableCard)
                    <a href="{{ $item->url }}" class="wb-card wb-no-decoration">
                @else
                    <article class="wb-card">
                @endif
                    @if ($thumbnailUrl !== null)
                        <div class="wb-card-header">
                            <img src="{{ $thumbnailUrl }}" alt="{{ $item->thumbnailAltText() }}" loading="lazy" decoding="async">
                        </div>
                    @endif

                    <div class="wb-card-body wb-stack wb-gap-2">
                        <strong>
                            @if ($settings->clickableCard)
                                {{ $item->title }}
                            @else
                                <a href="{{ $item->url }}" class="wb-link">{{ $item->title }}</a>
                            @endif
                        </strong>

                        @if ($item->description !== null)
                            <p class="wb-m-0">{{ $item->description }}</p>
                        @endif
                    </div>
                @if ($settings->clickableCard)
                    </a>
                @else
                    </article>
                @endif
            @endforeach
        </div>
    @else
        <div class="wb-link-list" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
            @foreach ($items as $item)
                @php($thumbnailUrl = $settings->showThumbnail ? $item->thumbnailUrl() : null)

                <a href="{{ $item->url }}" @class(['wb-link-list-item', 'wb-link-list-item--media' => $thumbnailUrl !== null])>
                    @if ($thumbnailUrl !== null)
                        <img src="{{ $thumbnailUrl }}" alt="{{ $item->thumbnailAltText() }}" class="wb-link-list-thumb" loading="lazy" decoding="async">
                    @endif

                    <div class="wb-link-list-main">
                        <span class="wb-link-list-title">{{ $item->title }}</span>
                    </div>

                    @if ($item->description !== null)
                        <div class="wb-link-list-desc">{{ $item->description }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
@endif
