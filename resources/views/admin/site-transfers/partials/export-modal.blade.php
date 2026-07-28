@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = $adminLocale ?? app(AdminLocaleResolver::class)->locale();
    $adminTranslator = $adminTranslator ?? app(CmsTranslator::class);
    $adminText = $adminText ?? static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $modalId = $modalId ?? 'siteTransferExportModal';
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $selectedSite = $selectedSite ?? null;
    $sites = $sites ?? collect();
    $show = $show ?? false;
    $modalTitle = $modalTitle ?? $adminText('site_transfers.export_site');
    $modalDescription = $modalDescription ?? $adminText('site_transfers.export_site_default_description');
    $closeUrl = $closeUrl ?? route('admin.site-transfers.exports.index');
    $formAction = $formAction ?? route('admin.site-transfers.exports.store');
    $modalKey = $modalKey ?? 'create-export';
    $siteFieldName = $siteFieldName ?? 'site_id';
    $selectedSiteId = old($siteFieldName, $selectedSite?->id);
    $hasExportErrors = $errors->has('site_export') || $errors->has($siteFieldName) || $errors->has('includes_media');
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $show) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $show ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $modalTitle }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $modalDescription }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('site_transfers.close_export_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
                @csrf
                <input type="hidden" name="_site_export_modal" value="{{ $modalKey }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($show && $hasExportErrors)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('site_transfers.export_error') }}</div>
                                <div>{{ $errors->first('site_export') ?: $errors->first($siteFieldName) ?: $errors->first('includes_media') }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($selectedSite)
                        <input type="hidden" name="{{ $siteFieldName }}" value="{{ $selectedSite->id }}">

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.site_name') }}</span>
                                    <strong>{{ $selectedSite->name }}</strong>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.site_handle') }}</span>
                                    <code>{{ $selectedSite->handle }}</code>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2 wb-field">
                            <label for="{{ $modalId }}SiteId">{{ $adminText('site_transfers.site') }}</label>
                            <select id="{{ $modalId }}SiteId" name="{{ $siteFieldName }}" class="wb-select" required data-wb-export-site-select>
                                <option value="">{{ $adminText('site_transfers.select_site') }}</option>

                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) $selectedSiteId === $site->id)>{{ $site->name }} ({{ $site->handle }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Page picker. Rendered per site and revealed by the select
                         above, so the choice and the pages it applies to stay in
                         one form. Archived pages start unticked: on a site built
                         through staged updates they are discarded drafts, and
                         they can easily outweigh the live content. --}}
                    @if (! empty($exportablePages ?? []))
                        <div class="wb-stack wb-gap-2 wb-field" data-wb-export-pages>
                            <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                                <label class="wb-label">{{ $adminText('site_transfers.pages_to_include') }}</label>

                                <div class="wb-cluster wb-cluster-2">
                                    <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-export-pages-all>{{ $adminText('site_transfers.select_all_pages') }}</button>
                                    <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-export-pages-published>{{ $adminText('site_transfers.select_published_pages') }}</button>
                                    <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-export-pages-none>{{ $adminText('site_transfers.select_no_pages') }}</button>
                                </div>
                            </div>

                            {{-- Always submitted, so an all-unticked list reaches the
                                 server as an explicit empty selection instead of
                                 looking like no selection at all, which means
                                 "every page". --}}
                            <input type="hidden" name="page_ids[]" value="" data-wb-export-pages-empty>

                            @foreach ($exportablePages as $pagesSiteId => $sitePages)
                                <div class="wb-stack wb-gap-1" data-wb-export-page-group="{{ $pagesSiteId }}" hidden>
                                    <div class="wb-scroll-y" style="max-height: 15rem;">
                                        @foreach ($sitePages as $exportPage)
                                            <label class="wb-checkbox" for="{{ $modalId }}Page{{ $exportPage['id'] }}">
                                                <input
                                                    id="{{ $modalId }}Page{{ $exportPage['id'] }}"
                                                    type="checkbox"
                                                    name="page_ids[]"
                                                    value="{{ $exportPage['id'] }}"
                                                    data-wb-export-page-status="{{ $exportPage['status'] }}"
                                                    @checked($exportPage['checked'])
                                                    disabled
                                                >
                                                <span>
                                                    {{ $exportPage['title'] }}
                                                    <span class="wb-badge wb-badge-sm">{{ $exportPage['status'] }}</span>
                                                    @if ($exportPage['path'])
                                                        <span class="wb-text-sm wb-text-muted">{{ $exportPage['path'] }}</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="wb-text-sm wb-text-muted" data-wb-export-pages-count></div>
                                </div>
                            @endforeach

                            <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.pages_to_include_help') }}</div>
                        </div>
                    @endif

                    <div class="wb-stack wb-gap-2 wb-field">
                        <label class="wb-checkbox" for="{{ $modalId }}IncludesMedia">
                            <input id="{{ $modalId }}IncludesMedia" type="checkbox" name="includes_media" value="1" @checked(old('includes_media', true))>
                            <span>{{ $adminText('site_transfers.include_media_files') }}</span>
                        </label>

                        <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.include_media_help') }}</div>
                    </div>
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$adminText('site_transfers.export_site')"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>

@if (! empty($exportablePages ?? []))
@push('scripts')
  <script>
    (function () {
      var root = document.querySelector('[data-wb-export-pages]');
      var select = document.querySelector('[data-wb-export-site-select]');

      if (!root) { return; }

      function groups() { return root.querySelectorAll('[data-wb-export-page-group]'); }

      // The same modal is opened from the Sites screen with the site already
      // fixed and no select to read. Bailing out there would leave every box
      // disabled while the empty hidden input still submitted, which reads on
      // the server as "no pages selected" — an empty package, silently.
      function activeGroup() {
        if (!select) {
          return groups().length === 1 ? groups()[0] : null;
        }

        return root.querySelector('[data-wb-export-page-group="' + select.value + '"]');
      }

      function boxes(group) {
        return group ? group.querySelectorAll('input[type="checkbox"]') : [];
      }

      function updateCount(group) {
        if (!group) { return; }
        var all = boxes(group);
        var on = 0;
        all.forEach(function (b) { if (b.checked) { on++; } });
        var label = group.querySelector('[data-wb-export-pages-count]');
        if (label) { label.textContent = on + ' / ' + all.length; }
      }

      // Only the selected site's boxes are enabled, so a hidden group can never
      // smuggle its pages into the submitted selection.
      function sync() {
        groups().forEach(function (group) {
          var isActive = group === activeGroup();
          group.hidden = !isActive;
          boxes(group).forEach(function (box) { box.disabled = !isActive; });
          if (isActive) { updateCount(group); }
        });
      }

      function setAll(predicate) {
        var group = activeGroup();
        boxes(group).forEach(function (box) {
          box.checked = predicate(box.getAttribute('data-wb-export-page-status'));
        });
        updateCount(group);
      }

      if (select) { select.addEventListener('change', sync); }
      root.addEventListener('change', function (event) {
        if (event.target.matches('input[type="checkbox"]')) { updateCount(activeGroup()); }
      });

      root.querySelector('[data-wb-export-pages-all]').addEventListener('click', function () { setAll(function () { return true; }); });
      root.querySelector('[data-wb-export-pages-none]').addEventListener('click', function () { setAll(function () { return false; }); });
      root.querySelector('[data-wb-export-pages-published]').addEventListener('click', function () { setAll(function (status) { return status === 'published'; }); });

      sync();
    }());
  </script>
@endpush
@endif
