<?php

namespace WebBlocks\Cms\Support\System\Updates\Publishing;

use Dotenv\Dotenv;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use WebBlocks\Cms\Support\Updates\ReleaseDefaults;

final class UpdatePublisher
{
  private const PUBLISHER_ENV_KEYS = [
    'token' => 'WEBBLOCKS_PUBLISHER_TOKEN',
    'signing_key' => 'WEBBLOCKS_PUBLISHER_SIGNING_KEY',
  ];

  private const DETAIL_FIELDS = [
    'title',
    'summary',
    'highlights',
    'fixes',
    'compatibility_notes',
    'migration_notes',
    'asset_notes',
    'operator_notes',
    'technical_notes',
  ];

  private ?array $projectEnvironment = null;

  public function publish(array $options = []): UpdatePublishResult
  {
    $payloadPath = $this->resolvePayloadPath($options);
    $payload = $this->readPayload($payloadPath);
    $artifactPath = $this->resolveArtifactPath($options, $payload);
    $checksum = $this->verifyChecksum($artifactPath, $payload);
    $configuration = $this->publisherConfiguration();
    $product = ReleaseDefaults::PRODUCT_KEY;
    $channel = ReleaseDefaults::CHANNEL;
    $version = $this->stringOption($options, 'version')
      ?? $this->payloadString($payload, 'version')
      ?? throw new RuntimeException('Publisher payload is missing a release version.');
    $token = $configuration['token'];
    $publisherUrl = $configuration['url'];
    $configuredKeys = $this->configuredKeyStatuses($configuration);
    $this->signChecksum($checksum);

    if (($options['dry_run'] ?? false) === true) {
      return new UpdatePublishResult(
        status: 'dry_run',
        message: 'Dry-run passed. Artifact, checksum, metadata, endpoint, and token configuration were checked without publishing.',
        product: $product,
        channel: $channel,
        version: $version,
        artifactPath: $artifactPath,
        payloadPath: $payloadPath,
        checksumSha256: $checksum,
        tokenConfigured: $token !== '',
        published: false,
        verified: false,
        configuredKeys: $configuredKeys,
      );
    }

    if ($token === '') {
      return new UpdatePublishResult(
        status: 'skipped',
        message: 'Update publisher token is not configured. Artifact was generated but not published.',
        product: $product,
        channel: $channel,
        version: $version,
        artifactPath: $artifactPath,
        payloadPath: $payloadPath,
        checksumSha256: $checksum,
        tokenConfigured: false,
        published: false,
        verified: false,
        configuredKeys: $configuredKeys,
      );
    }

    $publishResponse = $this->sendPublishRequest($publisherUrl, $token, $artifactPath, $payload, $product, $channel, $version, $checksum);
    $latestResponse = $this->verifyLatestRelease($this->apiBaseUrl($publisherUrl), $product, $channel, $version, $checksum);

    return new UpdatePublishResult(
      status: 'published',
      message: 'Update publisher accepted the artifact and latest verification matched the published release metadata.',
      product: $product,
      channel: $channel,
      version: $version,
      artifactPath: $artifactPath,
      payloadPath: $payloadPath,
      checksumSha256: $checksum,
      tokenConfigured: true,
      published: true,
      verified: true,
      publishResponse: $publishResponse,
      latestResponse: $latestResponse,
      configuredKeys: $configuredKeys,
    );
  }

  private function resolvePayloadPath(array $options): string
  {
    $explicit = $this->stringOption($options, 'payload');

    if ($explicit !== null) {
      return $this->existingFile($explicit, 'Publisher payload');
    }

    $version = $this->stringOption($options, 'version');
    $candidates = $this->payloadCandidates($version);

    if ($candidates === []) {
      throw new RuntimeException($version === null
        ? 'No retained update-server publisher payload was found. Run composer release:prepare or pass --payload.'
        : "No retained update-server publisher payload was found for version {$version}. Pass --payload.");
    }

    if (count($candidates) > 1) {
      throw new RuntimeException('Multiple retained update-server publisher payloads were found. Pass --version or --payload to publish explicitly.');
    }

    return $candidates[0];
  }

