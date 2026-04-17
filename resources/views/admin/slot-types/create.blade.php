@extends('layouts.admin', ['title' => 'Create Slot Type', 'heading' => 'Create Slot Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Slot Type',
        'description' => 'Create a new slot type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.slot-types.store') }}" class="wb-stack wb-gap-4">
                @csrf

                @include('admin.slot-types._form')
            </form>
        </div>
    </div>
@endsection
