@if ($seoMarker = request()->attributes->get('cms_seo_marker'))<!--{{ $seoMarker }}:{{ $block->id }}:{{ $block->page_id }}-->@endif
@include($block->publicRenderView(), ['block' => $block])
@if ($seoMarker)<!--/{{ $seoMarker }}-->@endif
