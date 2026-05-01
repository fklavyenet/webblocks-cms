@php
    $wrapper = $slot['wrapper'] ?? [];
    $tag = $wrapper['element'] ?? 'div';
    $class = trim((string) ($wrapper['class'] ?? ''));
    $bodyClass = trim((string) ($wrapper['body_class'] ?? ''));
    $slotPartial = 'pages.partials.slots.'.$slot['slug'];
    $attributes = 'data-wb-slot="'.$slot['slug'].'"';

    if ($slot['slug'] === 'main') {
        $attributes .= ' id="main-content"';
    }

    if ($class !== '') {
        $attributes .= ' class="'.e($class).'"';
    }
@endphp

<{{ $tag }} {!! $attributes !!}>
    @if ($bodyClass !== '')
        <div class="{{ $bodyClass }}">
    @endif
        @if (view()->exists($slotPartial))
            @include($slotPartial, ['slot' => $slot, 'page' => $page, 'renderShell' => false])
        @elseif ($slot['blocks']->isNotEmpty())
            <div class="wb-stack">
                @foreach ($slot['blocks'] as $block)
                    @include('pages.partials.block', ['block' => $block])
                @endforeach
            </div>
        @endif
    @if ($bodyClass !== '')
        </div>
    @endif
</{{ $tag }}>
