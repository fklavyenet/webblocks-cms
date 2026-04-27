@php
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $eyebrow = $block->subtitle ?: trim((string) ($settings['eyebrow'] ?? $settings['subtitle'] ?? $settings['label'] ?? ''));
    $title = $block->title ?: trim((string) ($settings['headline'] ?? $settings['title'] ?? ''));
    $content = $block->content ?: trim((string) ($settings['body'] ?? $settings['content'] ?? $settings['copy'] ?? ''));
    $variant = $block->variant ?: 'default';
    $layout = trim((string) ($settings['layout'] ?? ($variant === 'centered' ? 'centered' : 'left')));
    $headingTag = in_array($settings['title_tag'] ?? null, ['h1', 'h2', 'h3'], true) ? $settings['title_tag'] : 'h1';
    $heroClasses = ['wb-card', 'wb-promo'];
    $copyClasses = ['wb-card-body', 'wb-promo-copy', 'wb-stack', 'wb-gap-3'];

    if (in_array($variant, ['muted', 'soft'], true)) {
        $heroClasses[] = 'wb-card-muted';
    }

    if ($variant === 'accent') {
        $heroClasses[] = 'wb-card-accent';
    }

    if ($layout === 'centered') {
        $copyClasses[] = 'wb-text-center';
    }

    $actionBlocks = $block->children
        ->filter(fn ($child) => $child->typeSlug() === 'button')
        ->filter(fn ($child) => filled($child->url) && filled($child->title))
        ->take(2)
        ->values();
@endphp

<section class="{{ implode(' ', $heroClasses) }}">
    <div class="{{ implode(' ', $copyClasses) }}">
        @if ($eyebrow !== '')
            <p class="wb-eyebrow">{{ $eyebrow }}</p>
        @endif

        @if ($title !== '')
            <{{ $headingTag }} class="wb-promo-title">{{ $title }}</{{ $headingTag }}>
        @endif

        @if ($content !== '')
            <p class="wb-promo-text">{{ $content }}</p>
        @endif

        @include('pages.partials.blocks._actions', [
            'buttons' => $actionBlocks,
            'wrapperClass' => 'wb-promo-actions wb-cluster wb-cluster-2',
        ])
    </div>
</section>
