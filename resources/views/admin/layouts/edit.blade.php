@extends('layouts.admin', ['title' => 'Edit Layout', 'heading' => 'Edit Layout'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Layout',
        'description' => 'Update the selected layout definition.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card"><div class="wb-card-body"><form method="POST" action="{{ route('admin.layouts.update', $layout) }}" class="wb-stack wb-gap-4">@csrf @method('PUT') @include('admin.layouts._form')</form></div></div>
@endsection
