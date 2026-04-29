@php
    $title = trim((string) ($block->title ?? ''));
    $subtitle = trim((string) ($block->subtitle ?? ''));
    $description = trim((string) ($block->content ?? ''));
    $actionLabel = trim((string) ($block->meta ?? ''));
    $url = $block->cardUrl();
    $target = $block->cardTarget();
    $footerBlocks = $block->children;
    $hasFooterBlocks = $footerBlocks->isNotEmpty();
    $showsLegacyAction = ! $hasFooterBlocks && $url !== null && $actionLabel !== '';
@endphp

<article class="wb-card">
    @if ($subtitle !== '')
        <div class="wb-card-header">{{ $subtitle }}</div>
    @endif

    <div class="wb-card-body wb-stack wb-gap-2">
        <strong>{{ $title }}</strong>

        @if ($description !== '')
            <p class="wb-m-0">{{ $description }}</p>
        @endif
    </div>

    @if ($hasFooterBlocks || $showsLegacyAction)
        <div class="wb-card-footer">
            @if ($hasFooterBlocks)
                @foreach ($footerBlocks as $child)
                    @include('pages.partials.block', ['block' => $child])
                @endforeach
            @else
                <a href="{{ $url }}" class="wb-btn wb-btn-secondary"@if ($target === '_blank') target="_blank" rel="noopener noreferrer"@endif>{{ $actionLabel }}</a>
            @endif
        </div>
    @endif
</article>
