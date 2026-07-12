@php
  $image = $block->media;
  $imageSource = $image?->transformUrl('content');
  $responsiveCandidates = $image?->responsiveCandidates() ?? [];
  $srcset = count($responsiveCandidates) >= 2
    ? collect($responsiveCandidates)->map(fn ($candidate) => $candidate->url.' '.$candidate->width.'w')->implode(', ')
    : null;
  $caption = trim((string) ($block->title ?? ''));
  $altText = trim((string) ($block->subtitle ?? ''));
  $fallbackAltText = trim((string) ($image?->alt_text ?: $image?->title ?: $caption ?: 'Image'));
  $resolvedAltText = $altText !== '' ? $altText : $fallbackAltText;
  $href = trim((string) ($block->url ?? ''));
  $linkAttributes = '';

  if ($href !== '' && preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $href)) {
    $linkAttributes = ' href="'.e($href).'"';
  } else {
    $href = '';
  }
@endphp

@if ($imageSource)
  <figure class="wb-stack wb-gap-2" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @if ($href !== '')
      <a{!! $linkAttributes !!}>
    @endif
    <img
      src="{{ $imageSource }}"
      @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 800px) 100vw, 1280px" @endif
      alt="{{ $resolvedAltText }}"
      loading="lazy"
      decoding="async"
      @if ($image?->width) width="{{ $image->width }}" @endif
      @if ($image?->height) height="{{ $image->height }}" @endif
    >
    @if ($href !== '')
      </a>
    @endif

    @if ($caption !== '')
      <figcaption>{{ $caption }}</figcaption>
    @endif
  </figure>
@endif
