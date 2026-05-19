@extends('webblocks-cms::layouts.admin', ['title' => 'Create Page Layout', 'heading' => 'Page Layouts'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Create Page Layout',
        'description' => 'Create a reusable page layout with managed body classes and slot wrappers.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.page-layouts._form', ['pageLayout' => $pageLayout])
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.page-layouts.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
