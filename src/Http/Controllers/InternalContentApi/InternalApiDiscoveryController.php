<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\Plugins\PluginApiDiscoveryRegistrar;
use WebBlocks\Cms\Support\WebBlocks;

class InternalApiDiscoveryController extends Controller
{
  public function __construct(
    private readonly CmsApiTokenAuthenticator $authenticator,
    private readonly CmsApiTokenCapabilities $capabilities,
    private readonly PluginApiDiscoveryRegistrar $pluginDiscovery,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $token = $this->authenticateOptional($request);

    if (! $token) {
      return response()->json([
        'product' => WebBlocks::NAME,
        'api_version' => '1',
        'authenticated' => false,
        'message' => 'Authenticate with a CMS API Bearer token to receive API discovery links.',
        '_links' => [
          'self' => '/webadmin/api',
        ],
      ]);
    }

    return response()->json([
      'product' => WebBlocks::NAME,
      'cms_version' => WebBlocks::version(),
      'product_version' => WebBlocks::version(),
      'api_version' => '1',
      'authenticated' => true,
      'token' => $this->capabilities->publicDescription($token),
      'recommended_next_steps' => [
        'Read the OpenAPI schema, AI guide, content contract, and examples.',
        'Validate content plans with POST /webadmin/api/content/validate before apply.',
        'Apply only after explicit user approval.',
        'Promote staged updates or publish only with explicit content.publish capability; page publishing does not publish draft blocks unless include_page_owned_blocks is true.',
        'For published-page staged updates, read GET /webadmin/api/pages/{staged_page}; follow page._actions.promote and do not call page publish on the staged page.',
        'Preview draft, in-review, published, and staged pages by fetching GET /webadmin/pages/{page}/preview with an authenticated admin browser session or an Authorization Bearer token that has content.read. This is the canonical HTML preview URL, not a JSON API endpoint.',
        'For allowlisted admin visual QA, use GET /webadmin/api/admin-render/system-updates with admin.render. The JSON response includes the rendered admin HTML; append ?format=html to receive text/html directly for local screenshot capture.',
        'Use navigation endpoints to create, update, hide, reorder, or delete menu items. Create, update, visibility, and reorder require navigation.write; delete requires explicit navigation.delete.',
        'Use GET /webadmin/api/locale-options to choose a standard locale_option, GET /webadmin/api/locales to inspect install locales, POST /webadmin/api/locales to create a locale, and PATCH /webadmin/api/locales/{locale} to update locale code, name, enabled state, or default status. Locale writes require site-settings.write.',
        'Use GET /webadmin/api/media with media.read, POST /webadmin/api/media with media.upload, POST /webadmin/api/media/fetch with media.upload for a single approved public file URL, PATCH /webadmin/api/media/{media} with media.write, POST /webadmin/api/media/{media}/replace with media.replace, POST /webadmin/api/media/{media}/move with media.move, and DELETE /webadmin/api/media/{media} with media.delete for Media Library work.',
        'For public site favicon and social image changes, upload or discover Media Library images, then use PATCH /webadmin/api/sites/{site}/branding with site-settings.write. Do not replace CMS product assets under /cms/brand.',
        'Use GET/PUT /webadmin/api/sites/{site}/assets/{css|js} with site-assets.read/write for canonical physical site.css and site.js edits; inspect readiness before writes and include expected_checksum on writes.',
        'When editing site.css, keep public page designs mode-aware: prefer WebBlocks UI/CMS public theme tokens and native wb-* component styles over hard-coded light or dark colors.',
        'When assigning block icons, read `GET /webadmin/api/icon-catalog?context=content` and use only returned slugs. Do not guess icon names; unknown or inactive icons are rejected by content validation or skipped by public rendering.',
        'Use PATCH /webadmin/api/blocks/{block} for supported existing block fields such as brand logo media; Shared Slot source blocks also require shared-slots.write.',
        'Use GET /webadmin/api/engagement/comments and GET /webadmin/api/engagement/ratings with engagement.read to analyze public feedback. Use PATCH /webadmin/api/engagement/comments/{comment} with engagement.moderate to approve, reject, hide, or mark comments as spam.',
        'Use plugin lifecycle endpoints only with explicit plugin capabilities: plugins.install uploads a plugin ZIP, plugins.manage enables/disables, plugins.setup runs plugin migrations, and plugins.uninstall removes disabled manually uploaded plugins without dropping plugin-owned tables.',
        ...$this->pluginDiscovery->guidance(),
        'Use JSON requests with Authorization, Accept, and Content-Type headers.',
      ],
      'workflows' => $this->workflows($token),
      '_links' => $this->links(),
    ]);
  }

  public function openapi(): JsonResponse
  {
    return response()->json([
      'openapi' => '3.1.0',
      'info' => [
        'title' => 'WebBlocks CMS Content API',
        'version' => '1',
      ],
      'servers' => [
        ['url' => '/webadmin/api'],
      ],
      'components' => [
        'securitySchemes' => [
          'BearerToken' => [
            'type' => 'http',
            'scheme' => 'bearer',
          ],
        ],
      ],
      'security' => [
        ['BearerToken' => []],
      ],
      'paths' => $this->openApiPaths(),
    ]);
  }

