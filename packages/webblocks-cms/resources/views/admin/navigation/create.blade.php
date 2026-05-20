@php
    $pageTitle = 'Add Navigation Item';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Create a page link, custom URL, or group for a selected menu.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.navigation.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.navigation._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.navigation.index', ['site_id' => old('site_id', $item->site_id ?: $site->id), 'menu_key' => old('menu_key', $item->menu_key ?: \WebBlocks\Cms\Models\NavigationItem::MENU_PRIMARY)])" submit-label="Create" />
            </div>
        </form>
    </div>
@endsection
