@php
    $indicatorLabels = $indicatorLabels ?? app(\WebBlocks\Cms\Support\ContentSources\ContentSourceIndicators::class)->labels($indicatorBlock);
    $indicatorLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $indicatorTitle = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-content.title', $indicatorLocale);
@endphp
@if ($indicatorLabels !== [])
    <div class="wb-stack wb-stack-1 wb-text-sm" aria-label="{{ $indicatorTitle }}">
        @foreach ($indicatorLabels as $indicatorLabel)
            <a href="{{ app(\WebBlocks\Cms\Support\ContentSources\ContentSourceIndicators::class)->authoringUrl($indicatorBlock) }}">{{ $indicatorLabel }}</a>
        @endforeach
    </div>
@endif
