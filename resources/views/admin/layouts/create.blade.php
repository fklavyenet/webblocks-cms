@extends('layouts.admin', ['title' => 'Create Layout', 'heading' => 'Create Layout'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Create Layout',
        'description' => 'Create a reusable layout definition for your page structure.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card"><div class="wb-card-body"><form method="POST" action="{{ route('admin.layouts.store') }}" class="wb-stack wb-gap-4">@csrf @include('admin.layouts._form')</form></div></div>
@endsection
