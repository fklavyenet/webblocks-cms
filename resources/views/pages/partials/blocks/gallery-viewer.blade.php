@php
    $firstItem = $galleryItems->first();
    $viewerTitleId = $viewerId.'-title';
@endphp

@if ($firstItem)
    <div class="wb-modal wb-modal-xl" id="{{ $viewerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $viewerTitleId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-body">
                <div class="wb-gallery-viewer">
                    <div class="wb-gallery-viewer-toolbar">
                        <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-prev" type="button" aria-label="Previous image">
                            <i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="wb-gallery-viewer-counter" aria-live="polite">1 / {{ $galleryItems->count() }}</div>
                        <div class="wb-cluster wb-cluster-2">
                            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-next" type="button" aria-label="Next image">
                                <i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i>
                            </button>
                            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-close" type="button" data-wb-dismiss="modal" aria-label="Close viewer">
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
                        <figcaption class="wb-gallery-viewer-caption" id="{{ $viewerTitleId }}">{{ $firstItem['caption'] }}</figcaption>
                    </figure>
                    <p class="wb-gallery-viewer-meta wb-text-sm wb-text-muted wb-m-0">{{ $firstItem['meta'] }}</p>
                </div>
            </div>
        </div>
    </div>
@endif
