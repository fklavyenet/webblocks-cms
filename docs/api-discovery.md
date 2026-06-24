# API Discovery

WebBlocks CMS exposes a discovery-first Content API for trusted AI and operator tools. An external tool should need only the CMS API base URL and a CMS API token to learn the available endpoints, schemas, examples, and safe content workflow.

## Base URL

```text
/webadmin/api
```

The first request should be:

```http
GET /webadmin/api
Authorization: Bearer <token>
Accept: application/json
```

## Unauthenticated Response

`GET /webadmin/api` is intentionally public-safe. Without a valid Bearer token it returns only minimal bootstrap JSON:

- product name
- API version
- `authenticated: false`
- a self link
- a short message telling the caller to authenticate

It must not return endpoint inventory, site data, content contracts, token previews, token hashes, user details, local paths, or server internals.

## Authenticated Response

With a valid CMS API Bearer token, discovery returns:

- `product: WebBlocks CMS`
- `api_version`
- `authenticated: true`
- token capability names, without token value, token preview, or token hash
- recommended next steps
- links for OpenAPI, AI guide, content contract, examples, validate/apply, pages, navigation, and Shared Slots

The authenticated response is the canonical bootstrap contract for AI/operator tools. Tools should follow returned links instead of assuming local filesystem access to the CMS repository or package docs.

## Linked Resources

Current discovery links include:

```text
GET /webadmin/api/openapi.json
GET /webadmin/api/ai-guide
GET /webadmin/api/content-contract
GET /webadmin/api/examples
GET /webadmin/api/examples/contact-page
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
GET /webadmin/api/pages
GET /webadmin/api/pages/{page}
GET /webadmin/api/navigation-menus
GET /webadmin/api/shared-slots
```

Protected links require:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## Capabilities

CMS API tokens expose the capabilities selected at token creation in discovery so tools can understand allowed actions before attempting writes.

Standard page-building capabilities:

- `content.read`
- `content.validate`
- `content.apply`
- `navigation.write`
- `shared-slots.write`

Destructive or publish capabilities are separate advanced options and are not selected by default:

- `content.publish`
- `pages.delete`

Destructive operations must require an explicit matching capability and should not be granted to normal page-building tokens.

## JSON-Only Errors

Content API endpoints return JSON errors instead of browser redirects, login pages, or CSRF HTML responses. Error payloads include guidance links when applicable:

- `api_discovery_url`
- `openapi_url`
- `documentation_url`
- `example_url`

Expected status behavior:

- `401` for missing, invalid, or revoked tokens
- `403` for missing capabilities
- `422` for invalid content payloads

## Security

Discovery, OpenAPI, AI guide, examples, and content contract responses must not expose real token values, token hashes, `.env` values, local filesystem paths, server paths, stack traces, raw exceptions, database internals, user lists, or private operator details.
