@php
    $slug = $block->typeSlug() ?? 'unknown';
    $expectedFile = resource_path('views/pages/partials/blocks/'.$slug.'.blade.php');
@endphp

<div class="wb-alert wb-alert-warning">
    <div>
        <div class="wb-alert-title">Missing Block Renderer</div>
        <div>Expected renderer for <code>{{ $slug }}</code> at <code>{{ $expectedFile }}</code>.</div>
    </div>
</div>
