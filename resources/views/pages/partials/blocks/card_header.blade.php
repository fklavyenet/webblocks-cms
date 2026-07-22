@php
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), 'content', $block->publicIconTone());
    $headerClass = trim('wb-card-header'.($iconClass !== null ? ' wb-icon-card' : ''));
@endphp

<div class="{{ $headerClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @if ($iconClass !== null)
        <i class="{{ $iconClass }}" aria-hidden="true"></i>
    @endif

    @foreach ($block->children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</div>
