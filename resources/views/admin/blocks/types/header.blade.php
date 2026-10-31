@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.header.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="text">{{ $adminText('text_label') }}</label>
        <textarea id="text" name="text" class="wb-textarea" rows="3" required>{{ old('text', $block->title) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="level">{{ $adminText('level_label') }}</label>
        <select id="level" name="level" class="wb-select" required>
            @foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level)
                <option value="{{ $level }}" @selected(old('level', $block->variant ?: 'h2') === $level)>{{ strtoupper($level) }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="anchor">{{ $adminText('anchor_label') }}</label>
        <input id="anchor" name="anchor" class="wb-input" type="text" value="{{ old('anchor', $block->explicitHeaderAnchor()) }}" placeholder="{{ $block->headerAnchor() }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('anchor_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="include_in_toc">{{ $adminText('toc_label') }}</label>
        <select id="include_in_toc" name="include_in_toc" class="wb-select">
            <option value="1" @selected((string) old('include_in_toc', $block->setting('include_in_toc', true) ? '1' : '0') === '1')>{{ $adminText('toc_include') }}</option>
            <option value="0" @selected((string) old('include_in_toc', $block->setting('include_in_toc', true) ? '1' : '0') === '0')>{{ $adminText('toc_exclude') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('toc_help') }}</div>
    </div>
</div>
