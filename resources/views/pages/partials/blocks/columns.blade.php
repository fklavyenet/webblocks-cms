@php
    $children = $block->children->where('status', 'published')->sortBy('sort_order')->values();
    $layoutClass = match (true) {
        $children->count() <= 1 => 'wb-stack wb-gap-4',
        $children->count() === 2 => 'wb-grid wb-grid-2',
        default => 'wb-grid wb-grid-3',
    };
@endphp

<section class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-4">
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
            <div class="wb-prose">
                <div>{!! nl2br(e($block->content)) !!}</div>
            </div>
        @endif

        @if ($children->isNotEmpty())
            <div class="{{ $layoutClass }}">
                @foreach ($children as $child)
                    @if ($child->isColumnItem())
                        <div class="wb-card">
                            <div class="wb-card-body">
                                @include('pages.partials.block', ['block' => $child])
                            </div>
                        </div>
                    @else
                        <div>
                            @include('pages.partials.block', ['block' => $child])
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
