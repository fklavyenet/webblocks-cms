@php
    // Main content stays block-driven, but the public presentation layer gives it a stable semantic wrapper and rhythm.
@endphp

@php
    $blocks = $slot['blocks']->values();
@endphp

<main class="wb-public-main" id="main-content">
    <div class="wb-container wb-container-lg">
        <div class="wb-stack wb-gap-6">
            @for ($index = 0; $index < $blocks->count(); $index++)
                @php
                    $block = $blocks[$index];
                    $nextBlock = $blocks[$index + 1] ?? null;
                    $typeSlug = $block->typeSlug();
                    $nextTypeSlug = $nextBlock?->typeSlug();
                    $isContactPairStart = $typeSlug === 'contact-info' && $nextTypeSlug === 'contact_form';
                @endphp

                @if ($isContactPairStart)
                    <section class="wb-public-block wb-public-contact-pair" data-wb-public-block-type="contact-pair">
                        <div>
                            @include('pages.partials.block', ['block' => $block])
                        </div>
                        <div>
                            @include('pages.partials.block', ['block' => $nextBlock])
                        </div>
                    </section>
                    @php($index++)
                    @continue
                @endif

                <section class="wb-public-block" data-wb-public-block-type="{{ $typeSlug }}">
                    @include('pages.partials.block', ['block' => $block])
                </section>
            @endfor
        </div>
    </div>
</main>
