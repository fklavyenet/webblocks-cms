@extends('layouts.admin', ['title' => 'Edit Layout Type', 'heading' => 'Edit Layout Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Layout Type',
        'description' => 'Update the selected layout type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.layout-types.update', $layoutType) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.layout-types._form')
            </form>
        </div>
    </div>
@endsection
