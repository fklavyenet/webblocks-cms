@php
    // Keep attachment compatibility for existing button blocks until they are migrated to dedicated download blocks.
    $attachmentUrl = $block->attachmentAsset()?->url();
    $buttonUrl = $attachmentUrl ?: $block->url;
    $variantClassMap = [
        'primary' => 'wb-btn wb-btn-primary',
        'secondary' => 'wb-btn wb-btn-secondary',
        'outline' => 'wb-btn wb-btn-outline',
        'ghost' => 'wb-btn wb-btn-ghost',
        'danger' => 'wb-btn wb-btn-danger',
    ];
    $buttonClasses = $variantClassMap[$block->variant ?: 'primary'] ?? $variantClassMap['primary'];
    $buttonLabel = $block->title ?: 'Open link';
    $buttonTarget = $block->subtitle ?: '_self';
@endphp

@if ($buttonUrl)
    <a href="{{ $buttonUrl }}" class="{{ $buttonClasses }}" @if ($buttonTarget === '_blank') target="_blank" rel="noopener noreferrer" @endif>
        {{ $buttonLabel }}
    </a>
@else
    <button type="button" class="{{ $buttonClasses }}">
        {{ $buttonLabel }}
    </button>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
