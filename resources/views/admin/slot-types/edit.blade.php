@extends('layouts.admin', ['title' => 'Edit Slot Type', 'heading' => 'Edit Slot Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Slot Type',
        'description' => 'Update the selected slot type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.slot-types.update', $slotType) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.slot-types._form')
            </form>
        </div>
    </div>
@endsection
