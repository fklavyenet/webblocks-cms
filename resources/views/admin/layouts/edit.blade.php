@extends('layouts.admin', ['title' => 'Edit Layout', 'heading' => 'Edit Layout'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Layout',
        'description' => 'Update the selected layout definition.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.layouts.update', $layout) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.layouts._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.layouts.index')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
