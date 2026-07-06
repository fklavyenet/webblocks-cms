@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.pagination.'.$key, $adminLocale, $replace);
  $ariaLabel = $ariaLabel ?? $adminText('aria_label');
  $compact = $compact ?? false;
  $summaryText = $paginator->firstItem() !== null && $paginator->lastItem() !== null
    ? $paginator->firstItem().'-'.$paginator->lastItem().'/'.$paginator->total()
    : null;
  $window = 2;
  $currentPage = $paginator->currentPage();
  $lastPage = $paginator->lastPage();
  $startPage = max(1, $currentPage - $window);
  $endPage = min($lastPage, $currentPage + $window);
  $pages = [];

  if ($lastPage > 0) {
    $pages[] = 1;

    for ($page = $startPage; $page <= $endPage; $page++) {
      $pages[] = $page;
    }

    $pages[] = $lastPage;
    $pages = array_values(array_unique($pages));
  }
@endphp

@if ($paginator->hasPages())
  <div class="wb-card-footer">
    <div class="wb-admin-pagination{{ $compact ? ' wb-admin-pagination-compact' : '' }}" data-admin-pagination>
      <nav class="wb-pagination{{ $compact ? ' wb-pagination-compact' : '' }}" aria-label="{{ $ariaLabel }}">
        <ol class="wb-pagination-list">
          <li class="wb-pagination-item{{ $paginator->onFirstPage() ? ' is-disabled' : '' }}">
            @if ($paginator->onFirstPage())
              <span class="wb-pagination-link">{{ $adminText('previous') }}</span>
            @else
              <a href="{{ $paginator->previousPageUrl() }}" class="wb-pagination-link" rel="prev">{{ $adminText('previous') }}</a>
            @endif
          </li>

          @php($previousPage = null)

          @foreach ($pages as $page)
            @if ($previousPage !== null && $page - $previousPage > 1)
              <li class="wb-pagination-item">
                <span class="wb-pagination-ellipsis" aria-hidden="true">&hellip;</span>
              </li>
            @endif

            <li class="wb-pagination-item{{ $page === $currentPage ? ' is-active' : '' }}">
              @if ($page === $currentPage)
                <span class="wb-pagination-link" aria-current="page">{{ $page }}</span>
              @else
                <a href="{{ $paginator->url($page) }}" class="wb-pagination-link">{{ $page }}</a>
              @endif
            </li>

            @php($previousPage = $page)
          @endforeach

          <li class="wb-pagination-item{{ $paginator->hasMorePages() ? '' : ' is-disabled' }}">
            @if ($paginator->hasMorePages())
              <a href="{{ $paginator->nextPageUrl() }}" class="wb-pagination-link" rel="next">{{ $adminText('next') }}</a>
            @else
              <span class="wb-pagination-link">{{ $adminText('next') }}</span>
            @endif
          </li>
        </ol>
      </nav>

      @if ($summaryText)
        <div class="wb-text-sm wb-text-muted wb-pagination-info" data-admin-pagination-summary>
          @if ($compact)
            {{ $summaryText }}
          @else
            {{ $adminText('showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
          @endif
        </div>
      @endif
    </div>
  </div>
@endif
