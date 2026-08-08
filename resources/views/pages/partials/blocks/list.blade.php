@php
    $settingsItems = collect($block->setting('items', $block->setting('cards', $block->setting('entries', []))))
        ->map(fn ($item) => is_array($item) ? ($item['label'] ?? $item['title'] ?? $item['content'] ?? null) : $item)
        ->map(fn ($item) => trim((string) $item))
        ->filter()
        ->values();
    $items = $settingsItems->isNotEmpty()
        ? $settingsItems
        : collect(preg_split('/\r\n|\r|\n/', (string) $block->content))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
    $listTag = $block->variant === 'ordered' ? 'ol' : 'ul';
@endphp

@if ($items->isNotEmpty())
    <div class="wb-stack wb-gap-2">
        @if ($block->title)
            <h3>{{ $block->title }}</h3>
        @endif

        {{-- wb-marker-list, not wb-stack alone: the shipped reset drops markers on
             every ul/ol so structural lists do not have to opt out, which left
             this block rendering an indent where a reader expects a bullet. --}}
        <{{ $listTag }} class="wb-marker-list">
            @foreach ($items as $item)
                <li>{{ $item }}</li>
            @endforeach
        </{{ $listTag }}>
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
