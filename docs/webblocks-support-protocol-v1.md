# WebBlocks Support Protocol 1.0

WebBlocks CMS and other products use this protocol to connect an installation
to a support provider without giving the installation an organization-wide
credential. WebBlocks Workbench is one provider; agencies may implement the
same contract on their own HTTPS origin.

## Discovery

`GET /.well-known/webblocks-support` returns JSON:

```json
{
  "protocol": "webblocks-support",
  "version": "1.0",
  "name": "Example Support",
  "api_base_url": "https://support.example.com/api/webblocks-support/v1",
  "capabilities": ["ticket.create", "ticket.list", "ticket.read", "ticket.reply"]
}
```

The discovery URL, API base URL and activation page must use the same public
HTTPS origin. Redirects are not followed. CMS 1.0 requires all four ticket
capabilities.

## Installation activation

`POST {api_base_url}/activations` accepts:

```json
{
  "install_ref": "random-install-uuid",
  "product": "webblocks-cms",
  "product_version": "1.74.0",
  "site_url": "https://example.com",
  "environment": "production"
}
```

It returns an activation secret for polling and a user-facing code and URL:

```json
{
  "activation_id": "act_123",
  "activation_secret": "one-install-polling-secret",
  "user_code": "ABCD-EFGH",
  "verification_url": "https://support.example.com/connect",
  "expires_at": "2026-08-28T14:00:00Z"
}
```

The provider owns login, purchase, organization selection and entitlement
rules. The CMS polls `GET {api_base_url}/activations/{activation_id}` with the
activation secret as a bearer token. A pending response is
`{"status":"pending"}`. Once approved it returns:

```json
{
  "status": "active",
  "credential": "installation-scoped-bearer-secret",
  "plan_name": "Support",
  "entitlement_expires_at": "2027-08-28T00:00:00Z"
}
```

The credential must be limited to one product and installation. It must not
allow organization, project, plan or other installation administration.

## Tickets

All ticket calls authenticate with the installation credential. The endpoints
are relative to `api_base_url`:

- `POST /tickets`
- `GET /tickets?external_user_ref=...&install_ref=...`
- `GET /tickets/{ticket}?install_ref=...`
- `POST /tickets/{ticket}/comments`
- `DELETE /installation` to revoke the installation credential

Ticket creation includes `title`, `body`, `type`, `external_user_ref`,
`external_user_name`, `install_ref`, `product`, `product_version`, `site_url`
and `environment`. The provider derives its project and entitlement from the
credential; the client never supplies a project id.

Ticket reads must be scoped by credential and `install_ref`. The CMS also
checks `external_user_ref` before showing a ticket, so one administrator cannot
read another administrator's ticket by guessing its id.

## Secret handling

Activation and installation credentials are server-to-server secrets. They
must never be returned to a browser, logged, placed in a site export or exposed
again in the UI. CMS stores them encrypted with its application key.
