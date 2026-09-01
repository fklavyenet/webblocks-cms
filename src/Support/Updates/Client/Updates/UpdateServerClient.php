<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Support\Updates\Client\PublisherClient;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;

/**
 * Queries the Publisher "latest" endpoint and maps the response into an
 * {@see UpdateCheckResult}. It is parameterized through `publisher-client.*`
 * configuration and {@see VersionResolver} (§5, §7.4):
 *   - field normalization: minimum_client_version / supported_from_version
 *   - cumulative changelog: release['changelog_entries'] is a list of
 *     per-version entries; degrades to a single entry when the Publisher
 *     returns only the target release
 *   - local_newer state + lenient semver normalization
 *
 * Badge tokens are neutral (danger|info|active|pending); per-product admin
 * views map them to their own theme.
 */
class UpdateServerClient
{
  private const RELEASE_DETAIL_GROUPS = [
    'highlights' => 'Highlights',
    'fixes' => 'Fixes',
    'compatibility_notes' => 'Compatibility notes',
    'migration_notes' => 'Migration notes',
    'asset_notes' => 'Asset notes',
    'operator_notes' => 'Operator notes',
    'technical_notes' => 'Technical notes',
  ];

  public function __construct(
    private readonly VersionResolver $versions,
    private readonly TelemetryProvider $telemetry,
  ) {}

  public function check(): UpdateCheckResult
  {
    $serverUrl = rtrim((string) config('publisher-client.server_url', ''), '/');
    $latestPath = (string) config('publisher-client.latest_path', '/api/updates/latest');
    $product = (string) config('publisher-client.product', '');
    $channel = (string) config('publisher-client.channel', 'stable');
    $installedVersion = $this->versions->current();

    if (! config('publisher-client.enabled', true)) {
      return $this->configState(
        'client_disabled', 'Update checks disabled',
        'The update client is disabled in configuration.',
        $serverUrl, $product, $channel, $installedVersion,
      );
    }

    if ($serverUrl === '') {
      return $this->configState(
        'invalid_configuration', 'Update server missing',
        'Configure an update server URL before checking for updates.',
        $serverUrl, $product, $channel, $installedVersion,
      );
    }

    if ($product === '') {
      return $this->configState(
        'invalid_configuration', 'Product not configured',
        'Set publisher-client.product before checking for updates.',
        $serverUrl, $product, $channel, $installedVersion,
      );
    }

    $request = Http::acceptJson()
      ->asJson()
      ->timeout((int) config('publisher-client.timeout_seconds', 5))
      ->connectTimeout((int) config('publisher-client.connect_timeout_seconds', 3))
      ->withHeaders(array_filter([
        'User-Agent' => 'WebBlocks-Publisher-Client/'.$product.'/'.$installedVersion,
      ], fn ($value): bool => is_string($value) && $value !== ''));

    $retryTimes = (int) config('publisher-client.retry_times', 0);

    if ($retryTimes > 0) {
      $request = $request->retry($retryTimes, (int) config('publisher-client.retry_sleep_milliseconds', 150));
    }

    try {
      $response = $request->get($serverUrl.$latestPath, array_merge([
        'product' => $product,
        'channel' => $channel,
        'installed_version' => $installedVersion,
      ], $this->telemetry->updateCheckPayload($product, $installedVersion, $channel)));
    } catch (ConnectionException $exception) {
      return $this->result(
        state: 'server_unreachable',
        label: 'Update server unavailable',
        message: 'The update server could not be reached within the configured timeout.',
        badgeClass: 'danger',
        serverReachable: false,
        apiVersion: null,
        serverUrl: $serverUrl,
        product: $product,
        channel: $channel,
        installedVersion: $installedVersion,
        latestVersion: null,
        updateAvailable: false,
        compatibility: ['status' => 'unknown', 'reasons' => []],
        release: null,
        errorCode: 'server_unreachable',
        errorMessage: $exception->getMessage(),
      );
    }

    $payload = $response->json();

    if (! is_array($payload)) {
      return $this->result(
        state: 'invalid_response',
        label: 'Invalid update response',
        message: 'The update server returned malformed JSON.',
        badgeClass: 'danger',
        serverReachable: $response->successful(),
        apiVersion: null,
        serverUrl: $serverUrl,
        product: $product,
        channel: $channel,
        installedVersion: $installedVersion,
        latestVersion: null,
        updateAvailable: false,
        compatibility: ['status' => 'unknown', 'reasons' => []],
        release: null,
        errorCode: 'invalid_response',
        errorMessage: 'The update server returned malformed JSON.',
      );
    }

    if (! $response->successful()) {
      $errorCode = Arr::get($payload, 'error.code');
      $errorMessage = Arr::get($payload, 'error.message', 'The update server returned an unexpected error.');

      if ($response->status() === 404 && $errorCode === 'release_not_found') {
        return $this->result(
          state: 'no_releases',
          label: 'No releases found',
          message: is_string($errorMessage) ? $errorMessage : 'No releases were found for this channel.',
          badgeClass: 'pending',
          serverReachable: true,
          apiVersion: Arr::get($payload, 'api_version'),
          serverUrl: $serverUrl,
          product: $product,
          channel: $channel,
          installedVersion: $installedVersion,
          latestVersion: null,
          updateAvailable: false,
          compatibility: ['status' => 'unknown', 'reasons' => []],
          release: null,
          errorCode: $errorCode,
          errorMessage: is_string($errorMessage) ? $errorMessage : null,
        );
      }

      return $this->result(
        state: 'server_error',
        label: 'Update server error',
        message: is_string($errorMessage) ? $errorMessage : 'The update server returned an unexpected error.',
        badgeClass: 'danger',
        serverReachable: true,
        apiVersion: Arr::get($payload, 'api_version'),
        serverUrl: $serverUrl,
        product: $product,
        channel: $channel,
        installedVersion: $installedVersion,
        latestVersion: null,
        updateAvailable: false,
        compatibility: ['status' => 'unknown', 'reasons' => []],
        release: null,
        errorCode: is_string($errorCode) ? $errorCode : 'server_error',
        errorMessage: is_string($errorMessage) ? $errorMessage : 'The update server returned an unexpected error.',
      );
    }

    return $this->fromSuccessfulPayload($payload, $serverUrl, $product, $channel, $installedVersion);
  }

