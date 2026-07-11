@php
    $items = $block->children
        ->map(function ($child) {
            $title = trim((string) ($child->title ?? ''));
            $content = trim((string) ($child->content ?? ''));

            if ($title === '' || $content === '') {
                return null;
            }

            return [
                'title' => $title,
                'content' => $content,
                'is_rich_text' => $child->typeSlug() === 'rich-text',
            ];
        })
        ->filter()
        ->values();
@endphp

@if ($items->isNotEmpty())
    <div class="wb-stack wb-gap-3">
        @if ($block->title || $block->content)
            <div class="wb-stack wb-gap-1">
                @if ($block->title)
                    <h3>{{ $block->title }}</h3>
                @endif
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
            </div>
        @endif

        <div class="wb-stack-2">
            @foreach ($items as $item)
                <details>
                    <summary>{{ $item['title'] }}</summary>
                    <div>
                        @if ($item['is_rich_text'])
                            {!! nl2br(e($item['content'])) !!}
                        @else
                            {{ $item['content'] }}
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endif
