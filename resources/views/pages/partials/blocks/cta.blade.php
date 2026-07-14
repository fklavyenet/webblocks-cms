@php
  $variant = $block->variant ?: 'default';
  $ctaClasses = ['wb-card', 'wb-promo'];
  $backgroundClass = $block->publicBackgroundMediaClass();
  $backgroundStyle = $block->publicBackgroundMediaStyle();

  if (in_array($variant, ['muted', 'soft'], true)) {
    $ctaClasses[] = 'wb-card-muted';
  }

  if ($variant === 'accent') {
    $ctaClasses[] = 'wb-card-accent';
  }

  if ($backgroundClass !== null) {
    $ctaClasses[] = $backgroundClass;
  }

  $actionBlocks = $block->children
    ->filter(fn ($child) => in_array($child->typeSlug(), ['button', 'button_link'], true))
    ->filter(fn ($child) => filled($child->url) && filled($child->title))
    ->take(2)
    ->values();
@endphp

<section class="{{ implode(' ', $ctaClasses) }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
  <div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">
    @if ($block->subtitle)
      <p class="wb-eyebrow">{{ $block->subtitle }}</p>
    @endif

    @if ($block->title)
      <h2 class="wb-promo-title">{{ $block->title }}</h2>
    @endif

    @if ($block->content)
      <p class="wb-promo-text">{{ $block->content }}</p>
    @endif

    @include('webblocks-cms::pages.partials.blocks._actions', [
      'buttons' => $actionBlocks,
      'wrapperClass' => 'wb-promo-actions wb-cluster wb-cluster-2',
    ])
  </div>
</section>
