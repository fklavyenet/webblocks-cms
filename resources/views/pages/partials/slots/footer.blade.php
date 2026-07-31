@if ($slot['blocks']->isNotEmpty())
  <div class="wb-stack">
    @foreach ($slot['blocks'] as $block)
      @include('webblocks-cms::pages.partials.block', ['block' => $block])
    @endforeach
  </div>
@endif
