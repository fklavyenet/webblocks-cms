@php
  $seoText = static fn (string $key, array $replace = []) => $adminTranslator->get('seo.'.$key, $adminLocale, $replace);
  $seoReport = $seoReport ?? null;
  $seoLocales = $page->availableSiteLocales()->whereIn('id', $page->translations->pluck('locale_id'));
  $seoSelectedLocale = $seoReport['locale'] ?? (is_string(request('seo_locale')) ? request('seo_locale') : null);
  $seoSelectedLocale = $seoLocales->firstWhere('code', $seoSelectedLocale)?->code
    ?? $seoLocales->firstWhere('is_default', true)?->code ?? $seoLocales->first()?->code;
  $seoTranslation = $page->translations->first(fn ($row) => $row->locale?->code === $seoSelectedLocale);
@endphp

<div class="wb-stack wb-gap-4">
  <div class="wb-stack wb-gap-2">
    <h3 class="wb-m-0 wb-text-base wb-font-semibold">{{ $seoText('title') }}</h3>
    <p class="wb-text-sm wb-text-muted">{{ $seoText('intro') }}</p>
    <p class="wb-text-sm wb-text-muted">{{ $seoText('nonblocking') }}</p>
  </div>
  @if ($canInspectSiteSeo ?? false)<a class="wb-link" href="{{ route('admin.sites.seo-audits.index', $page->site_id) }}">{{ $seoText('audit_title') }}</a>@endif
  <form method="GET" action="{{ route('admin.pages.seo-check', $page) }}#page-management" class="wb-cluster wb-cluster-2 wb-flex-wrap">
    <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
    <div class="wb-stack wb-gap-1">
      <label for="seo-content-locale">{{ $seoText('language') }}</label>
      <select id="seo-content-locale" name="locale" class="wb-select" required>
        @foreach ($seoLocales as $seoLocale)
          <option value="{{ $seoLocale->code }}" @selected($seoSelectedLocale === $seoLocale->code)>{{ $seoLocale->name }} ({{ strtoupper($seoLocale->code) }})</option>
        @endforeach
      </select>
      @error('locale')<span class="wb-text-danger">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="wb-btn wb-btn-secondary" @disabled($seoLocales->isEmpty())>{{ $seoText($seoReport ? 'rerun' : 'run') }}</button>
  </form>

  @if (! $seoReport)
    <div class="wb-alert wb-alert-info">{{ $seoText('idle') }}</div>
  @else
    <div class="wb-alert wb-alert-warning" role="status" data-wb-seo-stale @if (! $seoReport['stale']) hidden @endif>{{ $seoText('stale_report') }}</div>
    <div data-wb-seo-freshness="{{ route('admin.pages.seo-check.freshness', ['page' => $page, 'locale' => $seoReport['locale'], 'source_version' => $seoReport['source_version']]) }}"></div>
    <div class="wb-alert wb-alert-info" role="status" data-wb-seo-freshness-unavailable hidden>{{ $seoText('freshness_unavailable') }}</div>
    <section class="wb-card wb-card-muted" aria-labelledby="seo-scope-heading">
      <div class="wb-card-header"><h4 id="seo-scope-heading" class="wb-m-0 wb-text-base wb-font-semibold">{{ $seoText('scope') }}</h4></div>
      <div class="wb-card-body wb-stack wb-gap-2">
        <p>{{ $seoReport['blocks_publication_note'] }}</p>
        <div class="wb-table-wrap"><table class="wb-table"><tbody>
          <tr><th scope="row">{{ $seoText('language') }}</th><td>{{ strtoupper($seoReport['locale']) }}</td></tr>
          <tr><th scope="row">{{ $seoText('checked_at') }}</th><td><time datetime="{{ $seoReport['checked_at'] }}">{{ $seoReport['checked_at'] }}</time></td></tr>
          <tr><th scope="row">{{ $seoText('page_status') }}</th><td>{{ $page->workflowLabel() }}</td></tr>
          <tr><th scope="row">{{ $seoText('public_url') }}</th><td>{{ $seoReport['public_url'] ?? '—' }}</td></tr>
          @foreach (['title', 'description'] as $seoMetadata)
            <tr><th scope="row">{{ $seoText('metadata_'.$seoMetadata) }}</th><td>{{ $seoReport['metadata'][$seoMetadata]['value'] ?? '—' }} <span class="wb-text-sm wb-text-muted">({{ $seoText('metadata_sources.'.$seoReport['metadata'][$seoMetadata]['source']) }}{{ $seoMetadata === 'title' ? ' · '.$seoText('site_label') : '' }})</span></td></tr>
          @endforeach
        </tbody></table></div>
        @if ($canEditContent && $seoTranslation)
          <a class="wb-link" href="{{ route('admin.pages.translations.edit', ['page' => $page, 'translation' => $seoTranslation, 'return_url' => $pageReturnUrl]) }}#translation-seo-heading">{{ $seoText('edit_translation') }}</a>
        @endif
        <a class="wb-link" href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}">{{ $seoText('edit_content') }}</a>
      </div>
    </section>
    @if (collect($seoReport['not_checked'])->contains(fn ($item) => in_array($item['code'], ['render_failed', 'html_unavailable', 'main_content_missing', 'dynamic_content_incomplete'], true)))
      <div class="wb-alert wb-alert-warning" role="status">{{ $seoText('unavailable_title') }}</div>
    @endif
    @foreach (['technical', 'editorial'] as $seoCategory)
      <section class="wb-card" aria-labelledby="seo-{{ $seoCategory }}-heading" data-wb-seo-category="{{ $seoCategory }}">
        <div class="wb-card-header"><h4 id="seo-{{ $seoCategory }}-heading" class="wb-m-0 wb-text-base wb-font-semibold">{{ $seoText($seoCategory) }} ({{ count($seoReport[$seoCategory]) }})</h4></div>
        <div class="wb-card-body">
          @if ($seoReport[$seoCategory] === [])
            <p class="wb-text-sm wb-text-muted">{{ $seoText($seoReport['inspection_status'] === 'unavailable' ? 'unavailable_title' : 'empty_group') }}</p>
          @else
            <ul class="wb-stack wb-gap-3">
              @foreach ($seoReport[$seoCategory] as $seoFinding)
                <li data-wb-seo-code="{{ $seoFinding['code'] }}">
                  <p>{{ $seoFinding['message'] }}</p>
                  <p class="wb-text-sm wb-text-muted">{{ $seoText('target') }}: <code>{{ $seoFinding['target'] }}</code> · {{ $seoText('count') }}: {{ $seoFinding['count'] }}</p>
                  @foreach ($seoFinding['examples'] as $seoExample)
                    @if ($seoExample['example'] !== '')<p class="wb-text-sm">{{ $seoText('example') }}: {{ $seoExample['example'] }} <a class="wb-link" href="{{ $seoExample['action']['href'] }}">{{ $seoText('open_target') }}</a></p>@endif
                  @endforeach
                  <a class="wb-link" href="{{ $seoFinding['action']['href'] }}">{{ $seoText('open_target') }}</a>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </section>
    @endforeach
    <section class="wb-card" aria-labelledby="seo-not-checked-heading">
      <div class="wb-card-header"><h4 id="seo-not-checked-heading" class="wb-m-0 wb-text-base wb-font-semibold">{{ $seoText('not_checked_title') }}</h4></div>
      <div class="wb-card-body">
        <ul class="wb-stack wb-gap-2">
          @foreach ($seoReport['not_checked'] as $seoSkipped)
            <li>{{ $seoSkipped['message'] }}</li>
          @endforeach
        </ul>
      </div>
    </section>
  @endif
</div>
