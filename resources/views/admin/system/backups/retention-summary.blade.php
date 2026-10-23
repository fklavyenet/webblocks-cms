@php
    $retentionLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $retentionTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $retentionText = static fn (string $key, array $replace = []) => $retentionTranslator->get('backup-retention.'.$key, $retentionLocale, $replace);
    $budget = $plan['storage'] ?? null;
    $retentionBytes = static fn (int $bytes) => $bytes >= 1073741824 ? number_format($bytes / 1073741824, 2).' GiB' : number_format($bytes / 1048576, 1).' MiB';
@endphp
@if ($budget)
    <div class="wb-card wb-mb-4">
        <div class="wb-card-header"><strong>{{ $retentionText('title') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            @unless ($plan['ready'])<div class="wb-alert wb-alert-warning"><div>{{ $retentionText('schema_missing') }}</div></div>@endunless
            <div class="wb-grid wb-grid-2 wb-gap-3">
                @foreach (['total_bytes', 'protected_bytes', 'eligible_bytes', 'after_cleanup_bytes'] as $metric)
                    <div>{{ $retentionText($metric) }}: <strong>{{ in_array($budget['status'], ['available', 'missing'], true) ? $retentionBytes($budget[$metric]) : '—' }}</strong></div>
                @endforeach
            </div>
            <p @if (in_array($budget['budget_status'], ['warning', 'exceeded', 'unavailable'], true)) class="wb-alert wb-alert-warning" @endif>{{ $retentionText('budget_'.$budget['budget_status']) }}@if ($budget['budget_bytes']) · {{ $retentionBytes($budget['budget_bytes']) }}@endif</p>
            @if ($budget['remaining_over_budget_bytes'] > 0 && in_array($budget['status'], ['available', 'missing'], true))
                <div class="wb-alert wb-alert-warning"><div>{{ $retentionText('remaining_over', ['size' => $retentionBytes($budget['remaining_over_budget_bytes'])]) }}</div></div>
            @endif
            <p class="wb-text-sm wb-text-muted">{{ $retentionText('budget_help') }}</p>
            @if ($plan['last_run'] ?? null)
                <p class="wb-text-sm">{{ $retentionTranslator->get('storage.last_run', $retentionLocale) }}: {{ $plan['last_run']['finished_at'] ?? $plan['last_run']['started_at'] }} · {{ $retentionTranslator->get('storage.'.$plan['last_run']['status'], $retentionLocale) }}</p>
                <p class="wb-text-sm">{{ $retentionTranslator->get('storage.deleted', $retentionLocale, ['count' => $plan['last_run']['deleted_count'], 'bytes' => number_format($plan['last_run']['deleted_bytes']), 'failures' => $plan['last_run']['failure_count']]) }}</p>
            @endif
        </div>
    </div>
@endif
