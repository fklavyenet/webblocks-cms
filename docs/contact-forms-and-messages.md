# Contact Forms And Messages

Contact Form and Contact Messages are first-class WebBlocks CMS features. Use the native `contact_form` block for contact pages instead of Trusted HTML, raw form markup, or `mailto:` fallbacks.

This guide consolidates the user-facing Contact Form and Contact Messages behavior that is documented across block contracts, public render contracts, the Internal Content API docs, and the README. It does not define new runtime behavior.

## Overview

The Contact Form block renders a real public form and stores accepted messages in the CMS. Contact Messages gives admins a review screen for those submissions, including status, spam review signals, notification status, and safe delivery failure details.

The CMS treats storage and email notification as separate concerns:

- accepted real submissions are stored before notification is attempted
- notification can succeed, fail, or be skipped without changing the fact that the CMS accepted the public submission
- public visitors should see generic success or validation feedback, not spam scoring or delivery internals

## Adding A Contact Form Block

Add the block from the Page Builder block picker. The native block handle is:

```text
contact_form
```

The Contact Form block is not a child container. It owns the public form surface and does not accept nested content blocks as its normal composition model.

A typical contact page uses structured content around the native form:

```text
main slot
  section
    container
      content_header or hero
      contact_form
```

AI/operator tools should build the same kind of structure after live discovery confirms the available handles. They should not create forms with Trusted HTML, raw `<form>` markup, or `mailto:` links when `contact_form` exists in the target CMS install.

## Contact Form Block Fields

Visible copy is translatable:

- `title` or heading
- `content` or intro text
- `submit_label`
- `success_message`

Shared operational settings live on the block settings:

- `recipient_email`
- `send_email_notification`
- `store_submissions`

Keep editorial copy and operational routing separate. The form text can vary by locale, while recipient and notification settings stay shared for the block.

## Public Submit Behavior

The public renderer emits a native browser form that posts to:

```text
POST /contact-messages
```

The form is CSRF-protected and validates normal visitor fields.

Required fields:

- `name`
- `email`
- `message`

Optional field:

- `subject`

Renderer-generated anti-spam check field:

- `_form_check_name` signed metadata
- `form_check_{token}` generated check input

The renderer creates these fields automatically. Do not create them manually in content, raw HTML, or API payloads. The check field is not part of normal visitor input, and the old `website` field is no longer the public Contact Form contract.

Filled check-field submissions receive the same generic success behavior as a normal accepted submission, but they are not stored and do not trigger notification. Very fast automated submissions are handled the same way. Public visitors should not receive spam or delivery diagnostics.

## Storage And Contact Messages Admin

Accepted real submissions are stored before email notification is attempted.

Admin path:

```text
/webadmin/contact-messages
```

Contact Messages is an admin review screen. It is not a public listing and not a public delivery API.

The admin screen lets CMS users review submissions at a user-guide level:

- message status such as new, read, replied, archived, or spam
- source page context when available
- spam score and spam reason labels for review
- whether notification was skipped, sent, failed, or is pending
- safe failure details when notification delivery fails

Contact Messages also follows the shared admin listing behavior for selected bulk deletion where available. Treat deletion as an admin cleanup action, not as part of normal public form handling.

## Spam Handling

Spam handling has two layers.

The renderer-generated check-field layer is immediate discard:

- the hidden generated `form_check_{token}` field should stay empty for normal visitors
- filled check-field submissions receive generic success
- check-field submissions are not stored
- check-field submissions do not trigger notification

Submissions that pass the generated check field can still be scored conservatively. Current examples of scoring signals include:

- high link density or multiple links
- commercial outreach language
- generic sales-style subject and message combinations
- repeated submissions from the same IP address

Scored spam is intentionally retained with spam status for admin review. Do not describe automatic spam deletion as current behavior. A configurable auto-discard threshold is only a possible future direction after observing production data.

## Email Notification Fallback Chain

Contact Form notification uses this recipient order:

1. Block-level `recipient_email`
2. Current site's default Contact recipient from `Edit Site -> Contact`
3. `CONTACT_RECIPIENT_EMAIL`
4. `MAIL_FROM_ADDRESS` as the last safe fallback

Storage success is separate from notification success. A public success response means the CMS accepted the message flow, not necessarily that email delivery succeeded. Admins should inspect Contact Messages for notification status and safe failure details.

## Troubleshooting And Diagnostics

For local development, use a local SMTP catcher or a trusted SMTP test account. Avoid testing contact delivery against personal or production mailboxes unless the operator intentionally configured that environment.

The diagnostic command is:

```bash
php artisan contact:mail-diagnose
```

Use a specific Contact Form block ID to inspect recipient fallback for that block:

```bash
php artisan contact:mail-diagnose --block=ID
```

Use a controlled SMTP send check only when the target test address is intentional:

```bash
php artisan contact:mail-diagnose --send-test=address@example.com
```

Diagnostics must not print passwords, tokens, mail secrets, or raw sensitive configuration. Failed notifications can be inspected from Contact Messages delivery and failure details.

## Internal Content API And AI Operator Support

The Internal Content API is for trusted AI/operator tools. It is not a public delivery API and not an AI vendor integration.

AI/operator tools should start with live discovery:

```text
GET /webadmin/api
```

Then use the discovered links for current contracts:

```text
GET /webadmin/api/content-contract
GET /webadmin/api/block-types
GET /webadmin/api/examples/contact-page
```

`GET /webadmin/api/examples/contact-page` demonstrates native `contact_form` usage. AI/operator tools must not guess handles and must not build contact forms with Trusted HTML, raw form markup, or `mailto:` links when the native Contact Form block is available.

Content apply remains draft-first and does not publish by default. Operators should validate plans, apply only after explicit approval, preview the draft page, and leave publish to a human or explicitly approved workflow.

## Related Docs

- [Block Type Contracts](block-type-contracts.md)
- [Public Block Render Markup](public-block-render-markup.md)
- [Renderer Contracts](block-ui-renderer-contract.md)
- [Internal Content API](internal-content-api.md)
- [API Discovery](api-discovery.md)
- [AI Page Building Guide](ai-page-building-guide.md)
- [Feature Inventory](feature-inventory.md)
