@php
    $firstItem = $galleryItems->first();
    $viewerTitle = trim((string) ($viewerTitle ?? ''));
    $viewerTitleId = $viewerTitle !== '' ? $viewerId.'-title' : $viewerId.'-caption';
    $firstItemMeta = trim((string) (($firstItem['meta'] ?? $firstItem['overlay_title'] ?? $firstItem['overlay_text'] ?? '')));
    $a11yLocaleCode = strtolower((string) ($localeCode ?? app()->getLocale()));
    $a11y = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('blocks.a11y.'.$key, $a11yLocaleCode);
@endphp

@if ($firstItem)
    <div class="wb-modal wb-modal-xl" id="{{ $viewerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $viewerTitleId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-body">
                <div class="wb-gallery-viewer">
                    @if ($viewerTitle !== '')
                        <h2 class="wb-gallery-viewer-title wb-m-0" id="{{ $viewerTitleId }}">{{ $viewerTitle }}</h2>
                    @endif
                    <div class="wb-gallery-viewer-toolbar">
                        <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-prev" type="button" aria-label="{{ $a11y('previous_image') }}">
                            <i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="wb-gallery-viewer-counter" aria-live="polite">1 / {{ $galleryItems->count() }}</div>
                        <div class="wb-cluster wb-cluster-2">
                            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-next" type="button" aria-label="{{ $a11y('next_image') }}">
                                <i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i>
                            </button>
                            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-close" type="button" data-wb-dismiss="modal" aria-label="{{ $a11y('close_viewer') }}">
                                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <figure class="wb-gallery-viewer-media">
                        <img
                            class="wb-gallery-viewer-image"
                            src="{{ $firstItem['full_url'] }}"
                            alt="{{ $firstItem['alt'] }}"
                            @if ($firstItem['width']) width="{{ $firstItem['width'] }}" @endif
                            @if ($firstItem['height']) height="{{ $firstItem['height'] }}" @endif
                        >
                        <figcaption class="wb-gallery-viewer-caption" @if ($viewerTitle === '') id="{{ $viewerTitleId }}" @endif>{{ $firstItem['caption'] ?? '' }}</figcaption>
                    </figure>
                    <p class="wb-gallery-viewer-meta wb-text-sm wb-text-muted wb-m-0">{{ $firstItemMeta }}</p>
                </div>
            </div>
        </div>
    </div>
@endif
