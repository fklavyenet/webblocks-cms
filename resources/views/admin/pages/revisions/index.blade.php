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
            <strong>{{ $adminText('version_history') }}</strong>
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
                                <th>{{ $adminText('saved_at') }}</th>
                                <th>{{ $adminText('changes') }}</th>
                                <th>{{ $adminText('page_state') }}</th>
                                <th>{{ $adminText('saved_by') }}</th>
                                <th class="wb-table-actions">{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revisions as $revision)
                                <tr>
                                    <td>{{ $revision->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $revision->labelText() }}</strong>
                                            <span class="wb-text-sm wb-text-muted">
                                                @if ($revision->display_summary['type'] === 'initial')
                                                    {{ $adminText('summary_initial') }}
                                                @elseif ($revision->display_summary['type'] === 'unchanged')
                                                    {{ $adminText('summary_unchanged') }}
                                                @else
                                                    {{ collect($revision->display_summary['categories'])->map(fn ($key) => $adminText($key))->implode(', ') }}{{ $revision->display_summary['extra'] > 0 ? ' · '.$adminText('summary_more', ['count' => $revision->display_summary['extra']]) : '' }}
                                                @endif
                                            </span>
                                            @if ($revision->restoredFrom)
                                                <span class="wb-text-sm wb-text-muted">{{ $adminText('restored_from_revision', ['id' => $revision->restoredFrom->id]) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wb-badge">{{ str(data_get($revision->snapshot, 'page.status', 'unknown'))->headline() }}</span>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1 wb-text-sm">
                                            <span>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $revision->createdByUser])</span>
                                            <span class="wb-text-muted">{{ $revision->sourceText() }}</span>
                                        </div>
                                    </td>
                                    <td class="wb-table-actions">
                                        <a href="{{ route('admin.pages.revisions.show', [$page, $revision]) }}" class="wb-btn wb-btn-secondary">{{ $adminText('review') }}</a>
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
