@extends('layouts.admin', ['title' => 'Locales', 'heading' => 'Locales'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Locales',
        'description' => 'Manage enabled locales and keep the default locale safe and explicit.',
        'count' => $locales->total(),
        'actions' => '<a href="'.route('admin.locales.create').'" class="wb-btn wb-btn-primary">Add Locale</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Sites</th>
                            <th>Translations</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locales as $locale)
                            <tr>
                                <td><code>{{ $locale->code }}</code></td>
                                <td><strong>{{ $locale->name }}</strong></td>
                                <td>{{ $locale->sites_count }}</td>
                                <td>{{ $locale->page_translations_count }}</td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        @if ($locale->is_default)
                                            <span class="wb-status-pill wb-status-info">Default</span>
                                        @endif
                                        <span class="wb-status-pill {{ $locale->is_enabled ? 'wb-status-active' : 'wb-status-pending' }}">{{ $locale->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.locales.edit', $locale) }}" class="wb-action-btn wb-action-btn-edit" title="Edit locale" aria-label="Edit locale">
                                        <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.partials.pagination', ['paginator' => $locales])
    </div>
@endsection
