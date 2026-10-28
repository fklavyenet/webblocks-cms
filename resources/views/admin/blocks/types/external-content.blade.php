@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $t = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('consent.'.$key, $locale);
@endphp
<div class="wb-stack-3">
    <label for="external-content-title">{{ $t('name') }}</label><input id="external-content-title" class="wb-input" name="title" value="{{ old('title', $block->title) }}">
    <label for="external-content-url">{{ $t('embed_url') }}</label><textarea id="external-content-url" class="wb-textarea" name="url" rows="3" maxlength="2048" required aria-describedby="external-content-help">{{ old('url', $block->url) }}</textarea><p id="external-content-help">{{ $t('embed_help') }}</p>
    <label for="external-content-description">{{ $t('description') }}</label><textarea id="external-content-description" class="wb-textarea" name="content" rows="3">{{ old('content', $block->content) }}</textarea>
</div>
