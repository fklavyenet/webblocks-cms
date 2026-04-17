@php
    // Keep attachment compatibility for existing button blocks until they are migrated to dedicated download blocks.
    $attachmentUrl = $block->attachmentAsset()?->url();
    $buttonUrl = $attachmentUrl ?: ($block->url ?: '#');
@endphp

<a href="{{ $buttonUrl }}" class="wb-btn {{ ($block->variant ?? 'primary') === 'ghost' ? 'wb-btn-ghost' : 'wb-btn-primary' }}" @if (($block->subtitle ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif>
    {{ $block->title ?: 'Open link' }}
</a>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
