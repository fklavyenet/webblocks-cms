@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.quote.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="variant">{{ $adminText('variant_label') }}</label>
        <select id="variant" name="variant" class="wb-select">
            <option value="default" @selected(old('variant', $block->variant ?: 'default') === 'default')>{{ $adminText('default_quote') }}</option>
            <option value="testimonial" @selected(old('variant', $block->variant) === 'testimonial')>{{ $adminText('testimonial') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('variant_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('content_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('author_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('source_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>
</div>
