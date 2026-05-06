<div class="wb-cluster wb-cluster-2">
    @if ($page->isPublished() && $page->publicUrl())
        <a href="{{ $page->publicUrl() }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">View Page</a>
    @endif
    <a
        href="{{ request()->fullUrlWithQuery(['details' => 1]) }}"
        class="wb-btn wb-btn-secondary"
        aria-haspopup="dialog"
        aria-controls="pageDetailsModal"
        aria-label="Open page details"
    >
        <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
        <span>Details</span>
    </a>
</div>
