@extends('layouts.admin', ['title' => 'Edit Page Type', 'heading' => 'Edit Page Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Page Type',
        'description' => 'Update the selected page type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.page-types.update', $pageType) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.page-types._form')
            </form>
        </div>
    </div>
@endsection
