@php
  $image = $block->media;
  $boundImageSource = $block->boundPublicValue('image_source');
  $imageSource = is_string($boundImageSource) && preg_match('/^(https?:\/\/|\/)/i', $boundImageSource)
    ? $boundImageSource
    : $image?->transformUrl('content');
  $responsiveCandidates = $image?->responsiveCandidates() ?? [];
  $srcset = count($responsiveCandidates) >= 2
    ? collect($responsiveCandidates)->map(fn ($candidate) => $candidate->url.' '.$candidate->width.'w')->implode(', ')
    : null;
  $caption = trim((string) $block->boundPublicValue('title', $block->title ?? ''));
  $altText = trim((string) $block->boundPublicValue('subtitle', $block->subtitle ?? ''));
  $fallbackAltText = trim((string) ($image?->alt_text ?: $image?->title ?: $caption ?: 'Image'));
  $resolvedAltText = $altText !== '' ? $altText : $fallbackAltText;
  $href = trim((string) $block->boundPublicValue('url', $block->url ?? ''));
  $linkAttributes = '';
  $viewerEnabled = (bool) $block->setting('viewer_enabled', false);
  $viewerGroup = trim((string) $block->setting('viewer_group', 'page-images')) ?: 'page-images';
  $viewerRegistry = app(\WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry::class);
  $viewerId = $viewerRegistry->imageViewerId($viewerGroup);
  $fullImageSource = $image?->url() ?: $imageSource;

  if ($href !== '' && preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $href)) {
    $linkAttributes = ' href="'.e($href).'"';
  } else {
    $href = '';
  }

  $opensViewer = $viewerEnabled && $href === '' && $fullImageSource;

  if ($opensViewer) {
    $viewerRegistry->registerImageViewerItem($viewerGroup, (int) $block->id, [
      'thumbnail_url' => $imageSource,
      'full_url' => $fullImageSource,
      'alt' => $resolvedAltText,
      'caption' => $caption,
      'meta' => '',
      'width' => $image?->width,
      'height' => $image?->height,
      'srcset' => $srcset,
    ], $block->renderLocaleCode());
  }
@endphp

@if ($imageSource)
  <figure class="wb-stack wb-gap-2" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @if ($href !== '' || $opensViewer)
      <a
        @if ($opensViewer)
          href="{{ $fullImageSource }}"
          class="wb-gallery-trigger"
          data-wb-gallery-target="#{{ $viewerId }}"
          data-wb-gallery-group="{{ $viewerGroup }}"
          data-wb-gallery-full="{{ $fullImageSource }}"
          data-wb-gallery-alt="{{ $resolvedAltText }}"
          @if ($caption !== '') data-wb-gallery-caption="{{ $caption }}" @endif
          @if ($image?->width) data-wb-gallery-width="{{ $image->width }}" @endif
          @if ($image?->height) data-wb-gallery-height="{{ $image->height }}" @endif
        @else
          {!! $linkAttributes !!}
        @endif
      >
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
    @if ($href !== '' || $opensViewer)
      </a>
    @endif

    @if ($caption !== '')
      <figcaption>{{ $caption }}</figcaption>
    @endif
  </figure>
@endif
