@php
    $cookieAdminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $cookieAdminText = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('cookie_settings_block.'.$key, $cookieAdminLocale);
@endphp
<div class="wb-stack-2">
    <label for="variant">{{ $cookieAdminText('appearance') }}</label>
    <select id="variant" name="variant" class="wb-select">
        @foreach (['text', 'primary', 'secondary'] as $cookieVariant)
            <option value="{{ $cookieVariant }}" @selected(old('variant', $block->variant ?: 'text') === $cookieVariant)>{{ $cookieAdminText($cookieVariant) }}</option>
        @endforeach
    </select>
</div>
