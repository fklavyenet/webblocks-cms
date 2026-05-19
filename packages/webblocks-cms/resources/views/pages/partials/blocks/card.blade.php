@php
    $regionChildren = $block->children;
    $hasRegionChildren = $regionChildren->contains(fn ($child) => in_array($child->typeSlug(), ['card_header', 'card_body', 'card_footer'], true));
@endphp

@if ($hasRegionChildren)
    <article class="wb-card" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
        @foreach ($regionChildren as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </article>
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

    <article class="wb-card" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
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
