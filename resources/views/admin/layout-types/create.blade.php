@extends('layouts.admin', ['title' => 'Create Layout Type', 'heading' => 'Create Layout Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Layout Type',
        'description' => 'Create a new layout type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.layout-types.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.layout-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.layout-types.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
