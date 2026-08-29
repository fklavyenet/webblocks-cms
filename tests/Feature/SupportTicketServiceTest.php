<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SupportConnection;
use WebBlocks\Cms\Support\Tickets\InstallId;
use WebBlocks\Cms\Support\Tickets\SupportActivationService;
use WebBlocks\Cms\Support\Tickets\SupportTicketService;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class SupportTicketServiceTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function user(int $id = 1): Authenticatable
  {
    return new class($id) implements Authenticatable
    {
      public string $name = 'Ayse';

      public function __construct(private readonly int $id) {}

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

  private function connect(): SupportConnection
  {
    return SupportConnection::query()->create([
      'provider_url' => 'https://8.8.8.8',
      'provider_name' => 'Example Support',
      'api_base_url' => 'https://8.8.8.8/api/webblocks-support/v1',
      'protocol_version' => '1.0',
      'capabilities' => ['ticket.create', 'ticket.list', 'ticket.read', 'ticket.reply'],
      'status' => 'active',
      'credential' => 'installation-secret',
    ]);
  }

  public function test_credentials_are_encrypted_at_rest(): void
  {
    $connection = $this->connect();

    $this->assertSame('installation-secret', $connection->credential);
    $this->assertNotSame('installation-secret', $connection->getRawOriginal('credential'));
    $this->assertArrayNotHasKey('credential', $connection->toArray());
  }

  public function test_ticket_creation_is_install_scoped_and_provider_independent(): void
  {
    $this->connect();
    Http::fake(['8.8.8.8/*' => Http::response(['ticket' => ['id' => 'ticket-1']], 201)]);

    app(SupportTicketService::class)->file($this->user(), 'Issue', 'Broken page', 'Details');
    $install = app(InstallId::class)->value();

    Http::assertSent(function (Request $request) use ($install): bool {
      $payload = $request->data();

      return $request->hasHeader('Authorization', 'Bearer installation-secret')
        && $payload['install_ref'] === $install
        && $payload['external_user_ref'] === $install.':1'
        && $payload['product'] === 'webblocks-cms'
        && $payload['product_version'] === WebBlocks::VERSION
        && ! array_key_exists('project_id', $payload);
    });
  }

  public function test_another_reporters_ticket_is_not_exposed(): void
  {
    $this->connect();
    $install = app(InstallId::class)->value();
    Http::fake(['8.8.8.8/*' => Http::response([
      'ticket' => ['id' => 'ticket-1', 'external_user_ref' => $install.':2'],
      'comments' => [],
    ])]);

    $this->assertNull(app(SupportTicketService::class)->findForUser($this->user(1), 'ticket-1'));
  }

  public function test_activation_discovers_a_provider_and_stores_only_encrypted_secrets(): void
  {
    Http::fake([
      '8.8.8.8/.well-known/webblocks-support' => Http::response([
        'protocol' => 'webblocks-support',
        'version' => '1.0',
        'name' => 'Example Support',
        'api_base_url' => 'https://8.8.8.8/api/webblocks-support/v1',
        'capabilities' => ['ticket.create', 'ticket.list', 'ticket.read', 'ticket.reply'],
      ]),
      '8.8.8.8/api/webblocks-support/v1/activations' => Http::response([
        'activation_id' => 'activation-1',
        'activation_secret' => 'polling-secret',
        'user_code' => 'ABCD-EFGH',
        'verification_url' => 'https://8.8.8.8/connect?code=ABCD-EFGH',
        'expires_at' => now()->addMinutes(10)->toIso8601String(),
      ], 201),
    ]);

    $connection = app(SupportActivationService::class)->start('https://8.8.8.8');

    $this->assertSame('pending', $connection->status);
    $this->assertSame('ABCD-EFGH', $connection->activation_user_code);
    $this->assertSame('https://8.8.8.8/connect?code=ABCD-EFGH', $connection->activation_url);
    $this->assertSame('polling-secret', $connection->activation_secret);
    $this->assertNotSame('polling-secret', $connection->getRawOriginal('activation_secret'));
  }

  public function test_activation_repairs_a_missing_support_connections_table(): void
  {
    Schema::dropIfExists('wbcms_support_connections');
    Http::fake([
      '8.8.8.8/.well-known/webblocks-support' => Http::response([
        'protocol' => 'webblocks-support',
        'version' => '1.0',
        'name' => 'Example Support',
        'api_base_url' => 'https://8.8.8.8/api/webblocks-support/v1',
        'capabilities' => ['ticket.create', 'ticket.list', 'ticket.read', 'ticket.reply'],
      ]),
      '8.8.8.8/api/webblocks-support/v1/activations' => Http::response([
        'activation_id' => 'activation-1',
        'activation_secret' => 'polling-secret',
        'user_code' => 'ABCD-EFGH',
        'verification_url' => 'https://8.8.8.8/connect',
        'expires_at' => now()->addMinutes(10)->toIso8601String(),
      ], 201),
    ]);

    $connection = app(SupportActivationService::class)->start('https://8.8.8.8');

    $this->assertTrue(Schema::hasTable('wbcms_support_connections'));
    $this->assertSame('activation-1', $connection->activation_id);
  }

  public function test_approved_activation_replaces_the_polling_secret_with_an_installation_credential(): void
  {
    SupportConnection::query()->create([
      'provider_url' => 'https://8.8.8.8',
      'provider_name' => 'Example Support',
      'api_base_url' => 'https://8.8.8.8/api/webblocks-support/v1',
      'protocol_version' => '1.0',
      'capabilities' => ['ticket.create', 'ticket.list', 'ticket.read', 'ticket.reply'],
      'status' => 'pending',
      'activation_id' => 'activation-1',
      'activation_secret' => 'polling-secret',
      'activation_expires_at' => now()->addMinutes(10),
    ]);
    Http::fake(['8.8.8.8/*' => Http::response([
      'status' => 'active',
      'credential' => 'installation-secret',
      'plan_name' => 'Support',
    ])]);

    $connection = app(SupportActivationService::class)->refresh();

    $this->assertTrue($connection->isActive());
    $this->assertSame('Support', $connection->plan_name);
    $this->assertNull($connection->activation_secret);
    $this->assertNotSame('installation-secret', $connection->getRawOriginal('credential'));
  }
}
