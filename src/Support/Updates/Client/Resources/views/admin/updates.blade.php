{{--
  The fleet-standard System Updates screen (design v3, owner-approved).
  ONE card, TWO states (up to date / update available); the card body reads
  state → release notes → Update history (a collapsed accordion at its foot,
  rendered only when runs exist); run logs open in wb-modal via the eye action.
  Pressing "Update to X" submits directly and shows a progress modal with a
  spinner (no confirm step). WebBlocks UI classes only.

  Behavior note: opening a modal on form submit is not yet a UI-runtime feature
  (proposed upstream — see standards/publisher-client-backlog.md); the small
  script below is the shared, single-source interim until that lands.

  Contract (products @include this inside their own layout):
    'check'       => UpdateCheckResult->toArray()
    'runs'        => RunRecorder::all()          (optional)
    'checkRoute'  => POST url for re-check
    'updateRoute' => POST url for the update
--}}
@php
    $runs = $runs ?? [];
    $checkRoute = $checkRoute ?? '#';
    $updateRoute = $updateRoute ?? '#';
    // Optional lightweight GET endpoint the interstitial polls to detect when the
    // app is back after a self-update restarts php-fpm. Defaults to the current
    // page; a product may pass its JSON indicator route (which does not consume the
    // flash) so the reloaded screen can still show the success/failure message.
    $healthRoute = $healthRoute ?? null;
    // Optional pre-run advisory check (ComposerRepoPreflight->check()); absent
    // on product controllers that have not adopted it yet.
    $preflight = $preflight ?? null;
    $preflightIssues = ($preflight['relevant'] ?? false) && ! ($preflight['ok'] ?? true) ? ($preflight['issues'] ?? []) : [];
    $updateAvailable = ($check['state'] ?? null) === 'update_available' && ! empty($check['latest_version']);
    $stateKey = 'publisher-client::updates.states.'.($check['state'] ?? 'unknown');
    $stateLabel = trans()->has($stateKey) ? __($stateKey) : ($check['label'] ?? '');
    $calloutTone = $updateAvailable
        ? 'wb-callout-info'
        : (in_array($check['state'] ?? '', ['up_to_date', 'local_newer'], true) ? 'wb-callout-success' : 'wb-callout-warning');
    $changelog = $check['release']['changelog_entries'] ?? [];
    $currentNotes = $check['release']['release_details'] ?? null;
    $checkedAt = $check['checked_at'] ?? null;
    $checkedAtText = $checkedAt instanceof \DateTimeInterface ? $checkedAt->format('Y-m-d H:i') : (is_string($checkedAt) ? $checkedAt : null);
    // Run-status → wb-status-pill tone. Covers the RunStatus vocabulary; anything
    // unknown falls through to the error tone.
    $statusTones = [
        'success' => 'wb-status-active',
        'success_with_warnings' => 'wb-status-pending',
        'restored' => 'wb-status-pending',
        'running' => 'wb-status-pending',
        'failed' => 'wb-status-error',
    ];
@endphp

