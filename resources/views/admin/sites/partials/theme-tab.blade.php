@php
  $publicThemePresetValues = \WebBlocks\Cms\Models\Site::PUBLIC_THEME_PRESETS;
  $publicThemePresets = collect($publicThemePresets ?? $publicThemePresetValues)
    ->mapWithKeys(function ($label, $preset) use ($publicThemePresetValues): array {
      $value = is_string($preset) && in_array($preset, $publicThemePresetValues, true)
        ? $preset
        : strtolower(trim((string) $label));

      return in_array($value, $publicThemePresetValues, true)
        ? [$value => str($value)->headline()->toString()]
        : [];
    });
  $selectedPublicThemePreset = strtolower(trim((string) ($selectedPublicThemePreset ?? '')));
  $selectedPublicThemePreset = in_array($selectedPublicThemePreset, $publicThemePresets->keys()->all(), true)
    ? $selectedPublicThemePreset
    : \WebBlocks\Cms\Models\Site::PUBLIC_THEME_CANVAS;
@endphp

<div class="wb-grid wb-grid-2 wb-gap-4">
  <div class="wb-card wb-card-muted">
    <div class="wb-card-header">
      <strong>{{ $adminText('public_theme') }}</strong>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
      <div class="wb-stack-2 wb-field">
        <label for="site_public_theme_preset">{{ $adminText('theme_preset') }}</label>
        <select id="site_public_theme_preset" name="public_theme_preset" class="wb-input" @disabled(! $canManageSiteSettings)>
          @foreach ($publicThemePresets as $preset => $label)
            <option value="{{ $preset }}" @selected($selectedPublicThemePreset === $preset)>{{ $label }}</option>
          @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('public_theme_help') }}</div>
        @error('public_theme_preset')
          <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>
  </div>

  <div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>{{ $adminText('theme_preview') }}</strong>
      <span class="wb-badge" data-wb-theme-preview-label>{{ $publicThemePresets[$selectedPublicThemePreset] ?? $adminText('canvas') }}</span>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3" data-wb-public-theme-preview="{{ $selectedPublicThemePreset }}">
      <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
          <strong>{{ $site->publicDisplayName() }}</strong>
          <span class="wb-status-pill wb-status-active">{{ $adminText('published') }}</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-2">
          <div class="wb-text-sm wb-text-muted">{{ $adminText('theme_preview_help') }}</div>
          <div class="wb-cluster wb-cluster-2">
            <span class="wb-badge">{{ $adminText('hero') }}</span>
            <span class="wb-badge">{{ $adminText('card') }}</span>
            <span class="wb-badge">{{ $adminText('navigation') }}</span>
          </div>
        </div>
        <div class="wb-card-footer">
          <span class="wb-text-sm wb-text-muted">{{ $adminText('body_hook') }} <code data-wb-theme-preview-hook>data-wb-public-theme="{{ $selectedPublicThemePreset }}"</code></span>
        </div>
      </div>
    </div>
  </div>
</div>