  private function fromSuccessfulPayload(array $payload, string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
  {
    $data = Arr::get($payload, 'data');

    if (! is_array($data)) {
      return $this->invalidShape($serverUrl, $product, $channel, $installedVersion);
    }

    $latestVersion = Arr::get($data, 'version');

    if (! is_string($latestVersion) || $latestVersion === '') {
      return $this->invalidShape($serverUrl, $product, $channel, $installedVersion);
    }

    $normalizedRelease = $this->normalizeReleasePayload($data);
    $normalizedRelease['changelog_entries'] = $this->buildChangelogEntries($data, $normalizedRelease, $installedVersion, $product);

    $compatibility = $this->determineCompatibility($installedVersion, $normalizedRelease);

    $installedNormalized = $this->normalizeVersion($installedVersion);
    $latestNormalized = $this->normalizeVersion($latestVersion);
    $updateAvailable = version_compare($latestNormalized, $installedNormalized, '>');
    $localNewer = version_compare($installedNormalized, $latestNormalized, '>');

    $state = 'up_to_date';
    $label = 'Already up to date';
    $message = 'This install is already on the latest published release.';
    $badgeClass = 'active';

    if ($localNewer) {
      // The local build is ahead of the published channel.
      $state = 'local_newer';
      $label = 'Ahead of channel';
      $message = 'This install is newer than the latest published release for the selected channel.';
      $badgeClass = 'active';
    } elseif ($updateAvailable && $compatibility['status'] === 'incompatible') {
      $state = 'incompatible';
      $label = 'Incompatible update available';
      $message = 'A newer release exists, but this install does not meet its compatibility requirements.';
      $badgeClass = 'danger';
    } elseif ($updateAvailable) {
      $state = 'update_available';
      $label = 'Update available';
      $message = 'A newer published release is available from the configured update server.';
      $badgeClass = 'info';
    }

    return $this->result(
      state: $state,
      label: $label,
      message: $message,
      badgeClass: $badgeClass,
      serverReachable: true,
      apiVersion: config('publisher-client.api_version', '1'),
      serverUrl: $serverUrl,
      product: (string) Arr::get($data, 'product', $product),
      channel: (string) Arr::get($data, 'channel', $channel),
      installedVersion: $installedVersion,
      latestVersion: $latestVersion,
      updateAvailable: $updateAvailable,
      compatibility: $compatibility,
      release: $normalizedRelease,
      errorCode: null,
      errorMessage: null,
    );
  }

  private function normalizeReleasePayload(array $release): array
  {
    $version = (string) Arr::get($release, 'version', '');
    $releaseNotes = Arr::get($release, 'release_notes');
    $changelog = Arr::get($release, 'changelog');
    $minimumClientVersion = $this->minimumClientVersion($release);
    $releaseDetails = $this->normalizeReleaseDetails($release);

    return [
      'version' => $version,
      'name' => $releaseDetails['title'] ?? ($version !== '' ? $this->productDisplayName().' '.$version : null),
      'description' => is_string($releaseNotes) && $releaseNotes !== '' ? $releaseNotes : null,
      'changelog' => is_string($changelog) && $changelog !== ''
        ? $changelog
        : (is_string($releaseNotes) && $releaseNotes !== '' ? $releaseNotes : null),
      'release_details' => $releaseDetails,
      'download_url' => Arr::get($release, 'artifact_url') ?: Arr::get($release, 'download.url'),
      'checksum_sha256' => Arr::get($release, 'checksum_sha256') ?: Arr::get($release, 'checksum'),
      'signature' => Arr::get($release, 'signature'),
      'published_at' => Arr::get($release, 'published_at'),
      'is_critical' => (bool) Arr::get($release, 'required', false),
      'is_security' => false,
      'requirements' => [
        'min_php_version' => null,
        'min_laravel_version' => null,
        'supported_from_version' => $minimumClientVersion,
        'supported_until_version' => null,
      ],
      'source_type' => Arr::get($release, 'source_type'),
      'source_reference' => Arr::get($release, 'source_reference'),
      'release_date' => Arr::get($release, 'release_date'),
    ];
  }

  /**
   * Cumulative changelog (§7.4). Prefer an explicit list of intermediate
   * releases from the Publisher (`data.changelog` or `data.releases`); otherwise
   * degrade to a single entry built from the target release. Entries are kept
   * only when strictly newer than the installed version, newest first.
   *
   * @return list<array<string,mixed>>
   */
  private function buildChangelogEntries(array $data, array $normalizedTarget, string $installedVersion, string $product): array
  {
    $rawEntries = null;

    foreach (['changelog', 'releases', 'changelog_entries'] as $key) {
      $candidate = Arr::get($data, $key);

      if (is_array($candidate) && array_is_list($candidate) && $candidate !== []) {
        $rawEntries = $candidate;
        break;
      }
    }

    if ($rawEntries === null) {
      // Degraded single-entry case.
      $rawEntries = [$data];
    }

    $installedNormalized = $this->normalizeVersion($installedVersion);

    $entries = collect($rawEntries)
      ->filter(fn ($entry): bool => is_array($entry))
      ->map(function (array $entry): array {
        $version = (string) Arr::get($entry, 'version', '');
        $details = $this->normalizeReleaseDetails($entry);

        return [
          'version' => $version,
          'name' => $details['title'] ?? ($version !== '' ? $this->productDisplayName().' '.$version : null),
          'summary' => $details['summary'],
          'groups' => $details['groups'],
          'fallback_notes' => $details['fallback_notes'],
          'released_at' => Arr::get($entry, 'published_at') ?? Arr::get($entry, 'release_date'),
        ];
      })
      ->filter(function (array $entry) use ($installedNormalized): bool {
        if ($entry['version'] === '') {
          return true;
        }

        return version_compare($this->normalizeVersion($entry['version']), $installedNormalized, '>');
      })
      ->values()
      ->all();

    usort($entries, function (array $a, array $b): int {
      return version_compare($this->normalizeVersion((string) $b['version']), $this->normalizeVersion((string) $a['version']));
    });

    return $entries;
  }

  /**
   * Read the compatibility floor from whichever field the Publisher sends
   * (§5.5): minimum_client_version, supported_from_version, or the nested
   * requirements.supported_from_version.
   */
  private function minimumClientVersion(array $release): ?string
  {
    foreach (['minimum_client_version', 'supported_from_version', 'requirements.supported_from_version'] as $path) {
      $value = Arr::get($release, $path);

      if (is_string($value) && $value !== '') {
        return $value;
      }
    }

    return null;
  }

  private function normalizeReleaseDetails(array $release): array
  {
    $title = $this->releaseTextValue($release, 'title');
    $summary = $this->releaseTextValue($release, 'summary');
    $fallbackNotes = $this->releaseNoteItems(Arr::get($release, 'release_notes'));
    $groups = [];

    foreach (self::RELEASE_DETAIL_GROUPS as $key => $label) {
      $items = $this->releaseNoteItems($this->releaseValue($release, $key));

      if ($items !== []) {
        $groups[] = [
          'key' => $key,
          'label' => $label,
          'items' => $items,
        ];
      }
    }

    $engineNote = sprintf(
      'Installed update engine: Publisher Client %s (%s).',
      PublisherClient::VERSION,
      (string) config('publisher-client.distribution', 'composer'),
    );
    $technicalIndex = array_search('technical_notes', array_column($groups, 'key'), true);

    if ($technicalIndex === false) {
      $groups[] = [
        'key' => 'technical_notes',
        'label' => self::RELEASE_DETAIL_GROUPS['technical_notes'],
        'items' => [$engineNote],
      ];
    } else {
      $groups[$technicalIndex]['items'][] = $engineNote;
    }

    if ($summary === null && $groups === [] && $fallbackNotes !== []) {
      $summary = array_shift($fallbackNotes);
    }

    return [
      'title' => $title,
      'summary' => $summary,
      'groups' => $groups,
      'fallback_notes' => $fallbackNotes,
      'has_notes' => $title !== null || $summary !== null || $groups !== [] || $fallbackNotes !== [],
    ];
  }

  private function releaseTextValue(array $release, string $key): ?string
  {
    $value = $this->releaseValue($release, $key);

    if (! is_string($value)) {
      return null;
    }

    $value = trim($value);

    return $value !== '' ? $value : null;
  }

  private function releaseValue(array $release, string $key): mixed
  {
    foreach (['release_details.'.$key, 'details.'.$key, 'meta.release_details.'.$key, 'meta.details.'.$key, $key] as $path) {
      if (Arr::has($release, $path)) {
        return Arr::get($release, $path);
      }
    }

    return null;
  }

  private function releaseNoteItems(mixed $value): array
  {
    if (is_string($value)) {
      return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
        ->map(fn ($line) => $this->cleanReleaseNoteLine((string) $line))
        ->filter()
        ->values()
        ->all();
    }

    if (! is_array($value)) {
      return [];
    }

    return collect($value)
      ->flatMap(fn ($item) => $this->releaseNoteItems($item))
      ->map(fn ($line) => $this->cleanReleaseNoteLine((string) $line))
      ->filter()
      ->values()
      ->all();
  }

  private function cleanReleaseNoteLine(string $line): string
  {
    return trim((string) preg_replace('/^\s*(?:[-*]|\d+[.)])\s+/', '', trim($line)));
  }

  private function determineCompatibility(string $installedVersion, array $release): array
  {
    $minimumClientVersion = Arr::get($release, 'requirements.supported_from_version');
    $reasons = [];
    $status = 'compatible';

    if (is_string($minimumClientVersion) && $minimumClientVersion !== ''
      && version_compare($this->normalizeVersion($installedVersion), $this->normalizeVersion($minimumClientVersion), '<')) {
      $status = 'incompatible';
      $reasons[] = 'Installed version '.$installedVersion.' is lower than the minimum supported client version '.$minimumClientVersion.'.';
    }

    return [
      'status' => $status,
      'reasons' => $reasons,
    ];
  }

  /**
   * Lenient semver normalization: trim and strip a leading
   * "v" so "v1.2.3" and "1.2.3" compare equal. Deliberately not strict — it does
   * not reject non-semver, it just canonicalizes the common prefix.
   */
  private function normalizeVersion(string $version): string
  {
    $version = trim($version);

    if ($version !== '' && ($version[0] === 'v' || $version[0] === 'V')) {
      $version = substr($version, 1);
    }

    return $version;
  }

  private function productDisplayName(): string
  {
    $name = trim((string) config('publisher-client.product_name', ''));

    if ($name !== '') {
      return $name;
    }

    return (string) config('publisher-client.product', 'Release');
  }

  private function configState(string $state, string $label, string $message, string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
  {
    return $this->result(
      state: $state,
      label: $label,
      message: $message,
      badgeClass: 'danger',
      serverReachable: false,
      apiVersion: null,
      serverUrl: $serverUrl,
      product: $product,
      channel: $channel,
      installedVersion: $installedVersion,
      latestVersion: null,
      updateAvailable: false,
      compatibility: ['status' => 'unknown', 'reasons' => []],
      release: null,
      errorCode: $state,
      errorMessage: $message,
    );
  }

  private function invalidShape(string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
  {
    return $this->result(
      state: 'invalid_response',
      label: 'Invalid update response',
      message: 'The update server response is missing required keys.',
      badgeClass: 'danger',
      serverReachable: true,
      apiVersion: null,
      serverUrl: $serverUrl,
      product: $product,
      channel: $channel,
      installedVersion: $installedVersion,
      latestVersion: null,
      updateAvailable: false,
      compatibility: ['status' => 'unknown', 'reasons' => []],
      release: null,
      errorCode: 'invalid_response',
      errorMessage: 'The update server response is missing required keys.',
    );
  }

  private function result(
    string $state,
    string $label,
    string $message,
    string $badgeClass,
    bool $serverReachable,
    ?string $apiVersion,
    string $serverUrl,
    string $product,
    string $channel,
    string $installedVersion,
    ?string $latestVersion,
    bool $updateAvailable,
    array $compatibility,
    ?array $release,
    ?string $errorCode,
    ?string $errorMessage,
  ): UpdateCheckResult {
    return new UpdateCheckResult(
      state: $state,
      label: $label,
      message: $message,
      badgeClass: $badgeClass,
      serverReachable: $serverReachable,
      apiVersion: $apiVersion,
      serverUrl: $serverUrl,
      product: $product,
      channel: $channel,
      installedVersion: $installedVersion,
      latestVersion: $latestVersion,
      updateAvailable: $updateAvailable,
      compatibility: $compatibility,
      release: $release,
      errorCode: $errorCode,
      errorMessage: $errorMessage,
      checkedAt: CarbonImmutable::now(),
    );
  }
}
