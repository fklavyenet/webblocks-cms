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
            <div class="wb-card-body wb-stack wb-gap-4">
                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-overview">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-overview"><strong>Overview</strong></div>
                        <div class="wb-text-sm wb-text-muted">Current derived row totals for published searchable pages.</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped">
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Total indexed rows</strong>
                                        <div class="wb-text-sm wb-text-muted">One row per published page and locale.</div>
                                    </td>
                                    <td class="wb-text-end">{{ $totalRows }}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Last indexed at</strong>
                                        <div class="wb-text-sm wb-text-muted">Latest completed index write in this environment.</div>
                                    </td>
                                    <td class="wb-text-end">{{ $lastIndexedAt ? \Illuminate\Support\Carbon::parse($lastIndexedAt)->format('Y-m-d H:i:s') : 'Never' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-sites">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-sites"><strong>Coverage by Site</strong></div>
                        <div class="wb-text-sm wb-text-muted">Indexed row counts grouped by site identity.</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th class="wb-text-end">Indexed rows</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rowsBySite as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row->name }}</strong>
                                            <div class="wb-text-sm wb-text-muted">{{ collect([$row->domain, $row->handle])->filter()->implode(' / ') ?: 'No domain or handle recorded' }}</div>
                                        </td>
                                        <td class="wb-text-end">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="wb-text-sm wb-text-muted">No site coverage yet because there are no indexed rows.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-locales">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-locales"><strong>Coverage by Locale</strong></div>
                        <div class="wb-text-sm wb-text-muted">Indexed row counts grouped by enabled locale.</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Locale</th>
                                    <th class="wb-text-end">Indexed rows</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rowsByLocale as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row->name }}</strong>
                                            <div class="wb-text-sm wb-text-muted">{{ strtoupper($row->code) }}</div>
                                        </td>
                                        <td class="wb-text-end">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="wb-text-sm wb-text-muted">No locale coverage yet because there are no indexed rows.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    @endif
@endsection
