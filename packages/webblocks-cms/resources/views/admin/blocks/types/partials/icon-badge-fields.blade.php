@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.partials.icon_badge_fields.'.$key, $adminLocale);
    $iconContext = $iconContext ?? 'content';
    $supportsBadgeLabel = $supportsBadgeLabel ?? true;
    $settings = is_array($block->settings ?? null) ? $block->settings : json_decode((string) ($block->getRawOriginal('settings') ?? $block->settings ?? ''), true);
    $settings = is_array($settings) ? $settings : [];
    $selectedIcon = old('icon_slug', $settings['icon_slug'] ?? '');
    $selectedIconTone = old('icon_tone', $settings['icon_tone'] ?? 'default');
    $selectedTone = old('badge_tone', $settings['badge_tone'] ?? 'neutral');
    $iconOptions = app(\WebBlocks\Cms\Support\Icons\IconCatalog::class)->pickerOptions($iconContext, $selectedIcon, $settings['icon_slug'] ?? null);
    $iconToneOptions = [
        'default' => $adminText('default'),
        'soft' => $adminText('soft'),
        'brand' => $adminText('brand'),
        'accent' => $adminText('accent'),
        'highlight' => $adminText('highlight'),
        'bold' => $adminText('bold'),
        'quiet' => $adminText('quiet'),
    ];
@endphp

<div class="wb-grid wb-grid-3">
    <div class="wb-stack wb-gap-1">
        <label for="icon_slug">{{ $adminText('icon') }}</label>
        <select id="icon_slug" name="icon_slug" class="wb-select">
            <option value="">{{ $adminText('no_icon') }}</option>
            @foreach ($iconOptions as $icon)
                <option value="{{ $icon['slug'] }}" @selected($selectedIcon === $icon['slug'])>{{ $icon['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="icon_tone">{{ $adminText('icon_tone') }}</label>
        <select id="icon_tone" name="icon_tone" class="wb-select">
            @foreach ($iconToneOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedIconTone === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="badge_tone">{{ $adminText('badge_tone') }}</label>
        <select id="badge_tone" name="badge_tone" class="wb-select">
            @foreach (['neutral' => $adminText('neutral'), 'info' => $adminText('info'), 'success' => $adminText('success'), 'warning' => $adminText('warning'), 'danger' => $adminText('danger')] as $value => $label)
                <option value="{{ $value }}" @selected($selectedTone === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($supportsBadgeLabel)
    <div class="wb-stack wb-gap-1">
        <label for="badge_label">{{ $adminText('badge_label') }}</label>
        <input id="badge_label" name="badge_label" class="wb-input" type="text" maxlength="255" value="{{ old('badge_label', $block->eyebrow ?? $block->translatedTextFieldValue('eyebrow')) }}">
    </div>
@endif
