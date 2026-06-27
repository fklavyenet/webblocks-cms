@php
  $publicThemePresets = collect($publicThemePresets ?? \WebBlocks\Cms\Models\Site::PUBLIC_THEME_PRESETS)
    ->mapWithKeys(fn (string $preset) => [$preset => str($preset)->headline()->toString()]);
  $selectedPublicThemePreset = in_array($selectedPublicThemePreset ?? null, $publicThemePresets->keys()->all(), true)
    ? $selectedPublicThemePreset
    : \WebBlocks\Cms\Models\Site::PUBLIC_THEME_CANVAS;
@endphp

<div class="wb-grid wb-grid-2 wb-gap-4">
  <div class="wb-card wb-card-muted">
    <div class="wb-card-header">
      <strong>Public Theme</strong>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
      <div class="wb-stack-2 wb-field">
        <label for="site_public_theme_preset">Theme preset</label>
        <select id="site_public_theme_preset" name="public_theme_preset" class="wb-input" @disabled(! $canManageSiteSettings)>
          @foreach ($publicThemePresets as $preset => $label)
            <option value="{{ $preset }}" @selected($selectedPublicThemePreset === $preset)>{{ $label }}</option>
          @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">Public visual tones are design roles for site atmosphere and layout surfaces. They are separate from semantic status tones such as success, warning, and danger.</div>
        @error('public_theme_preset')
          <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>
  </div>

  <div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
      <strong>Theme Preview</strong>
      <span class="wb-badge">{{ $publicThemePresets[$selectedPublicThemePreset] ?? 'Canvas' }}</span>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3" data-wb-public-theme-preview="{{ $selectedPublicThemePreset }}">
      <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
          <strong>{{ $site->publicDisplayName() }}</strong>
          <span class="wb-status-pill wb-status-active">Published</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-2">
          <div class="wb-text-sm wb-text-muted">Header, content, cards, and calls to action inherit the selected public design role.</div>
          <div class="wb-cluster wb-cluster-2">
            <span class="wb-badge">Hero</span>
            <span class="wb-badge">Card</span>
            <span class="wb-badge">Navigation</span>
          </div>
        </div>
        <div class="wb-card-footer">
          <span class="wb-text-sm wb-text-muted">Body hook: <code>data-wb-public-theme="{{ $selectedPublicThemePreset }}"</code></span>
        </div>
      </div>
    </div>
  </div>
</div>
