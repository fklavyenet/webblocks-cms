@php
    $assetLabel = $asset->title ?: $asset->filename;
@endphp

<div class="wb-card wb-card-muted" data-wb-asset-card data-wb-asset-kind="{{ $asset->kind }}" data-wb-asset-folder-id="{{ $asset->folder_id ?? '' }}" data-wb-asset-search="{{ str()->lower(implode(' ', array_filter([$asset->title, $asset->filename, $asset->original_name, $asset->folder?->name]))) }}">
    <div class="wb-card-body wb-stack wb-gap-2">
        <div class="wb-stack wb-gap-1">
            @if ($asset->canPreview())
                <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $assetLabel }}" width="160" height="112">
            @else
                <div class="wb-cluster wb-cluster-2">
                    <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon {{ $asset->kind === \App\Models\Asset::KIND_DOCUMENT ? 'wb-icon-file-text' : 'wb-icon-file' }}"></i></span>
                    <span class="wb-text-sm wb-text-muted">{{ ucfirst($asset->kind) }}</span>
                </div>
            @endif

            <strong>{{ $assetLabel }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $asset->original_name }}</span>
            <span class="wb-text-sm wb-text-muted">{{ $asset->folder?->name ?? 'No folder' }}</span>
        </div>

        <div class="wb-cluster wb-cluster-2">
            @if ($multi ?? false)
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-asset-toggle data-wb-asset='@json($asset->pickerPayload())'>Select</button>
            @else
                <button type="button" class="wb-btn wb-btn-primary" data-wb-asset-select data-wb-asset='@json($asset->pickerPayload())'>Select</button>
            @endif
        </div>
    </div>
</div>
