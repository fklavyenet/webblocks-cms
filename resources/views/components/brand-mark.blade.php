@props([
  'decorative' => true,
  'label' => null,
  'surface' => null,
])

@php
  $isDecorative = filter_var($decorative, FILTER_VALIDATE_BOOLEAN);
  $titleId = $isDecorative ? null : 'wb_cms_brand_mark_'.uniqid();
@endphp

<svg
  {{ $attributes }}
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 128 128"
  fill="none"
  @if ($isDecorative)
    aria-hidden="true"
  @else
    role="img"
    aria-labelledby="{{ $titleId }}"
  @endif
  focusable="false"
>
  @unless ($isDecorative)
    <title id="{{ $titleId }}">{{ $label ?: 'WebBlocks CMS logo' }}</title>
  @endunless
  <g stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round">
    <rect x="14" y="14" width="100" height="100" rx="18" />
    <path d="M14 40H114" />
    <path d="M14 88H114" />
    <path d="M42 40V88" />
    <path d="M86 40V88" />
  </g>
</svg>
