@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.trusted_html.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-warning">
        <div><strong>{{ $adminText('warning_title') }}</strong> {{ $adminText('warning_body') }}</div>
    </div>

    <label for="content">{{ $adminText('label') }}</label>
    <textarea id="content" name="content" class="wb-textarea" rows="10" required>{{ old('content', $block->content) }}</textarea>
</div>
