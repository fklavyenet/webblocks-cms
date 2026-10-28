@php
  $assetUrl = $block->media?->url();
  $rawUrl = trim((string) ($block->url ?? ''));
  $parsedScheme = strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
  $safeUrl = $rawUrl !== '' && in_array($parsedScheme, ['http', 'https'], true) ? $rawUrl : null;
  $embed = app(\WebBlocks\Cms\Support\Consent\ConsentPresets::class)->embed($rawUrl);
  $embedUrl = $embed['url'] ?? null;

  $videoSource = $assetUrl;
@endphp

@if ($assetUrl || $embedUrl || $safeUrl || $block->title || $block->content)
  <div class="wb-card wb-card-muted" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    <div class="wb-card-body wb-stack wb-gap-3">
      @if ($block->title || $block->content)
        <div class="wb-stack wb-gap-1">
          @if ($block->title)
            <strong>{{ $block->title }}</strong>
          @endif
          @if ($block->content)
            <p class="wb-m-0">{{ $block->content }}</p>
          @endif
        </div>
      @endif

      @if ($videoSource)
        <video controls preload="metadata">
          <source src="{{ $videoSource }}">
        </video>
      @elseif ($embedUrl)
        @include('webblocks-cms::partials.consent-embed', ['embed' => $embed])
      @elseif ($safeUrl)
        <a href="{{ $safeUrl }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('consent.open_video', $block->renderLocaleCode()) }}</a>
      @endif
    </div>
  </div>
@endif
