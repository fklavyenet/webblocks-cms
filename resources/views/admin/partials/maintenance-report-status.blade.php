@php
    $reportLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $reportTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $reportText = static fn (string $key, array $replace = []) => $reportTranslator->get('maintenance_reports.'.$key, $reportLocale, $replace);
@endphp
<div class="wb-alert {{ $report['status'] === 'fresh' ? 'wb-alert-info' : 'wb-alert-warning' }} wb-mb-4" data-wb-maintenance-report="{{ $report['status'] }}">
    <div>
        <p>{{ $reportText($report['status'], ['time' => $report['checked_at'] ? gmdate('Y-m-d H:i:s', $report['checked_at']).' UTC' : '—']) }}</p>
        @if ($report['status'] !== 'fresh')
            <p>{{ $reportText('help') }} <a class="wb-link" href="{{ route('admin.system.health.index') }}">{{ $reportTranslator->get('system_health.details', $reportLocale) }}</a></p>
        @endif
        @if (($report['backup']?->overview['validation_pending'] ?? 0) > 0)
            <p>{{ $reportText('validation_pending', ['count' => $report['backup']->overview['validation_pending']]) }}</p>
        @endif
    </div>
</div>
