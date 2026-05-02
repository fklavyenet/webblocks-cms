@php
    $layoutTypeTitle = 'Edit Layout Type: '.$layoutType->name;
@endphp

@extends('layouts.admin', ['title' => $layoutTypeTitle, 'heading' => $layoutTypeTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $layoutTypeTitle,
        'description' => 'Update slot ownership, wrappers, and shared layout structure.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.layout-types.update', $layoutType) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.layout-types._form')
            </form>
        </div>
    </div>
@endsection
