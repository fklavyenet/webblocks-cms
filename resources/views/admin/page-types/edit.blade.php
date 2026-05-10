@extends('layouts.admin', ['title' => 'Edit Page Type', 'heading' => 'Edit Page Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Page Type',
        'description' => 'Update the selected page type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-types.update', $pageType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.page-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.page-types.index')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
