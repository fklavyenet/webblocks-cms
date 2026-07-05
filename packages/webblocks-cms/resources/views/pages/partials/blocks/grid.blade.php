@php
    $alternateSections = $block->gridAlternatesMediaTextSections();
    $class = trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? ''));
    $children = $block->children;

    if ($alternateSections) {
        $children = collect();
        $sectionPair = collect();
        $pairIndex = 0;
        $flushSectionPair = function () use (&$children, &$sectionPair, &$pairIndex, $block): void {
            if ($sectionPair->isEmpty()) {
                return;
            }

            if ($sectionPair->count() !== 2) {
                $sectionPair->each(fn ($section) => $children->push($section));
                $sectionPair = collect();
                $pairIndex++;

                return;
            }

            $mediaLeft = $block->gridSectionMediaLeft($pairIndex);
            $sortedPair = $sectionPair
                ->sortBy(fn ($section) => $section->hasMediaTextVisualContent() === $mediaLeft ? 0 : 1)
                ->values();

            $sortedPair->each(fn ($section) => $children->push($section));
            $sectionPair = collect();
            $pairIndex++;
        };

        foreach ($block->children as $child) {
            if ($child->typeSlug() === 'section') {
                $sectionPair->push($child);

                if ($sectionPair->count() === 2) {
                    $flushSectionPair();
                }

                continue;
            }

            $flushSectionPair();
            $children->push($child);
        }

        $flushSectionPair();
    }
@endphp
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</div>
