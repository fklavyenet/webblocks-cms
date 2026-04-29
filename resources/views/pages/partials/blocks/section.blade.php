@php($class = trim('wb-section '.($block->sectionSpacingClass() ?? '')))
<section class="{{ $class }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</section>