  private function payloadCandidates(?string $version): array
  {
    $roots = array_filter([
      storage_path('app/webblocks-cms-release'),
      storage_path('app/webblocks-cms-release/'.($version ?? '')),
    ], fn (string $path): bool => $path !== '' && is_dir($path));
    $pattern = $version === null
      ? '/^webblocks-cms-\d+\.\d+\.\d+(?:[-.][0-9A-Za-z.-]+)?-update-server-payload\.json$/'
      : '/^webblocks-cms-'.preg_quote($version, '/').'-update-server-payload\.json$/';
    $paths = [];

    foreach ($roots as $root) {
      $finder = Finder::create()
        ->files()
        ->ignoreUnreadableDirs()
        ->name('*-update-server-payload.json')
        ->depth('<= 4')
        ->in($root);

      foreach ($finder as $file) {
        if (preg_match($pattern, $file->getFilename()) === 1) {
          $paths[$file->getRealPath()] = $file->getMTime();
        }
      }
    }

    arsort($paths);

    if ($version !== null) {
      return array_keys($paths);
    }

    $latestMtime = reset($paths);

    if ($latestMtime === false) {
      return [];
    }

    return array_keys(array_filter($paths, fn (int $mtime): bool => $mtime === $latestMtime));
  }

  private function readPayload(string $payloadPath): array
  {
    $payload = json_decode(file_get_contents($payloadPath) ?: '', true);

    if (! is_array($payload)) {
      throw new RuntimeException("Publisher payload is not valid JSON: {$payloadPath}");
    }

    return $payload;
  }

  private function resolveArtifactPath(array $options, array $payload): string
  {
    $artifactPath = $this->stringOption($options, 'artifact')
      ?? $this->payloadString($payload, 'artifact_path');

    if ($artifactPath === null) {
      throw new RuntimeException('No artifact path was provided. Pass --artifact or include artifact_path in the publisher payload.');
    }

    return $this->existingFile($artifactPath, 'Package artifact');
  }

  private function verifyChecksum(string $artifactPath, array $payload): string
  {
    $expected = strtolower((string) Arr::get($payload, 'checksum_sha256', ''));

    if (! preg_match('/^[a-f0-9]{64}$/', $expected)) {
      throw new RuntimeException('Publisher payload is missing a valid checksum_sha256 value.');
    }

    $actual = strtolower((string) hash_file('sha256', $artifactPath));

    if (! hash_equals($expected, $actual)) {
      throw new RuntimeException('Artifact checksum mismatch. The artifact was not published.');
    }

    return $actual;
  }

  /**
   * Sign the release checksum with the maintainer's Ed25519 secret key so
   * installs can verify the release against the pinned public key. Returns null
   * when no signing key is configured (releases stay unsigned until it is set).
   */
  private function signChecksum(string $checksum): ?string
  {
    $signingKey = $this->signingKey();

    if ($signingKey === '') {
      return null;
    }

    $secret = base64_decode($signingKey, true);

    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
      throw new RuntimeException('WEBBLOCKS_PUBLISHER_SIGNING_KEY is not a valid base64 Ed25519 secret key.');
    }

