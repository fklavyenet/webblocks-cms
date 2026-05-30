@extends('webblocks-cms::layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
  @php
    $updateStatus = $report['version'];
    $diagnostics = $report['diagnostics'];
    $environment = $report['environment'];
    $release = $updateStatus['release'] ?? null;
    $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
    $storedInstalledVersion = $report['stored_installed_version'] ?? null;
    $pendingUpdate = $pendingUpdate ?? null;
    $pendingBackup = $pendingBackup ?? null;
    $runs = $updateRuns ?? collect();
    $historyRows = method_exists($runs, 'getCollection') ? $runs->getCollection() : collect($runs);
    $latestUpdateRun = $latestUpdateRun ?? $historyRows->first();
    $historyPage = method_exists($runs, 'currentPage') ? $runs->currentPage() : max(1, (int) request('history_page', 1));
    $autoUpdate = $report['auto_update'] ?? ['allowed' => false, 'blockers' => [], 'busy' => false];
    $compatibilityStatus = $updateStatus['compatibility']['status'] ?? 'unknown';
    $showLatestVersion = ($updateStatus['latest_version'] ?? null) !== null
      && (string) $installedVersion !== (string) $updateStatus['latest_version'];
    $currentCodeIsNewerThanPublished = is_string($updateStatus['latest_version'] ?? null)
      && version_compare((string) $installedVersion, (string) $updateStatus['latest_version'], '>');
    $publishedAt = $release['published_at'] ?? null;
    $latestPublishedAt = $publishedAt
      ? \Carbon\Carbon::parse($publishedAt)->format('Y-m-d H:i')
      : null;
    $currentVersionDate = $latestUpdateRun?->status === \WebBlocks\Cms\Models\SystemUpdateRun::STATUS_SUCCESS
      ? $latestUpdateRun->finished_at
      : null;
    $compatibilityBadgeClass = match ($compatibilityStatus) {
      'compatible' => 'wb-status-active',
      'incompatible' => 'wb-status-danger',
      default => 'wb-status-pending',
    };
    $state = (string) ($updateStatus['state'] ?? 'unknown');
    $summaryTitle = match ($state) {
      'update_available' => 'Update available',
      'incompatible' => 'Incompatible update available',
      'up_to_date' => $currentCodeIsNewerThanPublished ? 'Local/source version is newer' : 'Up to date',
      'server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled' => 'Update server unavailable / invalid response',
      default => $updateStatus['label'] ?? 'Update status',
    };
    $summaryMessage = match ($state) {
      'update_available' => 'A newer published release is available from the configured update server.',
      'incompatible' => 'A newer release is available, but this install is not compatible yet.',
      'up_to_date' => $currentCodeIsNewerThanPublished
        ? 'This codebase is ahead of the latest published package on the selected channel.'
        : 'This install already matches the latest published release for the selected channel.',
      'server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled' => 'The CMS could not complete a trusted update check. Review diagnostics below.',
      default => $updateStatus['message'] ?? 'Review the current update status below.',
    };

    $releaseDetails = $release['release_details'] ?? null;

    if (! is_array($releaseDetails)) {
      $fallbackText = trim((string) (($release['description'] ?? '') ?: ($release['changelog'] ?? '')));
      $fallbackNotes = collect(preg_split('/\r\n|\r|\n/', $fallbackText ?: ''))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
      $releaseDetails = [
        'title' => null,
        'summary' => $fallbackNotes->shift(),
        'groups' => [],
        'fallback_notes' => $fallbackNotes->all(),
        'has_notes' => $fallbackText !== '',
      ];
    }

    $releaseDetailGroups = collect($releaseDetails['groups'] ?? [])
      ->filter(fn ($group) => is_array($group) && collect($group['items'] ?? [])->filter()->isNotEmpty())
      ->values();
    $fallbackReleaseNotes = collect($releaseDetails['fallback_notes'] ?? [])->filter()->values();
    $hasReleaseDetails = (bool) ($releaseDetails['has_notes'] ?? false)
      || trim((string) ($releaseDetails['title'] ?? '')) !== ''
      || trim((string) ($releaseDetails['summary'] ?? '')) !== ''
      || $releaseDetailGroups->isNotEmpty()
      || $fallbackReleaseNotes->isNotEmpty();
    $showUpdateAction = ($pendingUpdate && $pendingBackup)
      || ($autoUpdate['allowed'] ?? false) === true;
    $diagnosticItems = collect($diagnostics)->prepend([
      'label' => 'Compatibility',
      'status' => $compatibilityStatus,
      'message' => ($updateStatus['compatibility']['reasons'] ?? []) === []
        ? 'No compatibility issues reported.'
        : implode(' ', $updateStatus['compatibility']['reasons']),
      'badge_class' => $compatibilityBadgeClass,
    ]);
    $diagnosticsNeedAttention = $diagnosticItems->contains(fn ($item) => ! in_array((string) ($item['status'] ?? ''), ['ok', 'pass', 'compatible'], true))
      || ! empty($updateStatus['error_message']);
  @endphp

  @include('webblocks-cms::admin.partials.page-header', [
    'title' => 'System Updates',
    'description' => 'Check the update server, apply validated WebBlocks CMS package updates, and review recent package update runs.',
  ])

  @include('webblocks-cms::admin.partials.flash')

  <div class="wb-stack wb-stack-4" data-webblocks-updates-layout="single-column">
    <section class="wb-card" data-webblocks-updates-card="summary">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">Update Summary</h2>
          <p class="wb-card-description">{{ $summaryMessage }}</p>
        </div>
        <div class="wb-action-group">
          <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $summaryTitle }}</span>
          <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">Check again</a>
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        @if ($updateStatus['error_message'])
          <div class="wb-alert wb-alert-warning">
            <div>
              <div class="wb-alert-title">Server detail</div>
              <div>{{ $updateStatus['error_message'] }}</div>
            </div>
          </div>
        @endif

        <div class="wb-meta-grid">
          <div>
            <span class="wb-meta-label">Current CMS Version</span>
            <strong>{{ $installedVersion }}</strong>
            <br><span class="wb-text-muted wb-text-sm">Update Date: {{ $currentVersionDate?->format('Y-m-d H:i') ?? 'Not available' }}</span>
          </div>

          @if ($showLatestVersion)
            <div>
              <span class="wb-meta-label">Latest Published Version</span>
              <strong>{{ $updateStatus['latest_version'] }}</strong>
              @if ($latestPublishedAt)
                <br><span class="wb-text-muted wb-text-sm">Published Date: {{ $latestPublishedAt }}</span>
              @endif
            </div>
          @endif
        </div>

        @if ($release)
          <div class="wb-accordion" data-wb-accordion data-webblocks-updates-accordion="release-notes">
            <div class="wb-accordion-item">
              <button
                class="wb-accordion-trigger"
                type="button"
                data-wb-accordion-trigger
                aria-expanded="false"
                aria-controls="webblocks-update-release-notes"
              >
                <span>Release notes</span>
                <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
              </button>
              <div class="wb-accordion-content" id="webblocks-update-release-notes">
                <div class="wb-accordion-body wb-stack wb-stack-3">
                  @if ($hasReleaseDetails)
                    @if (trim((string) ($releaseDetails['title'] ?? '')) !== '')
                      <strong>{{ $releaseDetails['title'] }}</strong>
                    @endif

                    @if (trim((string) ($releaseDetails['summary'] ?? '')) !== '')
                      <p>{{ $releaseDetails['summary'] }}</p>
                    @endif

                    @foreach ($releaseDetailGroups as $group)
                      <div class="wb-stack wb-gap-1">
                        <strong class="wb-text-sm">{{ in_array(($group['key'] ?? ''), ['fixes', 'changes'], true) ? 'Fixes and changes' : ($group['label'] ?? 'Release details') }}</strong>
                        <ul class="wb-m-0 wb-text-sm wb-text-muted">
                          @foreach (collect($group['items'] ?? [])->filter() as $item)
                            <li>{{ $item }}</li>
                          @endforeach
                        </ul>
                      </div>
                    @endforeach

                    @if ($fallbackReleaseNotes->isNotEmpty())
                      <ul class="wb-m-0 wb-text-sm wb-text-muted">
                        @foreach ($fallbackReleaseNotes as $note)
                          <li>{{ $note }}</li>
                        @endforeach
                      </ul>
                    @endif
                  @else
                    <p class="wb-text-muted">No release notes were provided for this release.</p>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endif
      </div>
    </section>

    <section class="wb-card" data-webblocks-updates-card="install">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">Install Update</h2>
          <p class="wb-card-description">Updates require SHA-256 checksum verification, staging, package boundary validation, and backup/restore protection before install.</p>
        </div>
        <div class="wb-action-group">
          @if ($pendingUpdate && $pendingBackup)
            <form method="POST" action="{{ route('admin.system.updates.continue') }}" data-wb-update-form>
              @csrf
              <button type="submit" class="wb-btn wb-btn-primary" data-wb-update-submit data-default-label="Continue update" data-busy-label="Updating...">Continue update</button>
            </form>
          @elseif ($showUpdateAction)
            <button
              type="submit"
              form="webblocks-update-install-form"
              class="wb-btn wb-btn-primary"
              data-wb-update-submit
              data-default-label="Install update"
              data-busy-label="Updating..."
            >
              {{ $autoUpdate['busy'] ? 'Updating...' : 'Install update' }}
            </button>
          @else
            <button
              type="button"
              class="wb-btn wb-btn-secondary"
              disabled
            >
              Install update
            </button>
          @endif
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        @if ($pendingUpdate && $pendingBackup)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">Backup protection</div>
              <div>A pre-update backup was created automatically. Download it before continuing the installation.</div>
            </div>
          </div>

          <div class="wb-meta-grid">
            <div>
              <span class="wb-meta-label">Backup name</span>
              <strong>{{ $pendingBackup->archive_filename ?? ('Backup #'.$pendingBackup->id) }}</strong>
            </div>

            <div>
              <span class="wb-meta-label">Backup size</span>
              <strong>{{ $pendingBackup->humanArchiveSize() }}</strong>
            </div>
          </div>

          <div class="wb-action-group">
            <a href="{{ route('admin.system.backups.download', $pendingBackup) }}" class="wb-btn wb-btn-secondary">Download backup</a>

            <form method="POST" action="{{ route('admin.system.updates.cancel') }}">
              @csrf
              <button
                type="submit"
                class="wb-btn wb-btn-secondary"
              >
                Cancel
              </button>
            </form>
          </div>
        @elseif ($showUpdateAction)
          <div class="wb-stack wb-gap-2">
            <strong>Ready to install</strong>
            <div class="wb-text-sm wb-text-muted">A pre-update backup will be created automatically before installation. The CMS will download and verify the package, apply it, run required update migrations, clear runtime caches, record the run, and persist the installed version. Catalog repair is available as a separate maintenance command.</div>
          </div>

          <form id="webblocks-update-install-form" method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
            @csrf

            <label class="wb-checkbox">
              <input type="checkbox" name="download_pre_update_backup" value="1" @checked(old('download_pre_update_backup'))>
              <span>Download the backup before installation starts</span>
            </label>
          </form>
        @else
          <div class="wb-alert wb-alert-info">
            <strong>Install update is currently unavailable.</strong>
            <p>{{ $autoUpdate['blockers'][0] ?? 'No newer release is ready for this install.' }}</p>
          </div>
        @endif

        <div class="wb-accordion" data-wb-accordion data-webblocks-updates-accordion="package-safety">
          <div class="wb-accordion-item">
            <button
              class="wb-accordion-trigger"
              type="button"
              data-wb-accordion-trigger
              aria-expanded="false"
              aria-controls="webblocks-update-package-safety"
            >
              <span>Package safety details</span>
              <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
            </button>
            <div class="wb-accordion-content" id="webblocks-update-package-safety">
              <div class="wb-accordion-body wb-stack wb-stack-4">
                <p class="wb-card-description">System Updates apply published release packages only. Catalog repair, core catalog seeding, block type sync, slot repair, page layout repair, and icon repair stay separate maintenance workflows.</p>
                <div class="wb-code-block">SHA-256 checksum verification
