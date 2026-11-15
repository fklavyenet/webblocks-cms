@php
    $alternateSections = $block->gridAlternatesMediaTextSections();
    $class = trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? '').' '.($block->gridRatioClass() ?? ''));
    $children = app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block);

    if ($alternateSections) {
        $sourceChildren = $children;
        $children = collect();
        $childPair = collect();
        $pairIndex = 0;
        $flushChildPair = function () use (&$children, &$childPair, &$pairIndex, $block): void {
            if ($childPair->isEmpty()) {
                return;
            }

            if ($childPair->count() !== 2) {
                $childPair->each(fn ($child) => $children->push($child));
                $childPair = collect();
                $pairIndex++;

                return;
            }

            $mediaLeft = $block->gridMediaTextSequenceMediaLeft($pairIndex);
            $sortedPair = $childPair
                ->sortBy(fn ($child) => $child->hasMediaTextVisualContent() === $mediaLeft ? 0 : 1)
                ->values();

            $sortedPair->each(fn ($child) => $children->push($child));
            $childPair = collect();
            $pairIndex++;
        };

        foreach ($sourceChildren as $child) {
            if ($child->hasMediaTextLayoutContent()) {
                $childPair->push($child);

                if ($childPair->count() === 2) {
                    $flushChildPair();
                }

                continue;
            }

            $flushChildPair();
            $children->push($child);
        }

        $flushChildPair();
    }
    $mobileOrder = $block->appearanceSetting('columns') === '2' ? $block->appearanceSetting('mobile_order') : null;
    $pairs = $children->values()->chunk(2);
@endphp
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($pairs as $pair)
        @php($reverseOnMobile = $pair->count() === 2 && \WebBlocks\Cms\Support\Blocks\MobileContentOrder::reversesPair($mobileOrder, $pair->first(), $pair->last()))
        @if ($reverseOnMobile)
            <div class="wb-public-mobile-content-pair">
        @endif
        @foreach ($pair as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
        @if ($reverseOnMobile)
            </div>
        @endif
    @endforeach
</div>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
