@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.navigation_auto.'.$key, $adminLocale);
    $menuKey = old("{$prefix}.navigation_menu_key", $block->navigationMenuKey());
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('inline_system_help') }}</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_navigation_menu_key">{{ $adminText('menu_label') }}</label>
        <select id="block_{{ $index }}_navigation_menu_key" name="{{ $prefix }}[navigation_menu_key]" class="wb-select" required>
            @foreach (\WebBlocks\Cms\Models\NavigationItem::menuOptions() as $option => $label)
                <option value="{{ $option }}" @selected($menuKey === $option)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="wb-text-sm wb-text-muted">{{ $adminText('menu_help') }}</span>
    </div>
</div>