  public function aiGuide(): JsonResponse
  {
    return response()->json([
      'product' => WebBlocks::NAME,
      'format' => 'markdown',
      'content' => implode("\n\n", [
        '# WebBlocks CMS AI/API Guide',
        'Use the CMS API base URL `/webadmin/api` with a CMS API Bearer token. The first call should be `GET /webadmin/api`.',
        "Send every request with:\n\n```http\nAuthorization: Bearer <token>\nAccept: application/json\nContent-Type: application/json\n```",
        'Do not use browser automation or admin UI clicks for content API work. Follow discovery, then OpenAPI/content-contract/examples, then validate, then apply after explicit user approval.',
        'Content writes use JSON-only API responses. Missing, invalid, or insufficient tokens return JSON `401` or `403`; invalid payloads return JSON `422` with discovery and documentation links.',
        'Navigation item create/update/visibility/reorder operations require `navigation.write`. Navigation item deletion requires explicit `navigation.delete`; child items are never cascaded by delete and must be moved or deleted first.',
        'Media uploads require `media.upload`; metadata writes require `media.write`; file replacement requires `media.replace`; folder moves require `media.move`; deletion requires `media.delete`. Destructive operations require explicit capabilities such as `navigation.delete`, `media.replace`, `media.delete`, `pages.delete`, or `content.publish`. Standard page-building tokens should not include upload or destructive capabilities unless the operator explicitly grants them.',
        'Canonical site CSS/JS file edits require explicit `site-assets.read` and `site-assets.write`. Read `GET /webadmin/api/sites/{site}/assets/css`, inspect `asset.readiness`, `asset.guidance`, and `asset.analysis.mode_awareness`, update the returned `contents`, then write with `PUT /webadmin/api/sites/{site}/assets/css` and the returned `expected_checksum` value. The same pattern works for `js`. Hosting permission failures return JSON `422` with `asset.write`; do not invent alternate public paths or database-backed CSS fallbacks.',
        'For site.css, use native block settings and WebBlocks UI/CMS public theme tokens first. Avoid hard-coded light backgrounds, dark text, white cards, or separate custom light/dark palettes unless existing tokens cannot express the design. CSS that bypasses public theme tokens can make Light/Dark/Auto mode look mixed. If `asset.analysis.mode_awareness.status` is `warning`, review, report, or fix the listed warnings before considering a migration or new site setup complete.',
        'For public content icons, read `GET /webadmin/api/icon-catalog?context=content` and use only returned slugs. Do not guess icon names from the WebBlocks UI manifest.',
        'For page creation plans, `page.path` is the canonical public Page Translation path and may include section segments such as `/games/fruit-train`. Do not flatten it to `/fruit-train` and do not generate `/p/...`; CMS derives the short slug from the final path segment.',
        "Published page updates use staged pages. The safe flow is:\n\n1. Read `GET /webadmin/api/pages/{source_page}` and reuse any existing draft staged update exposed by the page metadata/actions.\n2. Use `create_staged_update_for_published_page` only when no active staged draft exists; repeated create calls for the same source page return the existing draft staged update instead of creating another page.\n3. Use `replace_staged_page_update` for subsequent content revisions on that staged page.\n4. Preview the staged page through `GET /webadmin/pages/{page}/preview` using an admin browser session or a Bearer token with `content.read`.\n5. After explicit approval, read `GET /webadmin/api/pages/{staged_page}` and follow page._actions.promote.\n6. Do not use `POST /webadmin/api/pages/{staged_page}/publish` to promote staged content; that endpoint is rejected for staged updates.",
        'For site favicon and public brand metadata, upload or discover image media, then use `PATCH /webadmin/api/sites/{site}/branding` with `favicon_media_id`, `social_image_media_id`, `display_name`, or `tagline`. Public site branding must remain visible in the admin Site Branding tab; do not overwrite `/cms/brand/*`, which belongs to the CMS product/admin shell.',
        'For existing structured blocks, do not use HTML fallbacks or invented URL settings to set native fields. Use slider -> slide for composable carousel sections; slide supports Media Library background media and nested content blocks. Discover or upload media, then use `PATCH /webadmin/api/blocks/{block}` for supported fields such as `media_id`, `settings.url`, `settings.target`, `settings.aria_label`, `settings.background_position`, `settings.background_overlay`, and text translations.',
        'For Media Library cleanup, use dedicated media endpoints and capabilities: metadata PATCH, replace, move, and delete are separate. Delete keeps the admin usage guard and returns a usage report instead of deleting media referenced by blocks, site branding, or page SEO. For approved public remote files, use only the discovered `POST /webadmin/api/media/fetch` endpoint; do not put remote URLs directly in content plans.',
        'For admin UI visual QA, use only allowlisted render snapshots. `GET /webadmin/api/admin-render/system-updates` requires `admin.render` and returns JSON with the rendered HTML. `GET /webadmin/api/admin-render/system-updates?format=html` returns direct `text/html` for local browser screenshot capture. This endpoint is read-only and does not click, install, publish, or mutate admin state.',
        'Public engagement feedback is separate from page-building. Reading comments and ratings requires `engagement.read`; changing comment status requires `engagement.moderate`. Engagement responses do not expose visitor hashes, IP hashes, or user-agent values.',
      ]),
      '_links' => $this->links(),
    ]);
  }

