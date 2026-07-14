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
  $mediaUrl = $block->publicBackgroundMediaUrl();
  // The split layout renders the hero media as a foreground image beside the
  // copy, so the same media is never also painted as a background.
  $isSplit = $layout === 'split' && $mediaUrl !== null;
  $backgroundClass = $isSplit ? null : $block->publicBackgroundMediaClass();
  $backgroundStyle = $isSplit ? null : $block->publicBackgroundMediaStyle();

  if ($isSplit) {
    $heroClasses[] = 'wb-promo--split';
  }

  if (in_array($variant, ['muted', 'soft'], true)) {
    $heroClasses[] = 'wb-card-muted';
  }

  if ($variant === 'accent') {
    $heroClasses[] = 'wb-card-accent';
  }

  if ($layout === 'centered') {
    $copyClasses[] = 'wb-text-center';
  }

  if ($backgroundClass !== null) {
    $heroClasses[] = $backgroundClass;
  }

  $actionBlocks = $block->children
    ->filter(fn ($child) => in_array($child->typeSlug(), ['button', 'button_link'], true))
    ->filter(fn ($child) => filled($child->url) && filled($child->title))
    ->take(2)
    ->values();
@endphp

<section class="{{ implode(' ', $heroClasses) }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
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

    @include('webblocks-cms::pages.partials.blocks._actions', [
      'buttons' => $actionBlocks,
      'wrapperClass' => 'wb-promo-actions wb-cluster wb-cluster-2',
    ])
  </div>

  @if ($isSplit)
    <figure class="wb-promo-media">
      <img src="{{ $mediaUrl }}" alt="" loading="lazy" decoding="async">
    </figure>
  @endif
</section>
