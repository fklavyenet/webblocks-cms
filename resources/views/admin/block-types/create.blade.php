@extends('layouts.admin', ['title' => 'Create Block Type', 'heading' => 'Create Block Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Block Type',
        'description' => 'Create a new block type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.block-types.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.block-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.block-types.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
