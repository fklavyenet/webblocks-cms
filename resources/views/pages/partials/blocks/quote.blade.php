<blockquote class="wb-card wb-card-muted">
    <div class="wb-card-body">
        <div class="wb-stack wb-gap-2">
            <p>{{ $block->content }}</p>
            @if ($block->title || $block->subtitle)
                <footer>{{ $block->title }}{{ $block->subtitle ? ' | '.$block->subtitle : '' }}</footer>
            @endif
        </div>
    </div>
</blockquote>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
