@extends('layouts.admin', ['title' => 'Docs', 'heading' => 'Docs'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Docs',
        'description' => 'Read a small, source-controlled Markdown docs set inside the admin shell. This pilot is read-only and does not mix docs with CMS pages or blocks yet.',
        'count' => $documents->count(),
    ])

    <div class="wb-stack wb-stack-4" id="available-docs">
        <div class="wb-alert wb-alert-info">
            <div>
                <div class="wb-alert-title">Docs migration pilot</div>
                <div>This surface renders selected repository Markdown files directly from disk. The pilot currently exposes README and changelog content without adding a database-backed docs module.</div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header">
                <strong>Available documents</strong>
            </div>

            <div class="wb-card-body">
                <div class="wb-link-list">
                    @foreach ($documents as $document)
                        <a href="{{ route('admin.docs.show', $document['slug']) }}" class="wb-link-list-item">
                            <div class="wb-link-list-main">
                                <div class="wb-link-list-title">{{ $document['title'] }}</div>
                                <div class="wb-link-list-meta">
                                    <code>{{ $document['relative_path'] }}</code>
                                    @if ($document['updated_at'])
                                        | Updated {{ $document['updated_at']->format('Y-m-d H:i') }}
                                    @endif
                                </div>
                            </div>

                            <div class="wb-link-list-desc">{{ $document['description'] }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
