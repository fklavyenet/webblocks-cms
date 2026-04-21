<div class="wb-cluster wb-cluster-2">
    @if ($page->publicUrl())
        <a href="{{ $page->publicUrl() }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">View Page</a>
    @endif
    <button
        type="button"
        class="wb-btn wb-btn-secondary"
        data-wb-toggle="drawer"
        data-wb-target="#pageDetailsDrawer"
        aria-controls="pageDetailsDrawer"
        aria-label="Open page details"
    >
        <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
        <span>Details</span>
    </button>
</div>
