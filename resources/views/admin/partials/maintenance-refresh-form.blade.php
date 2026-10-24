@php
    $refreshLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $refreshTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
@endphp
<form method="POST" action="{{ route('admin.system.health.refresh') }}">
    @csrf
    @if ($refreshSiteId ?? null)
        <input type="hidden" name="site_id" value="{{ $refreshSiteId }}">
    @endif
    @if ($refreshCleanupTab ?? null)
        <input type="hidden" name="cleanup_tab" value="{{ $refreshCleanupTab }}">
    @endif
    <button type="submit" class="wb-btn wb-btn-secondary" data-wb-busy data-wb-busy-label="{{ $refreshTranslator->get('maintenance_reports.refreshing', $refreshLocale) }}">
        <i class="wb-icon wb-icon-refresh-cw" aria-hidden="true"></i>
        <span data-wb-busy-text>{{ $refreshTranslator->get('system_health.refresh', $refreshLocale) }}</span>
    </button>
</form>
