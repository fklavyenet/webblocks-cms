@extends('layouts.admin', ['title' => 'Create Shared Slot', 'heading' => 'Shared Slots'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Shared Slot',
        'description' => 'Create reusable inner slot content for one site.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.shared-slots.store') }}" class="wb-stack wb-gap-4">
                @csrf
                @include('admin.shared-slots._form', ['sharedSlot' => $sharedSlot, 'sites' => $sites])
            </form>
        </div>
    </div>
@endsection
