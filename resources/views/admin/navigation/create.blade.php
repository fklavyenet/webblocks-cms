@php
    $pageTitle = 'Add Navigation Item';
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Create a page link, custom URL, or group for a selected menu.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card"><div class="wb-card-body"><form method="POST" action="{{ route('admin.navigation.store') }}" class="wb-stack wb-gap-4">@csrf @include('admin.navigation._form')</form></div></div>
@endsection
