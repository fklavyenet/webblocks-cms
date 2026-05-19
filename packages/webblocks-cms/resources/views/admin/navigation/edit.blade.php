@php
    $pageTitle = 'Edit Navigation Item: '.$item->resolvedTitle();
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Update the menu, hierarchy, and link settings for this item.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.navigation.update', $item) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('admin.navigation._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.navigation.index', ['site_id' => old('site_id', $item->site_id ?: $site->id), 'menu_key' => old('menu_key', $item->menu_key ?: \App\Models\NavigationItem::MENU_PRIMARY)])" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
