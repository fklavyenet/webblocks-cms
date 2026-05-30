@extends('webblocks-cms::layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
  @php
    $updateStatus = $report['version'];
    $diagnostics = $report['diagnostics'];
    $environment = $report['environment'];
    $release = $updateStatus['release'] ?? null;
    $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
    $storedInstalledVersion = $report['stored_installed_version'] ?? null;
    $latestUpdateRun = $latestUpdateRun ?? null;
    $pendingUpdate = $pendingUpdate ?? null;
    $pendingBackup = $pendingBackup ?? null;
    $historicalUpdateRuns = collect($historicalUpdateRuns ?? []);
    $autoUpdate = $report['auto_update'] ?? ['allowed' => false, 'blockers' => [], 'busy' => false];
    $compatibilityStatus = $updateStatus['compatibility']['status'] ?? 'unknown';
    $showLatestVersion = ($updateStatus['latest_version'] ?? null) !== null
      && (string) $installedVersion !== (string) $updateStatus['latest_version'];
    $currentCodeIsNewerThanPublished = is_string($updateStatus['latest_version'] ?? null)
      && version_compare((string) $installedVersion, (string) $updateStatus['latest_version'], '>');
    $latestPublishedAt = ($release['published_at'] ?? null)
      ? \Carbon\Carbon::parse($release['published_at'])->format('Y-m-d H:i:s')
      : 'N/A';
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
      'update_available' => 'A new WebBlocks CMS release is ready.',
      'incompatible' => 'A newer release is available, but this install is not compatible yet.',
      'up_to_date' => $currentCodeIsNewerThanPublished
        ? 'This codebase is ahead of the latest published package on the selected channel.'
        : 'This install is already on the latest published release.',
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
    $diagnosticsNeedAttention = $diagnosticItems->contains(fn ($item) => ! in_array((string) ($item['status'] ?? ''), ['ok', 'compatible'], true))
      || ! empty($updateStatus['error_message']);
    $historyRuns = collect([$latestUpdateRun])->filter()->merge($historicalUpdateRuns);
  @endphp

  @include('webblocks-cms::admin.partials.page-header', [
    'title' => 'System Updates',
    'description' => 'Review published WebBlocks CMS releases and apply compatible package updates.',
  ])

  @include('webblocks-cms::admin.partials.flash')

  <div class="wb-stack wb-stack-4">
    <div class="wb-card">
      <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-cluster wb-cluster-2">
          <strong>Update Summary</strong>
          <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $summaryTitle }}</span>
        </div>

        <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">Check again</a>
      </div>

      <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-stack wb-gap-1">
          <strong>{{ $summaryMessage }}</strong>
          @if (trim((string) ($updateStatus['message'] ?? '')) !== '' && (string) $updateStatus['message'] !== $summaryMessage)
            <div class="wb-text-sm wb-text-muted">{{ $updateStatus['message'] }}</div>
          @endif
        </div>

        @if ($updateStatus['error_message'])
          <div class="wb-alert wb-alert-warning">
            <div>
              <div class="wb-alert-title">Server detail</div>
              <div>{{ $updateStatus['error_message'] }}</div>
            </div>
          </div>
        @endif

        <div class="wb-grid wb-grid-2">
          <div class="wb-stack wb-gap-1">
            <div class="wb-text-sm wb-text-muted">Current CMS Version</div>
            <strong>{{ $installedVersion }}</strong>
          </div>

          @if ($showLatestVersion)
            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Latest Published Version</div>
              <strong>{{ $updateStatus['latest_version'] }}</strong>
            </div>
          @endif
        </div>

        @if ($release)
          <details>
            <summary><strong>Release notes</strong></summary>

            <div class="wb-stack wb-gap-2 wb-mt-2">
              @if ($hasReleaseDetails)
                @if (trim((string) ($releaseDetails['title'] ?? '')) !== '')
                  <strong>{{ $releaseDetails['title'] }}</strong>
                @endif

                @if (trim((string) ($releaseDetails['summary'] ?? '')) !== '')
                  <div class="wb-text-sm">{{ $releaseDetails['summary'] }}</div>
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
                <div class="wb-text-sm wb-text-muted">No release notes were provided for this release.</div>
              @endif
            </div>
          </details>
        @endif
      </div>
    </div>

    <div class="wb-card">
      <div class="wb-card-header">
        <strong>Install Update</strong>
      </div>

      <div class="wb-card-body wb-stack wb-gap-3">
        @if ($pendingUpdate && $pendingBackup)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">Backup protection</div>
              <div>A pre-update backup was created automatically. Download it before continuing the installation.</div>
            </div>
          </div>

          <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Backup name</div>
              <strong>{{ $pendingBackup->archive_filename ?? ('Backup #'.$pendingBackup->id) }}</strong>
            </div>

            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Backup size</div>
              <strong>{{ $pendingBackup->humanArchiveSize() }}</strong>
            </div>
          </div>

          <div class="wb-cluster wb-cluster-2">
            <a href="{{ route('admin.system.backups.download', $pendingBackup) }}" class="wb-btn wb-btn-secondary">Download backup</a>

            <form method="POST" action="{{ route('admin.system.updates.continue') }}" data-wb-update-form>
              @csrf
              <button type="submit" class="wb-btn wb-btn-primary" data-wb-update-submit data-default-label="Continue update" data-busy-label="Updating...">Continue update</button>
            </form>

            <form method="POST" action="{{ route('admin.system.updates.cancel') }}">
              @csrf
              <button type="submit" class="wb-btn wb-btn-secondary">Cancel</button>
            </form>
          </div>
        @else
          <div class="wb-stack wb-gap-2">
            <strong>{{ $showUpdateAction ? 'Ready to install' : 'No installable update right now' }}</strong>
            <div class="wb-text-sm wb-text-muted">
              @if ($showUpdateAction)
                A pre-update backup will be created automatically before installation. The CMS will download and verify the package, apply it, run required update migrations, clear runtime caches, record the run, and persist the installed version. Catalog repair is available as a separate maintenance command.
              @else
                {{ $autoUpdate['blockers'][0] ?? 'A compatible published package update is required before installation can start.' }}
              @endif
            </div>
          </div>

          <form method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
            @csrf

            <label class="wb-checkbox">
              <input type="checkbox" name="download_pre_update_backup" value="1" @checked(old('download_pre_update_backup')) @disabled(! $showUpdateAction)>
              <span>Download the backup before installation starts</span>
            </label>

            <div class="wb-mt-2">
              <button
                type="submit"
                class="wb-btn wb-btn-primary"
                data-wb-update-submit
                data-default-label="Update now"
                data-busy-label="Updating..."
                @disabled(! $showUpdateAction)
              >
                {{ $autoUpdate['busy'] ? 'Updating...' : 'Update now' }}
              </button>
            </div>
          </form>
        @endif

        <details>
          <summary><strong>Package safety</strong></summary>

          <ul class="wb-m-0 wb-mt-2 wb-text-sm wb-text-muted">
            <li>SHA-256 checksum verification</li>
            <li>Package boundary validation</li>
            <li>Backup and staging protection</li>
            <li>Required update migrations only</li>
            <li>Cache clears, update run recording, and installed version persistence</li>
          </ul>
        </details>
      </div>
    </div>

    <details class="wb-card wb-card-muted" @if ($diagnosticsNeedAttention) open @endif>
      <summary class="wb-card-header"><strong>Diagnostics</strong></summary>

      <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-text-sm wb-text-muted">Catalog repair is available as a separate maintenance command and is not part of automatic System Updates.</div>

        <div class="wb-grid wb-grid-2">
          @foreach ($diagnosticItems as $diagnostic)
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
              <div class="wb-stack wb-gap-1">
                <strong>{{ $diagnostic['label'] }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $diagnostic['message'] }}</div>
              </div>
              <span class="wb-status-pill {{ $diagnostic['badge_class'] }}">{{ $diagnostic['status'] }}</span>
            </div>
          @endforeach
        </div>

        <details>
          <summary><strong>Technical details</strong></summary>

          <div class="wb-grid wb-grid-2 wb-mt-2">
            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Update server</div>
              <strong>{{ $updateStatus['server_url'] ?: 'not configured' }}</strong>
            </div>

            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Site URL</div>
              <strong>{{ $environment['site_url'] }}</strong>
            </div>

            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">API version</div>
              <strong>{{ $updateStatus['api_version'] ?? 'N/A' }}</strong>
            </div>

            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Stored installed version</div>
              <strong>{{ is_string($storedInstalledVersion) && $storedInstalledVersion !== '' ? $storedInstalledVersion : 'N/A' }}</strong>
            </div>

            <div class="wb-stack wb-gap-1">
              <div class="wb-text-sm wb-text-muted">Runtime</div>
              <strong>PHP {{ $environment['php_version'] }} | Laravel {{ $environment['laravel_version'] }}</strong>
            </div>

            @if ($release)
              <div class="wb-stack wb-gap-1">
                <div class="wb-text-sm wb-text-muted">Package URL</div>
                <strong>{{ $release['download_url'] ?? 'N/A' }}</strong>
              </div>

              <div class="wb-stack wb-gap-1">
                <div class="wb-text-sm wb-text-muted">Published at</div>
                <strong>{{ $latestPublishedAt }}</strong>
              </div>

              <div class="wb-stack wb-gap-1">
                <div class="wb-text-sm wb-text-muted">SHA-256</div>
                <strong>{{ $release['checksum_sha256'] ?? 'N/A' }}</strong>
              </div>
            @endif
          </div>
        </details>
      </div>
    </details>

    <div class="wb-card">
      <div class="wb-card-header">
        <strong>Update History</strong>
      </div>

      <div class="wb-card-body">
        @if ($historyRuns->isEmpty())
          <div class="wb-text-sm wb-text-muted">No update runs have been recorded yet.</div>
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
                @foreach ($historyRuns as $run)
                  <tr>
                    <td>
                      <strong>{{ $run->to_version ?: 'N/A' }}</strong>
                      <div class="wb-text-sm wb-text-muted">{{ $run->from_version ?: 'N/A' }} to {{ $run->to_version ?: 'N/A' }}</div>
                    </td>
                    <td><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span></td>
                    <td>{{ $run->started_at?->format('Y-m-d H:i:s') ?: $run->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $run->durationLabel() }}</td>
                    <td>{{ $run->triggeredBy?->name ?? 'System' }}</td>
                    <td class="wb-table-actions">
                      <div class="wb-action-group">
                        @if ($run->output)
                          <details>
                            <summary>Output</summary>
                            <pre class="wb-code-block">{{ $run->output }}</pre>
                          </details>
                        @else
                          <span class="wb-text-sm wb-text-muted">None</span>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
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
