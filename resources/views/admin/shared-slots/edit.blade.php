@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('shared_slots.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('edit_title');
  $indexUrl = route('admin.shared-slots.index', ['site' => $sharedSlot->site_id]);
  $blocksUrl = route('admin.shared-slots.blocks.edit', $sharedSlot);
  $revisionsUrl = $canViewRevisions ? route('admin.shared-slots.revisions.index', $sharedSlot) : null;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$indexUrl.'">'.e($adminText('title')).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($sharedSlot->name).'</span></li></ol></nav>',
        'title' => $localizedPageTitle,
        'context' => '<span>'.e($sharedSlot->site?->name ?? $adminText('fallback_site')).'</span>',
        'actions' => '<div class="wb-cluster wb-cluster-2">'.($revisionsUrl ? '<a href="'.$revisionsUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('revision_history')).'</a>' : '').'<a href="'.$blocksUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('edit_blocks')).'</a><a href="'.$indexUrl.'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_shared_slots')).'</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <form method="POST" action="{{ route('admin.shared-slots.update', $sharedSlot) }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')

                <div class="wb-card-body">
                    @include('webblocks-cms::admin.shared-slots._form', ['sharedSlot' => $sharedSlot, 'sites' => $sites])
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="$indexUrl" :submit-label="$adminText('save_changes')" />
                </div>
            </form>
        </div>

        <div class="wb-stack wb-gap-4">
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('usage') }}</strong></div>
                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>{{ $adminText('handle') }}:</strong> <code>{{ $sharedSlot->handle }}</code></div>
                    <div><strong>{{ $adminText('slot') }}:</strong> {{ $sharedSlot->slotLabel() }}</div>
                    <div><strong>{{ $adminText('page_layout') }}:</strong> {{ $sharedSlot->publicShellLabel() }}</div>
                    <div><strong>{{ $adminText('status') }}:</strong> <span class="wb-status-pill {{ $sharedSlot->statusBadgeClass() }}">{{ $sharedSlot->statusLabel() }}</span></div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('danger_zone') }}</strong></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('delete_blocked_help') }}</div>
                </div>
                <div class="wb-card-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <button
                        type="button"
                        class="wb-btn wb-btn-danger"
                        data-wb-toggle="modal"
                        data-wb-target="#delete-shared-slot-{{ $sharedSlot->id }}"
                        aria-haspopup="dialog"
                    >{{ $adminText('delete_shared_slot') }}</button>
                    <a href="{{ $indexUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('cancel') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @include('webblocks-cms::admin.shared-slots.partials.delete-modal', [
        'sharedSlot' => $sharedSlot,
        'referenceCount' => (int) ($sharedSlot->page_slots_count ?? 0),
    ])
@endpush
