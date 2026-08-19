@php
    $definition = $block->readyApplicationDefinition();
    $settings = $block->applicationSettings();
    $showFailureState = (bool) $block->setting('show_failure_state', false);
    $width = in_array($block->setting('width'), ['content', 'wide', 'full'], true) ? $block->setting('width') : 'content';
    $loading = in_array($block->setting('loading'), ['lazy', 'eager'], true) ? $block->setting('loading') : 'lazy';
    $aspectRatio = in_array($block->setting('aspect_ratio'), ['auto', '16/9', '4/3', '1/1'], true) ? $block->setting('aspect_ratio') : 'auto';
    $minHeight = max(0, min(2000, (int) $block->setting('min_height', 0)));
    $context = $definition ? [
        'runtime_version' => 1,
        'handle' => $definition->handle,
        'instance' => 'application-block-'.$block->id,
        'locale' => $block->renderLocaleCode(),
        'theme' => $block->renderSite()?->resolvedPublicThemePreset(),
        'page' => ['id' => $block->renderPageId()],
        'user' => ['authenticated' => auth()->check()],
    ] : [];
@endphp

@if ($definition)
    <section
        class="wb-application wb-application--{{ $definition->renderMode }} wb-application--{{ $width }}"
        data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
        data-wb-application="{{ $definition->handle }}"
        data-wb-application-instance="application-block-{{ $block->id }}"
        data-wb-application-settings="{{ json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
        data-wb-application-context="{{ json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
        @if ($minHeight > 0) data-wb-application-min-height="{{ $minHeight }}" @endif
    >
        @if ($definition->renderMode === 'iframe' && $definition->entry)
            <div class="wb-application__frame-wrap wb-application__frame-wrap--{{ str_replace('/', '-', $aspectRatio) }}">
                <iframe
                    class="wb-application__frame"
                    src="{{ $definition->entry }}"
                    title="{{ $definition->name }}"
                    loading="{{ $loading }}"
                    sandbox="allow-scripts allow-same-origin"
                    @if ($definition->supports['fullscreen'] ?? false) allow="fullscreen" allowfullscreen @endif
                ></iframe>
            </div>
        @else
            @php
                $mountElement = in_array($definition->mount['element'] ?? null, ['div', 'section', 'canvas'], true)
                    ? $definition->mount['element']
                    : 'div';
                $mountClass = trim('wb-application__mount '.($definition->mount['class'] ?? ''));
            @endphp
            <{{ $mountElement }} class="{{ $mountClass }}" data-wb-application-mount></{{ $mountElement }}>
        @endif
    </section>
@elseif ($showFailureState)
    <div class="wb-alert wb-alert-warning" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}" role="status">
        {{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->public('applications.unavailable', $block->renderLocaleCode()) }}
    </div>
@endif
