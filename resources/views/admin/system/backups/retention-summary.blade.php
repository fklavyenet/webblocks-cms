@php
    $retentionLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $retentionTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $retentionText = static fn (string $key, array $replace = []) => $retentionTranslator->get('backup-retention.'.$key, $retentionLocale, $replace);
    $budget = $plan['storage'] ?? null;
    $retentionBytes = static function (int $bytes): string {
        if ($bytes < 1024) return number_format($bytes).' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1).' KiB';
        if ($bytes < 1073741824) return number_format($bytes / 1048576, 1).' MiB';
        return number_format($bytes / 1073741824, 2).' GiB';
    };
    $reasonSummary = $plan['reason_summary'] ?? null;
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
            @if ($reasonSummary)
                <h3 id="backup-retention-reasons">{{ $retentionText('summary_title') }}</h3>
                <p class="wb-text-sm wb-text-muted">{{ $retentionText('summary_help') }}</p>
                @if (in_array($reasonSummary['status'], ['partial', 'unavailable'], true))
                    <div class="wb-alert wb-alert-warning"><div>{{ $retentionText('summary_partial') }}</div></div>
                @endif
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped" aria-labelledby="backup-retention-reasons">
                        <thead><tr><th scope="col">{{ $retentionText('reason') }}</th><th scope="col">{{ $retentionText('summary_records') }}</th><th scope="col">{{ $retentionText('summary_files') }}</th><th scope="col">{{ $retentionText('summary_bytes') }}</th></tr></thead>
                        <tbody>
                            @foreach ($reasonSummary['groups'] as $group)
                                <tr>
                                    <th scope="row">{{ $retentionText('reason_'.$group['reason']) }}</th>
                                    <td>{{ number_format($group['record_count']) }}</td>
                                    <td>{{ number_format($group['file_count']) }}</td>
                                    <td>{{ $retentionBytes($group['bytes']) }}</td>
                                </tr>
                            @endforeach
                            @if ($reasonSummary['other_files']['bytes'] === null || $reasonSummary['other_files']['file_count'] > 0 || $reasonSummary['other_files']['bytes'] > 0)
                                <tr>
                                    <th scope="row">{{ $retentionText('summary_other') }}<div class="wb-text-sm wb-text-muted">{{ $retentionText('summary_other_help') }}</div></th>
                                    <td>—</td>
                                    <td>{{ $reasonSummary['other_files']['file_count'] === null ? '—' : number_format($reasonSummary['other_files']['file_count']) }}</td>
                                    <td>{{ $reasonSummary['other_files']['bytes'] === null ? '—' : $retentionBytes($reasonSummary['other_files']['bytes']) }}</td>
                                </tr>
                            @elseif ($reasonSummary['groups'] === [])
                                <tr><td colspan="4">{{ $retentionText('summary_empty') }}</td></tr>
                            @endif
                        </tbody>
                        <tfoot><tr><th scope="row">{{ $retentionText('summary_total') }}</th><td>{{ number_format($reasonSummary['record_count']) }}</td><td>{{ $reasonSummary['file_count'] === null ? '—' : number_format($reasonSummary['file_count']) }}</td><td>{{ $reasonSummary['bytes'] === null ? '—' : $retentionBytes($reasonSummary['bytes']) }}</td></tr></tfoot>
                    </table>
                </div>
                <p class="wb-text-sm wb-text-muted">{{ $retentionText('summary_shared_help') }}</p>
                <p class="wb-text-sm wb-text-muted">{{ $retentionText('summary_rule_help') }}</p>
            @endif
            @if ($plan['last_run'] ?? null)
                <p class="wb-text-sm">{{ $retentionTranslator->get('storage.last_run', $retentionLocale) }}: {{ $plan['last_run']['finished_at'] ?? $plan['last_run']['started_at'] }} · {{ $retentionTranslator->get('storage.'.$plan['last_run']['status'], $retentionLocale) }}</p>
                <p class="wb-text-sm">{{ $retentionTranslator->get('storage.deleted', $retentionLocale, ['count' => $plan['last_run']['deleted_count'], 'bytes' => number_format($plan['last_run']['deleted_bytes']), 'failures' => $plan['last_run']['failure_count']]) }}</p>
            @endif
        </div>
    </div>
@endif
