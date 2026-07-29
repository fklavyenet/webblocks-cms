@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('shared_slots.'.$key, $adminLocale, $replace);
  $pageTitle = $adminText('revisions_title', ['name' => $sharedSlot->name]);
  $sharedSlotEditUrl = route('admin.shared-slots.edit', $sharedSlot);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $adminText('revisions_description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.$sharedSlotEditUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_shared_slot')).'</a><a href="'.route('admin.shared-slots.blocks.edit', $sharedSlot).'" class="wb-btn wb-btn-secondary">'.e($adminText('edit_blocks')).'</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
            <span>{{ $adminText('site_label') }}: <strong>{{ $sharedSlot->site?->name ?? $adminText('fallback_site') }}</strong></span>
            <span>{{ $adminText('handle_label') }}: <strong><code>{{ $sharedSlot->handle }}</code></strong></span>
            <span>{{ $adminText('total_revisions') }}: <strong>{{ $revisions->count() }}</strong></span>
            <span class="wb-text-danger">{{ $adminText('restore_warning') }}</span>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('revision_history') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('newest_first') }}</span>
        </div>
        <div class="wb-card-body">
            @if ($revisions->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('no_revisions_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('no_revisions_help') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('created') }}</th>
                                <th>{{ $adminText('event') }}</th>
                                <th>{{ $adminText('audit') }}</th>
                                <th>{{ $adminText('details') }}</th>
                                <th>{{ $adminText('restore') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revisions as $revision)
                                <tr>
                                    <td>{{ $revision->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $revision->labelText() }}</strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $revision->eventText() }}</span>
                                            @if ($revision->summary)
                                                <span class="wb-text-sm wb-text-muted">{{ $revision->summary }}</span>
                                            @endif
                                            @if ($revision->restoredFrom)
                                                <span class="wb-text-sm wb-text-muted">{{ $adminText('restored_from_revision', ['id' => $revision->restoredFrom->id]) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1 wb-text-sm">
                                            <span>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $revision->createdByUser])</span>
                                            <span class="wb-text-muted">{{ $adminText('source') }}: {{ $revision->sourceText() }}</span>
                                            <span class="wb-text-muted">{{ $adminText('event') }}: {{ $revision->eventText() }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.shared-slots.revisions.show', [$sharedSlot, $revision]) }}" class="wb-btn wb-btn-secondary">{{ $adminText('inspect') }}</a>
                                    </td>
                                    <td>
                                        @if ($canRestoreRevisions)
                                            <button
                                                type="button"
                                                class="wb-btn wb-btn-secondary"
                                                data-wb-toggle="modal"
                                                data-wb-target="#restore-shared-slot-revision-{{ $revision->id }}"
                                                aria-haspopup="dialog"
                                            >{{ $adminText('restore') }}</button>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">{{ $adminText('view_only') }}</span>
                                        @endif
                                    </td>
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
        @foreach ($revisions as $revision)
            @include('webblocks-cms::admin.shared-slots.partials.restore-revision-modal', [
                'sharedSlot' => $sharedSlot,
                'revision' => $revision,
            ])
        @endforeach
    @endif
@endpush
