@php
    $navigationLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $navigationItemsText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('navigation_items.'.$key, $navigationLocale, $replace);
    $navigationFormText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('navigation_form.'.$key, $navigationLocale, $replace);
    $pageTitle = $navigationItemsText('edit_page_title', ['title' => $item->resolvedTitle()]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $navigationItemsText('edit_page_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.navigation.update', $item) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('webblocks-cms::admin.navigation._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.navigation.index', ['site_id' => old('site_id', $item->site_id ?: $site->id), 'menu_key' => old('menu_key', $item->menu_key ?: \WebBlocks\Cms\Models\NavigationItem::MENU_PRIMARY)])" :submit-label="$navigationFormText('save_changes')" />
            </div>
        </form>
    </div>
@endsection
