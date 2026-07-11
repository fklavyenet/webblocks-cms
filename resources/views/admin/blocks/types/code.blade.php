@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.code.'.$key, $adminLocale);
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('translated_fields') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="title">{{ $adminText('title') }}</label>
                    <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="subtitle">{{ $adminText('filename_label') }}</label>
                    <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content">{{ $adminText('code') }}</label>
                <textarea id="content" name="content" class="wb-textarea" rows="12">{{ old('content', $block->content) }}</textarea>
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('shared_fields') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-1">
            <label for="language">{{ $adminText('syntax_language') }}</label>
            <input id="language" name="language" class="wb-input" type="text" value="{{ old('language', $settings['language'] ?? $settings['lang'] ?? '') }}" @disabled($isNonDefaultLocale)>
            <div class="wb-text-sm wb-text-muted">
                @if ($isNonDefaultLocale)
                    {{ $adminText('shared_locale_help') }}
                @else
                    {!! $adminText('syntax_help') !!}
                @endif
            </div>
        </div>
    </div>
</div>
