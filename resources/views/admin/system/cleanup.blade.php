@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $text = static fn (string $key, array $replace = []) => $translator->admin('cleanup.'.$key, $adminLocale, $replace);
    $formatBytes = static function (int $bytes): string {
        if ($bytes < 1024) return number_format($bytes).' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1).' KB';
        return number_format($bytes / 1048576, 1).' MB';
    };
    $cards = [
        'backups' => ['title' => $text('backups'), 'result' => $backupPreview, 'count' => $backupPreview->candidateCount(), 'bytes' => $backupPreview->candidateBytes, 'help' => $text('backups_help')],
        'asset-revisions' => ['title' => $text('asset_revisions'), 'result' => $overview['asset_revisions'], 'count' => $overview['asset_revisions']->candidateCount, 'bytes' => $overview['asset_revisions']->candidateBytes, 'help' => $text('asset_revisions_help')],
        'media-variants' => ['title' => $text('media_variants'), 'result' => $overview['media_variants'], 'count' => $overview['media_variants']->candidateCount, 'bytes' => $overview['media_variants']->candidateBytes, 'help' => $text('media_variants_help')],
        'temporary-workspaces' => ['title' => $text('temporary_workspaces'), 'result' => $overview['temporary_workspaces'], 'count' => $overview['temporary_workspaces']->candidateCount, 'bytes' => $overview['temporary_workspaces']->candidateBytes, 'help' => $text('temporary_workspaces_help')],
    ];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description')])
    @include('webblocks-cms::admin.partials.flash')

    <form method="POST" action="{{ route('admin.system.cleanup.update') }}" class="wb-card wb-stack wb-gap-0">
        @csrf
        @method('PUT')
        <div class="wb-card-header"><strong>{{ $text('retention_policy') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <input type="hidden" name="backup_cleanup_enabled" value="0">
            <label class="wb-cluster wb-cluster-2"><input name="backup_cleanup_enabled" type="checkbox" value="1" @checked($settings['enabled'])> <span>{{ $text('automatic_backup_cleanup') }}</span></label>
            <div class="wb-grid wb-grid-2 wb-gap-4">
                @foreach ([
                    'backup_cleanup_pre_update_days' => [$text('pre_update_days'), $settings['pre_update_days'], 3650],
                    'backup_cleanup_keep_latest_pre_update' => [$text('keep_latest_backups'), $settings['keep_latest_pre_update'], 100],
                    'backup_cleanup_restore_safety_days' => [$text('restore_safety_days'), $settings['restore_safety_days'], 3650],
                    'backup_cleanup_content_apply_days' => [$text('content_apply_days'), $settings['content_apply_days'], 3650],
                    'asset_revision_days' => [$text('asset_revision_days'), $settings['asset_revision_days'], 3650],
                    'keep_latest_asset_revisions' => [$text('keep_latest_asset_revisions'), $settings['keep_latest_asset_revisions'], 1000],
                    'temporary_workspace_hours' => [$text('temporary_workspace_hours'), $settings['temporary_workspace_hours'], 8760],
                ] as $name => [$label, $value, $max])
                    <div class="wb-field wb-stack-2"><label for="cleanup_{{ $name }}">{{ $label }}</label><input class="wb-input" id="cleanup_{{ $name }}" name="{{ $name }}" type="number" min="1" max="{{ $max }}" required value="{{ old($name, $value) }}"></div>
                @endforeach
            </div>
        </div>
        <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ $text('save') }}</button></div>
    </form>

    <div class="wb-grid wb-grid-2 wb-gap-4 wb-mt-4">
        @foreach ($cards as $category => $card)
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $card['title'] }}</strong></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <p class="wb-text-muted">{{ $card['help'] }}</p>
                    <div class="wb-alert wb-alert-info"><div><div class="wb-alert-title">{{ $text('preview') }}</div><div>{{ $text('eligible', ['count' => $card['count'], 'size' => $formatBytes($card['bytes'])]) }}</div></div></div>
                </div>
                <div class="wb-card-footer wb-cluster wb-cluster-end">
                    <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#cleanup-{{ $category }}-modal" @disabled($card['count'] === 0)>{{ $text('clean_now') }}</button>
                </div>
            </div>
        @endforeach
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
                            <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $text('page_revisions') }}</td>
                            <td>{{ number_format($overview['page_revisions']) }}</td>
                            <td class="wb-table-actions"><span class="wb-text-muted">{{ $text('retained') }}</span></td>
                        </tr>
                        <tr>
                            <td>{{ $text('shared_slot_revisions') }}</td>
                            <td>{{ number_format($overview['shared_slot_revisions']) }}</td>
                            <td class="wb-table-actions"><span class="wb-text-muted">{{ $text('retained') }}</span></td>
                        </tr>
                        <tr>
                            <td>{{ $text('transfer_packages') }}</td>
                            <td>{{ number_format($overview['transfer_packages']) }}</td>
                            <td class="wb-table-actions"><a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ route('admin.site-transfers.exports.index') }}">{{ $text('manage') }}</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wb-card-footer wb-text-sm wb-text-muted">{{ $text('retained_history_help') }}</div>
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
