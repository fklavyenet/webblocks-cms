@extends('layouts.admin', ['title' => 'Edit Layout Type', 'heading' => 'Edit Layout Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Layout Type',
        'description' => 'Update the selected layout type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.layout-types.update', $layoutType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.layout-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.layout-types.index')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
