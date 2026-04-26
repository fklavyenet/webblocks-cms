@php
    // Main content stays block-driven, but the public presentation layer gives it a stable semantic wrapper and rhythm.
    $renderShell = $renderShell ?? true;
@endphp

@if ($renderShell)
    <main class="wb-public-main" id="main-content">
        <div class="wb-container wb-container-lg">
            <div class="wb-stack wb-gap-6">
@endif
                @foreach ($slot['blocks'] as $block)
                    <section class="wb-public-block" data-wb-public-block-type="{{ $block->typeSlug() }}">
                        @include('pages.partials.block', ['block' => $block])
                    </section>
                @endforeach
@if ($renderShell)
            </div>
        </div>
    </main>
@endif
