@php
    $renderShell = $renderShell ?? true;
@endphp

@if ($renderShell)
    <aside class="wb-public-sidebar" aria-label="{{ $slot['name'] }}">
        <div class="wb-container wb-container-lg">
            <div class="wb-stack wb-gap-4">
@endif
                @foreach ($slot['blocks'] as $block)
                    @include('pages.partials.block', ['block' => $block])
                @endforeach
@if ($renderShell)
            </div>
        </div>
    </aside>
@endif
