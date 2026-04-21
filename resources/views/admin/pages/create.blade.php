@php
    $pageTitle = 'Add Page: New Page';
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Create the page on a site and save the English base translation first.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.pages.store') }}" class="wb-stack wb-gap-4">
                @csrf

                @include('admin.pages._form')
            </form>
        </div>
    </div>
@endsection
