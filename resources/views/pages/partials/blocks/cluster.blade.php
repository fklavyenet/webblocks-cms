@php($class = trim('wb-cluster '.($block->clusterGapClass() ?? '').' '.($block->clusterAlignmentClass() ?? '')))
<div class="{{ $class }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
