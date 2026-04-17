@extends('layouts.admin', ['title' => 'Create Block Type', 'heading' => 'Create Block Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Block Type',
        'description' => 'Create a new block type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.block-types.store') }}" class="wb-stack wb-gap-4">
                @csrf

                @include('admin.block-types._form')
            </form>
        </div>
    </div>
@endsection
