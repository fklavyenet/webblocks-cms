@if ($slot['blocks']->isNotEmpty())
    <div class="wb-stack">
        @foreach ($slot['blocks'] as $block)
            @include('pages.partials.block', ['block' => $block])
        @endforeach
    </div>
@endif
