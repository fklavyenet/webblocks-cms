@php
    $eyebrow = trim((string) ($block->eyebrow ?? ''));
    $title = trim((string) ($block->title ?? ''));
    $subtitle = trim((string) ($block->subtitle ?? ''));
    $description = trim((string) ($block->content ?? ''));
    $imageCaption = trim((string) ($block->image_caption ?? ''));
    $media = $block->media;
    $imageSource = $media?->url();
    $imageAlt = trim((string) ($block->image_alt ?? ''));
    $resolvedImageAlt = $imageAlt !== ''
        ? $imageAlt
        : trim((string) ($media?->alt_text ?: $media?->title ?: $imageCaption ?: $title ?: 'Card image'));
    $imagePosition = $block->cardImagePosition();
    $imageAlign = $block->cardImageAlign();
    $showsImage = $imageSource !== null;
    $renderedDescription = app(\App\Support\Formatting\InlineRichTextRenderer::class)->render($description);
    $actionLabel = trim((string) ($block->meta ?? ''));
    $url = $block->cardUrl();
    $target = $block->cardTarget();
    $footerBlocks = $block->children;
    $hasFooterBlocks = $footerBlocks->isNotEmpty();
    $showsLegacyAction = ! $hasFooterBlocks && $url !== null && $actionLabel !== '';
    $imageFigureClasses = ['wb-stack', 'wb-gap-1'];

    if ($imageAlign === 'stretch') {
        $imageFigureClasses[] = 'wb-w-full';
    } elseif ($imageAlign === 'center') {
        $imageFigureClasses[] = 'wb-text-center';
    } elseif ($imageAlign === 'end') {
        $imageFigureClasses[] = 'wb-text-right';
    }

    $imageFigureClass = implode(' ', $imageFigureClasses);
@endphp

@if ($block->isPromoCard())
    <section class="wb-card wb-promo" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
        <div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">
            @if ($showsImage && in_array($imagePosition, ['top', 'middle'], true))
                <figure class="{{ $imageFigureClass }}">
                    <img
                        src="{{ $imageSource }}"
                        alt="{{ $resolvedImageAlt }}"
                        @if ($media?->width) width="{{ $media->width }}" @endif
                        @if ($media?->height) height="{{ $media->height }}" @endif
                    >
                    @if ($imageCaption !== '')
                        <figcaption>{{ $imageCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($eyebrow !== '')
                <p class="wb-eyebrow">{{ $eyebrow }}</p>
            @endif

            @if ($title !== '')
                <h2 class="wb-promo-title">{{ $title }}</h2>
            @endif

            @if ($subtitle !== '')
                <p class="wb-m-0"><strong>{{ $subtitle }}</strong></p>
            @endif

            @if ($description !== '')
                <p class="wb-promo-text">{!! $renderedDescription !!}</p>
            @endif

            @if ($showsImage && $imagePosition === 'bottom')
                <figure class="{{ $imageFigureClass }}">
                    <img
                        src="{{ $imageSource }}"
                        alt="{{ $resolvedImageAlt }}"
                        @if ($media?->width) width="{{ $media->width }}" @endif
                        @if ($media?->height) height="{{ $media->height }}" @endif
                    >
                    @if ($imageCaption !== '')
                        <figcaption>{{ $imageCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($hasFooterBlocks || $showsLegacyAction)
                <div class="wb-promo-actions wb-cluster wb-cluster-2">
                    @if ($hasFooterBlocks)
                        @foreach ($footerBlocks as $child)
                            @include('pages.partials.block', ['block' => $child])
                        @endforeach
                    @else
                        <a href="{{ $url }}" class="wb-btn wb-btn-secondary"@if ($target === '_blank') target="_blank" rel="noopener noreferrer"@endif>{{ $actionLabel }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@else
    <article class="wb-card" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
        @if ($subtitle !== '')
            <div class="wb-card-header">{{ $subtitle }}</div>
        @endif

        <div class="wb-card-body wb-stack wb-gap-2">
            @if ($showsImage && $imagePosition === 'top')
                <figure class="{{ $imageFigureClass }}">
                    <img
                        src="{{ $imageSource }}"
                        alt="{{ $resolvedImageAlt }}"
                        @if ($media?->width) width="{{ $media->width }}" @endif
                        @if ($media?->height) height="{{ $media->height }}" @endif
                    >
                    @if ($imageCaption !== '')
                        <figcaption>{{ $imageCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            <strong>{{ $title }}</strong>

            @if ($showsImage && $imagePosition === 'middle')
                <figure class="{{ $imageFigureClass }}">
                    <img
                        src="{{ $imageSource }}"
                        alt="{{ $resolvedImageAlt }}"
                        @if ($media?->width) width="{{ $media->width }}" @endif
                        @if ($media?->height) height="{{ $media->height }}" @endif
                    >
                    @if ($imageCaption !== '')
                        <figcaption>{{ $imageCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($description !== '')
                <p class="wb-m-0">{!! $renderedDescription !!}</p>
            @endif

            @if ($showsImage && $imagePosition === 'bottom')
                <figure class="{{ $imageFigureClass }}">
                    <img
                        src="{{ $imageSource }}"
                        alt="{{ $resolvedImageAlt }}"
                        @if ($media?->width) width="{{ $media->width }}" @endif
                        @if ($media?->height) height="{{ $media->height }}" @endif
                    >
                    @if ($imageCaption !== '')
                        <figcaption>{{ $imageCaption }}</figcaption>
                    @endif
                </figure>
            @endif
        </div>

        @if ($hasFooterBlocks || $showsLegacyAction)
            <div class="wb-card-footer">
                @if ($hasFooterBlocks)
                    @foreach ($footerBlocks as $child)
                        @include('pages.partials.block', ['block' => $child])
                    @endforeach
                @else
                    <a href="{{ $url }}" class="wb-btn wb-btn-secondary"@if ($target === '_blank') target="_blank" rel="noopener noreferrer"@endif>{{ $actionLabel }}</a>
                @endif
            </div>
        @endif
    </article>
@endif
