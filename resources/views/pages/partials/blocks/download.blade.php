@php
    $downloadUrl = $block->downloadAsset()?->url();
@endphp

@if ($downloadUrl)
    <div class="wb-stack wb-gap-2">
        <a href="{{ $downloadUrl }}" class="wb-btn {{ ($block->variant ?? 'secondary') === 'ghost' ? 'wb-btn-ghost' : (($block->variant ?? 'secondary') === 'primary' ? 'wb-btn-primary' : 'wb-btn-secondary') }}">
            {{ $block->title ?: 'Download file' }}
        </a>

        @if ($block->subtitle)
            <p>{{ $block->subtitle }}</p>
        @endif
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
