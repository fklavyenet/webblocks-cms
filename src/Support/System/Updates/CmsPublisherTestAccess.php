<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use WebBlocks\Cms\Support\Updates\Client\Contracts\UpdateRequestAuthorizer;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateAuthorizationException;
use WebBlocks\Cms\Support\Updates\ReleaseDefaults;

final class CmsPublisherTestAccess implements UpdateRequestAuthorizer
{
  public function __construct(private readonly CmsPublisherArtifact $artifact) {}

  public function authorize(PendingRequest $request, string $url, string $purpose, array $context = []): PendingRequest
  {
    if (config('publisher-client.channel') !== 'test') {
      return $request;
    }
    if ($purpose === 'check' && ($context['product'] ?? null) === ReleaseDefaults::PRODUCT_KEY
      && ($context['channel'] ?? null) === 'test' && self::publisherPath($url, ['/api/updates/latest', '/api/updates/plan'])) {
      $grant = $this->console('authorize-check', ['installed_version' => $context['installed_version'] ?? '']);

      return $request->withoutRedirecting()->withToken($grant);
    }
    if ($purpose === 'download') {
      $url = self::downloadUrl($url);
      $id = (int) basename($url);
      $grant = $this->console('authorize-download', ['release_id' => $id]);

      return $request->withoutRedirecting()->withToken($grant)
        ->withResponseMiddleware(function (ResponseInterface $response) use ($id): ResponseInterface {
          $proof = $response->getHeaderLine('X-Publisher-Pilot-Receipt');
          if ($response->getStatusCode() === 200 && preg_match('~\A[A-Za-z0-9+/=_-]{1,4096}\z~', $proof) === 1) {
            $this->artifact->receiptProof = $proof;
            $this->artifact->releaseId = $id;
          }

          return $response;
        });
    }
    throw $this->unavailable();
  }

  public function pair(#[\SensitiveParameter] string $code): void
  {
    $this->console('pair', ['installation_key' => $this->installationKey()], $code);
  }

  public function report(bool $healthy): bool
  {
    $artifact = $this->artifact;
    if (config('publisher-client.channel') !== 'test' || $artifact->releaseId === null || $artifact->receiptProof === null
      || $artifact->version === null || $artifact->checksum === null) {
      return false;
    }
    for ($attempt = 0; $attempt < 2; $attempt++) {
      try {
        $grant = $this->console('authorize-download', ['release_id' => $artifact->releaseId]);
        $response = Http::acceptJson()->withoutRedirecting()->timeout(8)->withToken($grant)
          ->withHeaders(['X-Publisher-Pilot-Receipt' => $artifact->receiptProof])
          ->post(rtrim((string) config('publisher-client.server_url'), '/').'/api/updates/test/releases/'.$artifact->releaseId.'/receipt', [
            'version' => $artifact->version,
            'checksum_sha256' => $artifact->checksum,
            'healthy' => $healthy,
          ]);
        if ($response->successful() && $response->json('data.release_id') === $artifact->releaseId
          && $response->json('data.healthy') === $healthy) {
          return true;
        }
      } catch (\Throwable) {
        // Retry the same receipt proof with a fresh download grant.
      }
    }

    return false;
  }

  public static function downloadUrl(?string $url): string
  {
    $path = $url === null ? null : parse_url($url, PHP_URL_PATH);
    if (! is_string($path) || preg_match('~\A/test-downloads/[1-9][0-9]*\z~', $path) !== 1
      || ! self::publisherPath($url, [$path])) {
      throw new UpdateAuthorizationException(__('webblocks-cms::admin.update_cli.publisher_test_unavailable'));
    }

    return $url;
  }

  private static function publisherPath(string $url, array $allowed): bool
  {
    $origin = rtrim((string) config('publisher-client.server_url'), '/');
    $path = parse_url($url, PHP_URL_PATH);

    return is_string($path) && str_starts_with($origin, 'https://') && $url === $origin.$path
      && in_array($path, $allowed, true) && parse_url($url, PHP_URL_USER) === null;
  }

  private function console(string $action, #[\SensitiveParameter] array $body, #[\SensitiveParameter] ?string $pairingCode = null): string
  {
    $console = rtrim((string) config('webblocks-updates.test.console_url'), '/');
    $installation = (string) config('webblocks-updates.test.installation_id');
    $origin = parse_url($console);
    if (($origin['scheme'] ?? null) !== 'https' || empty($origin['host'])
      || array_diff(array_keys($origin), ['scheme', 'host', 'port']) !== [] || ! Str::isUuid($installation)) {
      throw $this->unavailable();
    }
    try {
      $token = $pairingCode ?? $this->installationKey();
      $response = Http::acceptJson()->asJson()->withoutRedirecting()->timeout(8)->withToken($token)
        ->post($console.'/api/v1/publisher-test/'.$action, [
          'product' => ReleaseDefaults::PRODUCT_KEY,
          'installation_id' => $installation,
          ...$body,
        ]);
      if ($response->successful() && $response->json('ok') === true) {
        $authorization = $response->json('authorization');
        if ($action === 'pair' || (is_string($authorization) && $authorization !== '')) {
          return $authorization ?? '';
        }
      }
    } catch (\Throwable) {
      throw $this->unavailable();
    }
    throw $this->unavailable();
  }

  private function installationKey(): string
  {
    $key = (string) config('webblocks-updates.test.installation_key');
    if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $key) !== 1) {
      throw $this->unavailable();
    }

    return $key;
  }

  private function unavailable(): UpdateAuthorizationException
  {
    return new UpdateAuthorizationException(__('webblocks-cms::admin.update_cli.publisher_test_unavailable'));
  }
}
