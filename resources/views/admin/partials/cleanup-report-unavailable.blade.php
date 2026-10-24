@php
    $unavailableLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $unavailableTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
@endphp
<div class="wb-card wb-mt-4">
    <div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ $unavailableTranslator->get('maintenance_reports.cleanup_'.$refreshCleanupTab.'_unavailable', $unavailableLocale) }}</p>
        @include('webblocks-cms::admin.partials.maintenance-refresh-form')
    </div>
</div>
