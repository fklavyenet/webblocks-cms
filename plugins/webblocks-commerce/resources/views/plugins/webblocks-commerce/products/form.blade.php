@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->plugin('webblocks-commerce', 'admin.'.$key, $adminLocale, $replace, $fallback);
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $title,
        'description' => $commerceText('products.description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <section class="wb-card">
        <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-0">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="wb-card-header">
                <strong>{{ $commerceText('products.details') }}</strong>
            </div>

            <div class="wb-card-body">
                @include('webblocks-cms::plugins.webblocks-commerce.products._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$product->exists ? route('webblocks.plugins.webblocks_commerce.products.show', $product) : route('webblocks.plugins.webblocks_commerce.products.index')"
                    :submit-label="$submitLabel"
                />
            </div>
        </form>
    </section>
@endsection
