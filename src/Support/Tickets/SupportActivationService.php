<?php

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Support\Carbon;
use WebBlocks\Cms\Models\SupportConnection;

final class SupportActivationService
{
  public function __construct(
    private readonly SupportConnectionRepository $connections,
    private readonly CompatibleSupportProvider $provider,
  ) {}

  public function start(string $providerUrl): SupportConnection
  {
    if ($this->connections->current()) {
      throw new SupportProviderException('Disconnect the current support provider before starting another activation.');
    }

    $discovery = $this->provider->discover($providerUrl);
    $activation = $this->provider->beginActivation($discovery);

    return $this->connections->replace($discovery + [
      'status' => 'pending',
      'activation_id' => $activation['activation_id'],
      'activation_secret' => $activation['activation_secret'],
      'activation_user_code' => $activation['user_code'],
      'activation_url' => $activation['verification_url'],
      'activation_expires_at' => isset($activation['expires_at']) ? Carbon::parse($activation['expires_at']) : now()->addMinutes(15),
    ]);
  }

  public function refresh(): SupportConnection
  {
    $connection = $this->connections->current();

    if (! $connection || $connection->status !== 'pending') {
      throw new SupportProviderException('There is no pending support activation.');
    }

    if ($connection->activation_expires_at?->isPast()) {
      $connection->update(['status' => 'expired', 'last_error' => 'The activation expired.']);

      return $connection->refresh();
    }

    $result = $this->provider->pollActivation($connection);
    $status = (string) ($result['status'] ?? 'pending');

    if ($status === 'pending') {
      $connection->update(['last_verified_at' => now(), 'last_error' => null]);

      return $connection->refresh();
    }

    if ($status !== 'active' || ! is_string($result['credential'] ?? null) || trim($result['credential']) === '') {
      $connection->update(['status' => 'error', 'last_error' => 'The support provider rejected the activation.']);

      return $connection->refresh();
    }

    $connection->update([
      'status' => 'active',
      'credential' => $result['credential'],
      'plan_name' => trim((string) ($result['plan_name'] ?? '')) ?: null,
      'entitlement_expires_at' => isset($result['entitlement_expires_at']) ? Carbon::parse($result['entitlement_expires_at']) : null,
      'activated_at' => now(),
      'last_verified_at' => now(),
      'last_error' => null,
      'activation_secret' => null,
    ]);

    return $connection->refresh();
  }

  public function disconnect(): void
  {
    $connection = $this->connections->current();

    if ($connection) {
      $this->provider->revoke($connection);
    }

    $this->connections->disconnect();
  }
}