    return base64_encode(sodium_crypto_sign_detached(strtolower($checksum), $secret));
  }

  private function sendPublishRequest(string $publisherUrl, string $token, string $artifactPath, array $payload, string $product, string $channel, string $version, string $checksum): array
  {
    $fields = array_filter([
      'product' => $product,
      'channel' => $channel,
      'version' => $version,
      'checksum_sha256' => $checksum,
      'signature' => $this->signChecksum($checksum),
      'artifact_filename' => $this->payloadString($payload, 'artifact_filename') ?? basename($artifactPath),
      'minimum_client_version' => $this->payloadString($payload, 'minimum_client_version'),
      'source_reference' => $this->payloadString($payload, 'source_reference') ?? 'v'.$version,
      'release_notes' => $this->payloadString($payload, 'release_notes') ?? $this->payloadNotes($payload),
      'notes' => $this->payloadNotes($payload),
      'details' => $this->releaseDetails($payload),
      'release_details' => $this->releaseDetails($payload),
    ], fn (mixed $value): bool => $value !== null && $value !== '');

    try {
      $response = Http::acceptJson()
        ->asMultipart()
        ->timeout((int) config('webblocks-updates.publisher.timeout_seconds', 120))
        ->connectTimeout((int) config('webblocks-updates.publisher.connect_timeout_seconds', 5))
        ->withToken($token, 'Bearer')
        ->attach('package', fopen($artifactPath, 'r'), basename($artifactPath), ['Content-Type' => 'application/zip'])
        ->post($this->publishEndpoint($publisherUrl), $fields);
    } catch (ConnectionException $exception) {
      throw new RuntimeException('Update publisher request failed: '.$exception->getMessage(), previous: $exception);
    }

    if ($response->failed()) {
      throw new RuntimeException($this->failedPublishMessage($response->status(), $response->json(), $response->body(), $product, $channel));
    }

    $responsePayload = $response->json();

    return is_array($responsePayload) ? $responsePayload : [];
  }

  private function verifyLatestRelease(string $publisherUrl, string $product, string $channel, string $version, string $checksum): array
  {
    try {
      $response = Http::acceptJson()
        ->timeout((int) config('webblocks-updates.timeout_seconds', 5))
        ->connectTimeout((int) config('webblocks-updates.connect_timeout_seconds', 3))
        ->get($publisherUrl.ReleaseDefaults::LATEST_PATH, [
          'product' => $product,
          'channel' => $channel,
        ])
        ->throw();
    } catch (ConnectionException|RequestException $exception) {
      throw new RuntimeException('Update publisher latest verification failed: '.$exception->getMessage(), previous: $exception);
    }

    $payload = $response->json();

    if (! is_array($payload)) {
      throw new RuntimeException('Update publisher latest verification returned malformed JSON.');
    }

    $data = Arr::get($payload, 'data', []);
    $latestVersion = is_array($data) ? (string) ($data['version'] ?? '') : '';
    $latestProduct = is_array($data) ? (string) ($data['product'] ?? '') : '';
    $latestChannel = is_array($data) ? (string) ($data['channel'] ?? '') : '';
    $latestChecksum = is_array($data) ? (string) ($data['checksum_sha256'] ?? '') : '';
    $artifactUrl = is_array($data) ? (string) ($data['artifact_url'] ?? '') : '';

    if ($latestVersion !== $version || $latestProduct !== $product || $latestChannel !== $channel || $latestChecksum !== $checksum || $artifactUrl === '') {
      throw new RuntimeException("Update publisher latest verification failed. Expected {$product} {$channel} {$version} with matching checksum and artifact URL.");
    }

    return $payload;
  }

  private function releaseDetails(array $payload): ?string
  {
    $details = [];
    $source = Arr::get($payload, 'details', Arr::get($payload, 'release_details', []));

    if (is_array($source)) {
      foreach (self::DETAIL_FIELDS as $field) {
        if (array_key_exists($field, $source)) {
          $details[$field] = $source[$field];
        }
      }
    }

    foreach (self::DETAIL_FIELDS as $field) {
      if (array_key_exists($field, $payload)) {
        $details[$field] = $payload[$field];
      }
    }

    return $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES);
  }

  private function payloadNotes(array $payload): ?string
  {
    $notes = Arr::get($payload, 'notes');

    if (is_array($notes)) {
      return implode("\n", array_filter(array_map(fn (mixed $note): string => trim((string) $note), $notes)));
    }

    return is_string($notes) ? $notes : null;
  }

  private function publisherUrl(): string
  {
    $url = rtrim(ReleaseDefaults::publishUrl(), '/');

    if ($url === '') {
      throw new RuntimeException('Update publisher URL is not configured.');
    }

    return $url;
  }

  private function publisherToken(): string
  {
    return $this->publisherConfiguration()['token'];
  }

  private function publisherConfiguration(): array
  {
    $url = rtrim(ReleaseDefaults::publishUrl(), '/');

    if ($url === '') {
      throw new RuntimeException('Update publisher URL is not configured.');
    }

    return [
      'url' => $url,
      'token' => $this->publisherConfigValue('token'),
      'signing_key' => $this->signingKey(),
      'product' => ReleaseDefaults::PRODUCT_KEY,
      'channel' => ReleaseDefaults::CHANNEL,
    ];
  }

  private function publisherConfigValue(string $key, ?string $default = null): string
  {
    $envValue = $this->cachedConfigProjectEnvValue(self::PUBLISHER_ENV_KEYS[$key]);

    if ($envValue !== null) {
      return $envValue;
    }

    $configValue = config('webblocks-updates.publisher.'.$key);

    if (is_scalar($configValue) && trim((string) $configValue) !== '') {
      return trim((string) $configValue);
    }

    return $default ?? '';
  }

  private function signingKey(): string
  {
    $processValue = getenv(self::PUBLISHER_ENV_KEYS['signing_key']);

    if (is_string($processValue) && trim($processValue) !== '') {
      return trim($processValue);
    }

    $envValue = $this->projectEnvironment()[self::PUBLISHER_ENV_KEYS['signing_key']] ?? null;

    if (is_string($envValue) && trim($envValue) !== '') {
      return trim($envValue);
    }

    return trim((string) config('webblocks-updates.signature.signing_key', ''));
  }

  private function cachedConfigProjectEnvValue(string $key): ?string
  {
    if (! app()->configurationIsCached()) {
      return null;
    }

    $value = $this->projectEnvironment()[$key] ?? null;

    return is_string($value) && trim($value) !== '' ? trim($value) : null;
  }

  private function projectEnvironment(): array
  {
    if ($this->projectEnvironment !== null) {
      return $this->projectEnvironment;
    }

    $path = $this->projectEnvironmentPath();

    if (! is_file($path)) {
      return $this->projectEnvironment = [];
    }

    $environment = Dotenv::parse(file_get_contents($path) ?: '');

    return $this->projectEnvironment = array_intersect_key($environment, array_flip(self::PUBLISHER_ENV_KEYS));
  }

  private function projectEnvironmentPath(): string
  {
    if (app()->bound('webblocks.publisher.env_path')) {
      $path = app('webblocks.publisher.env_path');

      if (is_string($path) && $path !== '') {
        return $path;
      }
    }

    return base_path('.env');
  }

  private function configuredKeyStatuses(array $configuration): array
  {
    return [
      self::PUBLISHER_ENV_KEYS['token'] => $configuration['token'] !== '',
      self::PUBLISHER_ENV_KEYS['signing_key'] => $configuration['signing_key'] !== '',
    ];
  }

  private function publishEndpoint(string $publisherUrl): string
  {
    return str_ends_with($publisherUrl, ReleaseDefaults::PUBLISH_PATH)
      ? $publisherUrl
      : $publisherUrl.ReleaseDefaults::PUBLISH_PATH;
  }

  private function apiBaseUrl(string $publisherUrl): string
  {
    return str_ends_with($publisherUrl, ReleaseDefaults::PUBLISH_PATH)
      ? substr($publisherUrl, 0, -strlen(ReleaseDefaults::PUBLISH_PATH))
      : $publisherUrl;
  }

  private function failedPublishMessage(int $status, mixed $json, string $body, string $product, string $channel): string
  {
    $summary = $this->safeResponseSummary($json, $body);
    $message = "Update publisher request failed with HTTP {$status}.";

    if ($status === 401) {
      $message .= " Check that the Bearer publish token is valid for product [{$product}] and channel [{$channel}]. Publisher tokens may be product/channel scoped.";
    }

    if ($summary !== '') {
      $message .= ' Response: '.$summary;
    }

    return $message;
  }

  private function safeResponseSummary(mixed $json, string $body): string
  {
    $summary = '';

    if (is_array($json)) {
      $parts = array_filter([
        'message' => $this->safeScalar(Arr::get($json, 'message')),
        'status' => $this->safeScalar(Arr::get($json, 'status')),
        'service' => $this->safeScalar(Arr::get($json, 'service')),
      ]);

      $summary = implode('; ', array_map(
        fn (string $key, string $value): string => $key.': '.$value,
        array_keys($parts),
        $parts,
      ));
    } else {
      $summary = trim(strip_tags($body));
    }

    return $this->redactSecrets(mb_substr(preg_replace('/\s+/', ' ', $summary) ?? '', 0, 300));
  }

  private function safeScalar(mixed $value): ?string
  {
    if (! is_scalar($value)) {
      return null;
    }

    $value = trim((string) $value);

    return $value === '' ? null : $this->redactSecrets($value);
  }

  private function redactSecrets(string $value): string
  {
    $token = $this->publisherToken();

    if ($token !== '') {
      $value = str_replace($token, '[redacted]', $value);
    }

    return preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [redacted]', $value) ?? $value;
  }

  private function existingFile(string $path, string $label): string
  {
    $path = $this->absolutePath($path);

    if (! is_file($path)) {
      throw new RuntimeException("{$label} does not exist: {$path}");
    }

    return $path;
  }

  private function absolutePath(string $path): string
  {
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
      return $path;
    }

    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
      return $path;
    }

    if (str_starts_with($path, 'storage/app/')) {
      return Storage::path(substr($path, strlen('storage/app/')));
    }

    return base_path($path);
  }

  private function stringOption(array $options, string $key): ?string
  {
    $value = $options[$key] ?? null;

    return is_string($value) && trim($value) !== '' ? trim($value) : null;
  }

  private function payloadString(array $payload, string $key): ?string
  {
    $value = Arr::get($payload, $key);

    return is_string($value) && trim($value) !== '' ? trim($value) : null;
  }
}
