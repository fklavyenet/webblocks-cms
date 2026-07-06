@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;
  use WebBlocks\Cms\Support\System\SystemUpdateInspector;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('updates.title'), 'heading' => $adminText('updates.title')])

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
    $updateBlockerText = static fn (?string $message) => match ($message) {
      SystemUpdateInspector::BLOCKER_BUSY => $adminText('updates.blockers.busy'),
      SystemUpdateInspector::BLOCKER_NO_NEWER_RELEASE_READY => $adminText('updates.blockers.no_newer_release_ready'),
      SystemUpdateInspector::BLOCKER_MISSING_PACKAGE_URL => $adminText('updates.blockers.missing_package_url'),
      null, '' => null,
      default => $message,
    };
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
      'update_available' => $adminText('updates.states.update_available'),
      'incompatible' => $adminText('updates.states.incompatible'),
      'up_to_date' => $currentCodeIsNewerThanPublished ? $adminText('updates.states.local_newer') : $adminText('updates.states.up_to_date'),
      'server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled' => $adminText('updates.states.server_unavailable'),
      default => $updateStatus['label'] ?? $adminText('updates.states.status'),
    };
    $summaryMessage = match ($state) {
      'update_available' => $adminText('updates.messages.update_available'),
      'incompatible' => $adminText('updates.messages.incompatible'),
      'up_to_date' => $currentCodeIsNewerThanPublished
        ? $adminText('updates.messages.local_newer')
        : $adminText('updates.messages.up_to_date'),
      'server_unreachable', 'server_error', 'invalid_configuration', 'invalid_response', 'client_disabled' => $adminText('updates.messages.server_unavailable'),
      default => $updateStatus['message'] ?? $adminText('updates.messages.status'),
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
        : $adminText('updates.release_package');
    }
    $showUpdateAction = ($pendingUpdate && $pendingBackup)
      || ($autoUpdate['allowed'] ?? false) === true;
    $diagnosticItems = collect($diagnostics)->prepend([
        'label' => $adminText('updates.compatibility'),
        'status' => $compatibilityStatus,
        'message' => ($updateStatus['compatibility']['reasons'] ?? []) === []
        ? $adminText('updates.no_compatibility_issues')
        : implode(' ', $updateStatus['compatibility']['reasons']),
      'badge_class' => $compatibilityBadgeClass,
    ]);
    $readinessNeedsAttention = $diagnosticItems->contains(fn ($item) => ! in_array((string) ($item['status'] ?? ''), ['ok', 'pass', 'compatible'], true))
      || ! empty($updateStatus['error_message'])
      || ($autoUpdate['allowed'] ?? false) !== true && $state === 'update_available';
    $latestRunModalId = $latestUpdateRun ? 'updateRunDetailsModal-'.$latestUpdateRun->id : null;
    $updateProgressModalId = 'systemUpdateProgressModal';
  @endphp

  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $adminText('updates.title'),
    'description' => $adminText('updates.description'),
  ])

  <div class="wb-stack wb-stack-4" data-webblocks-updates-layout="designed">
    <div data-webblocks-updates-flash>
      @include('webblocks-cms::admin.partials.flash')
    </div>

    <section class="wb-card" data-webblocks-updates-card="summary" data-webblocks-updates-state="{{ $state }}">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">{{ $adminText('updates.update_status') }}</h2>
          <p class="wb-card-description">{{ $showLatestVersion ? $adminText('updates.published_package_ready') : $adminText('updates.quiet_until_available') }}</p>
        </div>
        <div class="wb-action-group">
          <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $summaryTitle }}</span>
          <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">{{ $adminText('updates.check_again') }}</a>
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
                <span class="wb-meta-label">{{ $showLatestVersion ? $adminText('updates.version_path') : $adminText('updates.installed_version') }}</span>
                <strong>{{ $versionPath }}</strong>
              </div>
            </div>
          </div>

          <div data-webblocks-updates-hero-aside>
            <div>
              @if ($showLatestVersion)
                <span class="wb-meta-label">{{ $adminText('updates.latest_published') }}</span>
                <strong>{{ $updateStatus['latest_version'] }}</strong>
              @else
                <span class="wb-meta-label">{{ $adminText('updates.last_checked') }}</span>
                <strong>{{ optional($checkedAt ?? null)->format('Y-m-d H:i') ?? $adminText('common.not_available') }}</strong>
              @endif
              @if ($showLatestVersion && $latestPublishedAt)
                <span class="wb-text-muted wb-text-sm">{{ $adminText('updates.published_at', ['date' => $latestPublishedAt]) }}</span>
              @endif
            </div>
            <div class="wb-action-group">
              @if ($pendingUpdate && $pendingBackup)
                <form method="POST" action="{{ route('admin.system.updates.continue') }}" data-wb-update-form>
                  @csrf
                  <button type="submit" class="wb-btn wb-btn-primary" data-wb-update-submit data-default-label="{{ $adminText('updates.continue_update') }}" data-busy-label="{{ $adminText('updates.updating') }}">{{ $adminText('updates.continue_update') }}</button>
                </form>
              @elseif ($showUpdateAction)
                <button
                  type="submit"
                  form="webblocks-update-install-form"
                  class="wb-btn wb-btn-primary"
                  data-wb-update-submit
                  data-default-label="{{ $adminText('updates.update_now') }}"
                  data-busy-label="{{ $adminText('updates.updating') }}"
                >
                  {{ $autoUpdate['busy'] ? $adminText('updates.updating') : $adminText('updates.update_now') }}
                </button>
              @endif
            </div>
          </div>
        </div>

        @if ($updateStatus['error_message'])
          <div class="wb-alert wb-alert-warning">
            <div>
              <div class="wb-alert-title">{{ $adminText('updates.server_detail') }}</div>
              <div>{{ $updateStatus['error_message'] }}</div>
            </div>
          </div>
        @endif

        @if ($pendingUpdate && $pendingBackup)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">{{ $adminText('updates.backup_protection') }}</div>
              <div>{{ $adminText('updates.backup_protection_help') }}</div>
            </div>
          </div>

          <div class="wb-meta-grid">
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.backup_name') }}</span>
              <strong>{{ $pendingBackup->archive_filename ?? ($adminText('backups.backup_number', ['id' => $pendingBackup->id])) }}</strong>
            </div>

            <div>
              <span class="wb-meta-label">{{ $adminText('updates.backup_size') }}</span>
              <strong>{{ $pendingBackup->humanArchiveSize() }}</strong>
            </div>
          </div>

          <div class="wb-action-group">
            <a href="{{ route('admin.system.backups.download', $pendingBackup) }}" class="wb-btn wb-btn-secondary">{{ $adminText('backups.download_backup') }}</a>

            <form method="POST" action="{{ route('admin.system.updates.cancel') }}">
              @csrf
              <button type="submit" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</button>
            </form>
          </div>
        @elseif ($showUpdateAction)
          <div class="wb-alert wb-alert-success">
            <div>
              <div class="wb-alert-title">{{ $installedVersion }} → {{ $updateStatus['latest_version'] }}</div>
              <div>{{ $adminText('updates.pre_update_backup_notice') }}</div>
            </div>
          </div>

          <form id="webblocks-update-install-form" method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
            @csrf

            <label class="wb-checkbox">
              <input type="checkbox" name="download_pre_update_backup" value="1" @checked(old('download_pre_update_backup'))>
              <span>{{ $adminText('updates.download_backup_before_install') }}</span>
            </label>
          </form>
        @else
          <p class="wb-text-muted">{{ $updateBlockerText($autoUpdate['blockers'][0] ?? null) ?? $adminText('updates.blockers.no_newer_release_ready') }}</p>
        @endif

      </div>
    </section>

    @if ($showUpdateAction || $pendingUpdate || $readinessNeedsAttention || $state === 'incompatible')
    <div class="wb-grid wb-grid-3" data-webblocks-updates-card="safety-summary">
      <section class="wb-card">
        <div class="wb-card-body">
          <div data-webblocks-updates-safety-card>
            <span data-webblocks-updates-card-icon aria-hidden="true"><i class="wb-icon wb-icon-cloud"></i></span>
            <div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <strong>{{ $adminText('updates.publisher') }}</strong>
                <span class="wb-status-pill {{ $publisherUnavailable ? 'wb-status-danger' : 'wb-status-active' }}">{{ $publisherUnavailable ? $adminText('updates.needs_attention') : $adminText('updates.online') }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $updateStatus['server_url'] ?: $adminText('updates.server_not_configured') }}</p>
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
                <strong>{{ $adminText('updates.preflight') }}</strong>
                <span class="wb-status-pill {{ $readinessNeedsAttention ? 'wb-status-danger' : 'wb-status-active' }}">{{ $readinessNeedsAttention ? $adminText('updates.needs_attention') : $adminText('updates.ready') }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $readinessNeedsAttention ? $adminText('updates.review_readiness') : $adminText('updates.required_checks_passing') }}</p>
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
                <strong>{{ $adminText('updates.backup') }}</strong>
                <span class="wb-status-pill {{ $pendingBackup ? 'wb-status-info' : 'wb-status-active' }}">{{ $pendingBackup ? $adminText('updates.created') : $adminText('updates.will_be_created') }}</span>
              </div>
              <p class="wb-text-sm wb-text-muted wb-m-0">{{ $pendingBackup ? $adminText('updates.download_prepared_backup') : $adminText('updates.pre_update_backup_created') }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
    @endif

    <section class="wb-card" data-webblocks-updates-card="maintenance-details">
      <div class="wb-card-header">
        <div>
          <h2 class="wb-card-title">{{ $adminText('updates.technical_details_history') }}</h2>
          <p class="wb-card-description">{{ $adminText('updates.technical_details_history_help') }}</p>
        </div>
      </div>

      <div class="wb-card-body">
        <div class="wb-accordion" data-wb-accordion data-webblocks-updates-accordion="maintenance-details">
          <div class="wb-accordion-item">
            <button
              class="wb-accordion-trigger"
              type="button"
              data-wb-accordion-trigger
              aria-expanded="false"
              aria-controls="webblocks-update-maintenance-details"
            >
              <span>{{ $adminText('updates.show_technical_details') }}</span>
              <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
            </button>
            <div class="wb-accordion-content" id="webblocks-update-maintenance-details">
              <div class="wb-accordion-body wb-stack wb-stack-4">
                <div data-webblocks-updates-metrics>
                  <div>
                    <span class="wb-meta-label">{{ $adminText('updates.current_cms_version') }}</span>
                    <strong>{{ $installedVersion }}</strong>
                  </div>

                  @if ($showLatestVersion)
                    <div>
                      <span class="wb-meta-label">{{ $adminText('updates.latest_published_version') }}</span>
                      <strong>{{ $updateStatus['latest_version'] }}</strong>
                      @if ($latestPublishedAt)
                        <span class="wb-text-muted wb-text-sm">{{ $adminText('updates.published_date', ['date' => $latestPublishedAt]) }}</span>
                      @endif
                    </div>
                  @endif

                  <div>
                    <span class="wb-meta-label">{{ $adminText('updates.compatibility') }}</span>
                    <strong>{{ $compatibilityStatus }}</strong>
                  </div>

                  <div>
                    <span class="wb-meta-label">{{ $adminText('updates.channel') }}</span>
                    <strong>{{ $updateStatus['channel'] ?? 'stable' }}</strong>
                  </div>

                  <div>
                    <span class="wb-meta-label">{{ $adminText('updates.update_server') }}</span>
                    <strong>{{ $updateStatus['server_url'] ?: $adminText('updates.not_configured') }}</strong>
                  </div>
                </div>

                <div class="wb-grid wb-grid-2" data-webblocks-updates-card="details">
      <section class="wb-card" data-webblocks-updates-panel="release">
        <div class="wb-card-header">
          <div class="wb-cluster wb-cluster-2 wb-items-center">
            <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon wb-icon-package"></i></span>
            <div>
              <h2 class="wb-card-title">{{ $adminText('updates.release') }}</h2>
              <p class="wb-card-description">{{ $adminText('updates.release_help') }}</p>
            </div>
          </div>
          @if ($release)
            <span class="wb-status-pill wb-status-info">{{ $updateStatus['latest_version'] ?? $adminText('updates.published') }}</span>
          @endif
        </div>

        <div class="wb-card-body wb-stack wb-stack-4">
          @if ($release)
            <div data-webblocks-updates-release-meta>
              <div>
                <span class="wb-meta-label">{{ $adminText('updates.product') }}</span>
                <strong>{{ $updateStatus['product'] ?? 'webblocks-cms' }}</strong>
              </div>
              <div>
                <span class="wb-meta-label">{{ $adminText('updates.checksum') }}</span>
                <strong>{{ ($release['checksum_sha256'] ?? $release['checksum'] ?? null) ? $adminText('updates.sha256_provided') : $adminText('common.not_available') }}</strong>
              </div>
              <div>
                <span class="wb-meta-label">{{ $adminText('updates.artifact') }}</span>
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
                    <strong class="wb-text-sm">{{ in_array(($group['key'] ?? ''), ['fixes', 'changes'], true) ? $adminText('updates.fixes_and_changes') : ($group['label'] ?? $adminText('updates.release_details')) }}</strong>
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
                      <span>{{ $adminText('updates.technical_package_metadata') }}</span>
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
              <p class="wb-text-muted">{{ $adminText('updates.no_release_notes') }}</p>
            @endif
          @else
            <p class="wb-text-muted">{{ $adminText('updates.no_published_release_details') }}</p>
          @endif
        </div>
      </section>

      <section class="wb-card" data-webblocks-updates-panel="readiness">
        <div class="wb-card-header">
          <div class="wb-cluster wb-cluster-2 wb-items-center">
            <span class="wb-action-btn" aria-hidden="true"><i class="wb-icon wb-icon-shield-check"></i></span>
            <div>
              <h2 class="wb-card-title">{{ $adminText('updates.readiness') }}</h2>
              <p class="wb-card-description">{{ $adminText('updates.readiness_help') }}</p>
            </div>
          </div>
          <span class="wb-status-pill {{ $readinessNeedsAttention ? 'wb-status-danger' : 'wb-status-active' }}">{{ $readinessNeedsAttention ? $adminText('updates.needs_attention') : $adminText('updates.ready') }}</span>
        </div>

        <div class="wb-card-body wb-stack wb-stack-4">
          <div data-webblocks-updates-readiness-summary>
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.stored_installed_version') }}</span>
              <strong>{{ is_string($storedInstalledVersion) && $storedInstalledVersion !== '' ? $storedInstalledVersion : $adminText('common.na') }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.product') }}</span>
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
                  <span>{{ $adminText('updates.package_safety_details') }}</span>
                  <i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i>
                </button>
                <div class="wb-accordion-content" id="webblocks-update-package-safety">
                  <div class="wb-accordion-body wb-stack wb-stack-4">
                    <p class="wb-card-description">{{ $adminText('updates.package_safety_help') }}</p>
                    <div class="wb-code-block">{{ $adminText('updates.package_safety_checks') }}</div>
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
          <h2 class="wb-card-title">{{ $adminText('updates.history') }}</h2>
          <p class="wb-card-description">{{ $adminText('updates.history_help') }}</p>
        </div>
        <div class="wb-action-group">
          <a href="{{ route('admin.system.updates.support-report') }}" class="wb-btn wb-btn-secondary">{{ $adminText('updates.download_support_report') }}</a>
        </div>
      </div>

      <div class="wb-card-body wb-stack wb-stack-4">
        @if ($latestUpdateRun)
          <div class="wb-alert wb-alert-info">
            <div>
              <div class="wb-alert-title">{{ $adminText('updates.last_update_run') }}</div>
              <div>{{ $latestUpdateRun->from_version ?: $adminText('common.na') }} → {{ $latestUpdateRun->to_version ?: $adminText('common.na') }} / {{ $latestUpdateRun->summary ?? $adminText('updates.no_summary_recorded') }}</div>
            </div>
          </div>
        @endif

        @if ($retainedUpdateRuns->isNotEmpty())
          <div class="wb-table-wrap">
            <table class="wb-table wb-table-striped wb-table-hover">
              <thead>
                <tr>
                  <th>{{ $adminText('updates.version') }}</th>
                  <th>{{ $adminText('common.status') }}</th>
                  <th>{{ $adminText('updates.started') }}</th>
                  <th>{{ $adminText('updates.duration') }}</th>
                  <th>{{ $adminText('updates.details') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($retainedUpdateRuns as $run)
                  <tr>
                    <td>{{ $run->from_version ?: $adminText('common.na') }} → {{ $run->to_version ?: $adminText('common.na') }}</td>
                    <td><span class="wb-status-pill {{ $run->statusBadgeClass() }}">{{ $adminText('updates.statuses.'.$run->status) }}</span></td>
                    <td>{{ optional($run->started_at)->format('Y-m-d H:i') ?? optional($run->created_at)->format('Y-m-d H:i') ?? $adminText('common.not_available') }}</td>
                    <td>{{ $run->durationLabel() }}</td>
                    <td>{{ $run->summary ?? $adminText('updates.no_summary_recorded') }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="wb-text-muted">{{ $adminText('updates.no_update_runs') }}</p>
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
            {{ $adminText('updates.view_run_details') }}
          </button>
        @endif
      </div>
    </section>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  @if ($showUpdateAction)
    <div
      class="wb-modal"
      id="{{ $updateProgressModalId }}"
      role="dialog"
      aria-modal="true"
      aria-labelledby="{{ $updateProgressModalId }}Title"
      aria-describedby="{{ $updateProgressModalId }}Description"
      data-webblocks-update-progress-modal
      hidden
    >
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <div>
            <h3 class="wb-modal-title" id="{{ $updateProgressModalId }}Title">{{ $adminText('updates.updating_title') }}</h3>
            <p class="wb-card-description">
              {{ $installedVersion ?: $adminText('updates.current_version') }} → {{ $updateStatus['latest_version'] ?? $adminText('updates.latest_release') }}
            </p>
          </div>
        </div>

        <div class="wb-modal-body">
          <div class="wb-loading-inline" role="status" aria-live="polite" aria-atomic="true">
            <span class="wb-spinner-pulse wb-spinner-pulse-lg" aria-hidden="true"><span></span><span></span><span></span></span>
            <div class="wb-stack wb-gap-1">
              <strong id="{{ $updateProgressModalId }}Description">{{ $adminText('updates.applying_package') }}</strong>
              <span class="wb-text-sm wb-text-muted">{{ $adminText('updates.keep_tab_open') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif

  @if ($latestUpdateRun)
    <div class="wb-modal" id="{{ $latestRunModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $latestRunModalId }}Title">
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <div>
            <h3 class="wb-modal-title" id="{{ $latestRunModalId }}Title">{{ $adminText('updates.run_details') }}</h3>
            <p class="wb-card-description">{{ $latestUpdateRun->from_version ?: $adminText('common.na') }} → {{ $latestUpdateRun->to_version ?: $adminText('common.na') }} / {{ $adminText('updates.statuses.'.$latestUpdateRun->status) }}</p>
          </div>
          <button
            class="wb-modal-close"
            type="button"
            data-wb-dismiss="modal"
            aria-label="{{ $adminText('updates.close_run_details') }}"
            title="{{ $adminText('common.close') }}"
          >&times;</button>
        </div>

        <div class="wb-modal-body wb-stack wb-stack-4">
          <p>{{ $latestUpdateRun->summary ?? $adminText('updates.no_summary_recorded') }}</p>
          <div class="wb-meta-grid">
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.started') }}</span>
              <strong>{{ optional($latestUpdateRun->started_at)->format('Y-m-d H:i') ?? $adminText('common.not_available') }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.finished') }}</span>
              <strong>{{ optional($latestUpdateRun->finished_at)->format('Y-m-d H:i') ?? $adminText('common.not_available') }}</strong>
            </div>
            <div>
              <span class="wb-meta-label">{{ $adminText('updates.duration') }}</span>
              <strong>{{ $latestUpdateRun->durationLabel() }}</strong>
            </div>
          </div>

          @if ($latestUpdateRun->output)
            <div class="wb-stack wb-stack-2">
              <strong>{{ $adminText('updates.safe_output_log') }}</strong>
              <pre class="wb-code-block">{{ app(\WebBlocks\Cms\Support\System\Updates\SystemUpdateRunOutputSanitizer::class)->sanitize($latestUpdateRun->output) }}</pre>
            </div>
          @endif
        </div>

        <div class="wb-modal-footer">
          <button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">{{ $adminText('common.close') }}</button>
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

      if (!button && form.id) {
        button = document.querySelector('[data-wb-update-submit][form="' + form.id + '"]');
      }

      if (!button || button.disabled) {
        return;
      }

      button.disabled = true;
      button.textContent = button.getAttribute('data-busy-label') || @json($adminText('updates.updating'));

      var modal = document.querySelector('[data-webblocks-update-progress-modal]');

      if (modal && window.WBModal && typeof window.WBModal.open === 'function') {
        modal.addEventListener('wb:overlay:close-request', function (closeEvent) {
          closeEvent.preventDefault();
        });
        window.WBModal.open(modal, button);
      }
    });
  </script>
@endpush
