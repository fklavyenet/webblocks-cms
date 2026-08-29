<?php

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Models\SupportConnection;
use WebBlocks\Cms\Support\WebBlocks;

final class CompatibleSupportProvider
{
  public const PROTOCOL = 'webblocks-support';

  public const VERSION = '1.0';

  public const REQUIRED_CAPABILITIES = ['ticket.create', 'ticket.list', 'ticket.read', 'ticket.reply'];

  public function __construct(
    private readonly InstallId $install,
    private readonly SupportProviderUrlGuard $urlGuard,
  ) {}

  public function discover(string $providerUrl): array
  {
    $providerUrl = $this->urlGuard->normalize($providerUrl);
    $payload = $this->json($this->publicRequest()->get($providerUrl.'/.well-known/webblocks-support'), 'discover the support provider');

    if (($payload['protocol'] ?? null) !== self::PROTOCOL || ($payload['version'] ?? null) !== self::VERSION) {
      throw new SupportProviderException('The server does not provide WebBlocks Support Protocol 1.0.');
    }

    $apiBaseUrl = $this->urlGuard->normalize((string) ($payload['api_base_url'] ?? ''));

    if ($this->origin($apiBaseUrl) !== $this->origin($providerUrl)) {
      throw new SupportProviderException('The support API must use the provider origin.');
    }

    $capabilities = array_values(array_unique(array_filter((array) ($payload['capabilities'] ?? []), 'is_string')));

    foreach (self::REQUIRED_CAPABILITIES as $capability) {
      if (! in_array($capability, $capabilities, true)) {
        throw new SupportProviderException('The support provider is missing a required capability.');
      }
    }

    return [
      'provider_url' => $providerUrl,
      'provider_name' => trim((string) ($payload['name'] ?? '')) ?: parse_url($providerUrl, PHP_URL_HOST),
      'api_base_url' => $apiBaseUrl,
      'protocol_version' => self::VERSION,
      'capabilities' => $capabilities,
    ];
  }

  public function beginActivation(array $provider): array
  {
    $url = $this->urlGuard->normalize($provider['api_base_url'].'/activations');
    $payload = $this->json($this->publicRequest()->post($url, [
      'install_ref' => $this->install->value(),
      'product' => 'webblocks-cms',
      'product_version' => WebBlocks::VERSION,
      'site_url' => (string) config('app.url'),
      'environment' => app()->environment(),
    ]), 'start support activation');

    foreach (['activation_id', 'activation_secret', 'user_code', 'verification_url'] as $key) {
      if (! is_string($payload[$key] ?? null) || trim($payload[$key]) === '') {
        throw new SupportProviderException('The support provider returned an invalid activation response.');
      }
    }

    // Provider-owned activation pages commonly carry the short user code in
    // the query string. It is safe only after the normal HTTPS/public-host
    // checks and the same-origin assertion below have both passed.
    $verificationUrl = $this->urlGuard->normalizeNavigationUrl($payload['verification_url']);

    if ($this->origin($verificationUrl) !== $this->origin($provider['provider_url'])) {
      throw new SupportProviderException('The activation page must use the provider origin.');
    }

    return $payload + ['verification_url' => $verificationUrl];
  }

  public function pollActivation(SupportConnection $connection): array
  {
    $url = $this->urlGuard->normalize($connection->api_base_url.'/activations/'.rawurlencode((string) $connection->activation_id));

    return $this->json(
      $this->publicRequest()->withToken((string) $connection->activation_secret)
        ->get($url),
      'check support activation',
    );
  }

  public function revoke(SupportConnection $connection): void
  {
    if (! $connection->isActive()) {
      return;
    }

    $response = $this->authenticated($connection)->delete($connection->api_base_url.'/installation');

    if ($response->failed() && $response->status() !== 404) {
      throw new SupportProviderException('The support provider could not disconnect this installation.');
    }
  }

  public function tickets(SupportConnection $connection, string $userRef): array
  {
    return $this->json($this->authenticated($connection)->get($connection->api_base_url.'/tickets', [
      'external_user_ref' => $userRef,
      'install_ref' => $this->install->value(),
    ]), 'list tickets')['tickets'] ?? [];
  }

  public function createTicket(SupportConnection $connection, array $payload): array
  {
    return $this->json($this->authenticated($connection)->post($connection->api_base_url.'/tickets', $payload), 'file a ticket')['ticket'] ?? [];
  }

  public function ticket(SupportConnection $connection, string $ticketId): ?array
  {
    $response = $this->authenticated($connection)->get($connection->api_base_url.'/tickets/'.rawurlencode($ticketId), [
      'install_ref' => $this->install->value(),
    ]);

    return $response->status() === 404 ? null : $this->json($response, 'read a ticket');
  }

  public function reply(SupportConnection $connection, string $ticketId, array $payload): void
  {
    $this->json($this->authenticated($connection)->post($connection->api_base_url.'/tickets/'.rawurlencode($ticketId).'/comments', $payload), 'reply to a ticket');
  }

  private function publicRequest(): PendingRequest
  {
    return Http::acceptJson()->asJson()->timeout(10)->connectTimeout(5)->withOptions(['allow_redirects' => false]);
  }

  private function authenticated(SupportConnection $connection): PendingRequest
  {
    $this->urlGuard->normalize($connection->api_base_url);

    return $this->publicRequest()->withToken((string) $connection->credential);
  }

  private function json(Response $response, string $action): array
  {
    if ($response->failed()) {
      throw new SupportProviderException('Could not '.$action.'.');
    }

    $payload = $response->json();

    if (! is_array($payload)) {
      throw new SupportProviderException('The support provider returned an invalid response.');
    }

    return $payload;
  }

  private function origin(string $url): string
  {
    $parts = parse_url($url);

    return strtolower((string) ($parts['scheme'] ?? '')).'://'.strtolower((string) ($parts['host'] ?? '')).':'.($parts['port'] ?? 443);
  }
}
