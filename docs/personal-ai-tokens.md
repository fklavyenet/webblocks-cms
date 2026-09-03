---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/personal-ai-tokens
cms_title: Personal AI Tokens
cms_layout: docs
cms_source_id: webblocks-cms:docs/personal-ai-tokens.md
---

# Personal AI Tokens

Personal AI tokens let a signed-in CMS user delegate work to an AI or operator tool without sharing a password or granting installation-wide authority. The AI acts as that user: every request is limited by the token settings **and** the user's current CMS access.

## Personal And System Tokens

| Token type | Created from | Intended owner | Authority |
| --- | --- | --- | --- |
| Personal AI token | Profile → Personal AI Tokens | Editor, Site admin, or Super admin | Content and site work within the owner's live role and selected sites |
| System API token | System → API Tokens | Super admin | Trusted installation-level automation with explicitly selected capabilities |

Personal tokens deliberately exclude installation-level backups, maintenance, plugins, Embedded Applications, domains, physical site/page assets, and admin rendering. A super admin who needs to automate those operations must create a separate System API token. This keeps a personal assistant from silently becoming an installation operator.

## Effective Permission Rule

A personal token can perform an operation only when all of these checks pass:

1. The token is active and has not expired.
2. Its owner is still an active CMS user.
3. The requested capability is selected on the token.
4. The owner's current role still permits that capability.
5. The target site is selected on the token and remains accessible to the owner.
6. The page's current workflow state permits the owner to make the requested change.
7. The request comes from an allowed network, when an IP allowlist is configured.
8. The token-specific and installation-wide request limits have not been exceeded.

Changing a user's role, site assignments, or active state takes effect on the next API request. A token never preserves authority its owner has lost.

## Role Matrix

| Area | Editor token | Site admin token | Super admin personal token | System token |
| --- | --- | --- | --- | --- |
| Read assigned-site content | Yes | Yes | All sites | If granted |
| Create and change draft content | Yes | Yes | All sites | If granted |
| Publish or archive | No | If granted | If granted | If granted |
| Safe site presentation settings | No | If granted | If granted | If granted |
| Navigation, Shared Slots, media, and engagement | Within selected and assigned sites, if granted | Within selected and assigned sites, if granted | Within selected sites, if granted | If granted |
| Users, updates, backups, maintenance, plugins, applications, domains, physical assets | No | No | No | Only with the corresponding capability |

The token form shows only capabilities that the current user may delegate. Selecting a capability is an upper bound, not a way to bypass the role.

## Create A Token

1. Open **Profile** and select **Manage AI Tokens**.
2. Enter a name that identifies the client or job.
3. Select the sites the AI may reach.
4. Select only the capabilities the job needs.
5. Choose an expiry period.
6. Optionally configure network controls.
7. Select **Create Token**.
8. Copy the token immediately. Its plain value is shown only once.

The success panel provides the API base URL, environment-variable example, and a copy-ready AI setup prompt. Give the secret only to the intended tool and store it in that tool's secret store.

## Edit, Review, Revoke, Or Delete

- **Edit** changes the name, selected sites, capabilities, renewed expiry, IP allowlist, and request ceiling without revealing or replacing the secret.
- **Activity** shows the latest ten requests: outcome, time, method, path without query string, route, required capability, IP, and a shortened user agent.
- **Revoke** immediately prevents further authentication while retaining the token and activity history.
- **Delete** permanently removes the token and its activity history.

Request and response bodies, query strings, bearer values, token hashes, and token previews are never stored in activity rows.

## Network Controls

The allowlist accepts one exact IPv4/IPv6 address or CIDR network per line, for example:

```text
203.0.113.10
198.51.100.0/24
2001:db8::/32
```

Leave the list empty to allow any network. Choose a token-specific ceiling of 30, 60, 120, or 300 requests per minute. Existing tokens without a stored ceiling use 60 requests per minute. The installation-wide API throttle still applies, so the effective limit is the lowest applicable limit.

### Reverse Proxies And CDNs

Network checks use the client IP resolved by Laravel. When the host is behind a load balancer, reverse proxy, or CDN, configure Laravel's trusted-proxy handling for the actual proxy addresses and forwarded headers. Verify the resolved address before enabling a restrictive allowlist. Do not trust forwarded headers from arbitrary internet clients; otherwise a caller may spoof the address used by the policy.

## Connect An AI

Configure the generated values in the tool's trusted secret store:

```dotenv
WEBBLOCKS_CMS_API_URL=https://example.com/webadmin/api
WEBBLOCKS_CMS_API_TOKEN=...
```

The tool should call `GET /webadmin/api` first. Authenticated discovery returns its capabilities, personal network policy, OpenAPI and guide links, and recommended next steps. It must validate content plans before applying them and request explicit user approval before publish or destructive operations.

## Error Contract

| HTTP | Code | Meaning |
| --- | --- | --- |
| 401 | `invalid_internal_api_token` | Token is missing, invalid, revoked, expired, or no longer backed by an active CMS user |
| 403 | `missing_internal_api_capability` | The token does not currently hold the required capability |
| 403 | `delegated_site_access_denied` | The selected resource or submitted site is outside the token owner's live scope |
| 403 | `delegated_workflow_access_denied` | The owner cannot edit the page in its current workflow state |
| 403 | `delegated_operation_denied` | The operation is installation-level and cannot use a personal token |
| 403 | `delegated_network_access_denied` | The resolved client IP is outside the token allowlist |
| 429 | `personal_api_token_rate_limit_exceeded` | The token-specific request ceiling was reached; honour `Retry-After` |
| 422 | Endpoint-specific validation code | The request is authenticated but its payload or state transition is invalid |

All API errors are JSON and include public-safe discovery links where available.

## Recommended Practice

- Create one token per AI, integration, or bounded job.
- Give it the fewest sites and capabilities it needs.
- Prefer short expiry periods and renew deliberately.
- Use a stable egress IP allowlist when the AI platform provides one.
- Review recent activity after initial setup and after sensitive work.
- Revoke immediately if a secret may have been exposed.
- Never paste tokens into tickets, logs, screenshots, source control, or prompts that may be retained by unrelated services.

See [Internal Content API](internal-content-api.md), [Users And Permissions](users-and-permissions.md), and [Security](security.md) for the underlying API, role, and deployment boundaries.
