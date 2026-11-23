@php
    use Illuminate\Support\Str;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $recoveryLocale = app(AdminLocaleResolver::class)->locale();
    $recoveryTranslator = app(CmsTranslator::class);
    $recoveryText = static fn (string $key, array $replace = []) => $recoveryTranslator->admin('plugin_recovery.'.$key, $recoveryLocale, $replace);
    $heading = $recoveryText('title');
    $actions = Route::has('admin.system.plugins.index')
        ? '<a href="'.e(route('admin.system.plugins.index')).'" class="wb-btn wb-btn-secondary"><i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i> '.e($recoveryText('back_to_plugins')).'</a>'
        : (Route::has('admin.dashboard') ? '<a href="'.e(route('admin.dashboard')).'" class="wb-btn wb-btn-secondary">'.e($recoveryText('return')).'</a>' : null);
@endphp

@extends('webblocks-cms::layouts.admin-recovery', ['title' => $heading])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $heading, 'actions' => $actions])

    <div class="wb-alert wb-alert-info">
        <div class="wb-cluster wb-cluster-2">
            <i class="wb-icon wb-icon-shield-check" aria-hidden="true"></i>
            <strong>{{ $recoveryText('mode') }}</strong>
        </div>
        <p class="wb-m-0">{{ $recoveryText('description') }}</p>
    </div>
    @if (session('status'))
        <div class="wb-alert wb-alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($plugins === [])
        <div class="wb-empty"><div class="wb-empty-title">{{ $recoveryText('empty') }}</div></div>
    @else
        <div class="wb-table-wrap">
            <table class="wb-table wb-extension-recovery-table" role="table" aria-label="{{ $heading }}">
                <thead role="rowgroup">
                    <tr role="row">
                        @foreach (['plugin', 'version', 'status', 'actions'] as $column)
                            <th scope="col" role="columnheader">{{ $recoveryTranslator->admin('system_plugins_index.'.$column, $recoveryLocale) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach (collect($plugins)->sortByDesc('failed') as $plugin)
                        @php
                            $previous = $plugin['previous'];
                            $identity = ['plugin' => $plugin['label']];
                            $operations = ['disable' => $recoveryText('disable')];
                            if (($previous['rollback_safe'] ?? false) === true) {
                                $operations['restore'] = $recoveryText('restore', ['version' => $previous['version']]);
                                $identity['version'] = $previous['version'];
                            }
                        @endphp
                        <tr role="row" @class(['wb-extension-recovery-quarantined' => $plugin['failed']])>
                            <th scope="row" role="rowheader" class="wb-table-break">
                                {{ $plugin['label'] }}
                                @if ($plugin['failed'] && $plugin['label'] !== $plugin['handle'])
                                    <div class="wb-text-sm wb-text-muted"><code>{{ $plugin['handle'] }}</code></div>
                                @endif
                            </th>
                            <td role="cell">
                                <span class="wb-extension-recovery-mobile-label wb-text-muted" aria-hidden="true">{{ $recoveryTranslator->admin('system_plugins_index.version', $recoveryLocale) }}</span>
                                {{ $plugin['version'] }}
                            </td>
                            <td role="cell">
                                @if ($plugin['failed'])
                                    <span class="wb-status wb-status-pending">{{ $recoveryText('quarantined_status') }}</span>
                                    <div class="wb-text-sm wb-text-muted">{{ $recoveryText('quarantined') }}</div>
                                    @if (Str::isUuid($plugin['failure_reference'] ?? ''))
                                        <details class="wb-collapse-panel wb-text-sm wb-mt-1">
                                            <summary class="wb-collapse-trigger">{{ $recoveryText('diagnostic_details') }}</summary>
                                            <div class="wb-collapse-body">
                                                <p class="wb-m-0 wb-table-break">{{ $recoveryTranslator->admin('extension_errors.reference', $recoveryLocale, ['reference' => $plugin['failure_reference']]) }}</p>
                                            </div>
                                        </details>
                                    @endif
                                @else
                                    <span class="wb-status {{ $plugin['enabled'] ? 'wb-status-active' : 'wb-status-inactive' }}">
                                        {{ $recoveryText($plugin['enabled'] ? 'enabled' : 'disabled') }}
                                    </span>
                                @endif
                            </td>
                            <td role="cell" class="wb-extension-recovery-actions">
                                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                    @foreach ($operations as $operation => $label)
                                        @php($modalId = 'extension-recovery-'.$operation.'-'.$plugin['handle'])
                                        <button class="wb-btn wb-btn-secondary wb-btn-sm" type="button" data-wb-toggle="modal"
                                                data-wb-target="#{{ $modalId }}" aria-haspopup="dialog" aria-controls="{{ $modalId }}"
                                                aria-label="{{ $label }} · {{ $plugin['label'] }}">{{ $label }}</button>
                                        @push('overlays')
                                            @include('webblocks-cms::admin.system.plugins.partials.recovery-confirmation', [
                                                'id' => $modalId,
                                                'action' => route('admin.plugins.recovery.update', $plugin['handle']),
                                                'operation' => $operation,
                                                'expectedVersion' => $operation === 'restore' ? $previous['version'] : null,
                                                'title' => $label,
                                                'description' => $recoveryText('confirm_'.$operation, $identity),
                                                'cancelLabel' => $recoveryTranslator->admin('common.cancel', $recoveryLocale),
                                            ])
                                        @endpush
                                    @endforeach
                                </div>
                                @if ($previous && ! isset($operations['restore']))
                                    <p class="wb-text-sm wb-text-muted wb-mt-1 wb-mb-0">{{ $recoveryText('restore_unsafe') }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
