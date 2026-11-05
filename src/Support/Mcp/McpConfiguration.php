<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;
use WebBlocks\Cms\Support\System\SystemSettings;

final class McpConfiguration
{
  public const PATH = '/webadmin/mcp';

  public const RESOURCE_METADATA = '/.well-known/oauth-protected-resource'.self::PATH;

  public const SERVER_METADATA = '/.well-known/oauth-authorization-server'.self::PATH;

  public const READ = 'pages:read';

  public const WRITE = 'pages:write-draft';

  public function assertAvailable(Request $request): void
  {
    abort_unless($this->enabled()
      && $this->ready()
      && $this->origin() === $request->getSchemeAndHttpHost()
      && ! app(PluginRecoveryMode::class)->active(), 404);
  }

  public function enabled(): bool
  {
    return (bool) app(SystemSettings::class)->get(SystemSettings::MCP_ENABLED, false);
  }

  public function ready(): bool
  {
    return $this->hasHttpsOrigin() && $this->hasOAuthSchema() && Schema::hasTable('wbcms_staged_page_bases');
  }

  public function hasOAuthSchema(): bool
  {
    return Schema::hasTable('wbcms_mcp_oauth_clients')
      && Schema::hasTable('wbcms_mcp_oauth_redirects')
      && Schema::hasColumns('wbcms_mcp_grants', ['oauth_client_id', 'redirect_hash']);
  }

  public function origin(): string
  {
    return rtrim((string) config('app.url'), '/');
  }

  public function hasHttpsOrigin(): bool
  {
    return str_starts_with($this->origin(), 'https://')
      && self::validRedirectUri($this->origin())
      && parse_url($this->origin(), PHP_URL_PATH) === null
      && parse_url($this->origin(), PHP_URL_QUERY) === null;
  }

  public function resource(): string
  {
    return $this->origin().self::PATH;
  }

  public function clientFingerprint(string $id): string
  {
    return hash('sha256', $this->resource().'|'.$id);
  }

  public static function validRedirectUri(string $uri): bool
  {
    $parts = parse_url($uri);
    if (filter_var($uri, FILTER_VALIDATE_URL) === false || preg_match('/[\x00-\x20\x7f\\\\]/', $uri)
      || isset($parts['user']) || isset($parts['fragment'])) {
      return false;
    }

    foreach (preg_split('/[&;]/', $parts['query'] ?? '') as $parameter) {
      $key = ltrim(urldecode(explode('=', $parameter, 2)[0]));
      if (preg_match('/\A(?:code|error(?:_description|_uri)?|iss|state)(?:\[|\z)/', $key)) {
        return false;
      }
    }

    return ($parts['scheme'] ?? null) === 'https'
      || (($parts['scheme'] ?? null) === 'http' && in_array($parts['host'] ?? null, ['localhost', '127.0.0.1', '[::1]'], true));
  }
}
