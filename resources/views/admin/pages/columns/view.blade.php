@if ($page->isPublished() && $defaultPublicUrl)
    <a
        href="{{ $defaultPublicUrl }}"
        target="_blank"
        rel="noopener noreferrer"
        class="wb-action-btn wb-action-btn-view"
        data-wb-tooltip="{{ $adminText('pages.open_new_tab') }}"
        aria-label="{{ $adminText('pages.open_new_tab') }}"
    >
        <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
    </a>
@else
    <a
        href="{{ $previewUrl }}"
        target="_blank"
        rel="noopener noreferrer"
        class="wb-action-btn wb-action-btn-view"
        data-wb-tooltip="{{ $adminText('pages.preview_new_tab') }}"
        aria-label="{{ $adminText('pages.preview_new_tab') }}"
    >
        <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
    </a>
@endif
