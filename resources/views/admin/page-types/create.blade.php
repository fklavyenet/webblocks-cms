@extends('layouts.admin', ['title' => 'Create Page Type', 'heading' => 'Create Page Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Page Type',
        'description' => 'Create a new page type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-types.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.page-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.page-types.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
