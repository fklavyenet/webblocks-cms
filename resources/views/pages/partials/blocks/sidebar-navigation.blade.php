@php
    $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title') ?? 'Documentation navigation';
    $items = $block->children
        ->where('status', 'published')
        ->filter(fn ($child) => $child->isSidebarNavItem() || $child->isSidebarNavGroup())
        ->sortBy('sort_order')
        ->values();
@endphp

@if ($items->isNotEmpty())
    <nav class="wb-sidebar-nav" aria-label="{{ $label }}">
        <div class="wb-sidebar-section">
            @foreach ($items as $item)
                @include('pages.partials.block', ['block' => $item])
            @endforeach
        </div>
    </nav>
@endif
