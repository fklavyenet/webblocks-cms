@extends('layouts.admin', ['title' => 'Create Page Type', 'heading' => 'Create Page Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Page Type',
        'description' => 'Create a new page type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.page-types.store') }}" class="wb-stack wb-gap-4">
                @csrf

                @include('admin.page-types._form')
            </form>
        </div>
    </div>
@endsection
