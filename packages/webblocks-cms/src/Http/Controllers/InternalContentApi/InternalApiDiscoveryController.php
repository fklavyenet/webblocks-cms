<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\WebBlocks;

class InternalApiDiscoveryController extends Controller
{
  public function __construct(
    private readonly CmsApiTokenAuthenticator $authenticator,
    private readonly CmsApiTokenCapabilities $capabilities,
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
      'api_version' => '1',
      'authenticated' => true,
      'token' => $this->capabilities->publicDescription($token),
      'recommended_next_steps' => [
        'Read the OpenAPI schema, AI guide, content contract, and examples.',
        'Validate content plans with POST /webadmin/api/content/validate before apply.',
        'Apply only after explicit user approval.',
        'Use JSON requests with Authorization, Accept, and Content-Type headers.',
      ],
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
        'Destructive operations require explicit capabilities such as `pages.delete` or `content.publish`. Standard page-building tokens should not include destructive capabilities.',
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
      'path' => '/example-landing',
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
      'examples' => '/webadmin/api/examples',
      'content_validate' => '/webadmin/api/content/validate',
      'content_apply' => '/webadmin/api/content/apply',
      'pages' => '/webadmin/api/pages',
      'page' => '/webadmin/api/pages/{page}',
      'page_delete' => '/webadmin/api/pages/{page}',
      'navigation_menus' => '/webadmin/api/navigation-menus',
      'shared_slots' => '/webadmin/api/shared-slots',
    ];
  }

  private function openApiPaths(): array
  {
    $json = ['application/json' => ['schema' => ['type' => 'object']]];

    return [
      '/' => ['get' => ['summary' => 'API discovery', 'responses' => ['200' => ['description' => 'Discovery JSON', 'content' => $json]]]],
      '/openapi.json' => ['get' => ['summary' => 'OpenAPI schema', 'responses' => ['200' => ['description' => 'OpenAPI JSON', 'content' => $json]]]],
      '/ai-guide' => ['get' => ['summary' => 'AI usage guide', 'responses' => ['200' => ['description' => 'AI guide JSON', 'content' => $json]]]],
      '/content-contract' => ['get' => ['summary' => 'Content contract', 'responses' => ['200' => ['description' => 'Content contract JSON', 'content' => $json]]]],
      '/examples' => ['get' => ['summary' => 'Example index', 'responses' => ['200' => ['description' => 'Examples JSON', 'content' => $json]]]],
      '/examples/contact-page' => ['get' => ['summary' => 'Contact page example', 'responses' => ['200' => ['description' => 'Example JSON', 'content' => $json]]]],
      '/examples/landing-page' => ['get' => ['summary' => 'Landing page example', 'responses' => ['200' => ['description' => 'Example JSON', 'content' => $json]]]],
      '/pages' => ['get' => ['summary' => 'List pages', 'responses' => ['200' => ['description' => 'Pages JSON', 'content' => $json]]]],
      '/pages/{page}' => [
        'get' => ['summary' => 'Read page', 'parameters' => [['name' => 'page', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Page JSON', 'content' => $json]]],
        'delete' => ['summary' => 'Delete page', 'x-required-capability' => 'pages.delete', 'responses' => ['200' => ['description' => 'Deleted page JSON', 'content' => $json], '403' => ['description' => 'Requires pages.delete capability', 'content' => $json]]],
      ],
      '/content/validate' => ['post' => ['summary' => 'Validate content plan', 'responses' => ['200' => ['description' => 'Valid plan JSON', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/content/apply' => ['post' => ['summary' => 'Apply content plan', 'responses' => ['201' => ['description' => 'Applied plan JSON', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]]],
      '/navigation-menus' => ['get' => ['summary' => 'List navigation menus', 'responses' => ['200' => ['description' => 'Navigation JSON', 'content' => $json]]], 'post' => ['summary' => 'Create navigation menu items', 'responses' => ['201' => ['description' => 'Created navigation JSON', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}' => ['get' => ['summary' => 'Read navigation menu', 'responses' => ['200' => ['description' => 'Navigation JSON', 'content' => $json]]]],
      '/navigation-menus/{navigationMenu}/items' => ['post' => ['summary' => 'Create navigation item', 'responses' => ['201' => ['description' => 'Created navigation item JSON', 'content' => $json]]]],
      '/shared-slots' => ['get' => ['summary' => 'List Shared Slots', 'responses' => ['200' => ['description' => 'Shared Slots JSON', 'content' => $json]]], 'post' => ['summary' => 'Create Shared Slot', 'responses' => ['201' => ['description' => 'Created Shared Slot JSON', 'content' => $json]]]],
      '/shared-slots/{sharedSlot}' => ['get' => ['summary' => 'Read Shared Slot', 'responses' => ['200' => ['description' => 'Shared Slot JSON', 'content' => $json]]]],
      '/shared-slots/{sharedSlot}/blocks' => ['post' => ['summary' => 'Create Shared Slot block', 'responses' => ['201' => ['description' => 'Created Shared Slot block JSON', 'content' => $json]]]],
      '/pages/{page}/slots/{slot}/shared-slot' => ['post' => ['summary' => 'Assign Shared Slot to page slot', 'responses' => ['200' => ['description' => 'Assignment JSON', 'content' => $json]]]],
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
                    'primary_label' => 'Email support',
                    'primary_url' => 'mailto:support@example.test',
                    'secondary_label' => 'View services',
                    'secondary_url' => '/services',
                  ],
                ],
                [
                  'type' => 'plain_text',
                  'translations' => [
                    'content' => 'Use this draft page as a starting point for contact information, office details, and service questions.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }
}
