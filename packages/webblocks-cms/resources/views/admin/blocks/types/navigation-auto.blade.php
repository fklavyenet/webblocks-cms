@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.navigation_auto.'.$key, $adminLocale);
    $menuKey = old('navigation_menu_key', $block->navigationMenuKey());
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="navigation_menu_key">{{ $adminText('menu_label') }}</label>
            <select id="navigation_menu_key" name="navigation_menu_key" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Models\NavigationItem::menuOptions() as $option => $label)
                    <option value="{{ $option }}" @selected($menuKey === $option)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('menu_help') }}</span>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-1">
                <strong>{{ $adminText('system_block') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('system_block_help') }}</span>
            </div>
        </div>
    </div>
</div>
