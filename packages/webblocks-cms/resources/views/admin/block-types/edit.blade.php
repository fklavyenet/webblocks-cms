@extends('webblocks-cms::layouts.admin', ['title' => 'Edit Block Type: '.$blockType->name, 'heading' => 'Edit Block Type: '.$blockType->name])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Edit Block Type: '.$blockType->name,
        'description' => 'Update the selected block type record.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.block-types.update', $blockType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ $blockTypesReturnUrl }}">

            <div class="wb-card-body">
                @include('webblocks-cms::admin.block-types._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$blockTypesReturnUrl" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
