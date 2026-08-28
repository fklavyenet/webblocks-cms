<?php

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\SupportConnection;
use WebBlocks\Cms\Support\WebBlocks;

final class SupportTicketService
{
  public const TYPES = ['Issue', 'Suggestion', 'Question'];

  public function __construct(
    private readonly SupportConnectionRepository $connections,
    private readonly CompatibleSupportProvider $provider,
    private readonly InstallId $install,
  ) {}

  public function connection(): ?SupportConnection
  {
    return $this->connections->current();
  }

  public function isConfigured(): bool
  {
    return $this->connection()?->isActive() === true;
  }

  public function file(Authenticatable $user, string $type, string $title, string $body): array
  {
    return $this->provider->createTicket($this->activeConnection(), [
      'title' => $title,
      'body' => $body,
      'type' => $type,
      'external_user_ref' => $this->userRef($user),
      'external_user_name' => (string) $user->name,
      'install_ref' => $this->install->value(),
      'product' => 'webblocks-cms',
      'product_version' => WebBlocks::VERSION,
      'site_url' => (string) config('app.url'),
      'environment' => app()->environment(),
    ]);
  }

  public function forUser(Authenticatable $user): array
  {
    return $this->provider->tickets($this->activeConnection(), $this->userRef($user));
  }

  public function findForUser(Authenticatable $user, string $ticketId): ?array
  {
    $payload = $this->provider->ticket($this->activeConnection(), $ticketId);

    if ($payload === null || ($payload['ticket']['external_user_ref'] ?? null) !== $this->userRef($user)) {
      return null;
    }

    return ['ticket' => $payload['ticket'], 'comments' => $payload['comments'] ?? []];
  }

  public function reply(Authenticatable $user, string $ticketId, string $body): bool
  {
    if ($this->findForUser($user, $ticketId) === null) {
      return false;
    }

    $this->provider->reply($this->activeConnection(), $ticketId, [
      'body' => $body,
      'external_user_name' => (string) $user->name,
      'install_ref' => $this->install->value(),
    ]);

    return true;
  }

  private function activeConnection(): SupportConnection
  {
    $connection = $this->connection();

    if (! $connection?->isActive()) {
      throw new SupportProviderException('Support is not connected.');
    }

    return $connection;
  }

  private function userRef(Authenticatable $user): string
  {
    return $this->install->userRef($user->getAuthIdentifier());
  }
}
