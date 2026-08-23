@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $translator = app(CmsTranslator::class);
  $text = static fn (string $key, array $replace = []) => $translator->admin('page_revisions.'.$key, $adminLocale, $replace);
  $title = $text('review_title', ['id' => $revision->id, 'title' => $page->title]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $title,
        'description' => $text('review_description'),
        'actions' => '<a href="'.route('admin.pages.revisions.index', $page).'" class="wb-btn wb-btn-secondary">'.e($text('back_to_version_history')).'</a>',
    ])
    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $text('version_details') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                <div><strong>{{ $text('saved_at') }}:</strong> {{ $revision->created_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                <div><strong>{{ $text('saved_by') }}:</strong> @include('webblocks-cms::admin.partials.audit-actor', ['actor' => $revision->createdByUser])</div>
                <div><strong>{{ $text('source') }}:</strong> {{ $revision->sourceText() }}</div>
                <div><strong>{{ $text('event') }}:</strong> {{ $revision->eventText() }}</div>
                <div><strong>{{ $text('summary') }}:</strong> {{ $revision->reason ?: $revision->labelText() }}</div>
                <div><strong>{{ $text('snapshot_schema') }}:</strong> {{ $inspection['snapshot']['schema_version'] ?? '—' }}</div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $text('restore_readiness') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert {{ $inspection['health']['status'] === 'blocked' ? 'wb-alert-danger' : ($inspection['health']['status'] === 'warning' ? 'wb-alert-warning' : 'wb-alert-success') }}">
                    <div>
                        <div class="wb-alert-title">{{ $text('health_'.$inspection['health']['status']) }}</div>
                        <div>{{ $text('health_'.$inspection['health']['status'].'_help') }}</div>
                    </div>
                </div>
                @foreach ($inspection['health']['issues'] as $issue)
                    @php
                        $issueReplace = $issue['replace'];
                        if (isset($issueReplace['type_key'])) {
                            $issueReplace['type'] = $text($issueReplace['type_key']);
                        }
                    @endphp
                    <div class="wb-text-sm {{ $issue['level'] === 'blocking' ? 'wb-text-danger' : 'wb-text-muted' }}">{{ $text($issue['message_key'], $issueReplace) }}</div>
                @endforeach
                <div class="wb-text-sm wb-text-muted">{{ $text('shared_slot_boundary') }}</div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $text('current_vs_version') }}</strong></div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead><tr><th>{{ $text('field') }}</th><th>{{ $text('current_page') }}</th><th>{{ $text('selected_version') }}</th><th>{{ $text('result') }}</th></tr></thead>
                    <tbody>
                        @foreach ($inspection['comparison'] as $row)
                            <tr>
                                <td><strong>{{ $text($row['label_key']) }}</strong></td>
                                <td>{{ $row['current'] }}</td>
                                <td>{{ $row['selected'] }}</td>
                                <td><span class="wb-badge">{{ $row['current'] === $row['selected'] ? $text('unchanged') : $text('will_change') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $text('restore_scope') }}</strong></div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead><tr><th>{{ $text('category') }}</th><th>{{ $text('contents') }}</th><th>{{ $text('result') }}</th></tr></thead>
                    <tbody>
                        @foreach ($inspection['changes'] as $change)
                            <tr>
                                <td><strong>{{ $text($change['label_key']) }}</strong></td>
                                <td>
                                    @if ($change['before_count'] !== null)
                                        {{ $text('count_change', ['current' => $change['before_count'], 'selected' => $change['after_count']]) }}
                                    @else
                                        {{ $text('complete_category') }}
                                    @endif
                                </td>
                                <td><span class="wb-badge">{{ $change['changed'] ? $text('will_change') : $text('unchanged') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <span class="wb-text-sm wb-text-danger">{{ $text('live_restore_warning') }}</span>
            @if ($canRestoreRevisions)
                <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#restore-page-version-modal">{{ $text('replace_current_page') }}</button>
            @else
                <span class="wb-text-sm wb-text-muted">{{ $inspection['health']['status'] === 'blocked' ? $text('restore_blocked') : $text('view_only') }}</span>
            @endif
        </div>
    </div>
@endsection

@push('overlays')
    @if ($canRestoreRevisions)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'restore-page-version-modal',
            'title' => $text('replace_current_page'),
            'description' => $text('replace_description'),
            'action' => route('admin.pages.revisions.restore', [$page, $revision]),
            'method' => 'POST',
            'submitLabel' => $text('replace_current_page'),
        ])
            <p>{{ $text('replace_confirm', ['id' => $revision->id]) }}</p>
            <p>{{ $text('restore_warning') }}</p>
        @endcomponent
    @endif
@endpush
