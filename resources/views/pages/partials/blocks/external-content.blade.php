@php($embed = app(\WebBlocks\Cms\Support\Consent\ConsentPresets::class)->embed((string) $block->url))
@if ($embed)
<section class="wb-card wb-card-muted" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"><div class="wb-card-body wb-stack-3">
    @if ($block->title)<h2>{{ $block->title }}</h2>@endif
    @if ($block->content)<p>{{ $block->content }}</p>@endif
    @include('webblocks-cms::partials.consent-embed')
</div></section>
@endif
