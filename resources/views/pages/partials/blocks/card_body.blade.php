<div class="wb-card-body" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
