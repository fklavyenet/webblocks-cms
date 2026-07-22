@php($class = collect(['wb-navbar', $block->navbarPositionClass()])->filter()->implode(' '))

<nav class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</nav>

{{-- Mobile drawers pushed by navbar child blocks render directly after the
     navbar element, per the shipped wb-navbar-drawer contract. --}}
@php($navbarDrawers = app(\WebBlocks\Cms\Support\Blocks\PublicNavbarDrawerRegistry::class)->flush())
@foreach ($navbarDrawers as $navbarDrawerHtml)
    {!! $navbarDrawerHtml !!}
@endforeach
