@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_revisions.'.$key, $adminLocale, $replace);
  $pageTitle = $adminText('title', ['title' => $page->title]);
  $pagePublicUrl = $page->isPublished() ? $page->publicUrl() : null;
  $pageEditUrl = route('admin.pages.edit', $page);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $adminText('description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.$pageEditUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_page')).'</a>'.($pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>'.e($adminText('view_page')).'</span></a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
            <span>{{ $adminText('site') }}: <strong>{{ $page->site?->name ?? $adminText('fallback_site') }}</strong></span>
            <span>{{ $adminText('current_workflow') }}: <strong>{{ $page->workflowLabel() }}</strong></span>
            <span>{{ $adminText('total_revisions') }}: <strong>{{ $revisions->count() }}</strong></span>
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
                                <th>{{ $adminText('revision') }}</th>
                                <th>{{ $adminText('audit') }}</th>
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
                                            @if ($revision->reason)
                                                <span class="wb-text-sm wb-text-muted">{{ $revision->reason }}</span>
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
                                        @if ($canRestoreRevisions)
                                            <button
                                                type="button"
                                                class="wb-btn wb-btn-secondary"
                                                data-wb-toggle="modal"
                                                data-wb-target="#restore-page-revision-{{ $revision->id }}"
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
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'restore-page-revision-'.$revision->id,
                'title' => $adminText('restore_title'),
                'description' => $adminText('restore_description'),
                'action' => route('admin.pages.revisions.restore', [$page, $revision]),
                'method' => 'POST',
                'submitLabel' => $adminText('restore'),
            ])
                <p>{{ $adminText('restore_confirm_prefix') }} <strong>{{ $adminText('revision') }} #{{ $revision->id }}</strong>? {{ $adminText('restore_warning') }}</p>

                <dl class="wb-stack wb-gap-2 wb-text-sm">
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <dt class="wb-text-muted">{{ $adminText('created') }}</dt>
                        <dd>{{ $revision->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <dt class="wb-text-muted">{{ $adminText('event') }}</dt>
                        <dd>{{ $revision->eventText() }}</dd>
                    </div>
                </dl>
            @endcomponent
        @endforeach
    @endif
@endpush
