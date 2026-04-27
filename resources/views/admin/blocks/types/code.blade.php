@php
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Code title, label, and snippet body are translated per locale. The syntax language stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Translated Fields</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="title">Title</label>
                    <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="subtitle">Filename / Language Label</label>
                    <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content">Code</label>
                <textarea id="content" name="content" class="wb-textarea" rows="12">{{ old('content', $block->content) }}</textarea>
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Shared Fields</strong></div>
        <div class="wb-card-body wb-stack wb-gap-1">
            <label for="language">Syntax Language</label>
            <input id="language" name="language" class="wb-input" type="text" value="{{ old('language', $settings['language'] ?? $settings['lang'] ?? '') }}" @disabled($isNonDefaultLocale)>
            <div class="wb-text-sm wb-text-muted">
                @if ($isNonDefaultLocale)
                    Shared syntax settings can only be changed in the default locale.
                @else
                    Optional value such as <code>php</code>, <code>html</code>, or <code>js</code>.
                @endif
            </div>
        </div>
    </div>
</div>
