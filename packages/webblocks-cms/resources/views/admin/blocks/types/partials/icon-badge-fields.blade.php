@php
    $iconContext = $iconContext ?? 'content';
    $supportsBadgeLabel = $supportsBadgeLabel ?? true;
    $settings = is_array($block->settings ?? null) ? $block->settings : json_decode((string) ($block->getRawOriginal('settings') ?? $block->settings ?? ''), true);
    $settings = is_array($settings) ? $settings : [];
    $selectedIcon = old('icon_slug', $settings['icon_slug'] ?? '');
    $selectedTone = old('badge_tone', $settings['badge_tone'] ?? 'neutral');
    $iconOptions = app(\WebBlocks\Cms\Support\Icons\IconCatalog::class)->pickerOptions($iconContext, $selectedIcon, $settings['icon_slug'] ?? null);
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="icon_slug">Icon</label>
        <select id="icon_slug" name="icon_slug" class="wb-select">
            <option value="">No icon</option>
            @foreach ($iconOptions as $icon)
                <option value="{{ $icon['slug'] }}" @selected($selectedIcon === $icon['slug'])>{{ $icon['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="badge_tone">Badge tone</label>
        <select id="badge_tone" name="badge_tone" class="wb-select">
            @foreach (['neutral' => 'Neutral', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedTone === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($supportsBadgeLabel)
    <div class="wb-stack wb-gap-1">
        <label for="badge_label">Badge label</label>
        <input id="badge_label" name="badge_label" class="wb-input" type="text" maxlength="255" value="{{ old('badge_label', $block->eyebrow ?? $block->translatedTextFieldValue('eyebrow')) }}">
    </div>
@endif
