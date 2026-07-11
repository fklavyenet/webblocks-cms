@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localeCode = $block->renderLocaleCode();
    $label = $block->stringValueOrNull($block->title) ?? $translator->get('blocks.search_form.label', $localeCode);
    $placeholder = $block->stringValueOrNull($block->content) ?? $translator->get('blocks.search_form.placeholder', $localeCode);
    $buttonLabel = $block->stringValueOrNull($block->subtitle) ?? $translator->get('blocks.search_form.submit', $localeCode);
    $showButton = (bool) $block->setting('show_button', true);
    $searchPath = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class)->searchPath($block->renderLocaleCode(), $block->renderSite());
    $buttonClass = $block->variant === 'secondary' ? 'wb-btn wb-btn-secondary' : 'wb-btn wb-btn-primary';
@endphp

@if ($searchPath)
    <form action="{{ $searchPath }}" method="GET" role="search" class="wb-cluster wb-cluster-2" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
        <div class="wb-stack wb-gap-1 wb-flex-1">
            <label for="search-form-{{ $block->id }}">{{ $label }}</label>
            <input id="search-form-{{ $block->id }}" type="search" name="q" class="wb-input" placeholder="{{ $placeholder }}" value="{{ request()->query('q', '') }}">
        </div>

        @if ($showButton)
            <button type="submit" class="{{ $buttonClass }}">{{ $buttonLabel }}</button>
        @endif
    </form>
@endif
