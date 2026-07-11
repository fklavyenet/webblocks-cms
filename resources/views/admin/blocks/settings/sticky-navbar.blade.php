@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sticky_navbar_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="sticky_navbar_mode">{{ $adminText('position_label') }}</label>
        <select id="sticky_navbar_mode" name="sticky_navbar_mode" class="wb-select">
            @foreach ([
                'sticky' => $adminText('sticky'),
                'fixed' => $adminText('fixed'),
                'static' => $adminText('static'),
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('sticky_navbar_mode', $block->navbarPosition()) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('position_help') }}</div>
    </div>
</div>
