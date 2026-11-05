<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;

final class McpConfiguration
{
  public const PATH = '/webadmin/mcp';

  public const RESOURCE_METADATA = '/.well-known/oauth-protected-resource'.self::PATH;

  public const SERVER_METADATA = '/.well-known/oauth-authorization-server'.self::PATH;

  public const READ = 'pages:read';

  public const WRITE = 'pages:write-draft';

  public function assertAvailable(Request $request): void
  {
    abort_unless(config('webblocks-cms.mcp.enabled')
      && $this->isHttpsUrl($this->origin())
      && $this->origin() === $request->getSchemeAndHttpHost()
      && $this->isHttpsUrl($this->redirectUri())
      && $this->clientId() !== ''
      && strlen($this->clientSecret()) >= 32
      && ! app(PluginRecoveryMode::class)->active()
      && Schema::hasTable('wbcms_mcp_grants')
      && Schema::hasTable('wbcms_staged_page_bases'), 404);
  }

  public function origin(): string
  {
    return rtrim((string) (config('webblocks-cms.mcp.origin') ?: config('app.url')), '/');
  }

  public function resource(): string
  {
    return $this->origin().self::PATH;
  }

  public function redirectUri(): string
  {
    return (string) config('webblocks-cms.mcp.redirect_uri');
  }

  public function clientId(): string
  {
    return (string) config('webblocks-cms.mcp.client_id');
  }

  public function clientSecret(): string
  {
    return (string) config('webblocks-cms.mcp.client_secret');
  }

  public function fingerprint(): string
  {
    return hash('sha256', json_encode([$this->resource(), $this->clientId(), $this->redirectUri(), $this->clientSecret()], JSON_THROW_ON_ERROR));
  }

  private function isHttpsUrl(string $url): bool
  {
    $parts = parse_url($url);

    return filter_var($url, FILTER_VALIDATE_URL) !== false
      && ($parts['scheme'] ?? '') === 'https'
      && isset($parts['host'])
      && ! isset($parts['user'])
      && ! isset($parts['fragment'])
      && ! isset($parts['query']);
  }
}
