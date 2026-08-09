<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use WebBlocks\Cms\Support\Tickets\InstallId;
use WebBlocks\Cms\Support\Tickets\WorkbenchSupportService;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The CMS is given away and installed independently on many sites, so local
 * user ids restart at 1 in every install. Reporting under a bare user id would
 * put every install's "user 1" in one bucket on Workbench — and the ticket
 * list is keyed on exactly that, which would hand one site another's tickets.
 *
 * These tests pin the two halves that prevent it: the reporter reference is
 * namespaced by install, and every call names the install separately so
 * Workbench can enforce the same boundary server-side.
 */
class WorkbenchSupportServiceTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    File::delete(storage_path('app/webblocks-cms/support-install-id'));

    config()->set('webblocks-cms.workbench.url', 'https://workbench.test');
    config()->set('webblocks-cms.workbench.ticket_token', 'wbapi_test-token');
  }

  private function service(): WorkbenchSupportService
  {
    return app(WorkbenchSupportService::class);
  }

  private function installId(): string
  {
    return app(InstallId::class)->value();
  }

  private function user(int $id = 1, string $name = 'Ayse'): Authenticatable
  {
    return new class($id, $name) implements Authenticatable
    {
      public function __construct(private readonly int $id, public string $name) {}

      public function getAuthIdentifierName(): string
      {
        return 'id';
      }

      public function getAuthIdentifier(): int
      {
        return $this->id;
      }

      public function getAuthPasswordName(): string
      {
        return 'password';
      }

      public function getAuthPassword(): string
      {
        return '';
      }

      public function getRememberToken(): string
      {
        return '';
      }

      public function setRememberToken($value): void {}

      public function getRememberTokenName(): string
      {
        return 'remember_token';
      }
    };
  }

  public function test_the_install_id_is_generated_once_and_reused(): void
  {
    $first = $this->installId();

    $this->assertNotSame('', $first);
    $this->assertSame($first, (new InstallId)->value());
  }

  public function test_filing_names_the_install_the_reporter_and_the_version(): void
  {
    Http::fake(['workbench.test/api/tickets' => Http::response(['ticket' => ['id' => 'wbtk_1']], 201)]);

    $this->service()->file($this->user(1), 'Issue', 'Page will not publish', 'The button does nothing.');

    Http::assertSent(function (Request $request): bool {
      $body = $request->data();

      // "1" alone would be every install's first user. The install makes it
      // this install's first user.
      return $body['external_user_ref'] === $this->installId().':1'
        && $body['install_ref'] === $this->installId()
        && $body['external_user_name'] === 'Ayse'
        && $body['product_version'] === WebBlocks::VERSION
        // The product must never name a project: Workbench derives it from
        // the token.
        && ! array_key_exists('project_id', $body)
        && $request->hasHeader('Authorization', 'Bearer wbapi_test-token');
    });
  }

  public function test_the_list_is_scoped_to_this_install(): void
  {
    Http::fake(['workbench.test/api/tickets?*' => Http::response(['tickets' => []])]);

    $this->service()->forUser($this->user(1));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'install_ref='.$this->installId())
      && str_contains($request->url(), rawurlencode($this->installId().':1')));
  }

  public function test_a_ticket_belonging_to_another_reporter_is_refused(): void
  {
    Http::fake(['workbench.test/api/tickets/wbtk_1*' => Http::response([
      'ticket' => ['id' => 'wbtk_1', 'external_user_ref' => $this->installId().':2'],
      'comments' => [],
    ])]);

    // Workbench scopes reads to the project and the install, not to one
    // person, so the reporter check has to happen here.
    $this->assertNull($this->service()->findForUser($this->user(1), 'wbtk_1'));
  }

  public function test_a_reply_carries_the_install(): void
  {
    Http::fake([
      'workbench.test/api/tickets/wbtk_1/comments' => Http::response([], 201),
      'workbench.test/api/tickets/wbtk_1*' => Http::response([
        'ticket' => ['id' => 'wbtk_1', 'external_user_ref' => $this->installId().':1'],
        'comments' => [],
      ]),
    ]);

    $this->assertTrue($this->service()->reply($this->user(1), 'wbtk_1', 'Still broken.'));

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/comments')
      && $request->data()['install_ref'] === $this->installId());
  }

  public function test_it_is_unconfigured_without_a_token(): void
  {
    config()->set('webblocks-cms.workbench.ticket_token', null);

    Http::fake();

    $this->assertFalse($this->service()->isConfigured());

    $this->expectException(RuntimeException::class);
    $this->service()->file($this->user(1), 'Issue', 'x', 'y');
  }

  public function test_a_rejected_call_raises_a_runtime_exception_rather_than_leaking_the_status(): void
  {
    Http::fake(['workbench.test/*' => Http::response([], 500)]);

    $this->expectException(RuntimeException::class);
    $this->service()->forUser($this->user(1));
  }
}
