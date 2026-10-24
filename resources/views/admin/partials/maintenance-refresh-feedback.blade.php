@if (session('maintenance_refresh_pending'))
    <div class="wb-alert wb-alert-warning wb-mb-4" role="status">
        <div>{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('maintenance_reports.refresh_pending', app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale()) }}</div>
    </div>
@endif
