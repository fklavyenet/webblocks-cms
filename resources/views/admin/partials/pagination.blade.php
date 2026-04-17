@if ($paginator->hasPages())
    <div class="wb-card-footer">
        <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-text-sm wb-text-muted">
                Showing {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            </div>

            <div class="wb-cluster wb-cluster-2">
                @if ($paginator->onFirstPage())
                    <span class="wb-btn wb-btn-secondary" aria-disabled="true">Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="wb-btn wb-btn-secondary">Previous</a>
                @endif

                <span class="wb-status-pill wb-status-info">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="wb-btn wb-btn-secondary">Next</a>
                @else
                    <span class="wb-btn wb-btn-secondary" aria-disabled="true">Next</span>
                @endif
            </div>
        </div>
    </div>
@endif