<section class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between">
        <h2 class="wb-card-title">{{ __('publisher-client::updates.title') }}</h2>
        <form method="POST" action="{{ $checkRoute }}">
            @csrf
            <button type="submit" class="wb-btn wb-btn-secondary">{{ __('publisher-client::updates.check_button') }}</button>
        </form>
    </div>

    <div class="wb-card-body wb-stack wb-stack-4">
        {{-- Filled by the update script when the run is rejected or fails with a
             direct response (e.g. "already running") — the silent-redirect fix. --}}
        <div class="wb-callout wb-callout-error" data-pc-inline-error hidden>
            <div class="wb-stack wb-stack-1">
                <strong class="wb-callout-title">{{ __('publisher-client::updates.error_title') }}</strong>
                <span class="wb-text-sm" data-pc-inline-error-text></span>
            </div>
        </div>

        @if (! empty($preflightIssues))
            <div class="wb-callout wb-callout-warning">
                <div class="wb-stack wb-stack-1">
                    <strong class="wb-callout-title">{{ __('publisher-client::updates.preflight.title') }}</strong>
                    @foreach ($preflightIssues as $issue)
                        <span class="wb-text-sm">
                            {{ __('publisher-client::updates.preflight.'.($issue['key'] ?? 'unreachable'), ['url' => $issue['url'] ?? '']) }}
                            @if (! empty($issue['detail']))
                                <span class="wb-text-muted">({{ $issue['detail'] }})</span>
                            @endif
                        </span>
                    @endforeach
                    <span class="wb-text-sm wb-text-muted">{{ __('publisher-client::updates.preflight.help') }}</span>
                </div>
            </div>
        @endif

        @if ($updateAvailable)
            <div class="wb-callout {{ $calloutTone }} wb-cluster wb-cluster-4 wb-cluster-between">
                <div class="wb-cluster wb-cluster-4">
                    <span class="wb-avatar wb-avatar-lg wb-avatar-info" aria-hidden="true">
                        <i class="wb-icon wb-icon-lg wb-icon-arrow-up-circle" aria-hidden="true"></i>
                    </span>
                    <div class="wb-stack wb-stack-1">
                        <strong class="wb-callout-title">{{ $stateLabel }}</strong>
                        <span class="wb-text-sm wb-text-muted">{{ __('publisher-client::updates.available_help') }}</span>
                        <h3 class="wb-mt-2">{{ $check['installed_version'] ?? '—' }} → {{ $check['latest_version'] }}</h3>
                    </div>
                </div>
                <div class="wb-stack wb-stack-2">
                    <form method="POST" action="{{ $updateRoute }}" data-pc-update-form>
                        @csrf
                        <button type="submit" class="wb-btn wb-btn-primary">
                            {{ __('publisher-client::updates.update_button', ['version' => $check['latest_version']]) }}
                        </button>
                    </form>
                    <span class="wb-text-xs wb-text-muted">{{ __('publisher-client::updates.backup_note') }}</span>
                    <span class="wb-text-xs wb-text-muted">{{ __('publisher-client::updates.server_backup_advice') }}</span>
                </div>
            </div>

            @if (! empty($changelog))
                <div class="wb-stack wb-stack-2">
                    <h3 class="wb-card-title">{{ __('publisher-client::updates.whats_new', ['version' => $check['installed_version'] ?? '']) }}</h3>
                    <div class="wb-accordion" data-wb-accordion>
                        @foreach ($changelog as $entry)
                            @php
                                $hasBody = ! empty($entry['groups']) || ! empty($entry['fallback_notes']);
                                $open = $loop->first && $hasBody;
                            @endphp
                            <div class="wb-accordion-item{{ $open ? ' is-open' : '' }}">
                                @if ($hasBody)
                                    <button class="wb-accordion-trigger{{ $open ? ' is-open' : '' }}" type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="pc-release-{{ $loop->iteration }}">
                                        <span class="wb-cluster wb-cluster-2">
                                            <strong>{{ $entry['version'] }}</strong>
                                            @if (! empty($entry['summary']))
                                                <span class="wb-text-sm wb-text-muted">{{ $entry['summary'] }}</span>
                                            @endif
                                        </span>
                                        <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                                    </button>
                                    <div class="wb-accordion-content{{ $open ? ' is-open' : '' }}" id="pc-release-{{ $loop->iteration }}">
                                        <div class="wb-accordion-body wb-stack wb-stack-2 wb-mt-3">
                                            @foreach (($entry['groups'] ?? []) as $group)
                                                <h4 class="wb-text-sm"><strong>{{ $group['label'] }}</strong></h4>
                                                <ul class="wb-text-sm">
                                                    @foreach ($group['items'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            @endforeach
                                            @foreach (($entry['fallback_notes'] ?? []) as $note)
                                                <p class="wb-text-sm">{{ $note }}</p>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    {{-- Version-only entry: a plain padded row, not a clickable trigger. --}}
                                    <div class="wb-list-item wb-cluster wb-cluster-2">
                                        <strong>{{ $entry['version'] }}</strong>
                                        @if (! empty($entry['summary']))
                                            <span class="wb-text-sm wb-text-muted">{{ $entry['summary'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif (! empty($check['release']['changelog']))
                <p class="wb-text-sm">{{ $check['release']['changelog'] }}</p>
            @endif
        @else
            @php
                $stateIcon = $calloutTone === 'wb-callout-success' ? 'wb-icon-check' : 'wb-icon-triangle-alert';
                $avatarTone = $calloutTone === 'wb-callout-success' ? 'wb-avatar-success' : 'wb-avatar-warning';
            @endphp
            <div class="wb-callout {{ $calloutTone }}">
                <div class="wb-cluster wb-cluster-4">
                    <span class="wb-avatar wb-avatar-lg {{ $avatarTone }}" aria-hidden="true">
                        <i class="wb-icon wb-icon-lg {{ $stateIcon }}" aria-hidden="true"></i>
                    </span>
                    <div class="wb-stack wb-stack-1">
                        <strong class="wb-callout-title">{{ $stateLabel }}</strong>
                        <span class="wb-text-sm wb-text-muted">{{ $check['message'] ?? '' }}</span>
                        <span class="wb-text-sm wb-text-muted">
                            {{ __('publisher-client::updates.installed_version') }} <strong>{{ $check['installed_version'] ?? '—' }}</strong>@if ($checkedAtText) · {{ __('publisher-client::updates.last_checked') }} <strong>{{ $checkedAtText }}</strong>@endif
                        </span>
                    </div>
                </div>
            </div>

            @if (! empty($currentNotes['has_notes']) && (! empty($currentNotes['groups']) || ! empty($currentNotes['summary'])))
                <div class="wb-accordion" data-wb-accordion>
                    <div class="wb-accordion-item">
                        <button class="wb-accordion-trigger" type="button" aria-expanded="false" aria-controls="pc-current-notes">
                            <span>{{ __('publisher-client::updates.whats_in_this', ['version' => $check['installed_version'] ?? '']) }}</span>
                            <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                        </button>
                        <div class="wb-accordion-content" id="pc-current-notes">
                            <div class="wb-accordion-body wb-stack wb-stack-2 wb-mt-3">
                                @if (! empty($currentNotes['summary']))
                                    <p class="wb-text-sm">{{ $currentNotes['summary'] }}</p>
                                @endif
                                @foreach (($currentNotes['groups'] ?? []) as $group)
                                    <h4 class="wb-text-sm"><strong>{{ $group['label'] }}</strong></h4>
                                    <ul class="wb-text-sm">
                                        @foreach ($group['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if (! empty($runs))
            <div class="wb-accordion" data-wb-accordion>
                <div class="wb-accordion-item">
                    <button class="wb-accordion-trigger" type="button" aria-expanded="false" aria-controls="pc-update-history">
                        <span>{{ __('publisher-client::updates.history_title') }} ({{ count($runs) }})</span>
                        <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                    </button>
                    <div class="wb-accordion-content" id="pc-update-history">
                        <div class="wb-accordion-body">
                            <div class="wb-table-wrap wb-mt-3">
                                <table class="wb-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('publisher-client::updates.history.from') }}</th>
                                            <th>{{ __('publisher-client::updates.history.to') }}</th>
                                            <th>{{ __('publisher-client::updates.history.status') }}</th>
                                            <th>{{ __('publisher-client::updates.history.when') }}</th>
                                            <th class="wb-table-actions">{{ __('publisher-client::updates.history.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($runs as $run)
                                            <tr>
                                                <td><strong>{{ $run['from_version'] ?? '—' }}</strong></td>
                                                <td><strong>{{ $run['to_version'] ?? '—' }}</strong></td>
                                                <td><span class="wb-status-pill {{ $statusTones[$run['status'] ?? 'failed'] ?? 'wb-status-error' }}">{{ __('publisher-client::updates.history.statuses.'.($run['status'] ?? 'failed')) }}</span></td>
                                                <td class="wb-text-sm">{{ $run['finished_at'] ?? '—' }}</td>
                                                <td class="wb-table-actions">
                                                    @if (! empty($run['output']))
                                                        <button type="button" class="wb-icon-btn" data-wb-toggle="modal" data-wb-target="#pc-run-log-{{ $loop->iteration }}" aria-label="{{ __('publisher-client::updates.view_log') }}">
                                                            <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

{{-- Run logs for the history rows above; modals live outside the card. --}}
@if (! empty($runs))
    @foreach ($runs as $run)
        @if (! empty($run['output']))
            <div id="pc-run-log-{{ $loop->iteration }}" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="pc-run-log-{{ $loop->iteration }}-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div>
                            <h3 class="wb-modal-title" id="pc-run-log-{{ $loop->iteration }}-title">{{ __('publisher-client::updates.log_title') }}</h3>
                            <p class="wb-card-description">{{ $run['from_version'] ?? '—' }} → {{ $run['to_version'] ?? '—' }} · {{ __('publisher-client::updates.history.statuses.'.($run['status'] ?? 'failed')) }}</p>
                        </div>
                        <button type="button" class="wb-icon-btn wb-modal-close wb-ms-auto" data-wb-dismiss="modal" aria-label="{{ __('publisher-client::updates.close') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="wb-modal-body">
                        <pre class="wb-text-xs">{{ $run['output'] }}</pre>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif

@if ($updateAvailable)
    {{-- Progress modal: shown while the update runs. No dismiss — a live update cannot be cancelled. --}}
    <div id="pc-update-progress" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="pc-update-progress-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h3 class="wb-modal-title" id="pc-update-progress-title">{{ __('publisher-client::updates.updating_title', ['version' => $check['latest_version']]) }}</h3>
            </div>
            <div class="wb-modal-body">
                <div class="wb-loading-inline" role="status" aria-live="polite" aria-atomic="true">
                    <span class="wb-spinner-pulse wb-spinner-pulse-lg" aria-hidden="true"><span></span><span></span><span></span></span>
                    <div class="wb-stack wb-stack-1">
                        <strong id="pc-update-progress-status">{{ __('publisher-client::updates.updating_body') }}</strong>
                        <span class="wb-text-sm wb-text-muted">{{ __('publisher-client::updates.keep_open') }}</span>
                    </div>
                </div>
            </div>
            {{-- Invisible dismiss control the script clicks when a rejection/
                 failure comes back directly and the overlay must close. --}}
            <button type="button" hidden id="pc-update-progress-dismiss" data-wb-dismiss="modal" aria-hidden="true" tabindex="-1"></button>
        </div>
    </div>
    <button type="button" hidden id="pc-update-progress-open"
            data-wb-toggle="modal" data-wb-target="#pc-update-progress"
            data-pc-health-url="{{ $healthRoute ?? '' }}"
            data-pc-waiting-label="{{ __('publisher-client::updates.waiting_body') }}"
            data-pc-error-label="{{ __('publisher-client::updates.update_failed_generic') }}"
            aria-hidden="true" tabindex="-1"></button>
    <script>
        (function () {
            var form = document.querySelector('[data-pc-update-form]');
            var opener = document.getElementById('pc-update-progress-open');
            if (!form || !opener) { return; }

            // No fetch (very old browser): fall back to a plain submit + spinner.
            if (!window.fetch) {
                form.addEventListener('submit', function () { opener.click(); });
                return;
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                opener.click(); // show the progress overlay

                var action = form.getAttribute('action');
                var body = new FormData(form);
                var here = window.location.href;
                var health = opener.getAttribute('data-pc-health-url') || here;
                var status = document.getElementById('pc-update-progress-status');
                var waiting = opener.getAttribute('data-pc-waiting-label');
                var started = Date.now();
                var done = false;

                function goHome() { if (done) return; done = true; window.location.assign(here); }

                // A successful full-root self-update swaps the app's own code and
                // restarts php-fpm, so the request that triggers it (and the next few)
                // can drop with a 502 while fresh workers spin up. Fire the update,
                // then poll a lightweight endpoint until the app answers again, and
                // only then navigate — the reload window stays hidden behind the
                // overlay instead of surfacing as a 502.
                function poll() {
                    if (done) { return; }
                    if (Date.now() - started > 180000) { goHome(); return; } // 3-min safety net
                    fetch(health, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
                        .then(function (res) {
                            if (res && res.status >= 200 && res.status < 500) { goHome(); }
                            else { setTimeout(poll, 1500); }
                        })
                        .catch(function () { setTimeout(poll, 1500); });
                }

                // The submit asks for JSON so a direct rejection ("already
                // running", disabled, a failed run that answered normally)
                // comes back as a readable 409 instead of a redirect whose
                // flash the health-poll used to consume silently.
                function showError(message) {
                    done = true; // never navigate away from the visible error
                    var dismiss = document.getElementById('pc-update-progress-dismiss');
                    if (dismiss) { dismiss.click(); }
                    var box = document.querySelector('[data-pc-inline-error]');
                    if (!box) { window.location.assign(here); return; }
                    var text = box.querySelector('[data-pc-inline-error-text]');
                    if (text) { text.textContent = message || opener.getAttribute('data-pc-error-label') || ''; }
                    box.hidden = false;
                    if (box.scrollIntoView) { box.scrollIntoView({ block: 'nearest' }); }
                }

                function proceed() {
                    if (waiting && status) { status.textContent = waiting; }
                    poll();
                }

                fetch(action, {
                    method: 'POST',
                    body: body,
                    redirect: 'manual',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) {
                        if (res && res.status === 409) {
                            res.json().then(
                                function (data) { showError(data && data.message); },
                                function () { showError(null); }
                            );
                            return;
                        }
                        proceed();
                    }, proceed);
            });
        }());
    </script>
@endif
