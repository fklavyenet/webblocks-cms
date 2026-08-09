<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use WebBlocks\Cms\Support\WebBlocks;

/**
 * Files and follows up WebBlocks CMS support tickets on Workbench.
 *
 * The CMS stores nothing about a ticket: Workbench owns the record, and this
 * service reads it back on demand. That keeps the two sides from drifting, at
 * the cost of needing Workbench reachable to show the support screens — which
 * the controller surfaces as a plain error rather than a stack trace.
 *
 * The CMS is given away and installed independently on many sites, so every
 * request carries the install id twice over: folded into the reporter
 * reference, which is what groups a person's tickets, and as `install_ref`,
 * which is what Workbench scopes its reads by. See InstallId for why both.
 *
 * Each install holds its own token. Workbench's per-token monthly quota then
 * bounds one site's reporting without touching anyone else's.
 */
final class WorkbenchSupportService
{
  public const TYPES = ['Issue', 'Suggestion', 'Question'];

  public function __construct(private readonly InstallId $install) {}

  public function isConfigured(): bool
  {
    return $this->baseUrl() !== '' && $this->token() !== '';
  }

  /**
   * @return array<string, mixed>
   */
  public function file(Authenticatable $user, string $type, string $title, string $body): array
  {
    $response = $this->send(fn (): Response => $this->request()->post($this->endpoint('/api/tickets'), [
      'title' => $title,
      'body' => $body,
      'type' => $type,
      // Workbench never learns the user's email or name-as-identity —
      // only this opaque, install-scoped reference.
      'external_user_ref' => $this->userRef($user),
      'external_user_name' => $user->name,
      'install_ref' => $this->install->value(),
      // Diagnostics Workbench cannot infer and cannot backfill once the
      // ticket exists: "fixed in 2.1" is unanswerable without a version.
      'product_version' => WebBlocks::VERSION,
      'site_url' => (string) config('app.url'),
      'environment' => app()->environment(),
    ]), 'file a ticket');

    return $this->decode($response, 'file a ticket')['ticket'] ?? [];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function forUser(Authenticatable $user): array
  {
    $response = $this->send(fn (): Response => $this->request()->get($this->endpoint('/api/tickets'), [
      'external_user_ref' => $this->userRef($user),
      'install_ref' => $this->install->value(),
    ]), 'list tickets');

    return $this->decode($response, 'list tickets')['tickets'] ?? [];
  }

  /**
   * Workbench scopes reads to the token's project and the named install, not
   * to one person, so the reporter check still has to happen here —
   * otherwise one editor could read a colleague's ticket by guessing its id.
   *
   * @return array{ticket: array<string, mixed>, comments: array<int, array<string, mixed>>}|null
   */
  public function findForUser(Authenticatable $user, string $ticketId): ?array
  {
    $response = $this->send(
      fn (): Response => $this->request()->get($this->endpoint('/api/tickets/'.$ticketId), [
        'install_ref' => $this->install->value(),
      ]),
      'read a ticket',
    );

    if ($response->status() === 404) {
      return null;
    }

    $payload = $this->decode($response, 'read a ticket');
    $ticket = $payload['ticket'] ?? [];

    if (($ticket['external_user_ref'] ?? null) !== $this->userRef($user)) {
      return null;
    }

    return [
      'ticket' => $ticket,
      'comments' => $payload['comments'] ?? [],
    ];
  }

  public function reply(Authenticatable $user, string $ticketId, string $body): bool
  {
    if ($this->findForUser($user, $ticketId) === null) {
      return false;
    }

    $this->decode(
      $this->send(
        fn (): Response => $this->request()->post($this->endpoint('/api/tickets/'.$ticketId.'/comments'), [
          'body' => $body,
          'external_user_name' => $user->name,
          'install_ref' => $this->install->value(),
        ]),
        'reply to a ticket',
      ),
      'reply to a ticket',
    );

    return true;
  }

  private function userRef(Authenticatable $user): string
  {
    return $this->install->userRef($user->getAuthIdentifier());
  }

  private function request(): PendingRequest
  {
    if (! $this->isConfigured()) {
      throw new RuntimeException('Workbench support is not configured.');
    }

    return Http::withToken($this->token())
      ->acceptJson()
      ->timeout((int) config('webblocks-cms.workbench.timeout', 10));
  }

  private function endpoint(string $path): string
  {
    return $this->baseUrl().$path;
  }

  /**
   * Every call funnels through here so an unreachable Workbench looks the
   * same to callers as a rejected one: a RuntimeException, never a raw
   * connection error escaping into a controller.
   *
   * @param  callable(): Response  $call
   */
  private function send(callable $call, string $action): Response
  {
    try {
      return $call();
    } catch (ConnectionException $exception) {
      Log::warning('Workbench support could not '.$action.': unreachable.', [
        'reason' => $exception->getMessage(),
      ]);

      throw new RuntimeException('Workbench support could not '.$action.'.', 0, $exception);
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function decode(Response $response, string $action): array
  {
    if ($response->failed()) {
      // The token is in the request, never the log line.
      Log::warning('Workbench support could not '.$action.'.', [
        'status' => $response->status(),
      ]);

      throw new RuntimeException('Workbench support could not '.$action.'.');
    }

    return (array) $response->json();
  }

  private function baseUrl(): string
  {
    return rtrim((string) config('webblocks-cms.workbench.url'), '/');
  }

  private function token(): string
  {
    return trim((string) config('webblocks-cms.workbench.ticket_token'));
  }
}
