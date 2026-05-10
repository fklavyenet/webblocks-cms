@php
    $pageTitle = 'Add Page: New Page';
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Create the page on a site and save the English base translation first. New pages start as draft.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.pages.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.pages._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.pages.index')" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
