@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('shared_slots.'.$key, $adminLocale, $replace);
  $pageTitle = $adminText('revision_title', ['id' => $revision->id]);
  $historyUrl = route('admin.shared-slots.revisions.index', $sharedSlot);
  ob_start();
@endphp
<div class="wb-cluster wb-cluster-2">
    <a href="{{ $historyUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('back_to_revision_history') }}</a>
    @if ($canRestoreRevisions)
        <button
            type="button"
            class="wb-btn wb-btn-secondary"
            data-wb-toggle="modal"
            data-wb-target="#restore-shared-slot-revision-{{ $revision->id }}"
            aria-haspopup="dialog"
        >{{ $adminText('restore_revision') }}</button>
    @endif
</div>
@php
    $pageHeaderActions = trim(ob_get_clean());
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $adminText('revision_description'),
        'actions' => $pageHeaderActions,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $adminText('revision_metadata') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                <div><strong>{{ $adminText('shared_slot') }}:</strong> {{ $sharedSlot->name }}</div>
                <div><strong>{{ $adminText('created') }}:</strong> {{ $revision->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                <div><strong>{{ $adminText('source') }}:</strong> {{ $revision->sourceText() }}</div>
                <div><strong>{{ $adminText('event') }}:</strong> {{ $revision->eventText() }}</div>
                <div><strong>{{ $adminText('user') }}:</strong> @include('webblocks-cms::admin.partials.audit-actor', ['actor' => $revision->createdByUser])</div>
                <div><strong>{{ $adminText('summary') }}:</strong> {{ $revision->summary ?? $adminText('none') }}</div>
                @if ($revision->restoredFrom)
                    <div><strong>{{ $adminText('restored_from') }}:</strong> {{ $adminText('revision_title', ['id' => $revision->restoredFrom->id]) }}</div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $adminText('snapshot_metadata') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                <div><strong>{{ $adminText('name') }}:</strong> {{ $snapshotMetadata['name'] ?? '-' }}</div>
                <div><strong>{{ $adminText('handle') }}:</strong> <code>{{ $snapshotMetadata['handle'] ?? '-' }}</code></div>
                <div><strong>{{ $adminText('slot') }}:</strong> {{ $snapshotMetadata['slot_name'] ?? $adminText('any') }}</div>
                <div><strong>{{ $adminText('page_layout') }}:</strong> {{ $snapshotMetadata['public_shell'] ?? $adminText('any_page_layout') }}</div>
                <div><strong>{{ $adminText('status') }}:</strong> {{ array_key_exists('is_active', $snapshotMetadata) ? ((bool) $snapshotMetadata['is_active'] ? $adminText('active') : $adminText('inactive')) : '-' }}</div>
                <div class="wb-text-danger">{{ $adminText('snapshot_restore_warning') }}</div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('snapshot_block_tree') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('block_count', ['count' => $snapshotBlocks->count()]) }}</span>
        </div>
        <div class="wb-card-body">
            @if ($snapshotBlocks->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('no_blocks_revision_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('no_blocks_revision_help') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('block') }}</th>
                                <th>{{ $adminText('preview') }}</th>
                                <th>{{ $adminText('order') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshotBlocks as $snapshotBlock)
                                <tr>
                                    <td>
                                        <span style="padding-left: {{ $snapshotBlock['depth'] * 1.25 }}rem; display: inline-block;">
                                            {{ str($snapshotBlock['type'])->replace('-', ' ')->headline() }}
                                        </span>
                                    </td>
                                    <td>{{ $snapshotBlock['title'] ?: $adminText('no_text_preview') }}</td>
                                    <td>{{ $snapshotBlock['sort_order'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('overlays')
    @if ($canRestoreRevisions)
        @include('webblocks-cms::admin.shared-slots.partials.restore-revision-modal', [
            'sharedSlot' => $sharedSlot,
            'revision' => $revision,
        ])
    @endif
@endpush
