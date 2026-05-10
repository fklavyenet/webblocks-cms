@extends('layouts.admin', ['title' => 'Edit Slot Type', 'heading' => 'Edit Slot Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Slot Type',
        'description' => 'Update the selected slot type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.slot-types.update', $slotType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.slot-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.slot-types.index')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
