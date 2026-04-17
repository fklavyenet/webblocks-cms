@php
    $pageTitle = 'Edit Navigation Item: '.$item->resolvedTitle();
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Update the menu, hierarchy, and link settings for this item.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card"><div class="wb-card-body"><form method="POST" action="{{ route('admin.navigation.update', $item) }}" class="wb-stack wb-gap-4">@csrf @method('PUT') @include('admin.navigation._form')</form></div></div>
@endsection
