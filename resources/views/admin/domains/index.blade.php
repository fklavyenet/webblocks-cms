@php
    $domainsIndexLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $domainsIndexText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('domains_index.'.$key, $domainsIndexLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $domainsIndexText('title'), 'heading' => $domainsIndexText('title')])

@section('content')
@php
    $tableSort = \WebBlocks\Cms\Queries\AdminTableSort::for('domains');
@endphp
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $domainsIndexText('title'),
        'description' => $domainsIndexText('description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $domainsIndexText('select_site') }}</strong></div>
        <div class="wb-card-body">
            @if ($sites->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $domainsIndexText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $domainsIndexText('empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover" @include('webblocks-cms::admin.partials.table-sort-attributes')>
                        <thead>
                            <tr>
                                @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'name', 'label' => $domainsIndexText('site')])
                                <th>{{ $domainsIndexText('primary_domain') }}</th>
                                @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'site_domains_count', 'label' => $domainsIndexText('assigned_domains')])
                                <th>{{ $domainsIndexText('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sites as $site)
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $site->name }}</strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $site->handle }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $site->canonicalDomain() ?? $domainsIndexText('not_assigned') }}</td>
                                    <td>{{ $site->siteDomains()->count() }}</td>
                                    <td>
                                        <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-btn wb-btn-secondary">{{ $domainsIndexText('manage_domains') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
