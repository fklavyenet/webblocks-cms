@extends('layouts.admin', ['title' => 'Create Layout', 'heading' => 'Create Layout'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Layout',
        'description' => 'Create a reusable layout definition for your page structure.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.layouts.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.layouts._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.layouts.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
