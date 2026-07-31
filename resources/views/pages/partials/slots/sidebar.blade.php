@foreach ($slot['blocks'] as $block)
    @include('webblocks-cms::pages.partials.block', ['block' => $block])
@endforeach
