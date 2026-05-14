@php
    $items = $block->children->where('status', 'published')->filter(fn ($child) => $child->isLinkListItem())->sortBy('sort_order')->values();
    $introMeta = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $introTitle = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $introDescription = $block->stringValueOrNull($block->content) ?? $block->translatedTextFieldValue('content');
@endphp

@if ($items->isNotEmpty())
    <div class="wb-stack wb-gap-3">
        @if ($introMeta !== null || $introTitle !== null || $introDescription !== null)
            <div class="wb-stack wb-gap-1">
                @if ($introMeta !== null)
                    <div class="wb-link-list-meta">{{ $introMeta }}</div>
                @endif

                @if ($introTitle !== null)
                    <h2>{{ $introTitle }}</h2>
                @endif

                @if ($introDescription !== null)
                    <p>{{ $introDescription }}</p>
                @endif
            </div>
        @endif

        <div class="wb-link-list">
        @foreach ($items as $item)
            @include('pages.partials.blocks.link-list-item', ['block' => $item])
        @endforeach
        </div>
    </div>
@endif
