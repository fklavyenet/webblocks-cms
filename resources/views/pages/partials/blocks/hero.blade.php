@php
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $eyebrow = $block->subtitle ?: trim((string) ($settings['eyebrow'] ?? $settings['subtitle'] ?? $settings['label'] ?? ''));
    $title = $block->title ?: trim((string) ($settings['headline'] ?? $settings['title'] ?? ''));
    $content = $block->content ?: trim((string) ($settings['body'] ?? $settings['content'] ?? $settings['copy'] ?? ''));
    $variant = $block->variant ?: 'default';
    $promoClasses = ['wb-promo'];

    if ($variant === 'centered') {
        $promoClasses[] = 'wb-text-center';
    }

    $actionBlocks = $block->children->filter(fn ($child) => $child->typeSlug() === 'button')->values();
@endphp

<div class="{{ implode(' ', $promoClasses) }}">
    <div class="wb-promo-copy">
        @if ($eyebrow !== '')
            <p class="wb-eyebrow">{{ $eyebrow }}</p>
        @endif

        @if ($title !== '')
            <h2 class="wb-promo-title">{{ $title }}</h2>
        @endif

        @if ($content !== '')
            <p class="wb-promo-text">{{ $content }}</p>
        @endif

        @if ($actionBlocks->isNotEmpty())
            <div class="wb-promo-actions">
                @foreach ($actionBlocks as $child)
                    @include('pages.partials.block', ['block' => $child])
                @endforeach
            </div>
        @endif
    </div>
</div>
