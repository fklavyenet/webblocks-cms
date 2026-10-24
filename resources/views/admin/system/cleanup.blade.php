@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $text = static fn (string $key, array $replace = []) => $translator->admin('cleanup.'.$key, $adminLocale, $replace);
    $storageText = static fn (string $key, array $replace = []) => $translator->get('storage.'.$key, $adminLocale, $replace);
    $retentionText = static fn (string $key, array $replace = []) => $translator->get('backup-retention.'.$key, $adminLocale, $replace);
    $cleanupTab = old('_cleanup_tab', request()->query('tab', 'report'));
    $cleanupTab = in_array($cleanupTab, ['report', 'settings', 'actions'], true) ? $cleanupTab : 'report';
    $formatBytes = static function (int $bytes): string {
        if ($bytes < 1024) return number_format($bytes).' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1).' KB';
        if ($bytes < 1073741824) return number_format($bytes / 1048576, 1).' MB';
        return number_format($bytes / 1073741824, 2).' GB';
    };
    $cards = $overview ? [
        'backups' => ['title' => $text('backups'), 'result' => $backupPreview, 'count' => $backupPreview->candidateCount(), 'bytes' => $backupPreview->candidateBytes, 'help' => $retentionText('cleanup_help')],
        'asset-revisions' => ['title' => $text('asset_revisions'), 'result' => $overview['asset_revisions'], 'count' => $overview['asset_revisions']->candidateCount, 'bytes' => $overview['asset_revisions']->candidateBytes, 'help' => $text('asset_revisions_help')],
        'media-variants' => ['title' => $text('media_variants'), 'result' => $overview['media_variants'], 'count' => $overview['media_variants']->candidateCount, 'bytes' => $overview['media_variants']->candidateBytes, 'help' => $text('media_variants_help')],
        'temporary-workspaces' => ['title' => $text('temporary_workspaces'), 'result' => $overview['temporary_workspaces'], 'count' => $overview['temporary_workspaces']->candidateCount, 'bytes' => $overview['temporary_workspaces']->candidateBytes, 'help' => $text('temporary_workspaces_help')],
        'page-revisions' => ['title' => $text('page_revisions'), 'result' => $overview['page_revision_cleanup'], 'count' => $overview['page_revision_cleanup']->candidateCount, 'bytes' => $overview['page_revision_cleanup']->candidateBytes, 'help' => $text('page_revisions_help')],
        'shared-slot-revisions' => ['title' => $text('shared_slot_revisions'), 'result' => $overview['shared_slot_revision_cleanup'], 'count' => $overview['shared_slot_revision_cleanup']->candidateCount, 'bytes' => $overview['shared_slot_revision_cleanup']->candidateBytes, 'help' => $text('shared_slot_revisions_help')],
    ] : [];
    $policyGroups = [
        'backups' => [
            'backup_cleanup_pre_update_days' => [$text('pre_update_days'), $settings['pre_update_days'], 3650],
            'backup_cleanup_keep_latest_pre_update' => [$text('keep_latest_backups'), $settings['keep_latest_pre_update'], 100],
            'backup_cleanup_restore_safety_days' => [$text('restore_safety_days'), $settings['restore_safety_days'], 3650],
            'backup_cleanup_content_apply_days' => [$text('content_apply_days'), $settings['content_apply_days'], 3650],
        ],
        'asset-revisions' => [
            'asset_revision_days' => [$text('asset_revision_days'), $settings['asset_revision_days'], 3650],
            'keep_latest_asset_revisions' => [$text('keep_latest_asset_revisions'), $settings['keep_latest_asset_revisions'], 1000],
        ],
        'page-revisions' => [
            'page_revision_days' => [$text('page_revision_days'), $settings['page_revision_days'], 3650],
            'keep_latest_page_revisions' => [$text('keep_latest_page_revisions'), $settings['keep_latest_page_revisions'], 1000],
        ],
        'shared-slot-revisions' => [
            'shared_slot_revision_days' => [$text('shared_slot_revision_days'), $settings['shared_slot_revision_days'], 3650],
            'keep_latest_shared_slot_revisions' => [$text('keep_latest_shared_slot_revisions'), $settings['keep_latest_shared_slot_revisions'], 1000],
        ],
        'temporary-workspaces' => [
            'temporary_workspace_hours' => [$text('temporary_workspace_hours'), $settings['temporary_workspace_hours'], 8760],
        ],
    ];
    $scheduleTimes = ['asset-revisions' => '04:00', 'media-variants' => '04:10', 'temporary-workspaces' => '04:20', 'page-revisions' => '04:30', 'shared-slot-revisions' => '04:45'];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description')])
    @include('webblocks-cms::admin.partials.flash')

    @include('webblocks-cms::admin.partials.maintenance-report-status', ['report' => $maintenanceReport])
    @foreach ($overview['capacity_warnings'] ?? [] as $warning)
        <div class="wb-alert wb-alert-warning wb-mb-4"><div><div class="wb-alert-title">{{ $text('capacity_warning_title') }}</div><div>{{ $text('capacity_warning_'.$warning) }}</div></div></div>
    @endforeach

    <div class="wb-tabs" data-wb-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $retentionText('tabs') }}">
            @foreach (['report', 'settings', 'actions'] as $tab)
                <button type="button" class="wb-tabs-btn {{ $cleanupTab === $tab ? 'is-active' : '' }}" data-wb-tab="cleanup-{{ $tab }}-panel" aria-selected="{{ $cleanupTab === $tab ? 'true' : 'false' }}" @if ($cleanupTab !== $tab) tabindex="-1" @endif>{{ $retentionText($tab.'_tab') }}</button>
            @endforeach
        </div>
        <div class="wb-tabs-panels">
            <div class="wb-tabs-panel {{ $cleanupTab === 'report' ? 'is-active' : '' }}" id="cleanup-report-panel" @if ($cleanupTab !== 'report') hidden @endif>
    @if ($overview)
    <div class="wb-card wb-mb-4">
        <div class="wb-card-header"><strong>{{ $storageText('report_title') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <p class="wb-text-muted">{{ $storageText('report_help', ['time' => $overview['storage_report']['measured_at']]) }}</p>
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead><tr><th scope="col">{{ $text('history_type') }}</th><th scope="col">{{ $storageText('files') }}</th><th scope="col">{{ $text('storage_size') }}</th><th scope="col">{{ $storageText('status') }}</th><th scope="col">{{ $storageText('eligible_bytes') }}</th></tr></thead>
                    <tbody>
                        @foreach ($overview['storage_report']['categories'] as $category => $measurement)
                            @php
                                $eligibleBytes = match ($category) {
                                    'backups' => $backupPreview->candidateBytes,
                                    'asset_revisions' => $overview['asset_revisions']->failures === [] ? $overview['asset_revisions']->candidateBytes : null,
                                    'media_variants' => $overview['media_variants']->failures === [] ? $overview['media_variants']->candidateBytes : null,
                                    'temporary_workspaces' => $overview['temporary_workspaces']->failures === [] ? $overview['temporary_workspaces']->candidateBytes : null,
                                    default => null,
                                };
                            @endphp
                            <tr>
                                <td>{{ $storageText($category) }}</td>
                                <td>{{ in_array($measurement['status'], ['available', 'missing', 'partial'], true) ? number_format($measurement['files']) : '—' }}</td>
                                <td>{{ in_array($measurement['status'], ['available', 'missing', 'partial'], true) ? $formatBytes($measurement['bytes']) : '—' }}</td>
                                <td>{{ $storageText($measurement['status']) }}</td>
                                <td>{{ $eligibleBytes === null ? '—' : $formatBytes($eligibleBytes) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="wb-text-sm wb-text-muted">{{ $storageText('backup_policy') }}</p>
        </div>
    </div>

    @include('webblocks-cms::admin.system.backups.retention-summary', ['plan' => $backupPreview->overview])
    <div class="wb-grid wb-grid-2 wb-gap-4 wb-mt-4">
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $text('block_growth') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div>{{ $text('live_blocks') }}: <strong>{{ number_format($overview['block_storage']['live_count']) }}</strong></div>
                <div>{{ $text('highest_block_id') }}: <strong>{{ number_format($overview['block_storage']['highest_id']) }}</strong></div>
                <p class="wb-text-sm wb-text-muted">{{ $text('block_growth_help', ['count' => number_format($overview['block_storage']['id_gap'])]) }}</p>
            </div>
        </div>
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $text('media_storage') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div>{{ $text('media_originals') }}: <strong>{{ number_format($overview['media_storage']['items']) }} · {{ $formatBytes($overview['media_storage']['original_bytes']) }}</strong></div>
                <div>{{ $text('media_generated') }}: <strong>{{ number_format($overview['media_storage']['variant_files']) }} · {{ $formatBytes($overview['media_storage']['variant_bytes']) }}</strong></div>
                @if (($overview['media_storage']['variant_measurement_status'] ?? 'available') !== 'available')
                    <p class="wb-text-sm wb-text-muted">{{ $storageText('partial') }}</p>
                @endif
                <p class="wb-text-sm wb-text-muted">{{ $text('media_storage_help') }}</p>
            </div>
        </div>
    </div>

    <div class="wb-card wb-mt-4">
        <div class="wb-card-header"><strong>{{ $text('site_growth') }}</strong></div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead><tr><th>{{ $text('site') }}</th><th>{{ $text('pages') }}</th><th>{{ $text('live_blocks') }}</th><th>{{ $text('page_revisions') }}</th><th>{{ $text('shared_slot_revisions') }}</th><th>{{ $text('storage_size') }}</th></tr></thead>
                    <tbody>
                        @foreach ($overview['site_growth'] as $site)
                            <tr>
                                <td><strong>{{ $site['name'] }}</strong><br><span class="wb-text-sm wb-text-muted">{{ $site['handle'] }}</span></td>
                                <td>{{ number_format($site['pages']) }}</td>
                                <td>{{ number_format($site['blocks']) }}</td>
                                <td>{{ number_format($site['page_revisions']) }}</td>
                                <td>{{ number_format($site['shared_slot_revisions']) }}</td>
                                <td>{{ $formatBytes($site['page_revision_bytes'] + $site['shared_slot_revision_bytes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wb-card wb-mt-4">
        <div class="wb-card-header"><strong>{{ $text('retained_history') }}</strong></div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead>
                        <tr>
                            <th scope="col">{{ $text('history_type') }}</th>
                            <th scope="col">{{ $text('item_count') }}</th>
                            <th scope="col">{{ $text('storage_size') }}</th>
                            <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $text('page_revisions') }}<div class="wb-text-sm wb-text-muted">{{ $text('policy_managed') }}</div></td>
                            <td>{{ number_format($overview['page_revisions']) }}</td>
                            <td>{{ $formatBytes($overview['page_revision_bytes']) }}</td>
                            <td class="wb-table-actions">
                                <div class="wb-action-group">
                                    <a href="{{ route('admin.system.cleanup.index', ['tab' => 'settings']) }}#cleanup-page-revisions-policy" class="wb-action-btn wb-action-btn-edit" title="{{ $storageText('configure_policy', ['category' => $text('page_revisions')]) }}" aria-label="{{ $storageText('configure_policy', ['category' => $text('page_revisions')]) }}"><i class="wb-icon wb-icon-settings" aria-hidden="true"></i></a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>{{ $text('shared_slot_revisions') }}<div class="wb-text-sm wb-text-muted">{{ $text('policy_managed') }}</div></td>
                            <td>{{ number_format($overview['shared_slot_revisions']) }}</td>
                            <td>{{ $formatBytes($overview['shared_slot_revision_bytes']) }}</td>
                            <td class="wb-table-actions">
                                <div class="wb-action-group">
                                    <a href="{{ route('admin.system.cleanup.index', ['tab' => 'settings']) }}#cleanup-shared-slot-revisions-policy" class="wb-action-btn wb-action-btn-edit" title="{{ $storageText('configure_policy', ['category' => $text('shared_slot_revisions')]) }}" aria-label="{{ $storageText('configure_policy', ['category' => $text('shared_slot_revisions')]) }}"><i class="wb-icon wb-icon-settings" aria-hidden="true"></i></a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>{{ $text('transfer_packages') }}</td>
                            <td>{{ number_format($overview['transfer_packages']) }}</td>
                            <td>—</td>
                            <td class="wb-table-actions">
                                <div class="wb-action-group">
                                    <a class="wb-action-btn wb-action-btn-view" href="{{ route('admin.site-transfers.exports.index') }}" title="{{ $storageText('manage_transfers') }}" aria-label="{{ $storageText('manage_transfers') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wb-card-footer wb-text-sm wb-text-muted">{{ $storageText('history_help') }}</div>
    </div>
            @endif
            </div>
            <div class="wb-tabs-panel {{ $cleanupTab === 'settings' ? 'is-active' : '' }}" id="cleanup-settings-panel" @if ($cleanupTab !== 'settings') hidden @endif>
    <form method="POST" action="{{ route('admin.system.cleanup.update') }}" class="wb-card wb-stack wb-gap-0">
        @csrf
        @method('PUT')
        <input type="hidden" name="_cleanup_tab" value="settings">
        <div class="wb-card-header"><strong>{{ $text('retention_policy') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <section class="wb-stack wb-gap-3" role="group" aria-labelledby="cleanup-automatic-title">
                <h3 class="wb-m-0 wb-text-base wb-font-semibold" id="cleanup-automatic-title">{{ $storageText('automatic_title') }}</h3>
                <p class="wb-text-muted">{{ $storageText('automatic_help') }}</p>
                <p>{{ $storageText('scheduler') }}: <strong>{{ $storageText($automation['scheduler']['scheduler_status']) }}</strong></p>
                @unless ($automation['ready'])
                    <div class="wb-alert wb-alert-warning"><div>{{ $storageText('schema_missing') }}</div></div>
                @endunless
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    @foreach ($automation['enabled'] as $category => $enabled)
                        <div class="wb-stack wb-gap-1">
                            <input type="hidden" name="automatic[{{ $category }}]" value="0">
                            <label class="wb-check"><input name="automatic[{{ $category }}]" type="checkbox" value="1" @checked(old('automatic.'.$category, $enabled)) @disabled(! $automation['ready'])> <span>{{ $text(str_replace('-', '_', $category)) }}</span></label>
                            <span class="wb-text-sm wb-text-muted">{{ $storageText(in_array($category, ['page-revisions', 'shared-slot-revisions'], true) ? 'weekly_schedule' : 'daily_schedule', ['time' => $scheduleTimes[$category]]) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
            <p class="wb-text-sm wb-text-muted" id="cleanup-retention-help">{{ $storageText('retention_help') }}</p>
            @foreach ($policyGroups as $category => $fields)
                <section class="wb-stack wb-gap-3" role="group" aria-labelledby="cleanup-{{ $category }}-title" id="cleanup-{{ $category }}-policy" tabindex="-1" @if (in_array($category, ['asset-revisions', 'page-revisions', 'shared-slot-revisions'], true)) aria-describedby="cleanup-retention-help" @endif>
                    <h3 class="wb-m-0 wb-text-base wb-font-semibold" id="cleanup-{{ $category }}-title">{{ $text(str_replace('-', '_', $category)) }}</h3>
                    @if ($category === 'backups')
                        <input type="hidden" name="backup_cleanup_enabled" value="0">
                        <label class="wb-check"><input name="backup_cleanup_enabled" type="checkbox" value="1" @checked(old('backup_cleanup_enabled', $settings['enabled']))> <span>{{ $text('automatic_backup_cleanup') }}</span></label>
                    @endif
                    <div class="wb-grid wb-grid-2 wb-gap-4">
                        @foreach ($fields as $name => [$label, $value, $max])
                            <div class="wb-field wb-stack-2"><label for="cleanup_{{ $name }}">{{ $label }}</label><input class="wb-input" id="cleanup_{{ $name }}" name="{{ $name }}" type="number" min="1" max="{{ $max }}" required value="{{ old($name, $value) }}"></div>
                        @endforeach
                    </div>
                    @if ($category === 'backups')
                        <input type="hidden" name="manual_enabled" value="0">
                        <label class="wb-check"><input name="manual_enabled" type="checkbox" value="1" @checked(old('manual_enabled', $settings['manual_enabled']))><span>{{ $retentionText('manual_enabled') }}</span></label>
                        <div class="wb-grid wb-grid-2 wb-gap-4">
                            @foreach (['manual_days' => 3650, 'keep_latest_manual' => 100, 'keep_latest_restore_safety' => 100, 'keep_latest_content_apply' => 100] as $field => $maximum)
                                <div class="wb-field wb-stack-2"><label for="cleanup_{{ $field }}">{{ $retentionText($field) }}</label><input class="wb-input" id="cleanup_{{ $field }}" name="{{ $field }}" type="number" min="1" max="{{ $maximum }}" required value="{{ old($field, $settings[$field]) }}"></div>
                            @endforeach
                            <div class="wb-field wb-stack-2"><label for="cleanup_budget_gb">{{ $retentionText('budget_gb') }}</label><input class="wb-input" id="cleanup_budget_gb" name="budget_gb" type="number" min="0.01" max="10240" step="0.01" value="{{ old('budget_gb', $settings['budget_gb'] ?: '') }}" aria-describedby="cleanup-budget-help"></div>
                        </div>
                        <p class="wb-text-sm wb-text-muted" id="cleanup-budget-help">{{ $retentionText('budget_help') }}</p>
                    @endif
                </section>
            @endforeach
        </div>
        <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ $text('save') }}</button></div>
    </form>

            </div>
            <div class="wb-tabs-panel {{ $cleanupTab === 'actions' ? 'is-active' : '' }}" id="cleanup-actions-panel" @if ($cleanupTab !== 'actions') hidden @endif>
    <div class="wb-grid wb-grid-2 wb-gap-4 wb-mt-4">
        @foreach ($cards as $category => $card)
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $card['title'] }}</strong></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <p class="wb-text-muted">{{ $card['help'] }}</p>
                    @if ($category !== 'backups' && $card['result']->failures !== [])
                        <div class="wb-alert wb-alert-warning"><div>{{ $storageText('preview_unavailable') }}</div></div>
                    @endif
                    @if ($category !== 'backups')
                        @php($execution = $automation['categories'][$category] ?? null)
                        @if ($execution)
                            <p class="wb-text-sm">{{ $storageText('last_run') }}: {{ $execution['finished_at'] ?? $execution['started_at'] }} · {{ $storageText($execution['status']) }} · {{ $storageText($execution['source']) }}</p>
                            <p class="wb-text-sm">{{ $storageText('deleted', ['count' => $execution['deleted_count'], 'bytes' => number_format($execution['deleted_bytes']), 'failures' => $execution['failure_count']]) }}</p>
                            <p class="wb-text-sm">{{ $storageText('last_success') }}: {{ $execution['last_success_at'] ?? '—' }}</p>
                        @else
                            <p class="wb-text-sm wb-text-muted">{{ $storageText('never') }}</p>
                        @endif
                    @endif
                    @if ($category === 'backups' || $card['result']->failures === [])
                        <div class="wb-alert wb-alert-info"><div><div class="wb-alert-title">{{ $text('preview') }}</div><div>{{ $text('eligible', ['count' => $card['count'], 'size' => $formatBytes($card['bytes'])]) }}</div></div></div>
                    @endif
                </div>
                <div class="wb-card-footer wb-cluster wb-cluster-end">
                    @if ($card['count'] > 0)
                        <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#cleanup-{{ $category }}-modal">{{ $text('clean_now') }}</button>
                    @elseif ($category === 'backups' || $card['result']->failures === [])
                        <span class="wb-text-sm wb-text-muted">{{ $storageText('nothing_to_clean') }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @foreach ($cards as $category => $card)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'cleanup-'.$category.'-modal', 'title' => $card['title'], 'description' => $text('confirm'),
            'action' => route('admin.system.cleanup.run', $category), 'method' => 'POST', 'submitLabel' => $text('clean_now'),
        ])
            <p>{{ $text('eligible', ['count' => $card['count'], 'size' => $formatBytes($card['bytes'])]) }}</p>
        @endcomponent
    @endforeach
@endpush
