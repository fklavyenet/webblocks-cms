@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('search_index.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('title');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $localizedPageTitle,
        'description' => $adminText('description'),
        'actions' => '<form method="POST" action="'.route('admin.system.search.rebuild').'">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-primary">'.e($adminText('rebuild')).'</button></form>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $searchIndexReady)
        <div class="wb-alert wb-alert-warning">
            <div>{{ $adminText('not_ready') }}</div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('status') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-4">
                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-overview">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-overview"><strong>{{ $adminText('overview') }}</strong></div>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('overview_help') }}</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped">
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>{{ $adminText('total_rows') }}</strong>
                                        <div class="wb-text-sm wb-text-muted">{{ $adminText('total_rows_help') }}</div>
                                    </td>
                                    <td class="wb-text-end">{{ $totalRows }}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>{{ $adminText('last_indexed_at') }}</strong>
                                        <div class="wb-text-sm wb-text-muted">{{ $adminText('last_indexed_at_help') }}</div>
                                    </td>
                                    <td class="wb-text-end">{{ $lastIndexedAt ? \Illuminate\Support\Carbon::parse($lastIndexedAt)->format('Y-m-d H:i:s') : $adminText('never') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-sites">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-sites"><strong>{{ $adminText('coverage_by_site') }}</strong></div>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('coverage_by_site_help') }}</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('site') }}</th>
                                    <th class="wb-text-end">{{ $adminText('indexed_rows') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rowsBySite as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row->name }}</strong>
                                            <div class="wb-text-sm wb-text-muted">{{ collect([$row->domain, $row->handle])->filter()->implode(' / ') ?: $adminText('no_domain_or_handle') }}</div>
                                        </td>
                                        <td class="wb-text-end">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="wb-text-sm wb-text-muted">{{ $adminText('no_site_coverage') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="wb-stack wb-gap-2" aria-labelledby="search-index-locales">
                    <div class="wb-stack wb-gap-1">
                        <div id="search-index-locales"><strong>{{ $adminText('coverage_by_locale') }}</strong></div>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('coverage_by_locale_help') }}</div>
                    </div>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('locale') }}</th>
                                    <th class="wb-text-end">{{ $adminText('indexed_rows') }}</th>
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
                                        <td colspan="2" class="wb-text-sm wb-text-muted">{{ $adminText('no_locale_coverage') }}</td>
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
