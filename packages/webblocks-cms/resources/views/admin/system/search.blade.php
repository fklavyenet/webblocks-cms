@extends('webblocks-cms::layouts.admin', ['title' => 'Search', 'heading' => 'Search'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Search',
        'description' => 'Review derived public search index coverage for published pages and rebuild it safely when needed.',
        'actions' => '<form method="POST" action="'.route('admin.system.search.rebuild').'">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-primary">Rebuild Index</button></form>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $searchIndexReady)
        <div class="wb-alert wb-alert-warning">
            <div>Search index tables are not available yet. Run the latest migrations before using Search.</div>
        </div>
    @else
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header"><strong>Overview</strong></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-settings-row">
                        <div class="wb-settings-row-label">
                            <strong>Total indexed rows</strong>
                            <span>One row per published page and locale.</span>
                        </div>
                        <div class="wb-settings-row-control"><span>{{ $totalRows }}</span></div>
                    </div>

                    <div class="wb-settings-row">
                        <div class="wb-settings-row-label">
                            <strong>Last indexed at</strong>
                            <span>Latest completed index write in this environment.</span>
                        </div>
                        <div class="wb-settings-row-control"><span>{{ $lastIndexedAt ? \Illuminate\Support\Carbon::parse($lastIndexedAt)->format('Y-m-d H:i:s') : 'Never' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>By Site</strong></div>
                <div class="wb-card-body wb-stack wb-gap-2">
                    @forelse ($rowsBySite as $row)
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>{{ $row->name }}</strong>
                                <span>{{ $row->handle }}</span>
                            </div>
                            <div class="wb-settings-row-control"><span>{{ $row->total }}</span></div>
                        </div>
                    @empty
                        <div class="wb-text-sm wb-text-muted">No indexed rows yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>By Locale</strong></div>
                <div class="wb-card-body wb-stack wb-gap-2">
                    @forelse ($rowsByLocale as $row)
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>{{ $row->name }}</strong>
                                <span>{{ strtoupper($row->code) }}</span>
                            </div>
                            <div class="wb-settings-row-control"><span>{{ $row->total }}</span></div>
                        </div>
                    @empty
                        <div class="wb-text-sm wb-text-muted">No indexed rows yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endsection
