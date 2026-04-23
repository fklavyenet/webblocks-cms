# Editorial Workflow

## Workflow Statuses

Pages use four workflow statuses:

- `draft`
- `in_review`
- `published`
- `archived`

New pages start as `draft`.

## What The Statuses Mean

### `draft`

- working content
- not public
- editors can edit content in this state

### `in_review`

- ready for review
- not public
- editors cannot keep editing page content until the page is moved back to `draft`

### `published`

- public
- routable on the public site
- page visibility still works together with block-level visibility rules inside the page

### `archived`

- retired from live use
- not public
- can be moved back to `draft` or published again by an allowed role

## Who Can Move Between Statuses

### `editor`

Allowed actions:

- `draft` -> `in_review`
- `in_review` -> `draft`

Not allowed:

- publishing
- archiving

### `site_admin`

Allowed actions for assigned sites:

- `draft` -> `in_review`
- `draft` -> `published`
- `in_review` -> `draft`
- `in_review` -> `published`
- `in_review` -> `archived`
- `published` -> `draft`
- `published` -> `archived`
- `archived` -> `draft`
- `archived` -> `published`

### `super_admin`

`super_admin` follows the same workflow abilities as `site_admin`, but across the whole install.

## Public Visibility Rules

Only pages with status `published` are public.

- `draft` pages return `404`
- `in_review` pages return `404`
- `archived` pages return `404`

This applies across normal public routes, multisite routing, and localized routes.

## Workflow And Page Editing

Workflow state also affects who can keep editing page content.

- editors can edit page content only while the page is in `draft`
- once a page leaves `draft`, content editing requires a `site_admin` or `super_admin`, or a workflow move back to `draft`
- `site_admin` and `super_admin` users can continue working across workflow states they are allowed to manage

## Practical Flow

1. editor creates a page in `draft`
2. editor finishes content and submits it for review
3. site admin or super admin reviews the page
4. site admin or super admin publishes the page
5. later updates can move the page back to `draft`, then through review again
