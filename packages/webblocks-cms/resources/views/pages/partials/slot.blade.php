@php
    $renderWrapper = $renderWrapper ?? true;
    $wrapper = $slot['wrapper'] ?? [];
    $tag = $wrapper['element'] ?? 'div';
    $class = trim((string) ($wrapper['class'] ?? ''));
    $bodyClass = trim((string) ($wrapper['body_class'] ?? ''));
    $slotPartial = 'webblocks-cms::pages.partials.slots.'.$slot['slug'];
    $attributes = 'data-wb-slot="'.$slot['slug'].'"';
    $extraAttributes = $wrapper['attributes'] ?? [];

    if ($slot['slug'] === 'main') {
        $attributes .= ' id="main-content"';
    }

    foreach ($extraAttributes as $attributeName => $attributeValue) {
        if (! is_string($attributeName) || $attributeName === '' || $attributeValue === null) {
            continue;
        }

        if ($attributeName === 'id' && $slot['slug'] === 'main') {
            continue;
        }

        $attributes .= ' '.e($attributeName).'="'.e((string) $attributeValue).'"';
    }

    if ($class !== '') {
        $attributes .= ' class="'.e($class).'"';
    }
@endphp

@if ($renderWrapper && ! empty($wrapper['before_html']))
    {!! $wrapper['before_html'] !!}
@endif
@if ($renderWrapper)
    <{{ $tag }} {!! $attributes !!}>
@endif
@if ($renderWrapper && ! empty($wrapper['start_html']))
    {!! $wrapper['start_html'] !!}
@endif
@if ($bodyClass !== '')
    <div class="{{ $bodyClass }}">
@endif
    @if (view()->exists($slotPartial))
        @include($slotPartial, ['slot' => $slot, 'page' => $page, 'renderShell' => false])
    @elseif ($slot['blocks']->isNotEmpty())
        <div class="wb-stack">
            @foreach ($slot['blocks'] as $block)
                @include('webblocks-cms::pages.partials.block', ['block' => $block])
            @endforeach
        </div>
    @endif
@if ($bodyClass !== '')
    </div>
@endif
@if ($renderWrapper && ! empty($wrapper['end_html']))
    {!! $wrapper['end_html'] !!}
@endif
@if ($renderWrapper)
    </{{ $tag }}>
@endif
@if ($renderWrapper && ! empty($wrapper['after_html']))
    {!! $wrapper['after_html'] !!}
@endif
