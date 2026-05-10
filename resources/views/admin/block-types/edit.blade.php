@extends('layouts.admin', ['title' => 'Edit Block Type', 'heading' => 'Edit Block Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Block Type',
        'description' => 'Update the selected block type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.block-types.update', $blockType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.block-types._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.block-types.index')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
