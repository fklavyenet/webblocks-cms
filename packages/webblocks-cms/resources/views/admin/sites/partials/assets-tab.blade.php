@php
  $siteAssets = collect($siteAssets ?? []);
@endphp

<div class="wb-card wb-card-muted">
  <div class="wb-card-header">
    <div class="wb-stack wb-gap-1">
      <strong>Site Assets</strong>
      <span class="wb-text-sm wb-text-muted">Manage canonical site-level override files without SSH. Existing files are protected by checksum checks and revision snapshots before overwrite.</span>
    </div>
  </div>

  <div class="wb-card-body wb-stack wb-gap-4">
    @if (! $site->exists)
      <div class="wb-empty-state">
        <div class="wb-empty-title">Save the site first.</div>
        <div class="wb-empty-text">Site assets are attached to an existing site handle.</div>
      </div>
    @else
      @if (! $canManageSiteSettings)
        <div class="wb-alert wb-alert-info">
          <div>
            <div class="wb-alert-title">Read only</div>
            <div>Site assets are visible here, but only site admins and super admins can save changes.</div>
          </div>
        </div>
      @endif

      @foreach ($siteAssets as $asset)
        @php($formId = 'site-asset-'.$asset['type'].'-form')
        @php($isFailedAsset = old('_site_asset_type') === $asset['type'])
        <section class="wb-card">
          <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
              <strong>{{ $asset['label'] }} override</strong>
              <span class="wb-text-sm wb-text-muted"><code>/{{ $asset['relative_path'] }}</code></span>
            </div>

            <span class="wb-status-pill {{ $asset['exists'] ? 'wb-status-active' : 'wb-status-pending' }}">{{ $asset['exists'] ? 'File exists' : 'Not created' }}</span>
          </div>

          <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-2 wb-gap-3">
              <div class="wb-text-sm wb-text-muted">
                Public URL: <code>{{ $asset['public_path'] }}</code>
              </div>
              <div class="wb-text-sm wb-text-muted">
                @if ($asset['exists'])
                  Size: {{ number_format((int) $asset['size']) }} bytes
                @else
                  This file will be created on first save.
                @endif
              </div>
            </div>

            <div class="wb-stack-2 wb-field">
              <label for="site_asset_{{ $asset['type'] }}_contents">{{ $asset['label'] }} contents</label>
              <textarea
                id="site_asset_{{ $asset['type'] }}_contents"
                name="contents"
                form="{{ $formId }}"
                class="wb-input"
                rows="16"
                spellcheck="false"
                @disabled(! $canManageSiteSettings)
              >{{ $isFailedAsset ? old('contents', $asset['contents']) : $asset['contents'] }}</textarea>
              <div class="wb-text-sm wb-text-muted">Only this canonical {{ $asset['label'] }} file is managed here. Arbitrary public paths are intentionally not editable from this screen.</div>
              @if ($isFailedAsset)
                @error('contents')
                  <div class="wb-alert wb-alert-danger">{{ $message }}</div>
                @enderror
              @endif
            </div>
          </div>

          @if ($canManageSiteSettings)
            <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2">
              <span class="wb-text-sm wb-text-muted">Checksum guard: {{ $asset['checksum'] ? str($asset['checksum'])->limit(16, '') : 'new file' }}</span>
              <button type="submit" form="{{ $formId }}" class="wb-btn wb-btn-primary">Save {{ $asset['label'] }}</button>
            </div>
          @endif
        </section>
      @endforeach
    @endif
  </div>
</div>
