<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Support\System\InstalledVersionStore;

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
    private readonly InstalledVersionStore $installedVersionStore,
  ) {}

  public function check(): UpdateCheckResult
  {
    $serverUrl = rtrim((string) config('webblocks-updates.server_url', ''), '/');
    $product = (string) config('webblocks-updates.product', 'webblocks-cms');
    $channel = (string) config('webblocks-updates.channel', 'stable');
    $installedVersion = $this->installedVersionStore->storedVersion()
      ?? $this->installedVersionStore->currentVersion();

    if (! config('webblocks-updates.enabled', true)) {
      return $this->result(
        state: 'client_disabled',
        label: 'Update checks disabled',
        message: 'The CMS update client is disabled in configuration.',
        badgeClass: 'wb-status-danger',
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
        errorCode: 'client_disabled',
        errorMessage: 'The CMS update client is disabled in configuration.',
      );
    }

    if ($serverUrl === '') {
      return $this->result(
        state: 'invalid_configuration',
        label: 'Update server missing',
        message: 'Configure an update server URL before checking for updates.',
        badgeClass: 'wb-status-danger',
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
        errorCode: 'invalid_configuration',
        errorMessage: 'Configure an update server URL before checking for updates.',
      );
    }

    $request = Http::acceptJson()
      ->asJson()
      ->timeout((int) config('webblocks-updates.timeout_seconds', 5))
      ->connectTimeout((int) config('webblocks-updates.connect_timeout_seconds', 3))
      ->withHeaders(array_filter([
        'User-Agent' => 'WebBlocks-CMS/'.$installedVersion,
        'X-WebBlocks-Site-Url' => (string) config('webblocks-updates.site_url', config('app.url')),
        'X-WebBlocks-Instance-Id' => config('webblocks-updates.instance_id'),
      ], fn ($value): bool => is_string($value) && $value !== ''));

    $retryTimes = (int) config('webblocks-updates.retry_times', 0);

    if ($retryTimes > 0) {
      $request = $request->retry($retryTimes, (int) config('webblocks-updates.retry_sleep_milliseconds', 150));
    }

    try {
      $response = $request->get($serverUrl.'/api/updates/latest', [
        'product' => $product,
        'channel' => $channel,
        'installed_version' => $installedVersion,
        'php_version' => PHP_VERSION,
        'laravel_version' => Application::VERSION,
      ]);
    } catch (ConnectionException $exception) {
      return $this->result(
        state: 'server_unreachable',
        label: 'Update server unavailable',
        message: 'The update server could not be reached within the configured timeout.',
        badgeClass: 'wb-status-danger',
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
        badgeClass: 'wb-status-danger',
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
          message: $errorMessage,
          badgeClass: 'wb-status-pending',
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
          errorMessage: $errorMessage,
        );
      }

      return $this->result(
        state: 'server_error',
        label: 'Update server error',
        message: $errorMessage,
        badgeClass: 'wb-status-danger',
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
    $compatibility = $this->determineCompatibility($installedVersion, $normalizedRelease);
    $updateAvailable = version_compare($latestVersion, $installedVersion, '>');

    $state = 'up_to_date';
    $label = 'Already up to date';
    $message = 'This install already matches the latest published release for the selected channel.';
    $badgeClass = 'wb-status-active';

    if ($updateAvailable && $compatibility['status'] === 'incompatible') {
      $state = 'incompatible';
      $label = 'Incompatible update available';
      $message = 'A newer release exists, but this install does not meet its compatibility requirements.';
      $badgeClass = 'wb-status-danger';
    } elseif ($updateAvailable) {
      $state = 'update_available';
      $label = 'Update available';
      $message = 'A newer published release is available from the configured update server.';
      $badgeClass = 'wb-status-info';
    }

    return $this->result(
      state: $state,
      label: $label,
      message: $message,
      badgeClass: $badgeClass,
      serverReachable: true,
      apiVersion: config('webblocks-updates.api_version', '1'),
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
    $minimumClientVersion = Arr::get($release, 'minimum_client_version');
    $releaseDetails = $this->normalizeReleaseDetails($release);

    return [
      'version' => $version,
      'name' => $releaseDetails['title'] ?? ($version !== '' ? 'WebBlocks CMS '.$version : null),
      'description' => is_string($releaseNotes) && $releaseNotes !== '' ? $releaseNotes : null,
      'changelog' => is_string($changelog) && $changelog !== ''
        ? $changelog
        : (is_string($releaseNotes) && $releaseNotes !== '' ? $releaseNotes : null),
      'release_details' => $releaseDetails,
      'download_url' => Arr::get($release, 'artifact_url') ?: Arr::get($release, 'download.url'),
      'checksum_sha256' => Arr::get($release, 'checksum_sha256') ?: Arr::get($release, 'checksum'),
      'published_at' => Arr::get($release, 'published_at'),
      'is_critical' => (bool) Arr::get($release, 'required', false),
      'is_security' => false,
      'requirements' => [
        'min_php_version' => null,
        'min_laravel_version' => null,
        'supported_from_version' => is_string($minimumClientVersion) && $minimumClientVersion !== '' ? $minimumClientVersion : null,
        'supported_until_version' => null,
      ],
      'source_type' => Arr::get($release, 'source_type'),
      'source_reference' => Arr::get($release, 'source_reference'),
      'release_date' => Arr::get($release, 'release_date'),
    ];
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
    foreach (['release_details.'.$key, 'details.'.$key, $key] as $path) {
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

    if (is_string($minimumClientVersion) && $minimumClientVersion !== '' && version_compare($installedVersion, $minimumClientVersion, '<')) {
      $status = 'incompatible';
      $reasons[] = 'Installed version '.$installedVersion.' is lower than the minimum supported client version '.$minimumClientVersion.'.';
    }

    return [
      'status' => $status,
      'reasons' => $reasons,
    ];
  }

  private function invalidShape(string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
  {
    return $this->result(
      state: 'invalid_response',
      label: 'Invalid update response',
      message: 'The update server response is missing required keys.',
      badgeClass: 'wb-status-danger',
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
