@php($pagination = $block->getAttribute('content_source_pagination'))
@if (is_array($pagination) && ($pagination['last_page'] ?? 1) > 1)
  <nav class="wb-pagination" aria-label="{{ __('webblocks-cms::blocks.collection_pagination') }}">
    @if ($pagination['previous_url'] ?? null)
      <a class="wb-btn wb-btn-secondary" href="{{ $pagination['previous_url'] }}">{{ __('webblocks-cms::blocks.collection_previous') }}</a>
    @endif
    <span>{{ __('webblocks-cms::blocks.collection_page', ['current' => $pagination['current_page'], 'last' => $pagination['last_page']]) }}</span>
    @if ($pagination['next_url'] ?? null)
      <a class="wb-btn wb-btn-secondary" href="{{ $pagination['next_url'] }}">{{ __('webblocks-cms::blocks.collection_next') }}</a>
    @endif
  </nav>
@endif
