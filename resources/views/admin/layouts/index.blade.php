@extends('layouts.admin', ['title' => 'Layouts', 'heading' => 'Layouts'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Layouts',
        'description' => 'Manage reusable layout definitions for page rendering.',
        'count' => $layouts->total(),
        'actions' => '<a href="'.route('admin.layouts.create').'" class="wb-btn wb-btn-primary">New Layout</a>',
    ])

    @include('admin.partials.flash')

    @if ($layouts->isEmpty())
        <div class="wb-card"><div class="wb-card-body"><div class="wb-empty"><div class="wb-empty-title">No layouts yet</div><div class="wb-empty-action"><a href="{{ route('admin.layouts.create') }}" class="wb-btn wb-btn-primary">Create Layout</a></div></div></div></div>
    @else
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead><tr><th>Name</th><th>Slug</th><th>Layout Type</th><th>Pages</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($layouts as $layout)
                                <tr>
                                    <td class="wb-nowrap"><strong>{{ $layout->name }}</strong></td>
                                    <td class="wb-nowrap"><code>{{ $layout->slug }}</code></td>
                                    <td>{{ $layout->layoutType?->name ?? '-' }}</td>
                                    <td class="wb-nowrap">{{ $layout->pages_count }}</td>
                                    <td class="wb-nowrap">
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.layouts.edit', $layout) }}" class="wb-action-btn wb-action-btn-edit" title="Edit layout" aria-label="Edit layout">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.layouts.destroy', $layout) }}">@csrf @method('DELETE')<button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete layout" aria-label="Delete layout"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $layouts])
        </div>
    @endif
@endsection
