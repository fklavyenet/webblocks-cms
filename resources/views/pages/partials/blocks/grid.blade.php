@php
    $alternateSections = $block->gridAlternatesMediaTextSections();
    $class = trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? '').' '.($block->gridRatioClass() ?? ''));
    $children = app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block);

    if ($alternateSections) {
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

        foreach ($children as $child) {
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
@endphp
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</div>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
