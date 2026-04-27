@php
    $children = $block->children->where('status', 'published')->sortBy('sort_order')->values();
    $childSlugs = $children->map(fn ($child) => $child->typeSlug())->values();
    $isContactColumns = $childSlugs->count() === 2
        && $childSlugs->contains('contact-info')
        && $childSlugs->contains('contact_form');
    $columnsVariant = $block->variant ?: 'cards';
    $gridClass = match (true) {
        $children->count() <= 1 => 'wb-stack wb-gap-3',
        $children->count() === 2 => 'wb-grid wb-grid-2',
        $children->count() === 3 => 'wb-grid wb-grid-3',
        default => 'wb-grid wb-grid-4',
    };
    $layoutClass = $gridClass;
@endphp

<section class="wb-stack wb-gap-4">
        @if ($block->title || $block->subtitle)
            <div class="wb-stack wb-gap-1">
                @if ($block->title)
                    <h2>{{ $block->title }}</h2>
                @endif

                @if ($block->subtitle)
                    <p class="wb-text-muted">{{ $block->subtitle }}</p>
                @endif
            </div>
        @endif

        @if ($block->content)
            <div class="wb-stack wb-gap-2">
                <p class="wb-m-0">{!! nl2br(e($block->content)) !!}</p>
            </div>
        @endif

        @if ($children->isNotEmpty())
            <div class="{{ $layoutClass }}{{ $isContactColumns ? ' wb-public-contact-columns' : '' }}">
                @foreach ($children as $child)
                    @if ($child->isColumnItem())
                        @include($child->publicRenderView(), ['block' => $child, 'columnsVariant' => $columnsVariant])
                    @else
                        <div>
                            @include('pages.partials.block', ['block' => $child])
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
</section>
