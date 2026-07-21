@php
  $tone = match ($block->variant) {
    'success' => 'success',
    'warning' => 'warning',
    'danger' => 'danger',
    default => 'info',
  };
  $title = trim((string) ($block->title ?? ''));
  $content = trim((string) ($block->content ?? ''));
@endphp

<div class="wb-alert wb-alert-{{ $tone }}">
  @if ($title !== '')
    <h3 class="wb-alert-title">{{ $title }}</h3>
  @endif

  @if ($content !== '')
    <p>{{ $content }}</p>
  @endif
</div>

@if ($block->children->isNotEmpty())
  <div class="wb-stack wb-gap-4">
    @foreach ($block->children as $child)
      @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
  </div>
@endif
