@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_actions.'.$key, $adminLocale, $replace);
@endphp

<div class="wb-cluster wb-cluster-2">
    @if ($page->isPublished() && $page->publicUrl())
        <a href="{{ $page->publicUrl() }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">{{ $adminText('view_page') }}</a>
    @endif
    <a
        href="{{ request()->fullUrlWithQuery(['details' => 1]) }}"
        class="wb-btn wb-btn-secondary"
        aria-haspopup="dialog"
        aria-controls="pageDetailsModal"
        aria-label="{{ $adminText('open_page_details') }}"
    >
        <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
        <span>{{ $adminText('details') }}</span>
    </a>
</div>
