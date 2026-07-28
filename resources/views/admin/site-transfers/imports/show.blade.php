@php
    use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportPlan;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $transferStatusLabel = static fn (?string $status) => $status ? $adminText('site_transfers.statuses.'.$status) : '-';
    $yesNoLabel = static fn (bool $value) => $value ? $adminText('common.yes') : $adminText('common.no');

    // The modal names the phase it is on, so the phase list and its labels have
    // to line up; building the map from the plan keeps a new phase from showing
    // up in the UI as a raw key.
    $importPhaseLabels = collect(SiteImportPlan::keys())
        ->mapWithKeys(static fn (string $phase) => [$phase => $adminText('site_transfers.import_phases.'.$phase)])
        ->put('starting', $adminText('site_transfers.import_phases.starting'))
        ->all();
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('site_transfers.import_details'), 'heading' => $adminText('site_transfers.import_details')])

@section('content')
    @php
        $outputLog = (string) ($siteImport->output_log ?? '');

        if ($siteImport->failure_message) {
            $duplicateFailureLines = [
                'Import failed: '.$siteImport->failure_message,
                'Import validation failed: '.$siteImport->failure_message,
            ];

            $outputLog = collect(explode(PHP_EOL, $outputLog))
                ->reject(fn ($line) => in_array(trim((string) $line), $duplicateFailureLines, true))
                ->implode(PHP_EOL);
        }
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $siteImport->source_archive_name ?? $adminText('site_transfers.import_number', ['id' => $siteImport->id]),
        'description' => $adminText('site_transfers.import_details_description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">'.$adminText('site_transfers.back_to_export_import').'</a>'.($siteImport->targetSite ? '<a href="'.route('admin.sites.edit', $siteImport->targetSite).'" class="wb-btn wb-btn-secondary">'.$adminText('site_transfers.open_imported_site').'</a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('site_transfers.import_status') }}</strong>
                    <span class="wb-status-pill {{ $siteImport->statusBadgeClass() }}">{{ $transferStatusLabel($siteImport->status) }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $adminText('site_transfers.source_package') }}:</strong> {{ $siteImport->source_archive_name ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.imported_site_handle') }}:</strong> {{ $siteImport->imported_site_handle ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.imported_site_domain') }}:</strong> {{ $siteImport->imported_site_domain ?? '-' }}</div>

                    @if ($siteImport->failure_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('site_transfers.import_failed') }}</div>
                                <div>{{ $siteImport->failure_message }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('site_transfers.manifest_preview') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $adminText('site_transfers.product') }}:</strong> {{ $manifest['product'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.format_version') }}:</strong> {{ $manifest['format_version'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_site') }}:</strong> {{ $manifest['source_site_name'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_handle') }}:</strong> {{ $manifest['source_site_handle'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_domain') }}:</strong> {{ $manifest['source_site_domain'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.locales') }}:</strong> {{ collect($manifest['locales'] ?? [])->implode(', ') ?: '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.includes_media') }}:</strong> {{ $yesNoLabel((bool) ($manifest['includes_media'] ?? false)) }}</div>
                </div>
            </div>
        </div>

        @if ($siteImport->isValidated() || $siteImport->isResumable())
            <div class="wb-card wb-card-accent">
                <div class="wb-card-header"><strong>{{ $adminText('site_transfers.create_new_site_from_package') }}</strong></div>

                <div class="wb-card-body">
                    <form method="POST" action="{{ route('admin.site-transfers.imports.run', $siteImport) }}" class="wb-stack wb-gap-4"
                        data-wb-import-form
                        data-wb-step-url="{{ route('admin.site-transfers.imports.step', $siteImport) }}"
                        data-wb-resuming="{{ $siteImport->isResumable() ? '1' : '0' }}">
                        @csrf

                        @if ($siteImport->isResumable())
                            <div class="wb-alert wb-alert-info">
                                <div class="wb-alert-title">{{ $adminText('site_transfers.resume_notice_title') }}</div>
                                <div>{{ $adminText('site_transfers.resume_notice_body', ['percent' => $siteImport->progressPercent()]) }}</div>
                            </div>
                        @endif

                        <div class="wb-grid wb-grid-3" @if ($siteImport->isResumable()) hidden @endif>
                            <div class="wb-stack wb-gap-2">
                                <label for="site_name">{{ $adminText('site_transfers.new_site_name') }}</label>
                                <input id="site_name" type="text" name="site_name" class="wb-input" value="{{ old('site_name', $manifest['source_site_name'] ?? '') }}" required>
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <label for="site_handle">{{ $adminText('site_transfers.new_site_handle') }}</label>
                                <input id="site_handle" type="text" name="site_handle" class="wb-input" value="{{ old('site_handle', $manifest['source_site_handle'] ?? '') }}">
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.handle_exists_help') }}</div>
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <label for="site_domain">{{ $adminText('site_transfers.optional_domain') }}</label>
                                <input id="site_domain" type="text" name="site_domain" class="wb-input" value="{{ old('site_domain') }}">
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.optional_domain_help') }}</div>
                            </div>
                        </div>

                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <button type="submit" class="wb-btn wb-btn-primary" data-wb-import-submit>
                                    {{ $siteImport->isResumable() ? $adminText('site_transfers.resume_import') : $adminText('site_transfers.run_import') }}
                                </button>
                                <a href="{{ route('admin.site-transfers.exports.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</a>
                            </div>
                        </div>
                    </form>

                    @if ($siteImport->isResumable())
                        <form method="POST" action="{{ route('admin.site-transfers.imports.discard', $siteImport) }}" class="wb-mt-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="wb-btn wb-btn-danger wb-btn-sm">{{ $adminText('site_transfers.discard_partial_import') }}</button>
                            <div class="wb-text-sm wb-text-muted wb-mt-2">{{ $adminText('site_transfers.discard_partial_import_help') }}</div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <section class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.package_counts') }}</strong></div>

            <div class="wb-card-body">
                @if (count($counts) > 0)
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('site_transfers.area') }}</th>
                                    <th style="text-align: right;">{{ $adminText('site_transfers.count') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($counts as $label => $value)
                                    <tr>
                                        <td>{{ str($label)->replace('_', ' ')->title() }}</td>
                                        <td style="text-align: right;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_count_summary') }}</div>
                    </div>
                @endif
            </div>
        </section>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.output_log') }}</strong></div>

            <div class="wb-card-body">
                @if (trim($outputLog) !== '')
                    <pre class="wb-code-block">{{ $outputLog }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_output_log') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    {{-- Progress modal: the import runs as a series of committed steps that the
         browser drives, so this reports real phase and row counts rather than a
         spinner. No dismiss while it runs; closing the tab pauses the import
         instead of losing it, and the card offers Resume afterwards. --}}
    @if ($siteImport->isValidated() || $siteImport->isResumable())
        <div
            class="wb-modal"
            id="wb-import-progress"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wb-import-progress-title"
            data-wb-import-progress-modal
            data-wb-return-url="{{ route('admin.site-transfers.imports.show', $siteImport) }}"
            data-wb-busy-label="{{ $adminText('site_transfers.import_busy') }}"
            hidden
        >
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <h3 class="wb-modal-title" id="wb-import-progress-title">{{ $adminText('site_transfers.importing_title') }}</h3>
                </div>
                <div class="wb-modal-body">
                    <div class="wb-stack wb-gap-2" role="status" aria-live="polite" aria-atomic="true">
                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3">
                            <strong data-wb-import-phase>{{ $adminText('site_transfers.import_phases.starting') }}</strong>
                            <span class="wb-text-sm wb-text-muted" data-wb-import-counter></span>
                        </div>
                        <div class="wb-progress-bar" data-wb-import-bar>
                            <div class="wb-progress-bar-fill" data-wb-import-fill style="width: 0%;"></div>
                        </div>
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.import_keep_open') }}</span>
                    </div>

                    <div class="wb-alert wb-alert-danger wb-mt-3" data-wb-import-error hidden>
                        <div class="wb-alert-title">{{ $adminText('site_transfers.import_failed') }}</div>
                        <div data-wb-import-error-message></div>
                    </div>
                </div>
                <div class="wb-modal-footer" data-wb-import-actions hidden>
                    <button type="button" class="wb-btn wb-btn-primary" data-wb-import-retry>{{ $adminText('site_transfers.resume_import') }}</button>
                    <a href="{{ route('admin.site-transfers.imports.show', $siteImport) }}" class="wb-btn wb-btn-secondary">{{ $adminText('site_transfers.import_close_and_review') }}</a>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($siteImport->isValidated() || $siteImport->isResumable())
@push('scripts')
    <script>
      (function () {
        var form = document.querySelector('[data-wb-import-form]');
        var modal = document.querySelector('[data-wb-import-progress-modal]');

        if (!form || !modal || !window.fetch) { return; }

        var phaseEl = modal.querySelector('[data-wb-import-phase]');
        var counterEl = modal.querySelector('[data-wb-import-counter]');
        var fillEl = modal.querySelector('[data-wb-import-fill]');
        var barEl = modal.querySelector('[data-wb-import-bar]');
        var errorEl = modal.querySelector('[data-wb-import-error]');
        var errorMessageEl = modal.querySelector('[data-wb-import-error-message]');
        var actionsEl = modal.querySelector('[data-wb-import-actions]');
        var retryEl = modal.querySelector('[data-wb-import-retry]');
        var phaseLabels = @json($importPhaseLabels);
        var stepUrl = form.getAttribute('data-wb-step-url');
        var returnUrl = modal.getAttribute('data-wb-return-url');
        var busyLabel = modal.getAttribute('data-wb-busy-label');
        var token = form.querySelector('input[name="_token"]');

        function openModal(trigger) {
          // A live import cannot be cancelled from here; it can only be left,
          // and leaving is safe because every step has committed.
          modal.addEventListener('wb:overlay:close-request', function (event) { event.preventDefault(); });

          if (window.WBModal && typeof window.WBModal.open === 'function') {
            window.WBModal.open(modal, trigger);
          } else {
            modal.hidden = false;
            modal.classList.add('is-open');
          }
        }

        function render(state) {
          var percent = Math.max(0, Math.min(100, state.percent || 0));
          fillEl.style.width = percent + '%';
          phaseEl.textContent = phaseLabels[state.phase] || phaseLabels.starting;
          counterEl.textContent = state.total ? (state.done + ' / ' + state.total + ' (' + percent + '%)') : '';
        }

        function fail(message) {
          barEl.classList.add('wb-progress-bar-danger');
          errorMessageEl.textContent = message || '';
          errorEl.hidden = false;
          actionsEl.hidden = false;
        }

        function finish() {
          barEl.classList.add('wb-progress-bar-success');
          fillEl.style.width = '100%';
          window.location.assign(returnUrl);
        }

        function stepOnce() {
          var body = new FormData(form);

          return fetch(stepUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          }).then(function (response) {
            return response.json().then(function (state) { return { status: response.status, state: state }; });
          });
        }

        function run() {
          errorEl.hidden = true;
          actionsEl.hidden = true;
          barEl.classList.remove('wb-progress-bar-danger');

          stepOnce().then(function (result) {
            var state = result.state || {};

            // 409: another tab holds the step lock. Its work is our work, so
            // wait for it rather than competing.
            if (result.status === 409) {
              phaseEl.textContent = busyLabel;
              setTimeout(run, 2000);
              return;
            }

            render(state);

            if (state.failed) { fail(state.failure_message); return; }
            if (state.finished) { finish(); return; }

            run();
          }).catch(function () {
            // A dropped request loses at most the slice in flight; the cursor
            // on the server is authoritative, so just try the next step.
            setTimeout(run, 3000);
          });
        }

        form.addEventListener('submit', function (event) {
          event.preventDefault();

          var button = form.querySelector('[data-wb-import-submit]');
          if (button) { button.disabled = true; }

          openModal(button);
          run();
        });

        if (retryEl) { retryEl.addEventListener('click', run); }

        void token;
      }());
    </script>
@endpush
@endif

