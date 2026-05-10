@extends('layouts.admin', ['title' => 'Create Slot Type', 'heading' => 'Create Slot Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Slot Type',
        'description' => 'Create a new slot type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.slot-types.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.slot-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.slot-types.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
