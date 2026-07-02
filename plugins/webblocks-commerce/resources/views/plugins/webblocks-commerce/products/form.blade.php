@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $title,
        'description' => 'Create and maintain simple commerce products.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <section class="wb-card">
        <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-0">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="wb-card-header">
                <strong>Product Details</strong>
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
