@php
  $downloadUrl = $block->downloadAsset()?->url();
  $variantClass = match ($block->variant ?? 'secondary') {
    'ghost' => 'wb-btn wb-btn-ghost',
    'primary' => 'wb-btn wb-btn-primary',
    default => 'wb-btn wb-btn-secondary',
  };
@endphp

@if ($downloadUrl)
  <div class="wb-stack wb-gap-2" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    <a href="{{ $downloadUrl }}" class="{{ $variantClass }}" download>
      {{ $block->title ?: 'Download file' }}
    </a>

    @if ($block->subtitle)
      <p>{{ $block->subtitle }}</p>
    @endif
  </div>
@endif
