@php
    $regionChildren = $block->children;
    $hasRegionChildren = $regionChildren->contains(fn ($child) => in_array($child->typeSlug(), ['card_header', 'card_body', 'card_footer'], true));
    $cardVariant = trim((string) ($block->variant ?? ''));
    $cardVariantClass = match ($cardVariant) {
        'flat' => 'wb-card-flat',
        'muted' => 'wb-card-muted',
        'highlight' => 'wb-card-highlight',
        'accent' => 'wb-card-accent',
        default => null,
    };
    $cardClass = collect(['wb-card', $cardVariantClass, $block->publicBackgroundMediaClass()])->filter()->implode(' ');
    $backgroundStyle = $block->publicBackgroundMediaStyle();
    $cardUrl = $block->cardUrl();
    $cardTarget = $block->cardTarget();
@endphp

@if ($hasRegionChildren)
    @if ($cardUrl !== null)
        <a href="{{ $cardUrl }}" class="{{ $cardClass }} wb-no-decoration" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($cardTarget === '_blank') target="_blank" rel="noopener noreferrer"@endif @if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
    @else
        <article class="{{ $cardClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
    @endif
        @foreach ($regionChildren as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    @if ($cardUrl !== null)
        </a>
    @else
        </article>
    @endif
@else
    @php
        $subtitle = trim((string) ($block->subtitle ?? ''));
        $title = trim((string) ($block->title ?? ''));
        $description = trim((string) ($block->content ?? ''));
        $actionLabel = trim((string) ($block->meta ?? ''));
        $url = $block->cardUrl();
        $target = $block->cardTarget();
        $hasLegacyAction = $url !== null && $actionLabel !== '';
        $renderedDescription = app(\WebBlocks\Cms\Support\Formatting\InlineRichTextRenderer::class)->render($description);
    @endphp

    <article class="{{ $cardClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
        @if ($subtitle !== '')
            <div class="wb-card-header">{{ $subtitle }}</div>
        @endif

        @if ($title !== '' || $description !== '')
            <div class="wb-card-body wb-stack wb-gap-2">
                @if ($title !== '')
                    <strong>{{ $title }}</strong>
                @endif

                @if ($description !== '')
                    <p class="wb-m-0">{!! $renderedDescription !!}</p>
                @endif
            </div>
        @endif

        @if ($hasLegacyAction)
            <div class="wb-card-footer">
                <a href="{{ $url }}" class="wb-btn wb-btn-secondary"@if ($target === '_blank') target="_blank" rel="noopener noreferrer"@endif>{{ $actionLabel }}</a>
            </div>
        @endif
    </article>
@endif
