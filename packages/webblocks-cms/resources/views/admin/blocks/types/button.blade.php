@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.button.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('target') }}</label>
            <select id="subtitle" name="subtitle" class="wb-select">
                <option value="_self" @selected(old('subtitle', $block->subtitle ?: '_self') === '_self')>{{ $adminText('same_tab') }}</option>
                <option value="_blank" @selected(old('subtitle', $block->subtitle) === '_blank')>{{ $adminText('new_tab') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">{{ $adminText('variant') }}</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['primary', 'secondary', 'outline', 'ghost', 'danger'] as $variant)
                    <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'primary') === $variant)>{{ $adminText('variant_'.$variant) }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('variant_help') }}</div>
        </div>
    </div>
</div>
