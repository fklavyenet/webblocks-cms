@php
    $settingsRows = collect($block->setting('rows', []))
        ->map(function ($row) {
            return collect($row['columns'] ?? $row)
                ->map(fn ($cell) => is_array($cell) ? ($cell['label'] ?? '') : $cell)
                ->map(fn ($cell) => trim((string) $cell))
                ->values();
        })
        ->filter(fn ($row) => $row->isNotEmpty())
        ->values();
    $rows = $settingsRows->isNotEmpty()
        ? $settingsRows
        : collect(preg_split('/\r\n|\r|\n/', (string) $block->content))
            ->map(function ($row) {
                return collect(explode('|', (string) $row))
                    ->map(fn ($cell) => trim((string) $cell))
                    ->values();
            })
            ->filter(fn ($row) => $row->isNotEmpty())
            ->values();
    $hasHeader = $block->variant !== 'plain' && $rows->isNotEmpty();
    $header = $hasHeader ? $rows->first() : collect();
    $bodyRows = $hasHeader ? $rows->slice(1)->values() : $rows;
@endphp

@if ($rows->isNotEmpty())
    <div class="wb-stack wb-gap-2">
        @if ($block->title)
            <h3>{{ $block->title }}</h3>
        @endif

        <div class="wb-table-wrap">
            <table class="wb-table">
                @if ($header->isNotEmpty())
                    <thead>
                        <tr>
                            @foreach ($header as $cell)
                                <th>{{ $cell }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif

                @if ($bodyRows->isNotEmpty())
                    <tbody>
                        @foreach ($bodyRows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @elseif ($block->variant === 'plain')
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
