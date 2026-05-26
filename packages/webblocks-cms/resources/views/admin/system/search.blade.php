@extends('webblocks-cms::layouts.admin', ['title' => 'Search Index', 'heading' => 'Search Index'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Search Index',
        'description' => 'Review derived public search index coverage for published pages and safely rebuild the index when content needs to be refreshed.',
        'actions' => '<form method="POST" action="'.route('admin.system.search.rebuild').'">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-primary">Rebuild Search Index</button></form>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $searchIndexReady)
        <div class="wb-alert wb-alert-warning">
            <div>Search index tables are not available yet. Run the latest migrations before using Search.</div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-header"><strong>Search Index Status</strong></div>
            <div class="wb-card-body wb-stack wb-gap-5">
                <section class="wb-stack wb-gap-3" aria-labelledby="search-index-overview">
                    <div class="wb-stack wb-gap-1">
                        <h2 id="search-index-overview" class="wb-heading-5">Overview</h2>
                        <div class="wb-text-sm wb-text-muted">Current derived row totals for published searchable pages.</div>
                    </div>

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
                </section>

                <section class="wb-stack wb-gap-3" aria-labelledby="search-index-sites">
                    <div class="wb-stack wb-gap-1">
                        <h2 id="search-index-sites" class="wb-heading-5">Coverage by Site</h2>
                        <div class="wb-text-sm wb-text-muted">Indexed row counts grouped by site identity.</div>
                    </div>

                    @forelse ($rowsBySite as $row)
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>{{ $row->name }}</strong>
                                <span>{{ collect([$row->domain, $row->handle])->filter()->implode(' / ') ?: 'No domain or handle recorded' }}</span>
                            </div>
                            <div class="wb-settings-row-control"><span>{{ $row->total }}</span></div>
                        </div>
                    @empty
                        <div class="wb-text-sm wb-text-muted">No site coverage yet because there are no indexed rows.</div>
                    @endforelse
                </section>

                <section class="wb-stack wb-gap-3" aria-labelledby="search-index-locales">
                    <div class="wb-stack wb-gap-1">
                        <h2 id="search-index-locales" class="wb-heading-5">Coverage by Locale</h2>
                        <div class="wb-text-sm wb-text-muted">Indexed row counts grouped by enabled locale.</div>
                    </div>

                    @forelse ($rowsByLocale as $row)
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>{{ $row->name }}</strong>
                                <span>{{ strtoupper($row->code) }}</span>
                            </div>
                            <div class="wb-settings-row-control"><span>{{ $row->total }}</span></div>
                        </div>
                    @empty
                        <div class="wb-text-sm wb-text-muted">No locale coverage yet because there are no indexed rows.</div>
                    @endforelse
                </section>
            </div>
        </div>
    @endif
@endsection
