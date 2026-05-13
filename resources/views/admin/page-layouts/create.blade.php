@extends('layouts.admin', ['title' => 'Create Page Layout', 'heading' => 'Page Layouts'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Page Layout',
        'description' => 'Create a reusable page layout handle that maps to a supported public shell type.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.page-layouts._form', ['pageLayout' => $pageLayout])
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.page-layouts.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
