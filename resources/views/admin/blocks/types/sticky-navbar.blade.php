@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sticky_navbar.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('title') }}</div>
            <div>{!! $adminText('help') !!}</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="name">{{ $adminText('admin_label') }}</label>
        <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $block->layoutAdminName()) }}" placeholder="{{ $adminText('name_placeholder') }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('name_help') }}</div>
    </div>
</div>
