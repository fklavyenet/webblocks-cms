@extends('layouts.admin', ['title' => 'Sites', 'heading' => 'Sites'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sites',
        'description' => 'Manage the small multisite foundation and the locales available on each site.',
        'count' => $sites->total(),
        'actions' => '<a href="'.route('admin.sites.create').'" class="wb-btn wb-btn-primary">Add Site</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Handle</th>
                            <th>Domain</th>
                            <th>Locales</th>
                            <th>Pages</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sites as $site)
                            <tr>
                                <td><strong>{{ $site->name }}</strong></td>
                                <td><code>{{ $site->handle }}</code></td>
                                <td>{{ $site->domain ?: 'Not set' }}</td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                        @foreach ($site->locales as $locale)
                                            <span class="wb-status-pill {{ $locale->is_default ? 'wb-status-info' : 'wb-status-active' }}">{{ $locale->code }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $site->pages_count }}</td>
                                <td>
                                    <span class="wb-status-pill {{ $site->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $site->is_primary ? 'Primary' : 'Standard' }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.sites.edit', $site) }}" class="wb-action-btn wb-action-btn-edit" title="Edit site" aria-label="Edit site">
                                        <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.partials.pagination', ['paginator' => $sites])
    </div>
@endsection
