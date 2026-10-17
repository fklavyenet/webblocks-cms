@php($pagination = $block->getAttribute('content_source_pagination'))
@if (is_array($pagination) && ($pagination['last_page'] ?? 1) > 1)
  <nav class="wb-pagination" aria-label="{{ __('webblocks-cms::blocks.collection_pagination') }}">
    @if ($pagination['previous_url'] ?? null)
      @if (isset($dynamicAuthoringPreviewFields))
        <form method="POST" action="{{ $pagination['previous_url'] }}">@csrf
          @foreach ($dynamicAuthoringPreviewFields as $field => $value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
          <button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::blocks.collection_previous') }}</button>
        </form>
      @else
        <a class="wb-btn wb-btn-secondary" href="{{ $pagination['previous_url'] }}">{{ __('webblocks-cms::blocks.collection_previous') }}</a>
      @endif
    @endif
    <span>{{ __('webblocks-cms::blocks.collection_page', ['current' => $pagination['current_page'], 'last' => $pagination['last_page']]) }}</span>
    @if ($pagination['next_url'] ?? null)
      @if (isset($dynamicAuthoringPreviewFields))
        <form method="POST" action="{{ $pagination['next_url'] }}">@csrf
          @foreach ($dynamicAuthoringPreviewFields as $field => $value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
          <button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::blocks.collection_next') }}</button>
        </form>
      @else
        <a class="wb-btn wb-btn-secondary" href="{{ $pagination['next_url'] }}">{{ __('webblocks-cms::blocks.collection_next') }}</a>
      @endif
    @endif
  </nav>
@endif
