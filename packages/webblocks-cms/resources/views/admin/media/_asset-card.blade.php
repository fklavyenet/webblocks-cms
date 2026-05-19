@php
    $assetLabel = $asset->title ?: $asset->filename;
    $pickerVariant = $pickerVariant ?? 'card';
@endphp

@if ($pickerVariant === 'compact-list')
    <div class="wb-card wb-card-muted wb-picker-asset-row" data-wb-asset-card data-wb-asset-kind="{{ $asset->kind }}" data-wb-asset-mime-type="{{ strtolower((string) ($asset->mime_type ?? '')) }}" data-wb-asset-folder-id="{{ $asset->folder_id ?? '' }}" data-wb-asset-search="{{ str()->lower(implode(' ', array_filter([$asset->title, $asset->filename, $asset->original_name, $asset->folder?->name]))) }}" data-wb-picker-variant="compact-list">
        <div class="wb-card-body wb-picker-asset-row__body">
            <div class="wb-picker-asset-row__thumb" aria-hidden="true">
                @if ($asset->canPreview())
                    <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $assetLabel }}" width="56" height="40">
                @else
                    <span class="wb-action-btn"><i class="wb-icon {{ $asset->kind === \App\Models\Media::KIND_DOCUMENT ? 'wb-icon-file-text' : 'wb-icon-file' }}"></i></span>
                @endif
            </div>

            <div class="wb-picker-asset-row__main">
                <strong>{{ $assetLabel }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $asset->original_name }}</div>
            </div>

            <div class="wb-picker-asset-row__meta wb-text-sm wb-text-muted">
                <span>{{ $asset->folder?->name ?? 'No folder' }}</span>
                <span>{{ ucfirst($asset->kind) }}</span>
            </div>

            <div class="wb-picker-asset-row__action">
                @if ($multi ?? false)
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-asset-toggle data-wb-asset='@json($asset->pickerPayload())' aria-pressed="false">Select</button>
                @else
                    <button type="button" class="wb-btn wb-btn-primary" data-wb-asset-select data-wb-asset='@json($asset->pickerPayload())'>Select</button>
                @endif
            </div>
        </div>
    </div>
@else
    <div class="wb-card wb-card-muted" data-wb-asset-card data-wb-asset-kind="{{ $asset->kind }}" data-wb-asset-mime-type="{{ strtolower((string) ($asset->mime_type ?? '')) }}" data-wb-asset-folder-id="{{ $asset->folder_id ?? '' }}" data-wb-asset-search="{{ str()->lower(implode(' ', array_filter([$asset->title, $asset->filename, $asset->original_name, $asset->folder?->name]))) }}">
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-stack wb-gap-1">
                @if ($asset->canPreview())
                    <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $assetLabel }}" width="160" height="112">
                @else
                    <div class="wb-cluster wb-cluster-2">
                        <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon {{ $asset->kind === \App\Models\Media::KIND_DOCUMENT ? 'wb-icon-file-text' : 'wb-icon-file' }}"></i></span>
                        <span class="wb-text-sm wb-text-muted">{{ ucfirst($asset->kind) }}</span>
                    </div>
                @endif

                <strong>{{ $assetLabel }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $asset->original_name }}</span>
                <span class="wb-text-sm wb-text-muted">{{ $asset->folder?->name ?? 'No folder' }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
                @if ($multi ?? false)
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-asset-toggle data-wb-asset='@json($asset->pickerPayload())' aria-pressed="false">Select</button>
                @else
                    <button type="button" class="wb-btn wb-btn-primary" data-wb-asset-select data-wb-asset='@json($asset->pickerPayload())'>Select</button>
                @endif
            </div>
        </div>
    </div>
@endif
