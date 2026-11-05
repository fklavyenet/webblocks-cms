<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Support\Facades\DB;

final class McpOAuthClients
{
  public function register(?string $name, array $redirectUris, #[\SensitiveParameter] ?string $secret): string
  {
    $secretHash = $secret === null ? null : hash('sha256', $secret);

    return DB::transaction(function () use ($name, $redirectUris, $secretHash): string {
      $id = bin2hex(random_bytes(32));
      DB::table('wbcms_mcp_oauth_clients')->insert([
        'id' => $id,
        'name' => $name ?? substr((string) parse_url($redirectUris[0], PHP_URL_HOST), 0, 128),
        'client_secret_hash' => $secretHash,
        'created_at' => now(),
      ]);
      DB::table('wbcms_mcp_oauth_redirects')->insert(array_map(
        fn (string $uri): array => ['client_id' => $id, 'uri' => $uri],
        $redirectUris,
      ));

      return $id;
    });
  }

  public function find(string $id): ?McpOAuthClient
  {
    if (! preg_match('/\A[a-f0-9]{64}\z/', $id)) {
      return null;
    }

    $client = DB::table('wbcms_mcp_oauth_clients')->where('id', $id)->first();

    return $client ? new McpOAuthClient($client->id, $client->name,
      DB::table('wbcms_mcp_oauth_redirects')->where('client_id', $client->id)->orderBy('id')->pluck('uri')->all(),
      $client->client_secret_hash,
    ) : null;
  }
}
