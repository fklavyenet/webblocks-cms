@php
    $variant = $block->variant ?? 'default';
    $isPromoVariant = in_array($variant, ['promo', 'hero'], true);
    $promoClasses = ['wb-promo'];

    if ($variant === 'centered') {
        $promoClasses[] = 'wb-text-center';
    }
@endphp

@if ($isPromoVariant)
    <section class="wb-section">
        <div class="{{ implode(' ', $promoClasses) }}">
            <div class="wb-promo-copy">
                @if ($block->title)
                    <h2 class="wb-promo-title">{{ $block->title }}</h2>
                @endif

                @if ($block->content)
                    <p class="wb-promo-text">{{ $block->content }}</p>
                @endif

                @if ($block->children->isNotEmpty())
                    <div class="wb-promo-actions">
                        @foreach ($block->children as $child)
                            @include('pages.partials.block', ['block' => $child])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@else
    <section class="wb-section {{ $variant === 'muted' ? 'wb-section-muted' : '' }}">
        <div class="wb-stack wb-gap-3">
            @if ($block->title)
                <h2>{{ $block->title }}</h2>
            @endif

            @if ($block->content)
                <p>{{ $block->content }}</p>
            @endif

            @if ($block->children->isNotEmpty())
                <div class="wb-stack wb-gap-3">
                    @foreach ($block->children as $child)
                        @include('pages.partials.block', ['block' => $child])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
