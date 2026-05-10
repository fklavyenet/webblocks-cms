@extends('layouts.admin', ['title' => 'Create Shared Slot', 'heading' => 'Shared Slots'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Shared Slot',
        'description' => 'Create reusable inner slot content for one site.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.shared-slots.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.shared-slots._form', ['sharedSlot' => $sharedSlot, 'sites' => $sites])
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.shared-slots.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
