@extends('webblocks-cms::layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
  @php
    $updateStatus = $report['version'];
    $diagnostics = $report['diagnostics'];
    $release = $updateStatus['release'] ?? null;
    $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
    $storedInstalledVersion = $report['stored_installed_version'] ?? null;
    $pendingUpdate = $pendingUpdate ?? null;
    $pendingBackup = $pendingBackup ?? null;
    $latestUpdateRun = $latestUpdateRun ?? null;
    $retainedUpdateRuns = $retainedUpdateRuns ?? collect();
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
        : 'This install already matches the latest published release.',
      'server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled' => 'The CMS could not complete a trusted update check. Review readiness before installing.',
      default => $updateStatus['message'] ?? 'Review the current update status below.',
    };
    $publisherUnavailable = in_array($state, ['server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled'], true);
    $statusAlertClass = match (true) {
      $state === 'update_available' => 'wb-alert-info',
      $state === 'incompatible' || $publisherUnavailable => 'wb-alert-warning',
      default => 'wb-alert-success',
    };
    $statusIconClass = match (true) {
      $state === 'update_available' => 'wb-icon-download',
      $state === 'incompatible' || $publisherUnavailable => 'wb-icon-alert-triangle',
      default => 'wb-icon-check-circle',
    };
    $versionPath = $showLatestVersion
      ? $installedVersion.' → '.$updateStatus['latest_version']
      : $installedVersion;

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
    $technicalReleaseNotes = $releaseDetailGroups
      ->filter(fn ($group) => ($group['key'] ?? '') === 'technical_notes')
      ->flatMap(fn ($group) => collect($group['items'] ?? [])->filter())
      ->values();
    $visibleReleaseDetailGroups = $releaseDetailGroups
      ->reject(fn ($group) => ($group['key'] ?? '') === 'technical_notes')
      ->values();
    $fallbackReleaseNotes = collect($releaseDetails['fallback_notes'] ?? [])->filter()->values();
    $knownReleaseNoteLines = collect([
      $releaseDetails['title'] ?? null,
      $releaseDetails['summary'] ?? null,
    ])
      ->merge($releaseDetailGroups->flatMap(fn ($group) => collect($group['items'] ?? [])))
      ->filter()
      ->map(fn ($line) => strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $line))))
      ->values();
    $fallbackReleaseNotes = $fallbackReleaseNotes
      ->reject(function ($line) use ($knownReleaseNoteLines) {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $line)));

        return $knownReleaseNoteLines->contains($normalized)
          || preg_match('/^webblocks cms\s+\d+\.\d+\.\d+$/i', $normalized) === 1;
      })
      ->values();
    $hasReleaseDetails = (bool) ($releaseDetails['has_notes'] ?? false)
      || trim((string) ($releaseDetails['title'] ?? '')) !== ''
      || trim((string) ($releaseDetails['summary'] ?? '')) !== ''
      || $visibleReleaseDetailGroups->isNotEmpty()
      || $fallbackReleaseNotes->isNotEmpty();
    $artifactFilename = $release['artifact_filename'] ?? null;
    if (! is_string($artifactFilename) || $artifactFilename === '') {
      $downloadPath = parse_url((string) ($release['download_url'] ?? ''), PHP_URL_PATH);
      $artifactFilename = is_string($downloadPath) && $downloadPath !== ''
        ? basename($downloadPath)
        : 'Release package';
    }
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
    $readinessNeedsAttention = $diagnosticItems->contains(fn ($item) => ! in_array((string) ($item['status'] ?? ''), ['ok', 'pass', 'compatible'], true))
      || ! empty($updateStatus['error_message'])
      || ($autoUpdate['allowed'] ?? false) !== true && $state === 'update_available';
    $latestRunModalId = $latestUpdateRun ? 'updateRunDetailsModal-'.$latestUpdateRun->id : null;
  @endphp

  @include('webblocks-cms::admin.partials.page-header', [
    'title' => 'System Updates',
    'description' => 'Install validated WebBlocks CMS package updates and review release, readiness, and run history.',
  ])

  <div class="wb-stack wb-stack-4" data-webblocks-updates-layout="designed">
    <div data-webblocks-updates-flash>
      @include('webblocks-cms::admin.partials.flash')
    </div>

    <section class="wb-card" data-webblocks-updates-card="summary" data-webblocks-updates-state="{{ $state }}">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">Update Status</h2>
          <p class="wb-card-description">{{ $showLatestVersion ? 'A published package is ready for this install.' : 'Validated package status for this WebBlocks CMS install.' }}</p>
        </div>
        <div class="wb-action-group">
          <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $summaryTitle }}</span>
          <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">Check again</a>
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        <div data-webblocks-updates-hero>
          <div data-webblocks-updates-hero-main>
            <span data-webblocks-updates-hero-icon aria-hidden="true">
              <i class="wb-icon {{ $statusIconClass }}"></i>
            </span>
            <div class="wb-stack wb-gap-2">
              <div>
                <div class="wb-alert-title">{{ $summaryTitle }}</div>
                <p>{{ $summaryMessage }}</p>
              </div>
              <div data-webblocks-updates-version-path>
                <span class="wb-meta-label">{{ $showLatestVersion ? 'Version path' : 'Installed version' }}</span>
                <strong>{{ $versionPath }}</strong>
              </div>
            </div>
          </div>

          <div data-webblocks-updates-hero-aside>
            <div>
              <span class="wb-meta-label">{{ $showLatestVersion ? 'Latest published' : 'Installed version' }}</span>
              <strong>{{ $showLatestVersion ? $updateStatus['latest_version'] : $installedVersion }}</strong>
              @if ($latestPublishedAt)
                <span class="wb-text-muted wb-text-sm">Published {{ $latestPublishedAt }}</span>
              @endif
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
                  data-default-label="Update Now"
                  data-busy-label="Updating..."
                >
                  {{ $autoUpdate['busy'] ? 'Updating...' : 'Update Now' }}
                </button>
              @endif
            </div>
          </div>
        </div>

        @if ($updateStatus['error_message'])
          <div class="wb-alert wb-alert-warning">
            <div>
              <div class="wb-alert-title">Server detail</div>
              <div>{{ $updateStatus['error_message'] }}</div>
            </div>
          </div>
        @endif

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
              <button type="submit" class="wb-btn wb-btn-secondary">Cancel</button>
            </form>
          </div>
        @elseif ($showUpdateAction)
          <div class="wb-alert wb-alert-success">
            <div>
              <div class="wb-alert-title">{{ $installedVersion }} → {{ $updateStatus['latest_version'] }}</div>
              <div>A pre-update backup will be created automatically before installation.</div>
            </div>
          </div>

          <form id="webblocks-update-install-form" method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
            @csrf

            <label class="wb-checkbox">
              <input type="checkbox" name="download_pre_update_backup" value="1" @checked(old('download_pre_update_backup'))>
              <span>Download the backup before installation starts</span>
            </label>
          </form>
        @else
          <p class="wb-text-muted">{{ $autoUpdate['blockers'][0] ?? 'No newer release is ready for this install.' }}</p>
        @endif

        <div data-webblocks-updates-metrics>
          <div>
            <span class="wb-meta-label">Current CMS Version</span>
            <strong>{{ $installedVersion }}</strong>
          </div>

          @if ($showLatestVersion)
            <div>
              <span class="wb-meta-label">Latest Published Version</span>
              <strong>{{ $updateStatus['latest_version'] }}</strong>
              @if ($latestPublishedAt)
                <span class="wb-text-muted wb-text-sm">Published Date: {{ $latestPublishedAt }}</span>
              @endif
            </div>
          @endif

          <div>
            <span class="wb-meta-label">Compatibility</span>
            <strong>{{ $compatibilityStatus }}</strong>
          </div>

          <div>
            <span class="wb-meta-label">Channel</span>
            <strong>{{ $updateStatus['channel'] ?? 'stable' }}</strong>
          </div>

          <div>
            <span class="wb-meta-label">Update server</span>
            <strong>{{ $updateStatus['server_url'] ?: 'not configured' }}</strong>
          </div>

          <div>
            <span class="wb-meta-label">Last checked</span>
            <strong>{{ optional($checkedAt ?? null)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
          </div>
        </div>
      </div>
    </section>

    <div class="wb-grid wb-grid-3" data-webblocks-updates-card="safety-summary">
      <section class="wb-card">
        <div class="wb-card-body">
          <div data-webblocks-updates-safety-card>
            <span data-webblocks-updates-card-icon aria-hidden="true"><i class="wb-icon wb-icon-cloud"></i></span>
            <div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Publisher</strong>
                <span class="wb-status-pill {{ $publisherUnavailable ? 'wb-status-danger' : 'wb-status-active' }}">{{ $publisherUnavailable ? 'Needs attention' : 'Online' }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $updateStatus['server_url'] ?: 'Update server is not configured.' }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="wb-card">
        <div class="wb-card-body">
          <div data-webblocks-updates-safety-card>
            <span data-webblocks-updates-card-icon aria-hidden="true"><i class="wb-icon wb-icon-list-checks"></i></span>
            <div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Preflight</strong>
                <span class="wb-status-pill {{ $readinessNeedsAttention ? 'wb-status-danger' : 'wb-status-active' }}">{{ $readinessNeedsAttention ? 'Needs attention' : 'Ready' }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $readinessNeedsAttention ? 'Review readiness checks before installing.' : 'Required checks are passing.' }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="wb-card">
        <div class="wb-card-body">
          <div data-webblocks-updates-safety-card>
            <span data-webblocks-updates-card-icon aria-hidden="true"><i class="wb-icon wb-icon-archive"></i></span>
            <div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Backup</strong>
                <span class="wb-status-pill {{ $pendingBackup ? 'wb-status-info' : 'wb-status-active' }}">{{ $pendingBackup ? 'Created' : 'Will be created' }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $pendingBackup ? 'Download the prepared backup before continuing.' : 'A pre-update backup is created before apply.' }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="wb-grid wb-grid-2" data-webblocks-updates-card="details">
      <section class="wb-card" data-webblocks-updates-panel="release">
        <div class="wb-card-header">
          <div class="wb-cluster wb-cluster-2 wb-items-center">
            <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon wb-icon-package"></i></span>
            <div>
              <h2 class="wb-card-title">Release</h2>
              <p class="wb-card-description">Published package details for the selected channel.</p>
            </div>
          </div>
          @if ($release)
            <span class="wb-status-pill wb-status-info">{{ $updateStatus['latest_version'] ?? 'Published' }}</span>
          @endif
        </div>

        <div class="wb-card-body wb-stack wb-stack-4">
          @if ($release)
            <div data-webblocks-updates-release-meta>
              <div>
                <span class="wb-meta-label">Product</span>
                <strong>{{ $updateStatus['product'] ?? 'webblocks-cms' }}</strong>
              </div>
              <div>
                <span class="wb-meta-label">Checksum</span>
                <strong>{{ ($release['checksum_sha256'] ?? $release['checksum'] ?? null) ? 'SHA-256 provided' : 'Not available' }}</strong>
              </div>
              <div>
                <span class="wb-meta-label">Artifact</span>
                <code>{{ $artifactFilename }}</code>
              </div>
            </div>

            @if ($hasReleaseDetails)
              <div class="wb-stack wb-stack-3" data-webblocks-updates-release-notes>
                @if (trim((string) ($releaseDetails['title'] ?? '')) !== '')
                  <strong>{{ $releaseDetails['title'] }}</strong>
                @endif

                @if (trim((string) ($releaseDetails['summary'] ?? '')) !== '')
                  <p>{{ $releaseDetails['summary'] }}</p>
                @endif

                @foreach ($visibleReleaseDetailGroups as $group)
                  <div class="wb-stack wb-gap-1" data-webblocks-updates-release-group>
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
              </div>

              @if ($technicalReleaseNotes->isNotEmpty())
                <div class="wb-accordion" data-wb-accordion data-webblocks-updates-accordion="release-technical">
                  <div class="wb-accordion-item">
                    <button
                      class="wb-accordion-trigger"
                      type="button"
                      data-wb-accordion-trigger
                      aria-expanded="false"
                      aria-controls="webblocks-release-technical-notes"
                    >
                      <span>Technical package metadata</span>
                      <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                    </button>
                    <div class="wb-accordion-content" id="webblocks-release-technical-notes">
                      <div class="wb-accordion-body">
                        <ul class="wb-m-0 wb-text-sm wb-text-muted">
                          @foreach ($technicalReleaseNotes as $note)
                            <li>{{ $note }}</li>
                          @endforeach
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              @endif
            @else
              <p class="wb-text-muted">No release notes were provided for this release.</p>
            @endif
          @else
            <p class="wb-text-muted">No published release details are available for this update check.</p>
          @endif
        </div>
      </section>

      <section class="wb-card" data-webblocks-updates-panel="readiness">
        <div class="wb-card-header">
          <div class="wb-cluster wb-cluster-2 wb-items-center">
            <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon wb-icon-shield-check"></i></span>
            <div>
              <h2 class="wb-card-title">Readiness</h2>
              <p class="wb-card-description">Install and update-service checks before applying a package.</p>
            </div>
          </div>
          <span class="wb-status-pill {{ $readinessNeedsAttention ? 'wb-status-danger' : 'wb-status-active' }}">{{ $readinessNeedsAttention ? 'Needs attention' : 'Ready' }}</span>
        </div>

        <div class="wb-card-body wb-stack wb-stack-4">
          <div data-webblocks-updates-readiness-summary>
            <div>
              <span class="wb-meta-label">Stored installed version</span>
              <strong>{{ is_string($storedInstalledVersion) && $storedInstalledVersion !== '' ? $storedInstalledVersion : 'N/A' }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">Product</span>
              <strong>{{ $updateStatus['product'] ?? 'webblocks-cms' }}</strong>
            </div>
          </div>

          <div class="wb-stack wb-stack-2" data-webblocks-updates-checklist>
            @foreach ($diagnosticItems as $diagnostic)
              <div data-webblocks-updates-check>
                <span data-webblocks-updates-check-icon aria-hidden="true">
                  <i class="wb-icon {{ in_array((string) ($diagnostic['status'] ?? ''), ['ok', 'pass', 'compatible'], true) ? 'wb-icon-check' : 'wb-icon-alert-circle' }}"></i>
                </span>
                <div>
                  <div class="wb-list-item-title">{{ $diagnostic['label'] }}</div>
                  <div class="wb-list-item-sub">{{ $diagnostic['message'] }}</div>
                </div>
                <span class="wb-status-pill {{ $diagnostic['badge_class'] }}">{{ $diagnostic['status'] }}</span>
              </div>
            @endforeach
          </div>

          @if ($showUpdateAction)
            <div class="wb-accordion" data-wb-accordion data-webblocks-updates-accordion="package-safety">
              <div class="wb-accordion-item">
                <button
                  class="wb-accordion-trigger"
                  type="button"
                  data-wb-accordion-trigger
                  aria-expanded="false"
                  aria-controls="webblocks-update-package-safety"
                >
                  <span>Package Safety Details</span>
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
          @endif
        </div>
      </section>
    </div>

    <section class="wb-card" data-webblocks-updates-card="history">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">Update History</h2>
          <p class="wb-card-description">Recent retained update runs and latest run details.</p>
        </div>
        <div class="wb-action-group">
          <a href="{{ route('admin.system.updates.support-report') }}" class="wb-btn wb-btn-secondary">Download support report</a>
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        @if ($latestUpdateRun)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">Last update run</div>
              <div>{{ $latestUpdateRun->from_version ?: 'N/A' }} → {{ $latestUpdateRun->to_version ?: 'N/A' }} / {{ $latestUpdateRun->summary ?? 'No summary recorded.' }}</div>
            </div>
          </div>
        @endif

        @if ($retainedUpdateRuns->isNotEmpty())
          <div class="wb-table-wrap">
            <table class="wb-table wb-table-striped wb-table-hover">
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Started</th>
                  <th>Duration</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($retainedUpdateRuns as $run)
                  <tr>
                    <td>{{ $run->from_version ?: 'N/A' }} → {{ $run->to_version ?: 'N/A' }}</td>
                    <td><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span></td>
                    <td>{{ optional($run->started_at)->format('Y-m-d H:i') ?? optional($run->created_at)->format('Y-m-d H:i') ?? 'Not available' }}</td>
                    <td>{{ $run->durationLabel() }}</td>
                    <td>{{ $run->summary ?? 'No summary recorded.' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="wb-text-muted">No update runs have been recorded yet.</p>
        @endif

        @if ($latestUpdateRun)
          <button
            class="wb-btn wb-btn-secondary"
            type="button"
            data-wb-toggle="modal"
            data-wb-target="#{{ $latestRunModalId }}"
            aria-controls="{{ $latestRunModalId }}"
            aria-expanded="false"
          >
            View run details
          </button>
        @endif
      </div>
    </section>
  </div>

  @if ($latestUpdateRun)
    <div class="wb-modal" id="{{ $latestRunModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $latestRunModalId }}Title">
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <div>
            <h3 class="wb-modal-title" id="{{ $latestRunModalId }}Title">Update run details</h3>
            <p class="wb-card-description">{{ $latestUpdateRun->from_version ?: 'N/A' }} → {{ $latestUpdateRun->to_version ?: 'N/A' }} / {{ $latestUpdateRun->statusLabel() }}</p>
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
          <p>{{ $latestUpdateRun->summary ?? 'No summary recorded.' }}</p>
          <div class="wb-meta-grid">
            <div>
              <span class="wb-meta-label">Started</span>
              <strong>{{ optional($latestUpdateRun->started_at)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">Finished</span>
              <strong>{{ optional($latestUpdateRun->finished_at)->format('Y-m-d H:i') ?? 'Not available' }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">Duration</span>
              <strong>{{ $latestUpdateRun->durationLabel() }}</strong>
            </div>
          </div>

          @if ($latestUpdateRun->output)
            <div class="wb-stack wb-stack-2">
              <strong>Safe output log</strong>
              <pre class="wb-code-block">{{ app(\WebBlocks\Cms\Support\System\Updates\SystemUpdateRunOutputSanitizer::class)->sanitize($latestUpdateRun->output) }}</pre>
            </div>
          @endif
        </div>

        <div class="wb-modal-footer">
          <button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  @endif
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
