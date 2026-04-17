<aside class="wb-public-sidebar" aria-label="{{ $slot['name'] }}">
    <div class="wb-container wb-container-lg">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-4">
                @foreach ($slot['blocks'] as $block)
                    @include('pages.partials.block', ['block' => $block])
                @endforeach
            </div>
        </div>
    </div>
</aside>
