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
    $selectedIconSize = old('icon_size', $settings['icon_size'] ?? 'default');
    $selectedTone = old('badge_tone', $settings['badge_tone'] ?? 'neutral');
@endphp

@include('webblocks-cms::admin.blocks.partials.icon-picker-field', [
    'slugName' => 'icon_slug',
    'toneName' => 'icon_tone',
    'sizeName' => 'icon_size',
    'badgeToneName' => $supportsBadgeLabel ? 'badge_tone' : null,
    'slug' => $selectedIcon,
    'tone' => $selectedIconTone,
    'size' => $selectedIconSize,
    'badgeTone' => $selectedTone,
    'label' => $adminText('icon'),
])

@if ($supportsBadgeLabel)
    <div class="wb-stack wb-gap-1">
        <label for="badge_label">{{ $adminText('badge_label') }}</label>
        <input id="badge_label" name="badge_label" class="wb-input" type="text" maxlength="255" data-wb-badge-label-input value="{{ old('badge_label', $block->eyebrow ?? $block->translatedTextFieldValue('eyebrow')) }}">
    </div>
@endif
