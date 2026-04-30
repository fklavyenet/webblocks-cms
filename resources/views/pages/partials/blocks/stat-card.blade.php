@php
$label = $block->subtitle;
$value = $block->title;
$detail = $block->content;

$hasValue = $value !== null && trim((string) $value) !== '';
@endphp

<div class="wb-stat">
  @if(!blank($label))
    <div class="wb-stat-label">{{ $label }}</div>
  @endif

@if($hasValue) <div class="wb-stat-value">{{ $value }}</div>
@endif

@if(!blank($detail)) <div class="wb-stat-detail">{{ $detail }}</div>
@endif

</div>
