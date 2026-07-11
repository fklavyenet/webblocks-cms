@php
  $siteAssets = collect($siteAssets ?? []);
@endphp

<div class="wb-card wb-card-muted">
  <div class="wb-card-header">
    <div class="wb-stack wb-gap-1">
      <strong>{{ $adminText('site_assets') }}</strong>
      <span class="wb-text-sm wb-text-muted">{{ $adminText('site_assets_help') }}</span>
    </div>
  </div>

  <div class="wb-card-body wb-stack wb-gap-4">
    @if (! $site->exists)
      <div class="wb-empty-state">
        <div class="wb-empty-title">{{ $adminText('save_site_first') }}</div>
        <div class="wb-empty-text">{{ $adminText('site_assets_existing_help') }}</div>
      </div>
    @else
      @if (! $canManageSiteSettings)
        <div class="wb-alert wb-alert-info">
          <div>
            <div class="wb-alert-title">{{ $adminText('read_only') }}</div>
            <div>{{ $adminText('site_assets_read_only_help') }}</div>
          </div>
        </div>
      @endif

      @foreach ($siteAssets as $asset)
        @php($formId = 'site-asset-'.$asset['type'].'-form')
        @php($isFailedAsset = old('_site_asset_type') === $asset['type'])
        @php($readiness = $asset['readiness'] ?? [])
        @php($isWritable = (bool) ($readiness['writable'] ?? true))
        <section class="wb-card">
          <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
              <strong>{{ $asset['label'] }} {{ $adminText('override_suffix') }}</strong>
              <span class="wb-text-sm wb-text-muted"><code>/{{ $asset['relative_path'] }}</code></span>
            </div>

            <span class="wb-status-pill {{ $isWritable ? ($asset['exists'] ? 'wb-status-active' : 'wb-status-pending') : 'wb-status-danger' }}">{{ $isWritable ? ($asset['exists'] ? $adminText('file_exists') : $adminText('ready_to_create')) : $adminText('not_writable') }}</span>
          </div>

          <div class="wb-card-body wb-stack wb-gap-3">
            @if (! $isWritable)
              <div class="wb-alert wb-alert-warning">
                <div>
                  <div class="wb-alert-title">{{ $adminText('asset_not_writable') }}</div>
                  <div>{{ $readiness['problem'] ?? $adminText('asset_not_writable_help') }}</div>
                </div>
              </div>
            @endif

            <div class="wb-grid wb-grid-2 wb-gap-3">
              <div class="wb-text-sm wb-text-muted">
                {{ $adminText('public_url') }} <code>{{ $asset['public_path'] }}</code>
              </div>
              <div class="wb-text-sm wb-text-muted">
                @if ($asset['exists'])
                  {{ $adminText('file_size', ['size' => number_format((int) $asset['size'])]) }}
                @else
                  {{ $adminText('file_created_on_save') }}
                @endif
              </div>
            </div>

            <div class="wb-stack-2 wb-field">
              <label for="site_asset_{{ $asset['type'] }}_contents">{{ $asset['label'] }} {{ $adminText('contents_suffix') }}</label>
              <textarea
                id="site_asset_{{ $asset['type'] }}_contents"
                name="contents"
                form="{{ $formId }}"
                class="wb-input"
                rows="16"
                spellcheck="false"
                @disabled(! $canManageSiteSettings || ! $isWritable)
              >{{ $isFailedAsset ? old('contents', $asset['contents']) : $asset['contents'] }}</textarea>
              <div class="wb-text-sm wb-text-muted">{{ $adminText('asset_contents_help', ['label' => $asset['label']]) }}</div>
              @if ($isFailedAsset)
                @error('contents')
                  <div class="wb-alert wb-alert-danger">{{ $message }}</div>
                @enderror
              @endif
            </div>
          </div>

          @if ($canManageSiteSettings)
            <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2">
              <span class="wb-text-sm wb-text-muted">{{ $adminText('checksum_guard', ['checksum' => $asset['checksum'] ? str($asset['checksum'])->limit(16, '') : $adminText('new_file')]) }}</span>
              <button type="submit" form="{{ $formId }}" class="wb-btn wb-btn-primary" @disabled(! $isWritable)>{{ $adminText('save_asset', ['label' => $asset['label']]) }}</button>
            </div>
          @endif
        </section>
      @endforeach
    @endif
  </div>
</div>
