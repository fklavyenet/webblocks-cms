# Public Consent JS Audit

## Scope

This audit reviews the current public cookie-consent and Visitor Reports JavaScript before any refactor or file move.

No runtime code was changed as part of this audit.

## Current Inline Script Location

The consent-related inline JavaScript is currently rendered in:

- `resources/views/layouts/public.blade.php`

The related consent markup is rendered from:

- `resources/views/partials/public-privacy-consent.blade.php`
- `resources/views/pages/partials/slots/footer.blade.php`

### Layout Classification

- public layout: yes, the inline script is in `resources/views/layouts/public.blade.php`
- welcome screen: indirectly yes, because `resources/views/welcome.blade.php` extends the public layout
- auth layout: no
- admin layout: no
- install layout: no

## Related Public Consent Markup And Hooks

The current public layout and partials include the following consent-related hooks and state keys:

- `/privacy-consent/sync`
- `webblocks_visitor_consent`
- `wb-cookie-consent`
- `wb-cookie-consent-preferences`
- `wb:cookie-consent:change`

These appear in:

- `resources/views/layouts/public.blade.php`
- `resources/views/partials/public-privacy-consent.blade.php`
- `resources/views/pages/partials/slots/footer.blade.php`

## Syntax Error Root Cause

The reported browser error is consistent with the inline script in `resources/views/layouts/public.blade.php`.

The exact broken block is the slider section near the end of the second inline script:

```js
if (next) {
    next.addEventListener('click', function () {
        render(activeIndex + 1);
    });
});
```

There is one extra closing `);` after the `if (next)` block.

The correct structural shape should end the `if` block with `}` only, but the current code closes the `addEventListener(...)` call and then closes an additional non-existent call.

That extra closing token is the likely cause of:

`Uncaught SyntaxError: Unexpected token ')'`

## Existing JS Asset Structure

Current JavaScript locations checked:

- `resources/js`: does not exist
- `public/js`: does not exist
- `public/site/js`: exists
- `public/assets`: does not exist

Current JS files found:

- `public/site/js/site.js`

`public/site/js/site.js` currently contains only a comment block stating that it is for site-specific public behavior and that generic CMS core behavior should remain elsewhere.

## Build Pipeline Audit

No active Vite or frontend build pipeline was found.

Checked and not found:

- `package.json`
- `vite.config.*`
- `webpack.mix.js`
- Blade `@vite` usage
- documented Vite references

Conclusion:

- there is no active Vite setup
- there is no current npm-based or compiled JS pipeline in active use

## Responsibility Classification

### WebBlocks UI Pattern Behavior

These responsibilities belong to WebBlocks UI, or should remain conceptually owned by the UI pattern layer:

- consent banner and modal open or close behavior driven by `data-wb-cookie-consent`
- local browser-side consent state keys:
  - `wb-cookie-consent`
  - `wb-cookie-consent-preferences`
- event emission via `wb:cookie-consent:change`

### CMS Backend Consent Sync

These responsibilities are CMS-owned:

- posting consent state to `route('public.privacy-consent.sync')`
- syncing the backend cookie value such as `webblocks_visitor_consent`
- keeping Laravel/backend consent state aligned with browser-side pattern state
- using CSRF and same-origin fetch rules for consent sync

### Visitor Reports Consent Integration

These responsibilities are CMS-owned and specific to Visitor Reports behavior:

- mapping preferences to backend accepted/declined analytics consent
- keeping the consent cookie aligned with the analytics mode expected by Visitor Reports
- reconciling initial server choice with browser local storage when needed

### Unnecessary Duplicate Or Mixed Behavior

The current inline script mixes multiple responsibilities inside the public layout:

- server-to-local-storage hydration for consent state
- backend consent synchronization
- Visitor Reports consent-cookie integration
- unrelated public slider behavior

This is not yet a refactor recommendation, but the current script is serving more than one responsibility and includes logic that is not specific to consent sync.

## Recommended Target File Path

Do not use Vite.

Do not use `public/site/js/site.js`, because that file is intended for install-level or site override behavior rather than CMS core behavior.

Recommended CMS-owned static path:

- `public/assets/webblocks-cms/js/privacy-consent-sync.js`

This path is appropriate because:

- it clearly marks the file as CMS-owned
- it keeps core behavior separate from site override space
- it works with the current no-build static asset approach
- it leaves room for future CMS-owned public assets under one stable namespace

## Recommended Loading Rule

The CMS-owned consent sync script should load only in the public layout when consent sync is relevant.

Recommended load boundary:

- load from `resources/views/layouts/public.blade.php`
- only when public cookie consent or Visitor Reports consent synchronization is relevant for the rendered page

It should not load in:

- admin layout
- auth layout
- install layout

Practical condition:

- public pages using the public layout where the consent banner, modal, footer reopen control, or Visitor Reports consent synchronization is active

## What Should Remain CMS-Owned

- sync requests to `/privacy-consent/sync`
- `webblocks_visitor_consent` cookie alignment
- server-choice hydration needed for CMS backend state
- Visitor Reports analytics-consent mapping
- public-layout loading conditions for CMS consent sync

## What Should Be Left To WebBlocks UI

- consent pattern UI behavior
- consent preference storage model and browser events
- modal and banner interaction behavior for the shared consent component

## Summary

- Current inline script file: `resources/views/layouts/public.blade.php`
- Current syntax error root cause: extra closing `);` in the slider `next.addEventListener(...)` block
- No active Vite or JS build pipeline is in use
- Existing conventional JS override path: `public/site/js/site.js`, but it is not appropriate for CMS core consent logic
- Recommended CMS-owned target path: `public/assets/webblocks-cms/js/privacy-consent-sync.js`
- Recommended loading scope: public pages only, from the public layout, when consent sync is relevant
