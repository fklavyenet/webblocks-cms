@foreach ($slot['blocks'] as $block)
    @include('pages.partials.block', ['block' => $block])
@endforeach