Package boundary validation
Backup and staging protection
Required update migrations only
Cache clears, update run recording, and installed version persistence</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="wb-card" data-webblocks-updates-card="diagnostics">
      <div class="wb-accordion wb-accordion-flush" data-wb-accordion data-webblocks-updates-accordion="diagnostics">
        <div class="wb-accordion-item @if ($diagnosticsNeedAttention) is-open @endif">
          <button
            class="wb-accordion-trigger @if ($diagnosticsNeedAttention) is-open @endif"
            type="button"
            data-wb-accordion-trigger
            aria-expanded="{{ $diagnosticsNeedAttention ? 'true' : 'false' }}"
            aria-controls="webblocks-update-diagnostics"
          >
            <span>
              <span class="wb-card-title">Diagnostics</span>
              <span class="wb-card-description">{{ $diagnosticsNeedAttention ? 'Update readiness checks need attention before a package update can run.' : 'Diagnostics passed. Details stay collapsed until attention is needed.' }}</span>
            </span>
            <span class="wb-action-group">
              <span class="wb-status-pill {{ $diagnosticsNeedAttention ? 'wb-status-danger' : 'wb-status-active' }}">{{ $diagnosticsNeedAttention ? 'Needs attention' : 'Diagnostics passed' }}</span>
              <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
            </span>
          </button>

          <div class="wb-accordion-content @if ($diagnosticsNeedAttention) is-open @endif" id="webblocks-update-diagnostics">
            <div class="wb-accordion-body wb-stack wb-stack-4">
              <div class="wb-meta-grid">
                <div>
                  <span class="wb-meta-label">Compatibility</span>
                  <strong>{{ $compatibilityStatus }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Stored installed version</span>
                  <strong>{{ is_string($storedInstalledVersion) && $storedInstalledVersion !== '' ? $storedInstalledVersion : 'N/A' }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Update server</span>
                  <strong>{{ $updateStatus['server_url'] ?: 'not configured' }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Product</span>
                  <strong>{{ $updateStatus['product'] ?? 'webblocks-cms' }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Channel</span>
                  <strong>{{ $updateStatus['channel'] ?? 'stable' }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Last checked</span>
                  <strong>{{ optional($checkedAt ?? null)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Runtime</span>
                  <strong>PHP {{ $environment['php_version'] }} | Laravel {{ $environment['laravel_version'] }}</strong>
                </div>
                <div>
                  <span class="wb-meta-label">Site URL</span>
                  <strong>{{ $environment['site_url'] }}</strong>
                </div>
                @if ($release)
                  <div>
                    <span class="wb-meta-label">Package URL</span>
                    <strong>{{ $release['download_url'] ?? 'N/A' }}</strong>
                  </div>
                  <div>
                    <span class="wb-meta-label">SHA-256</span>
                    <strong>{{ $release['checksum_sha256'] ?? 'N/A' }}</strong>
                  </div>
                @endif
              </div>

              <div class="wb-callout wb-callout-muted">
                <strong>Catalog repair remains separate</strong>
                <p>Catalog repair is available as a separate maintenance command and is not part of automatic System Updates.</p>
              </div>

              <div class="wb-table-wrap">
                <table class="wb-table">
                  <caption>View diagnostics</caption>
                  <thead>
                    <tr>
                      <th>Check</th>
                      <th>Status</th>
                      <th>Message</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($diagnosticItems as $diagnostic)
                      <tr>
                        <td>{{ $diagnostic['label'] }}</td>
                        <td><span class="wb-status-pill {{ $diagnostic['badge_class'] }}">{{ $diagnostic['status'] }}</span></td>
                        <td>{{ $diagnostic['message'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="wb-card" data-webblocks-updates-card="history">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">Update History</h2>
          <p class="wb-card-description">Recent WebBlocks CMS package update run records.</p>
        </div>
        <div class="wb-action-group">
          @if ($latestUpdateRun)
            <span class="wb-status-pill {{ $latestUpdateRun->statusBadgeClass() }}">{{ $latestUpdateRun->statusLabel() }}</span>
          @endif
          <form method="GET" action="{{ route('admin.system.updates.index') }}" class="wb-action-group" data-webblocks-update-history-per-page>
            @foreach (request()->except(['history_page', 'history_per_page']) as $name => $value)
              @if (is_scalar($value) && $value !== null && $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
              @endif
            @endforeach
            <label for="webblocks-update-history-per-page" class="wb-text-sm wb-text-muted">Per page</label>
            <select id="webblocks-update-history-per-page" name="history_per_page" class="wb-select" onchange="this.form.submit()">
              @foreach ($historyPerPageOptions as $perPageOption)
                <option value="{{ $perPageOption }}" @selected((int) $historyPerPage === (int) $perPageOption)>{{ $perPageOption }}</option>
              @endforeach
            </select>
          </form>
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        @if ($latestUpdateRun && $latestUpdateRun->status === \WebBlocks\Cms\Models\SystemUpdateRun::STATUS_FAILED)
          <div class="wb-callout wb-callout-muted">
            <strong>Previous update attempt failed.</strong>
            <p>This previous attempt does not prevent checking or applying a newer compatible update.</p>
          </div>
        @endif

        @if ($runs->isEmpty())
          <p class="wb-text-muted">No update runs have been recorded yet.</p>
        @else
          <div class="wb-table-wrap">
            <table class="wb-table">
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Started</th>
                  <th>Duration</th>
                  <th>Triggered by</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($runs as $run)
                  @php($detailsModalId = 'updateRunDetailsModal-'.$run->id)
                  @php($deleteModalId = 'updateRunDeleteModal-'.$run->id)
                  @php($runIsInProgress = in_array($run->status, [\WebBlocks\Cms\Models\SystemUpdateRun::STATUS_PENDING, \WebBlocks\Cms\Models\SystemUpdateRun::STATUS_RUNNING], true))
                  <tr>
                    <td>{{ $run->from_version && $run->to_version ? $run->from_version.' → '.$run->to_version : ($run->to_version ?: 'N/A') }}</td>
                    <td><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span></td>
                    <td class="wb-nowrap">{{ optional($run->started_at)->format('Y-m-d H:i') ?? optional($run->created_at)->format('Y-m-d H:i') ?? 'Not available' }}</td>
                    <td>{{ $run->durationLabel() }}</td>
                    <td title="{{ $run->triggeredBy?->email ?? $run->triggeredBy?->name ?? 'System' }}"><span class="wb-text-sm">{{ $run->triggeredBy?->email ?? $run->triggeredBy?->name ?? 'System' }}</span></td>
                    <td class="wb-table-actions">
                      <div class="wb-action-group">
                        <button
                          class="wb-btn wb-btn-secondary"
                          type="button"
                          data-wb-toggle="modal"
                          data-wb-target="#{{ $detailsModalId }}"
                          aria-controls="{{ $detailsModalId }}"
                          aria-expanded="false"
                          aria-label="View update run details for {{ $run->from_version ?: 'unknown' }} to {{ $run->to_version ?: 'unknown' }}"
                          title="View details"
                        >
                          <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                        </button>
                        @if ($runIsInProgress)
                          <button
                            class="wb-btn wb-btn-danger"
                            type="button"
                            disabled
                            aria-label="Delete is unavailable for update runs still in progress"
                            title="Delete is unavailable while the update run is in progress"
                          >
                            <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                          </button>
                        @else
                          <button
                            class="wb-btn wb-btn-danger"
                            type="button"
                            data-wb-toggle="modal"
                            data-wb-target="#{{ $deleteModalId }}"
                            aria-controls="{{ $deleteModalId }}"
                            aria-expanded="false"
                            aria-label="Delete update history entry for {{ $run->from_version ?: 'unknown' }} to {{ $run->to_version ?: 'unknown' }}"
                            title="Delete history entry"
                          >
                            <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                          </button>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @foreach ($runs as $run)
            @php($detailsModalId = 'updateRunDetailsModal-'.$run->id)
            @php($detailsModalTitleId = $detailsModalId.'Title')
            <div class="wb-modal" id="{{ $detailsModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $detailsModalTitleId }}">
              <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                  <div>
                    <h3 class="wb-modal-title" id="{{ $detailsModalTitleId }}">Update run details</h3>
                    <p class="wb-card-description">{{ $run->from_version ?: 'N/A' }} → {{ $run->to_version ?: 'N/A' }} / {{ $run->statusLabel() }}</p>
                  </div>
                  <button
                    class="wb-modal-close"
                    type="button"
                    data-wb-dismiss="modal"
                    aria-label="Close update run details"
                    title="Close"
                  >&times;</button>
                </div>

                <div class="wb-modal-body wb-stack wb-stack-4">
                  <p>{{ $run->summary ?? 'No summary recorded.' }}</p>
                  <div class="wb-meta-grid">
                    <div>
                      <span class="wb-meta-label">Started</span>
                      <strong>{{ optional($run->started_at)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
                    </div>
                    <div>
                      <span class="wb-meta-label">Finished</span>
                      <strong>{{ optional($run->finished_at)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
                    </div>
                    <div>
                      <span class="wb-meta-label">Duration</span>
                      <strong>{{ $run->durationLabel() }}</strong>
                    </div>
                  </div>

                  @if ($run->output)
                    <div class="wb-stack wb-stack-2">
                      <strong>Safe output log</strong>
                      <pre class="wb-code-block">{{ $run->output }}</pre>
                    </div>
                  @endif
                </div>

                <div class="wb-modal-footer">
                  <button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          @endforeach

          @foreach ($runs as $run)
            @continue(in_array($run->status, [\WebBlocks\Cms\Models\SystemUpdateRun::STATUS_PENDING, \WebBlocks\Cms\Models\SystemUpdateRun::STATUS_RUNNING], true))
            @php($deleteModalId = 'updateRunDeleteModal-'.$run->id)
            @php($deleteModalTitleId = $deleteModalId.'Title')
            <div class="wb-modal" id="{{ $deleteModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $deleteModalTitleId }}">
              <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                  <div>
                    <h3 class="wb-modal-title" id="{{ $deleteModalTitleId }}">Delete update history entry</h3>
                    <p class="wb-card-description">Confirm history housekeeping for this update run.</p>
                  </div>
                  <button
                    class="wb-modal-close"
                    type="button"
                    data-wb-dismiss="modal"
                    aria-label="Close delete confirmation"
                    title="Close"
                  >&times;</button>
                </div>

                <div class="wb-modal-body wb-stack wb-stack-4">
                  <div class="wb-alert wb-alert-warning">
                    This only removes the selected update run history record. It does not change the current CMS version, installed version state, latest published metadata, release artifacts, backups, package files, update availability, migrations, or domain/content data.
                  </div>

                  <dl class="wb-list wb-list-flush">
                    <div class="wb-list-item">
                      <dt class="wb-list-item-title">Version</dt>
                      <dd class="wb-list-item-sub">{{ $run->from_version ?: 'N/A' }} → {{ $run->to_version ?: 'N/A' }}</dd>
                    </div>
                    <div class="wb-list-item">
                      <dt class="wb-list-item-title">Status</dt>
                      <dd class="wb-list-item-sub"><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span></dd>
                    </div>
                    <div class="wb-list-item">
                      <dt class="wb-list-item-title">Started at</dt>
                      <dd class="wb-list-item-sub">{{ optional($run->started_at)->format('Y-m-d H:i:s') ?? 'Not available' }}</dd>
                    </div>
                  </dl>
                </div>

                <div class="wb-modal-footer">
                  <button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">Cancel</button>
                  <form method="POST" action="{{ route('admin.system.updates.runs.destroy', $run) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="history_page" value="{{ $historyPage }}">
                    <input type="hidden" name="history_per_page" value="{{ $historyPerPage }}">
                    <button class="wb-btn wb-btn-danger" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            </div>
          @endforeach
        @endif
      </div>

      @if ($runs->isNotEmpty())
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $runs, 'ariaLabel' => 'Update History pagination', 'compact' => true])
      @endif
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-wb-update-form]');

      if (!form) {
        return;
      }

      var button = form.querySelector('[data-wb-update-submit]');

      if (!button || button.disabled) {
        return;
      }

      button.disabled = true;
      button.textContent = button.getAttribute('data-busy-label') || 'Updating...';
    });
  </script>
@endpush
