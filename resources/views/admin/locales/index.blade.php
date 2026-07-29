@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localesIndexText = fn (string $key, array $replace = []) => $adminTranslator->admin('locales_index.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localesIndexText('title'), 'heading' => $localesIndexText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $localesIndexText('title'),
        'description' => $localesIndexText('description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $localesIndexText('title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>

            <a href="{{ route('admin.locales.create') }}" class="wb-btn wb-btn-primary">{{ $localesIndexText('add_locale') }}</a>
        </div>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $localesIndexText('code') }}</th>
                            <th>{{ $localesIndexText('name') }}</th>
                            <th>{{ $localesIndexText('status') }}</th>
                            <th>{{ $localesIndexText('usage') }}</th>
                            <th>{{ $localesIndexText('lifecycle') }}</th>
                            <th>{{ $localesIndexText('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locales as $locale)
                            @php($report = $reports->get($locale->id))
                            <tr>
                                <td><code>{{ $locale->code }}</code></td>
                                <td><strong>{{ $locale->name }}</strong></td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        @if ($locale->is_default)
                                            <span class="wb-status-pill wb-status-info">{{ $localesIndexText('default') }}</span>
                                        @endif
                                        <span class="wb-status-pill {{ $locale->is_enabled ? 'wb-status-active' : 'wb-status-pending' }}">{{ $locale->is_enabled ? $localesIndexText('enabled') : $localesIndexText('disabled') }}</span>
                                        @if ($report?->inUse())
                                            <span class="wb-status-pill wb-status-info">{{ $localesIndexText('in_use') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-text-sm">{{ $localesIndexText('sites_count', ['count' => $report?->count('site_assignments') ?? 0]) }}</div>
                                        <div class="wb-text-sm">{{ $localesIndexText('pages_count', ['count' => $report?->count('page_translations') ?? 0]) }}</div>
                                        <div class="wb-text-sm">{{ $localesIndexText('blocks_count', ['count' => $report?->count('block_translation_rows') ?? 0]) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1 wb-text-sm">
                                        @if ($locale->is_default)
                                            <div class="wb-text-muted">{{ $localesIndexText('default_cannot_change') }}</div>
                                        @elseif ($report?->inUse())
                                            <div class="wb-text-muted">{{ $localesIndexText('cannot_delete_in_use') }}</div>
                                        @elseif ($locale->is_enabled)
                                            <div class="wb-text-muted">{{ $localesIndexText('disable_help') }}</div>
                                        @else
                                            <div class="wb-text-muted">{{ $localesIndexText('disabled_keeps_data') }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        <a href="{{ route('admin.locales.edit', $locale) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $localesIndexText('edit_locale') }}" aria-label="{{ $localesIndexText('edit_locale') }}">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>

                                        @if ($report?->canEnable())
                                            <form method="POST" action="{{ route('admin.locales.enable', $locale) }}">
                                                @csrf
                                                <button type="submit" class="wb-action-btn" title="{{ $localesIndexText('enable_locale') }}" aria-label="{{ $localesIndexText('enable_locale') }}">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @elseif ($report?->canDisable())
                                            <form method="POST" action="{{ route('admin.locales.disable', $locale) }}">
                                                @csrf
                                                <button type="submit" class="wb-action-btn" title="{{ $localesIndexText('disable_locale') }}" aria-label="{{ $localesIndexText('disable_locale') }}">
                                                    <i class="wb-icon wb-icon-eye-off" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="wb-action-btn" aria-disabled="true" title="{{ $report?->disableBlockedReason() ?? $localesIndexText('toggle_blocked') }}">
                                                <i class="wb-icon {{ $locale->is_enabled ? 'wb-icon-eye-off' : 'wb-icon-eye' }}" aria-hidden="true"></i>
                                            </span>
                                        @endif

                                        @if ($report?->canDelete())
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-locale-{{ $locale->id }}"
                                                title="{{ $localesIndexText('delete_locale') }}"
                                                aria-label="{{ $localesIndexText('delete_locale') }}"
                                                aria-haspopup="dialog"
                                            >
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <span class="wb-action-btn wb-action-btn-delete" aria-disabled="true" title="{{ $report?->deleteBlockedReason() ?? $localesIndexText('delete_blocked') }}">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $locales])
    </div>
@endsection

@push('overlays')
    @foreach ($locales as $locale)
        @php($report = $reports->get($locale->id))

        @if ($report?->canDelete())
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'delete-locale-'.$locale->id,
                'title' => $localesIndexText('delete_title'),
                'description' => $localesIndexText('delete_description'),
                'action' => route('admin.locales.destroy', $locale),
                'method' => 'DELETE',
                'submitLabel' => $localesIndexText('delete_locale'),
            ])
                <p>{{ $localesIndexText('delete_confirm_prefix') }} <strong>{{ $locale->name }}</strong> (<code>{{ $locale->code }}</code>)? {{ $localesIndexText('cannot_be_undone') }}</p>

                <div class="wb-alert wb-alert-warning">
                    {{ $localesIndexText('delete_unused_warning') }}
                </div>
            @endcomponent
        @endif
    @endforeach
@endpush
