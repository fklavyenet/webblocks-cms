@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.shared_slots.'.$key, $adminLocale, $replace);
    $modalId = 'usage-shared-slot-'.$sharedSlot->id;

    // A Shared Slot's own hidden source page is internal plumbing, not a place an
    // operator can go and change the slot source, so it never belongs in this list.
    $consumers = $sharedSlot->pageSlots
        ->filter(fn ($pageSlot) => $pageSlot->page && ! $pageSlot->page->isSharedSlotSourcePage())
        ->sortBy(fn ($pageSlot) => (string) ($pageSlot->page->defaultTranslation()?->path ?? ''))
        ->values();
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" aria-describedby="{{ $modalId }}-description">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <div>
                <h2 class="wb-modal-title" id="{{ $modalId }}-title">{{ $adminText('usage_title') }}</h2>
                <p class="wb-text-sm wb-text-muted" id="{{ $modalId }}-description">{{ $adminText('usage_description', ['name' => $sharedSlot->name]) }}</p>
            </div>

            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close_usage') }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>

        <div class="wb-modal-body wb-stack wb-gap-4">
            @if ($consumers->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('usage_empty_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('usage_empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('usage_page') }}</th>
                                <th>{{ $adminText('usage_path') }}</th>
                                <th>{{ $adminText('slot') }}</th>
                                <th>{{ $adminText('status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($consumers as $pageSlot)
                                @php($translation = $pageSlot->page->defaultTranslation())
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.pages.edit', $pageSlot->page) }}">
                                            <strong>{{ $translation?->name ?: $pageSlot->page->title }}</strong>
                                        </a>
                                    </td>
                                    <td><code>{{ $translation?->path ?: '-' }}</code></td>
                                    <td>{{ $pageSlot->slotSlug() }}</td>
                                    <td><span class="wb-status-pill {{ $pageSlot->page->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $pageSlot->page->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wb-text-sm wb-text-muted">{{ $adminText('usage_help') }}</div>
            @endif
        </div>

        <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('close') }}</button>
        </div>
    </div>
</div>
