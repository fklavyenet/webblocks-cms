@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.slide.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="name">{{ $adminText('name_label') }}</label>
        <input id="name" name="name" class="wb-input" type="text" maxlength="100" value="{{ old('name', $block->layoutAdminName()) }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('name_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="slide_aria_label">{{ $adminText('aria_label') }}</label>
        <input id="slide_aria_label" name="slide_aria_label" class="wb-input" type="text" maxlength="255" value="{{ old('slide_aria_label', $block->setting('aria_label')) }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('aria_help') }}</div>
    </div>

    @include('webblocks-cms::admin.blocks.types.partials.background-media-fields', ['overlayInherits' => true])

    <div class="wb-alert wb-alert-info">
        {{ $adminText('container_help') }}
    </div>
</div>
