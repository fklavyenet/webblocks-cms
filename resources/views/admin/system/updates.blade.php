{{--
  The fleet-standard System Updates screen (design v3, owner-approved).
  ONE card, TWO states (up to date / update available); the card body reads
  preflight → state → release notes → Update history (a collapsed accordion at
  its foot, rendered only when runs exist); run logs open in wb-modal via the
  eye action.
  Pressing "Update to X" submits directly (no confirm) and shows a progress
  modal that polls the indicator route until the app answers again.
  WebBlocks UI classes only; CMS-owned $adminText i18n.

  Contract (SystemUpdateController@index / InternalAdminRenderController):
    'report'    => SystemUpdateInspector::report()
    'runs'      => SystemUpdateRunRetention::retainedRuns()
    'checkedAt' => Carbon instance of the last update check
    'preflight' => $report['checks'] (pass|fail entries with badge_class)
--}}
@php
  use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunOutputSanitizer;
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('updates.title'), 'heading' => $adminText('updates.title')])

@section('content')
  @php
    $check = $report['version'] ?? [];
    $installedVersion = $report['installed_version'] ?? ($check['installed_version'] ?? '');
    $runs = $runs ?? collect();
    $preflight = collect($preflight ?? ($report['checks'] ?? []));
    $failingChecks = $preflight->filter(fn ($item) => ($item['status'] ?? '') !== 'pass')->values();
    $canUpdate = ($report['can_update'] ?? false) === true;
    $state = (string) ($check['state'] ?? 'unknown');
    $latestVersion = $check['latest_version'] ?? null;
    $updateAvailable = $state === 'update_available' && ! empty($latestVersion);
    $localNewer = $state === 'up_to_date'
      && is_string($latestVersion) && $latestVersion !== ''
      && version_compare((string) $installedVersion, $latestVersion, '>');
    $stateKey = $localNewer ? 'local_newer' : $state;
    $stateLabel = $adminTranslator->admin('updates.states.'.$stateKey, $adminLocale);
    $stateLabel = $stateLabel === 'admin.updates.states.'.$stateKey ? (string) ($check['label'] ?? $stateKey) : $stateLabel;
    $stateMessage = $adminTranslator->admin('updates.messages.'.$stateKey, $adminLocale);
    $stateMessage = $stateMessage === 'admin.updates.messages.'.$stateKey ? (string) ($check['message'] ?? '') : $stateMessage;
    $calloutTone = $updateAvailable
      ? 'wb-callout-info'
      : (in_array($stateKey, ['up_to_date', 'local_newer'], true) ? 'wb-callout-success' : 'wb-callout-warning');
    $changelog = $check['release']['changelog_entries'] ?? [];
    $currentNotes = $check['release']['release_details'] ?? null;
    $checkedAtText = ($checkedAt ?? null) instanceof \DateTimeInterface ? $checkedAt->format('Y-m-d H:i') : null;
    $sanitizer = app(SystemUpdateRunOutputSanitizer::class);
  @endphp

  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $adminText('updates.title'),
    'description' => $adminText('updates.description'),
  ])

  <div class="wb-stack wb-stack-4" data-webblocks-updates-flash>
    @include('webblocks-cms::admin.partials.flash')
  </div>

  <section class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between">
      <h2 class="wb-card-title">{{ $adminText('updates.title') }}</h2>
      <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">{{ $adminText('updates.check_button') }}</a>
    </div>

    <div class="wb-card-body wb-stack wb-stack-4">
      @if ($failingChecks->isNotEmpty())
        <div class="wb-callout wb-callout-warning wb-stack wb-stack-2" data-webblocks-updates-preflight>
          <strong class="wb-callout-title">{{ $adminText('updates.preflight_title') }}</strong>
          <span class="wb-text-sm">{{ $adminText('updates.preflight_help') }}</span>
          @foreach ($failingChecks as $failingCheck)
            <div class="wb-list-item wb-cluster wb-cluster-2">
              <span class="wb-status-pill {{ $failingCheck['badge_class'] ?? 'wb-status-danger' }}">{{ $failingCheck['status'] ?? 'fail' }}</span>
              <div class="wb-stack wb-stack-1">
                <strong class="wb-text-sm">{{ $failingCheck['label'] ?? '' }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $failingCheck['message'] ?? '' }}</span>
              </div>
            </div>
          @endforeach
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
              <span class="wb-text-sm wb-text-muted">{{ $adminText('updates.available_help') }}</span>
              <h3 class="wb-mt-2">{{ $installedVersion ?: '—' }} → {{ $latestVersion }}</h3>
            </div>
          </div>
          <div class="wb-stack wb-stack-2">
            @if ($canUpdate)
              <form method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
                @csrf
                <button type="submit" class="wb-btn wb-btn-primary" data-wb-update-submit>
                  {{ $adminText('updates.update_button', ['version' => $latestVersion]) }}
                </button>
              </form>
            @endif
            <span class="wb-text-xs wb-text-muted">{{ $adminText('updates.backup_note') }}</span>
            <span class="wb-text-xs wb-text-muted">
              {{ $adminText('updates.server_backup_advisory') }}
              <a href="{{ route('admin.system.backups.index') }}" class="wb-link">{{ $adminText('updates.server_backup_advisory_link') }}</a>
            </span>
          </div>
        </div>

        @if (! empty($changelog))
          <div class="wb-stack wb-stack-2">
            <h3 class="wb-card-title">{{ $adminText('updates.whats_new', ['version' => $installedVersion]) }}</h3>
            <div class="wb-accordion" data-wb-accordion>
              @foreach ($changelog as $entry)
                @php
                  $hasBody = ! empty($entry['groups']) || ! empty($entry['fallback_notes']);
                  $open = $loop->first && $hasBody;
                @endphp
                <div class="wb-accordion-item{{ $open ? ' is-open' : '' }}">
                  @if ($hasBody)
                    <button class="wb-accordion-trigger{{ $open ? ' is-open' : '' }}" type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="wb-release-{{ $loop->iteration }}">
                      <span class="wb-cluster wb-cluster-2">
                        <strong>{{ $entry['version'] }}</strong>
                        @if (! empty($entry['summary']))
                          <span class="wb-text-sm wb-text-muted">{{ $entry['summary'] }}</span>
                        @endif
                      </span>
                      <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                    </button>
                    <div class="wb-accordion-content{{ $open ? ' is-open' : '' }}" id="wb-release-{{ $loop->iteration }}">
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
              <span class="wb-text-sm wb-text-muted">{{ $stateMessage }}</span>
              <span class="wb-text-sm wb-text-muted">
                {{ $adminText('updates.installed_version') }} <strong>{{ $installedVersion ?: '—' }}</strong>@if ($checkedAtText) · {{ $adminText('updates.last_checked') }} <strong>{{ $checkedAtText }}</strong>@endif
              </span>
            </div>
          </div>
        </div>

        @if (! empty($check['error_message']))
          <div class="wb-callout wb-callout-warning">
            <div class="wb-stack wb-stack-1">
              <strong class="wb-callout-title">{{ $adminText('updates.server_detail') }}</strong>
              <span class="wb-text-sm">{{ $check['error_message'] }}</span>
            </div>
          </div>
        @endif

        @if (! empty($currentNotes['has_notes']) && (! empty($currentNotes['groups']) || ! empty($currentNotes['summary'])))
          <div class="wb-accordion" data-wb-accordion>
            <div class="wb-accordion-item">
              <button class="wb-accordion-trigger" type="button" aria-expanded="false" aria-controls="wb-current-notes">
                <span>{{ $adminText('updates.whats_in_this', ['version' => $installedVersion]) }}</span>
                <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
              </button>
              <div class="wb-accordion-content" id="wb-current-notes">
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

      @if ($runs->isNotEmpty())
        <div class="wb-accordion" data-wb-accordion>
          <div class="wb-accordion-item">
            <button class="wb-accordion-trigger" type="button" aria-expanded="false" aria-controls="wb-update-history">
              <span>{{ $adminText('updates.history_title') }} ({{ $runs->count() }})</span>
              <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
            </button>
            <div class="wb-accordion-content" id="wb-update-history">
              <div class="wb-accordion-body">
                <div class="wb-table-wrap wb-mt-3">
                  <table class="wb-table">
                    <thead>
                      <tr>
                        <th>{{ $adminText('updates.history.from') }}</th>
                        <th>{{ $adminText('updates.history.to') }}</th>
                        <th>{{ $adminText('updates.history.status') }}</th>
                        <th>{{ $adminText('updates.history.when') }}</th>
                        <th class="wb-table-actions">{{ $adminText('updates.history.actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($runs as $run)
                        <tr>
                          <td><strong>{{ $run->from_version ?: '—' }}</strong></td>
                          <td><strong>{{ $run->to_version ?: '—' }}</strong></td>
                          <td><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $adminText('updates.statuses.'.$run->status) }}</span></td>
                          <td class="wb-text-sm">{{ optional($run->finished_at)->format('Y-m-d H:i') ?? optional($run->started_at)->format('Y-m-d H:i') ?? '—' }}</td>
                          <td class="wb-table-actions">
                            @if ($run->output)
                              <button type="button" class="wb-icon-btn" data-wb-toggle="modal" data-wb-target="#wb-run-log-{{ $run->id }}" aria-label="{{ $adminText('updates.view_log') }}">
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
  @foreach ($runs as $run)
    @if ($run->output)
      <div id="wb-run-log-{{ $run->id }}" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="wb-run-log-{{ $run->id }}-title">
        <div class="wb-modal-dialog">
          <div class="wb-modal-header">
            <div>
              <h3 class="wb-modal-title" id="wb-run-log-{{ $run->id }}-title">{{ $adminText('updates.log_title') }}</h3>
              <p class="wb-card-description">{{ $run->from_version ?: '—' }} → {{ $run->to_version ?: '—' }} · {{ $adminText('updates.statuses.'.$run->status) }}</p>
            </div>
            <button type="button" class="wb-icon-btn wb-modal-close wb-ms-auto" data-wb-dismiss="modal" aria-label="{{ $adminText('updates.close') }}">
              <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
          </div>
          <div class="wb-modal-body">
            <pre class="wb-text-xs">{{ $sanitizer->sanitize($run->output) }}</pre>
          </div>
        </div>
      </div>
    @endif
  @endforeach

  @if ($updateAvailable && $canUpdate)
    {{-- Progress modal: shown while the update runs. No dismiss — a live update cannot be cancelled. --}}
    <div
      class="wb-modal"
      id="wb-update-progress"
      role="dialog"
      aria-modal="true"
      aria-labelledby="wb-update-progress-title"
      data-webblocks-update-progress-modal
      data-wb-health-url="{{ route('admin.system.updates.indicator') }}"
      data-wb-return-url="{{ route('admin.system.updates.index') }}"
      data-wb-waiting-label="{{ $adminText('updates.waiting_body') }}"
      hidden
    >
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <h3 class="wb-modal-title" id="wb-update-progress-title">{{ $adminText('updates.updating_title', ['version' => $latestVersion]) }}</h3>
        </div>
        <div class="wb-modal-body">
          <div class="wb-loading-inline" role="status" aria-live="polite" aria-atomic="true">
            <span class="wb-spinner-pulse wb-spinner-pulse-lg" aria-hidden="true"><span></span><span></span><span></span></span>
            <div class="wb-stack wb-stack-1">
              <strong id="wb-update-progress-status">{{ $adminText('updates.updating_body') }}</strong>
              <span class="wb-text-sm wb-text-muted">{{ $adminText('updates.keep_open') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@if ($updateAvailable && $canUpdate)
@push('admin-scripts')
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/system-update.js'])
@endpush
@endif
