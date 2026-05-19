@php($class = collect(['wb-section', $block->sectionSpacingClass(), 'wb-stack'])->filter()->implode(' '))
<section class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</section>
