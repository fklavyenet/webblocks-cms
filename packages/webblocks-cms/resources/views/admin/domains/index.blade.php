@extends('webblocks-cms::layouts.admin', ['title' => 'Domains', 'heading' => 'Domains'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Domains',
        'description' => 'Choose a site to map incoming hosts inside CMS after DNS, SSL, and server routing are already handled outside CMS.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header"><strong>Select a site</strong></div>
        <div class="wb-card-body">
            @if ($sites->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No accessible sites</div>
                    <div class="wb-empty-text">Domain management becomes available once at least one accessible site exists.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Site</th>
                                <th>Primary Domain</th>
                                <th>Assigned Domains</th>
                                <th>Action</th>
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
                                    <td>{{ $site->canonicalDomain() ?? 'Not assigned' }}</td>
                                    <td>{{ $site->siteDomains()->count() }}</td>
                                    <td>
                                        <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-btn wb-btn-secondary">Manage Domains</a>
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
