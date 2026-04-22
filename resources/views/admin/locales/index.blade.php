@extends('layouts.admin', ['title' => 'Locales', 'heading' => 'Locales'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Locales',
        'description' => 'Manage locale lifecycle safely. Disable locales to remove them from active routing and editing, and only delete locales that are fully unused.',
        'count' => $locales->total(),
        'actions' => '<a href="'.route('admin.locales.create').'" class="wb-btn wb-btn-primary">Add Locale</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Usage</th>
                            <th>Lifecycle</th>
                            <th>Action</th>
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
                                            <span class="wb-status-pill wb-status-info">Default</span>
                                        @endif
                                        <span class="wb-status-pill {{ $locale->is_enabled ? 'wb-status-active' : 'wb-status-pending' }}">{{ $locale->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                        @if ($report?->inUse())
                                            <span class="wb-status-pill wb-status-info">In Use</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-text-sm">Sites: {{ $report?->count('site_assignments') ?? 0 }}</div>
                                        <div class="wb-text-sm">Pages: {{ $report?->count('page_translations') ?? 0 }}</div>
                                        <div class="wb-text-sm">Blocks: {{ $report?->count('block_translation_rows') ?? 0 }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1 wb-text-sm">
                                        @if ($locale->is_default)
                                            <div class="wb-text-muted">Default locale cannot be disabled or deleted.</div>
                                        @elseif ($report?->inUse())
                                            <div class="wb-text-muted">Cannot delete because this locale is in use.</div>
                                        @elseif ($locale->is_enabled)
                                            <div class="wb-text-muted">Disable to remove it from active routing and editing.</div>
                                        @else
                                            <div class="wb-text-muted">Disabled locale keeps translation data until deleted.</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        <a href="{{ route('admin.locales.edit', $locale) }}" class="wb-action-btn wb-action-btn-edit" title="Edit locale" aria-label="Edit locale">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>

                                        @if ($report?->canEnable())
                                            <form method="POST" action="{{ route('admin.locales.enable', $locale) }}">
                                                @csrf
                                                <button type="submit" class="wb-action-btn" title="Enable locale" aria-label="Enable locale">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @elseif ($report?->canDisable())
                                            <form method="POST" action="{{ route('admin.locales.disable', $locale) }}">
                                                @csrf
                                                <button type="submit" class="wb-action-btn" title="Disable locale" aria-label="Disable locale">
                                                    <i class="wb-icon wb-icon-eye-off" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="wb-action-btn" aria-disabled="true" title="{{ $report?->disableBlockedReason() ?? 'Locale cannot be toggled' }}">
                                                <i class="wb-icon {{ $locale->is_enabled ? 'wb-icon-eye-off' : 'wb-icon-eye' }}" aria-hidden="true"></i>
                                            </span>
                                        @endif

                                        @if ($report?->canDelete())
                                            <form method="POST" action="{{ route('admin.locales.destroy', $locale) }}" onsubmit="return confirm('Delete this locale? This action cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete locale" aria-label="Delete locale">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="wb-action-btn wb-action-btn-delete" aria-disabled="true" title="{{ $report?->deleteBlockedReason() ?? 'Locale cannot be deleted safely' }}">
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

        @include('admin.partials.pagination', ['paginator' => $locales])
    </div>
@endsection