  public function examples(): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'examples' => [
        [
          'handle' => 'contact-page',
          'label' => 'Contact Page',
          'url' => '/webadmin/api/examples/contact-page',
        ],
        [
          'handle' => 'landing-page',
          'label' => 'Landing Page',
          'url' => '/webadmin/api/examples/landing-page',
        ],
      ],
    ]);
  }

  public function contactPageExample(): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'example' => [
        'handle' => 'contact-page',
        'description' => 'Generic draft contact page plan compatible with the Internal Content API content plan contract.',
        'flow' => [
          'GET /webadmin/api',
          'GET /webadmin/api/content-contract',
          'POST /webadmin/api/content/validate',
          'POST /webadmin/api/content/apply',
        ],
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'payload' => $this->contactPagePayload(),
      ],
    ]);
  }

  public function landingPageExample(): JsonResponse
  {
    $payload = $this->contactPagePayload();
    $payload['plan']['page'] = [
      'title' => 'Example Landing Page',
      'path' => '/campaigns/example-landing',
      'status' => 'draft',
    ];
    $payload['plan']['slots']['main'][0]['children'][0]['translations']['title'] = 'Build with WebBlocks CMS';
    $payload['plan']['slots']['main'][0]['children'][0]['translations']['subtitle'] = 'A generic draft landing page example.';

    return response()->json([
      'ok' => true,
      'example' => [
        'handle' => 'landing-page',
        'description' => 'Generic draft landing page plan.',
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'payload' => $payload,
      ],
    ]);
  }

  private function authenticateOptional(Request $request): ?CmsApiToken
  {
    $bearer = (string) $request->bearerToken();

    if ($bearer === '') {
      return null;
    }

    return $this->authenticator->authenticate($bearer, $request);
  }

  private function links(): array
  {
    return [
      'self' => '/webadmin/api',
      'openapi' => '/webadmin/api/openapi.json',
      'ai_guide' => '/webadmin/api/ai-guide',
      'content_contract' => '/webadmin/api/content-contract',
      'icon_catalog' => '/webadmin/api/icon-catalog?context=content',
      'examples' => '/webadmin/api/examples',
      'content_validate' => '/webadmin/api/content/validate',
      'content_apply' => '/webadmin/api/content/apply',
      'pages' => '/webadmin/api/pages',
      'locales' => '/webadmin/api/locales',
      'locale_options' => '/webadmin/api/locale-options',
      'locale_create' => '/webadmin/api/locales',
      'locale_update' => '/webadmin/api/locales/{locale}',
      'page' => '/webadmin/api/pages/{page}',
      'page_html_preview' => '/webadmin/pages/{page}/preview',
      'admin_render_system_updates' => '/webadmin/api/admin-render/system-updates',
      'admin_render_system_updates_html' => '/webadmin/api/admin-render/system-updates?format=html',
      'page_layout_slots_sync' => '/webadmin/api/pages/{page}/sync-layout-slots',
      'page_publish' => '/webadmin/api/pages/{page}/publish',
      'page_owned_blocks_publish' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
      'page_delete' => '/webadmin/api/pages/{page}',
      'navigation_menus' => '/webadmin/api/navigation-menus',
      'shared_slots' => '/webadmin/api/shared-slots',
      'shared_slot_blocks_publish' => '/webadmin/api/shared-slots/{sharedSlot}/publish-blocks',
      'site_public_theme' => '/webadmin/api/sites/{site}/public-theme',
      'site_branding' => '/webadmin/api/sites/{site}/branding',
      'site_asset' => '/webadmin/api/sites/{site}/assets/{css|js}',
      'media' => '/webadmin/api/media',
      'media_upload' => '/webadmin/api/media',
      'media_remote_fetch' => '/webadmin/api/media/fetch',
      'media_update' => '/webadmin/api/media/{media}',
      'media_replace' => '/webadmin/api/media/{media}/replace',
      'media_move' => '/webadmin/api/media/{media}/move',
      'media_delete' => '/webadmin/api/media/{media}',
      'block_update' => '/webadmin/api/blocks/{block}',
      'engagement_comments' => '/webadmin/api/engagement/comments',
      'engagement_comment_update' => '/webadmin/api/engagement/comments/{comment}',
      'engagement_ratings' => '/webadmin/api/engagement/ratings',
      'plugins' => '/webadmin/api/plugins',
      'plugin_install' => '/webadmin/api/plugins/install',
      'plugin_enable' => '/webadmin/api/plugins/{plugin}/enable',
      'plugin_setup' => '/webadmin/api/plugins/{plugin}/setup',
      'plugin_disable' => '/webadmin/api/plugins/{plugin}/disable',
      'plugin_uninstall' => '/webadmin/api/plugins/{plugin}',
      // Enabled plugins advertise their own endpoints (e.g. WebBlocks Commerce).
      ...$this->pluginDiscovery->resources(),
    ];
  }

  private function openApiPaths(): array
  {
    $json = ['application/json' => ['schema' => ['type' => 'object']]];
    $pathParameter = static fn (string $name, string $description): array => [
      'name' => $name,
      'in' => 'path',
      'required' => true,
      'description' => $description,
      'schema' => ['type' => 'string'],
    ];

    return [
      '/' => ['get' => ['summary' => 'API discovery', 'responses' => ['200' => ['description' => 'Discovery JSON', 'content' => $json]]]],
      '/openapi.json' => ['get' => ['summary' => 'OpenAPI schema', 'responses' => ['200' => ['description' => 'OpenAPI JSON', 'content' => $json]]]],
      '/ai-guide' => ['get' => ['summary' => 'AI usage guide', 'responses' => ['200' => ['description' => 'AI guide JSON', 'content' => $json]]]],
      '/content-contract' => ['get' => ['summary' => 'Content contract', 'responses' => ['200' => ['description' => 'Content contract JSON', 'content' => $json]]]],
      '/icon-catalog' => ['get' => ['summary' => 'List active safe icon slugs for content or navigation fields', 'x-supported-contexts' => ['content', 'navigation'], 'parameters' => [['name' => 'context', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['content', 'navigation'], 'default' => 'content']]], 'responses' => ['200' => ['description' => 'Icon catalog JSON', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/examples' => ['get' => ['summary' => 'Example index', 'responses' => ['200' => ['description' => 'Examples JSON', 'content' => $json]]]],
      '/examples/contact-page' => ['get' => ['summary' => 'Contact page example', 'responses' => ['200' => ['description' => 'Example JSON', 'content' => $json]]]],
      '/examples/landing-page' => ['get' => ['summary' => 'Landing page example', 'responses' => ['200' => ['description' => 'Example JSON', 'content' => $json]]]],
      '/admin-render/system-updates' => ['get' => ['summary' => 'Render the allowlisted System Updates admin screen for visual QA', 'x-required-capability' => 'admin.render', 'x-query-parameters' => ['format=html returns text/html instead of JSON'], 'responses' => ['200' => ['description' => 'Rendered admin screen HTML snapshot', 'content' => $json], '403' => ['description' => 'Requires admin.render and a system-capable token creator', 'content' => $json]]]],
      '/plugins' => ['get' => ['summary' => 'List installed and registered plugins', 'x-required-capability' => 'plugins.read', 'responses' => ['200' => ['description' => 'Plugin status JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.read capability', 'content' => $json]]]],
      '/plugins/install' => ['post' => ['summary' => 'Install a manually uploaded plugin ZIP artifact', 'x-required-capability' => 'plugins.install', 'x-consumes' => 'multipart/form-data', 'x-required-fields' => ['plugin_zip'], 'responses' => ['201' => ['description' => 'Installed plugin JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.install capability', 'content' => $json], '422' => ['description' => 'Invalid plugin package JSON', 'content' => $json]]]],
      '/plugins/catalog' => ['get' => ['summary' => 'List compatible Plugin Catalog packages', 'x-required-capability' => 'plugins.read', 'responses' => ['200' => ['description' => 'Plugin Catalog JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.read capability', 'content' => $json], '503' => ['description' => 'Plugin Catalog unavailable', 'content' => $json]]]],
      '/plugins/catalog/{plugin}' => ['get' => ['summary' => 'Show Plugin Catalog package details', 'x-required-capability' => 'plugins.read', 'parameters' => [$pathParameter('plugin', 'Catalog plugin handle')], 'responses' => ['200' => ['description' => 'Catalog plugin JSON', 'content' => $json], '404' => ['description' => 'Catalog plugin not found', 'content' => $json], '403' => ['description' => 'Requires plugins.read capability', 'content' => $json]]]],
      '/plugins/catalog/{plugin}/install' => ['post' => ['summary' => 'Install a checksum-verified Plugin Catalog package disabled', 'x-required-capability' => 'plugins.install', 'parameters' => [$pathParameter('plugin', 'Catalog plugin handle')], 'responses' => ['201' => ['description' => 'Installed plugin JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.install capability', 'content' => $json], '404' => ['description' => 'Catalog plugin not found', 'content' => $json], '422' => ['description' => 'Catalog package could not be installed', 'content' => $json]]]],
      '/plugins/{plugin}/enable' => ['post' => ['summary' => 'Enable an installed plugin version', 'x-required-capability' => 'plugins.manage', 'responses' => ['200' => ['description' => 'Enabled plugin JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.manage capability', 'content' => $json]]]],
      '/plugins/{plugin}/setup' => ['post' => ['summary' => 'Run plugin setup migrations', 'x-required-capability' => 'plugins.setup', 'responses' => ['200' => ['description' => 'Plugin setup JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.setup capability', 'content' => $json], '409' => ['description' => 'Plugin must be enabled first', 'content' => $json]]]],
      '/plugins/{plugin}/disable' => ['post' => ['summary' => 'Disable a plugin', 'x-required-capability' => 'plugins.manage', 'responses' => ['200' => ['description' => 'Disabled plugin JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.manage capability', 'content' => $json]]]],
      '/plugins/{plugin}' => ['delete' => ['summary' => 'Uninstall a disabled manually uploaded plugin without dropping plugin-owned tables', 'x-required-capability' => 'plugins.uninstall', 'responses' => ['200' => ['description' => 'Uninstalled plugin JSON', 'content' => $json], '403' => ['description' => 'Requires plugins.uninstall capability', 'content' => $json], '409' => ['description' => 'Plugin must be disabled and manually uploaded', 'content' => $json]]]],
      // Enabled plugins advertise their own OpenAPI paths (e.g. WebBlocks Commerce).
      ...$this->pluginDiscovery->openApiPaths(),
      '/pages' => ['get' => ['summary' => 'List pages', 'responses' => ['200' => ['description' => 'Pages JSON', 'content' => $json]]]],
      '/pages/{page}' => [
        'get' => ['summary' => 'Read page', 'parameters' => [['name' => 'page', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Page JSON', 'content' => $json]]],
        'delete' => ['summary' => 'Delete page', 'x-required-capability' => 'pages.delete', 'responses' => ['200' => ['description' => 'Deleted page JSON', 'content' => $json], '403' => ['description' => 'Requires pages.delete capability', 'content' => $json]]],
      ],
      '/pages/{page}/publish' => ['post' => ['summary' => 'Publish page', 'x-required-capability' => 'content.publish', 'x-defaults' => ['include_page_owned_blocks' => false], 'responses' => ['200' => ['description' => 'Published page JSON', 'content' => $json], '403' => ['description' => 'Requires content.publish capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/pages/{page}/publish-page-owned-blocks' => ['post' => ['summary' => 'Publish page-owned blocks without changing page status', 'x-required-capability' => 'content.publish', 'responses' => ['200' => ['description' => 'Published blocks JSON', 'content' => $json], '403' => ['description' => 'Requires content.publish capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/pages/{page}/sync-layout-slots' => ['post' => ['summary' => 'Create missing page layout slots', 'x-required-capability' => 'content.apply', 'responses' => ['200' => ['description' => 'Synced page slots JSON', 'content' => $json], '403' => ['description' => 'Requires content.apply capability', 'content' => $json]]]],
      '/sites/{site}/public-theme' => ['post' => ['summary' => 'Update safe site public theme preset', 'x-required-capability' => 'site-settings.write', 'responses' => ['200' => ['description' => 'Updated site JSON', 'content' => $json], '403' => ['description' => 'Requires site-settings.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/sites/{site}/branding' => ['patch' => ['summary' => 'Update safe site public branding fields such as favicon media', 'x-required-capability' => 'site-settings.write', 'x-supported-fields' => ['display_name', 'tagline', 'favicon_media_id', 'social_image_media_id'], 'x-public-site-branding-note' => 'Use Media Library image ids so changes remain visible in the admin Site Branding tab; do not overwrite /cms/brand product assets.', 'responses' => ['200' => ['description' => 'Updated site branding JSON', 'content' => $json], '403' => ['description' => 'Requires site-settings.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/sites/{site}/assets/{type}' => [
        'get' => ['summary' => 'Read canonical physical site CSS or JS override file', 'x-required-capability' => 'site-assets.read', 'x-supported-types' => ['css', 'js'], 'x-readiness' => 'asset.readiness reports whether CMS can create or write the canonical physical file', 'x-css-guidance' => 'asset.guidance explains token-first, mode-aware site.css expectations so Light/Dark/Auto mode remains consistent.', 'x-css-analysis' => 'asset.analysis.mode_awareness reports pass/warning status, literal color signals, detected anti-patterns, warning messages, and recommended --wb-public-* tokens for CSS assets.', 'responses' => ['200' => ['description' => 'Site asset JSON with contents, checksum, readiness, guidance, and CSS analysis', 'content' => $json], '403' => ['description' => 'Requires site-assets.read capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
        'put' => ['summary' => 'Write canonical physical site CSS or JS override file with checksum protection', 'x-required-capability' => 'site-assets.write', 'x-supported-types' => ['css', 'js'], 'x-required-fields' => ['contents', 'expected_checksum'], 'x-physical-paths' => ['public/site/{site_handle}/css/site.css', 'public/site/{site_handle}/js/site.js'], 'x-css-guidance' => 'For CSS writes, prefer native block settings and public theme/WebBlocks UI custom properties; avoid hard-coded light/dark page palettes that bypass mode behavior.', 'x-css-analysis' => 'CSS writes return asset.analysis.mode_awareness; warning status is advisory and should be reviewed or fixed by migration/new-site tools before completion.', 'x-permission-failure' => 'Returns JSON 422 with errors.0.path = asset.write and asset.readiness instead of an HTML server error', 'responses' => ['200' => ['description' => 'Updated site asset JSON', 'content' => $json], '403' => ['description' => 'Requires site-assets.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/locales' => [
        'get' => ['summary' => 'List locales', 'responses' => ['200' => ['description' => 'Locales JSON', 'content' => $json]]],
        'post' => ['summary' => 'Create a locale', 'x-required-capability' => 'site-settings.write', 'x-preferred-field' => 'locale_option from GET /webadmin/api/locale-options', 'x-supported-fields' => ['locale_option', 'code', 'name', 'is_default', 'is_enabled'], 'x-default-locale-behavior' => 'Setting is_default=true makes the locale enabled and demotes other default locales through the normal CMS locale invariant.', 'responses' => ['201' => ['description' => 'Created locale JSON', 'content' => $json], '403' => ['description' => 'Requires site-settings.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/locale-options' => [
        'get' => ['summary' => 'List standard locale options for locale creation', 'responses' => ['200' => ['description' => 'Locale option catalog JSON', 'content' => $json]]],
      ],
      '/locales/{locale}' => [
        'patch' => ['summary' => 'Update safe locale fields', 'x-required-capability' => 'site-settings.write', 'x-preferred-field' => 'locale_option from GET /webadmin/api/locale-options when changing the locale identity', 'x-supported-fields' => ['locale_option', 'code', 'name', 'is_default', 'is_enabled'], 'x-default-locale-behavior' => 'The default locale must stay enabled; setting is_default=true demotes other default locales through the normal CMS locale invariant.', 'responses' => ['200' => ['description' => 'Updated locale JSON', 'content' => $json], '403' => ['description' => 'Requires site-settings.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/engagement/comments' => ['get' => ['summary' => 'List public Comments block submissions for AI/operator feedback analysis', 'x-required-capability' => 'engagement.read', 'x-filters' => ['status', 'site_id', 'page_id', 'block_id', 'search', 'per_page'], 'x-privacy' => 'Visitor hash, IP hash, and user-agent values are not exposed.', 'responses' => ['200' => ['description' => 'Comments JSON', 'content' => $json], '403' => ['description' => 'Requires engagement.read capability', 'content' => $json]]]],
      '/engagement/comments/{comment}' => ['patch' => ['summary' => 'Moderate a public comment by changing its status', 'x-required-capability' => 'engagement.moderate', 'x-supported-statuses' => CommentEntry::statuses(), 'responses' => ['200' => ['description' => 'Updated comment JSON', 'content' => $json], '403' => ['description' => 'Requires engagement.moderate capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/engagement/ratings' => ['get' => ['summary' => 'List public Rating block submissions and aggregate summaries', 'x-required-capability' => 'engagement.read', 'x-filters' => ['status', 'site_id', 'page_id', 'block_id', 'per_page'], 'x-privacy' => 'Visitor hash, IP hash, and user-agent values are not exposed.', 'responses' => ['200' => ['description' => 'Ratings JSON', 'content' => $json], '403' => ['description' => 'Requires engagement.read capability', 'content' => $json]]]],
      '/content/validate' => ['post' => ['summary' => 'Validate content plan', 'x-supported-modes' => $this->contentModes(), 'responses' => ['200' => ['description' => 'Valid plan JSON', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/content/apply' => ['post' => ['summary' => 'Apply content plan', 'x-supported-modes' => $this->contentModes(), 'x-mode-capabilities' => ['promote_staged_page_update' => 'content.publish plus content.apply'], 'responses' => ['201' => ['description' => 'Applied plan JSON', 'content' => $json], '403' => ['description' => 'Promote requires content.publish capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/navigation-menus' => ['get' => ['summary' => 'List navigation menus', 'responses' => ['200' => ['description' => 'Navigation JSON', 'content' => $json]]], 'post' => ['summary' => 'Create navigation menu items', 'x-required-capability' => 'navigation.write', 'responses' => ['201' => ['description' => 'Created navigation JSON', 'content' => $json], '403' => ['description' => 'Requires navigation.write capability', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}' => ['get' => ['summary' => 'Read navigation menu', 'responses' => ['200' => ['description' => 'Navigation JSON', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}/items' => ['post' => ['summary' => 'Create navigation item', 'x-required-capability' => 'navigation.write', 'responses' => ['201' => ['description' => 'Created navigation item JSON', 'content' => $json], '403' => ['description' => 'Requires navigation.write capability', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}/items/reorder' => ['patch' => ['summary' => 'Reorder navigation items', 'x-required-capability' => 'navigation.write', 'x-required-fields' => ['items'], 'x-note' => 'Payload must include every item in the selected site/menu exactly once.', 'responses' => ['200' => ['description' => 'Reordered navigation JSON', 'content' => $json], '403' => ['description' => 'Requires navigation.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}/items/{item}' => [
        'patch' => ['summary' => 'Update navigation item', 'x-required-capability' => 'navigation.write', 'x-supported-fields' => ['label', 'title', 'url', 'link_type', 'page_id', 'target', 'visibility', 'sort_order', 'position', 'parent_id', 'icon'], 'responses' => ['200' => ['description' => 'Updated navigation item JSON', 'content' => $json], '403' => ['description' => 'Requires navigation.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
        'delete' => ['summary' => 'Delete navigation item', 'x-required-capability' => 'navigation.delete', 'x-child-policy' => 'Items with child items are rejected; delete or reorder children first.', 'responses' => ['200' => ['description' => 'Deleted navigation item JSON', 'content' => $json], '403' => ['description' => 'Requires navigation.delete capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/shared-slots' => ['get' => ['summary' => 'List Shared Slots', 'responses' => ['200' => ['description' => 'Shared Slots JSON', 'content' => $json]]], 'post' => ['summary' => 'Create Shared Slot', 'responses' => ['201' => ['description' => 'Created Shared Slot JSON', 'content' => $json]]]],
      '/shared-slots/{sharedSlot}' => ['get' => ['summary' => 'Read Shared Slot', 'responses' => ['200' => ['description' => 'Shared Slot JSON', 'content' => $json]]]],
      '/shared-slots/{sharedSlot}/blocks' => ['post' => ['summary' => 'Create Shared Slot block', 'x-supported-fields' => ['locale', 'type', 'settings', 'translations', 'children', 'media_id', 'parent_id', 'parent_block_id'], 'responses' => ['201' => ['description' => 'Created Shared Slot block JSON', 'content' => $json]]]],
      '/shared-slots/{sharedSlot}/publish-blocks' => ['post' => ['summary' => 'Publish Shared Slot blocks', 'x-required-capability' => 'shared-slots.write plus content.publish', 'responses' => ['200' => ['description' => 'Published Shared Slot blocks JSON', 'content' => $json], '403' => ['description' => 'Requires shared-slots.write and content.publish capabilities', 'content' => $json]]]],
      '/media' => [
        'get' => ['summary' => 'List Media items for API-safe media assignment', 'x-required-capability' => 'media.read; content.read accepted for transitional compatibility', 'parameters' => [['name' => 'kind', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']]], 'responses' => ['200' => ['description' => 'Media JSON', 'content' => $json], '403' => ['description' => 'Requires media.read capability', 'content' => $json]]],
        'post' => ['summary' => 'Upload a file into the Media Library', 'x-required-capability' => 'media.upload', 'x-consumes' => 'multipart/form-data', 'x-supported-kinds' => ['image', 'video', 'document', 'other'], 'responses' => ['201' => ['description' => 'Uploaded media JSON', 'content' => $json], '403' => ['description' => 'Requires media.upload capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/media/fetch' => ['post' => ['summary' => 'Fetch one approved public remote file into the Media Library', 'x-required-capability' => 'media.upload', 'x-required-fields' => ['source_url'], 'x-supported-fields' => ['folder_id', 'source_url', 'title', 'alt_text', 'caption', 'description'], 'x-safety-guards' => ['http/https only', 'private and reserved network targets rejected', 'redirect limit', 'remote fetch size limit', 'allowed media MIME types'], 'responses' => ['201' => ['description' => 'Fetched media JSON', 'content' => $json], '403' => ['description' => 'Requires media.upload capability', 'content' => $json], '422' => ['description' => 'Validation or fetch failure JSON', 'content' => $json]]]],
      '/media/{media}' => [
        'get' => ['summary' => 'Read Media Library item and usage summary', 'x-required-capability' => 'media.read; content.read accepted for transitional compatibility', 'responses' => ['200' => ['description' => 'Media JSON', 'content' => $json], '403' => ['description' => 'Requires media.read capability', 'content' => $json]]],
        'patch' => ['summary' => 'Update safe Media Library metadata', 'x-required-capability' => 'media.write', 'x-supported-fields' => ['title', 'alt_text', 'caption', 'description'], 'x-unsupported-fields' => ['file upload', 'delete', 'replace', 'folder moves', 'storage paths'], 'responses' => ['200' => ['description' => 'Updated media JSON', 'content' => $json], '403' => ['description' => 'Requires media.write capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
        'delete' => ['summary' => 'Delete unused Media Library item', 'x-required-capability' => 'media.delete', 'x-usage-guard' => 'Media in use by blocks, site branding, or page SEO is rejected with usage details.', 'responses' => ['200' => ['description' => 'Deleted media JSON', 'content' => $json], '403' => ['description' => 'Requires media.delete capability', 'content' => $json], '422' => ['description' => 'Media in use JSON', 'content' => $json]]],
      ],
      '/media/{media}/replace' => ['post' => ['summary' => 'Replace a Media Library file while preserving the media id', 'x-required-capability' => 'media.replace', 'x-kind-guard' => 'Replacement file must resolve to the same media kind as the existing record.', 'responses' => ['200' => ['description' => 'Replaced media JSON', 'content' => $json], '403' => ['description' => 'Requires media.replace capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/media/{media}/move' => ['post' => ['summary' => 'Move a Media Library item to another folder or clear its folder', 'x-required-capability' => 'media.move', 'responses' => ['200' => ['description' => 'Moved media JSON', 'content' => $json], '403' => ['description' => 'Requires media.move capability', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/blocks/{block}' => [
        'get' => ['summary' => 'Read block', 'parameters' => [['name' => 'block', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Block JSON', 'content' => $json]]],
        'patch' => ['summary' => 'Update safe fields on an existing structured block', 'x-required-capability' => 'content.apply; shared-slots.write also required for Shared Slot source blocks', 'x-supported-fields' => ['media_id for navbar-brand/sidebar-brand logo media', 'media_id for hero/section/card/cta/content_header/slide background media', 'settings.url', 'settings.target', 'settings.aria_label', 'settings.background_position', 'settings.background_overlay', 'settings.show_search for header-actions', 'settings.show_mode_toggle for header-actions', 'settings.show_accent_toggle for header-actions', 'translations.title', 'translations.subtitle', 'translations.content', 'translations.image.alt_text for image blocks', 'translations.image.caption for image blocks', 'translations.alt_text shorthand for image blocks', 'translations.caption shorthand for image blocks', 'url', 'variant'], 'responses' => ['200' => ['description' => 'Updated block JSON', 'content' => $json], '403' => ['description' => 'Requires additional capability for Shared Slot source blocks', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
      ],
      '/pages/{page}/slots/{slot}/shared-slot' => ['post' => ['summary' => 'Assign Shared Slot to page slot', 'responses' => ['200' => ['description' => 'Assignment JSON', 'content' => $json]]]],
      '/pages/{page}/slots/{slot}/blocks' => ['post' => ['summary' => 'Add a single draft block (with optional normalized children) to a page-owned slot', 'x-required-capability' => 'content.apply', 'x-draft-only' => 'Only draft pages accept direct block topology changes.', 'x-guards' => ['Shared Slot-backed slots are rejected; use the Shared Slot endpoints.'], 'x-optional-fields' => ['parent_id to append under an existing container block in the same slot'], 'responses' => ['201' => ['description' => 'Created block JSON', 'content' => $json], '403' => ['description' => 'Requires content.apply capability', 'content' => $json], '404' => ['description' => 'Page slot not found', 'content' => $json], '409' => ['description' => 'Page is not a draft', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/pages/{page}/slots/{slot}/blocks/reorder' => ['patch' => ['summary' => 'Reorder the block sibling group of a page-owned slot', 'x-required-capability' => 'content.apply', 'x-draft-only' => 'Only draft pages accept direct block topology changes.', 'x-required-fields' => ['blocks'], 'x-note' => 'blocks must contain every sibling id for one parent group exactly once, in the desired order; pass parent_id is derived from the submitted blocks.', 'responses' => ['200' => ['description' => 'Reordered blocks JSON', 'content' => $json], '403' => ['description' => 'Requires content.apply capability', 'content' => $json], '404' => ['description' => 'Page slot not found', 'content' => $json], '409' => ['description' => 'Page is not a draft', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/pages/{page}/slots/{slot}/blocks/{block}' => ['delete' => ['summary' => 'Delete a page-owned block and its subtree from a slot', 'x-required-capability' => 'content.blocks.delete', 'x-draft-only' => 'Only draft pages accept direct block topology changes.', 'x-guards' => ['Shared Slot source blocks are rejected; use the Shared Slot endpoints.'], 'parameters' => [['name' => 'block', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Deletion JSON with deleted_blocks_count', 'content' => $json], '403' => ['description' => 'Requires content.blocks.delete capability', 'content' => $json], '404' => ['description' => 'Page slot not found', 'content' => $json], '409' => ['description' => 'Page is not a draft', 'content' => $json], '422' => ['description' => 'Block is not in this slot or is Shared Slot-backed', 'content' => $json]]]],
    ];
  }

  private function contactPagePayload(): array
  {
    return [
      'plan' => [
        'site' => 'default',
        'locale' => 'en',
        'layout' => 'default',
        'page' => [
          'title' => 'Contact Us',
          'path' => '/contact',
          'status' => 'draft',
        ],
        'slots' => [
          'main' => [
            [
              'type' => 'section',
              'settings' => ['spacing' => 'comfortable'],
              'children' => [
                [
                  'type' => 'hero',
                  'translations' => [
                    'title' => 'Contact Us',
                    'subtitle' => 'Tell us how we can help.',
                  ],
                  'settings' => [
                    'secondary_label' => 'View services',
                    'secondary_url' => '/services',
                  ],
                ],
                [
                  'type' => 'contact_form',
                  'translations' => [
                    'title' => 'Send us a message',
                    'content' => 'Use this native CMS form for questions, requests, and follow-up.',
                    'submit_label' => 'Send message',
                    'success_message' => 'Thanks for your message. We will get back to you soon.',
                  ],
                  'settings' => [
                    'recipient_email' => null,
                    'send_email_notification' => true,
                    'store_submissions' => true,
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  private function contentModes(): array
  {
    return [
      'create_draft_page',
      'replace_existing_draft_page',
      'create_staged_update_for_published_page',
      'replace_staged_page_update',
      'promote_staged_page_update',
    ];
  }

  private function workflows(CmsApiToken $token): array
  {
    $canPromote = $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_APPLY)
      && $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_PUBLISH);

    return [
      'published_page_staged_update' => [
        'description' => 'Safely edit a published page through a staged draft, then promote it onto the source page after approval.',
        'steps' => [
          'GET /webadmin/api/pages/{source_page} and reuse an existing active staged draft when one is exposed.',
          'POST /webadmin/api/content/validate with mode=create_staged_update_for_published_page only when no active staged draft exists; repeated create calls reuse the existing staged draft.',
          'POST /webadmin/api/content/apply to create the staged draft or return the reusable staged draft.',
          'POST /webadmin/api/content/validate and apply with mode=replace_staged_page_update for subsequent revisions on the staged draft.',
          'Preview /webadmin/pages/{staged_page}/preview outside the API',
          'GET /webadmin/api/pages/{staged_page} and follow page._actions.promote',
          'POST /webadmin/api/content/apply with mode=promote_staged_page_update',
        ],
        'do_not_use' => [
          'POST /webadmin/api/pages/{staged_page}/publish',
        ],
        'required_capabilities' => [
          'create_or_replace' => [CmsApiTokenCapabilities::CONTENT_APPLY],
          'promote' => [
            CmsApiTokenCapabilities::CONTENT_APPLY,
            CmsApiTokenCapabilities::CONTENT_PUBLISH,
          ],
        ],
        'available' => [
          'promote' => $canPromote,
        ],
      ],
      'public_branding_media' => [
        'description' => 'Upload or discover Media Library images, then assign them to site favicon/social image fields or existing brand logo blocks while keeping admin-editable CMS state.',
        'steps' => [
          'POST /webadmin/api/media with multipart/form-data and media.upload when the image is not already in the Media Library',
          'GET /webadmin/api/media?kind=image to confirm the Media id and public URL',
          'PATCH /webadmin/api/sites/{site}/branding with favicon_media_id or social_image_media_id for site-level public metadata',
          'PATCH /webadmin/api/blocks/{block} with media_id for navbar-brand or sidebar-brand logo media',
          'PATCH /webadmin/api/blocks/{block} with media_id for hero, section, card, CTA, content-header, or slide background media',
        ],
        'do_not_use' => [
          'Do not overwrite /cms/brand/* product/admin assets for public site branding.',
          'Do not send settings.logo_url for brand blocks; use media_id.',
          'Do not set background image paths in site CSS when the block supports Media Library background media.',
        ],
        'required_capabilities' => [
          'upload_media' => [CmsApiTokenCapabilities::MEDIA_UPLOAD],
          'assign_site_branding' => [CmsApiTokenCapabilities::SITE_SETTINGS_WRITE],
          'assign_brand_block_logo' => [CmsApiTokenCapabilities::CONTENT_APPLY],
          'assign_shared_slot_brand_block_logo' => [
            CmsApiTokenCapabilities::CONTENT_APPLY,
            CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
          ],
        ],
        'available' => [
          'upload_media' => $this->capabilities->has($token, CmsApiTokenCapabilities::MEDIA_UPLOAD),
          'assign_site_branding' => $this->capabilities->has($token, CmsApiTokenCapabilities::SITE_SETTINGS_WRITE),
          'assign_brand_block_logo' => $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_APPLY),
        ],
      ],
      'navigation_menu_management' => [
        'description' => 'Manage CMS Navigation menu items through explicit item endpoints instead of browser-admin clicks or public CSS hiding.',
        'steps' => [
          'GET /webadmin/api/navigation-menus?site={site_handle} to inspect menu handles and item ids',
          'POST /webadmin/api/navigation-menus/{navigationMenu}/items to create a new item',
          'PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/{item} to update label, URL, target, visibility, sort order, parent, icon, or link type',
          'PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/reorder with every item id in the selected site/menu to reorder or reparent',
          'DELETE /webadmin/api/navigation-menus/{navigationMenu}/items/{item} only when the token has navigation.delete and the item has no children',
        ],
        'do_not_use' => [
          'Do not hide unwanted navigation items with site CSS when the API exposes update/delete operations.',
          'Do not delete parent items before moving or deleting their child items.',
        ],
        'required_capabilities' => [
          'create_update_reorder' => [CmsApiTokenCapabilities::NAVIGATION_WRITE],
          'delete' => [CmsApiTokenCapabilities::NAVIGATION_DELETE],
        ],
        'available' => [
          'create_update_reorder' => $this->capabilities->has($token, CmsApiTokenCapabilities::NAVIGATION_WRITE),
          'delete' => $this->capabilities->has($token, CmsApiTokenCapabilities::NAVIGATION_DELETE),
        ],
      ],
      'commerce_product_buy_button' => [
        'description' => 'Install and setup WebBlocks Commerce, create products, and place plugin-owned buy buttons through the content API.',
        'steps' => [
          'GET /webadmin/api/plugins with plugins.read to inspect installed WebBlocks Commerce status',
          'GET /webadmin/api/plugins/catalog with plugins.read to find WebBlocks Commerce when it is not installed',
          'POST /webadmin/api/plugins/catalog/webblocks-commerce/install with plugins.install to install its checksum-verified catalog release disabled',
          'POST /webadmin/api/plugins/webblocks-commerce/enable with plugins.manage',
          'POST /webadmin/api/plugins/webblocks-commerce/setup with plugins.setup',
          'POST /webadmin/api/commerce/products with commerce.products.write to create an active product',
          'GET /webadmin/api/block-types or /content-contract and use block type webblocks-commerce-buy-button',
          'POST /webadmin/api/content/validate and /content/apply with a block settings.commerce_product_id equal to the product id',
        ],
        'do_not_use' => [
          'Do not add Commerce Buy Button as a core block; it is provided by the enabled plugin.',
          'Do not collect card data through the CMS API; checkout remains on the public Commerce/PayPal flow.',
          'Do not uninstall an enabled plugin; disable first and preserve plugin-owned tables.',
        ],
        'required_capabilities' => [
          'plugin_lifecycle' => [
            CmsApiTokenCapabilities::PLUGINS_READ,
            CmsApiTokenCapabilities::PLUGINS_INSTALL,
            CmsApiTokenCapabilities::PLUGINS_MANAGE,
            CmsApiTokenCapabilities::PLUGINS_SETUP,
          ],
          'create_product' => ['commerce.products.write'],
          'place_button' => [
            CmsApiTokenCapabilities::CONTENT_VALIDATE,
            CmsApiTokenCapabilities::CONTENT_APPLY,
          ],
        ],
        'available' => [
          'read_plugins' => $this->capabilities->has($token, CmsApiTokenCapabilities::PLUGINS_READ),
          'install_plugins' => $this->capabilities->has($token, CmsApiTokenCapabilities::PLUGINS_INSTALL),
          'manage_plugins' => $this->capabilities->has($token, CmsApiTokenCapabilities::PLUGINS_MANAGE),
          'setup_plugins' => $this->capabilities->has($token, CmsApiTokenCapabilities::PLUGINS_SETUP),
          'create_product' => $this->capabilities->has($token, 'commerce.products.write'),
          'place_button' => $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_VALIDATE)
            && $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_APPLY),
        ],
      ],
      'canonical_site_assets' => [
        'description' => 'Read and write the canonical physical site.css or site.js files for the resolved public site without using Trusted HTML, ad hoc paths, or database fallbacks.',
        'steps' => [
          'GET /webadmin/api/sites/{site}/assets/css or /assets/js with site-assets.read and inspect asset.readiness, asset.guidance, and CSS asset.analysis.mode_awareness',
          'Edit the returned contents locally',
          'PUT /webadmin/api/sites/{site}/assets/css or /assets/js with contents and expected_checksum copied from the read response checksum',
          'If the write returns a stale checksum validation error, read the asset again and merge intentionally before retrying',
          'If the write returns asset.write, report the hosting permission readiness issue instead of retrying blindly',
        ],
        'css_mode_policy' => [
          'site.css must cooperate with WebBlocks UI Light/Dark/Auto mode rather than replacing it.',
          'Prefer public theme custom properties, inherited wb-* component styling, and semantic site custom properties over raw light/dark colors.',
          'Use native block settings for content, media, background media, icon tones, and layout roles before adding CSS.',
          'If custom colors are unavoidable, define semantic variables with both light and dark values tied to active mode selectors or public theme tokens.',
          'Treat asset.analysis.mode_awareness.status = warning as work to review, report, or fix before considering a migration or new site setup complete.',
        ],
        'do_not_use' => [
          'Do not create alternate /site paths for global site CSS or JS.',
          'Do not use Trusted HTML for page-wide styling when site.css is the intended surface.',
          'Do not overwrite /cms/brand/* product/admin assets.',
          'Do not hard-code page-wide light backgrounds, dark text, white cards, or one-off dark-mode palettes when WebBlocks UI/CMS public theme tokens can express the design.',
        ],
        'required_capabilities' => [
          'read' => [CmsApiTokenCapabilities::SITE_ASSETS_READ],
          'write' => [CmsApiTokenCapabilities::SITE_ASSETS_WRITE],
        ],
        'available' => [
          'read' => $this->capabilities->has($token, CmsApiTokenCapabilities::SITE_ASSETS_READ),
          'write' => $this->capabilities->has($token, CmsApiTokenCapabilities::SITE_ASSETS_WRITE),
        ],
      ],
    ];
  }
}
