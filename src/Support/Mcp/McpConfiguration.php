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

  public const CONTENT = 'pages:write-content-draft';

  public const CREATE = 'pages:create-draft';

  public const STRUCTURE = 'pages:write-draft-structure';

  public const TRANSLATION = 'pages:write-draft-translation';

  public const SEO = 'pages:write-draft-seo';

  public const UPLOAD = 'media:upload';

  public const READ_CONTENT = 'pages:read-content';

  public const READ_STRUCTURE = 'pages:read-draft-structure';

  public const READ_TRANSLATION = 'pages:read-draft-translation';

  public const READ_MEDIA = 'media:read';

  public const GRANT_COLUMNS = [
    self::WRITE => 'can_write',
    self::CONTENT => 'can_edit_content',
    self::CREATE => 'can_create_draft',
    self::STRUCTURE => 'can_edit_structure',
    self::TRANSLATION => 'can_edit_translations',
    self::SEO => 'can_edit_seo',
    self::UPLOAD => 'can_upload_media',
    self::READ_CONTENT => 'can_read_content',
    self::READ_STRUCTURE => 'can_read_structure',
    self::READ_TRANSLATION => 'can_read_translations',
    self::READ_MEDIA => 'can_read_media',
  ];

  public const SCOPES = [self::READ, self::WRITE, self::CONTENT, self::CREATE, self::STRUCTURE, self::TRANSLATION, self::UPLOAD,
    self::READ_CONTENT, self::READ_STRUCTURE, self::READ_TRANSLATION, self::READ_MEDIA, self::SEO];

  public const LABELS = [
    self::READ => 'scope_read',
    self::READ_CONTENT => 'scope_read_content',
    self::READ_STRUCTURE => 'scope_read_structure',
    self::READ_TRANSLATION => 'scope_read_translation',
    self::READ_MEDIA => 'scope_read_media',
    self::WRITE => 'scope_write_heading',
    self::CONTENT => 'scope_write_content',
    self::CREATE => 'scope_create',
    self::STRUCTURE => 'scope_write_structure',
    self::TRANSLATION => 'scope_write_translation',
    self::SEO => 'scope_write_seo',
    self::UPLOAD => 'scope_upload',
  ];

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
    return Schema::hasColumn('wbcms_mcp_oauth_clients', 'client_secret_hash')
      && Schema::hasTable('wbcms_mcp_oauth_redirects')
      && Schema::hasColumns('wbcms_mcp_grants', ['oauth_client_id', 'redirect_hash', ...array_values(self::GRANT_COLUMNS)])
      && Schema::hasColumn('wbcms_mcp_grants', 'drafts_created_only')
      && Schema::hasTable('wbcms_mcp_grant_drafts')
      && Schema::hasTable('wbcms_mcp_page_drafts')
      && Schema::hasTable('wbcms_staged_page_translation_fields');
  }

  public function availableScopes(): array
  {
    $settings = app(SystemSettings::class)->all();

    return array_values(array_filter(self::SCOPES, fn (string $scope) => ! isset(SystemSettings::MCP_SCOPE_KEYS[$scope])
      || (bool) ($settings[SystemSettings::MCP_SCOPE_KEYS[$scope]] ?? true)));
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
