@php
$label = $block->subtitle;
$value = $block->title;
$detail = $block->content;
$url = $block->stringValueOrNull($block->url);

$hasValue = $value !== null && trim((string) $value) !== '';

$translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
$resolvedLocaleCode = strtolower((string) ($block->getAttribute('resolved_locale_code') ?? app()->getLocale()));
$learnMoreLabel = $translator->get('blocks.stat_card.learn_more', $resolvedLocaleCode);
@endphp

<div class="wb-stat">
  @if(!blank($label))
    <div class="wb-stat-label">{{ $label }}</div>
  @endif

  @if($hasValue)
    <div class="wb-stat-value">{{ $value }}</div>
  @endif

  @if(!blank($detail))
    <div class="wb-stat-meta">{{ $detail }}</div>
  @endif

  @if($url !== null)
    <div class="wb-stat-meta"><a href="{{ $url }}">{{ $learnMoreLabel }}</a></div>
  @endif
</div>
