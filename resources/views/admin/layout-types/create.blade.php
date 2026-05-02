@extends('layouts.admin', ['title' => 'Create Layout Type', 'heading' => 'Create Layout Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Layout Type',
        'description' => 'Create a reusable layout shell and define which slots belong to the layout or each page.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.layout-types.store') }}" class="wb-stack wb-gap-4">
                @csrf

                @include('admin.layout-types._form')
            </form>
        </div>
    </div>
@endsection
