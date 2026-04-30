@php($items = $block->children->where('status', 'published')->filter(fn ($child) => $child->isLinkListItem())->sortBy('sort_order')->values())

@if ($items->isNotEmpty())
    <div class="wb-link-list">
        @foreach ($items as $item)
            @include('pages.partials.blocks.link-list-item', ['block' => $item])
        @endforeach
    </div>
@endif
