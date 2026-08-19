# Embedded Applications Plan

This document records the proposed architecture for embedding trusted HTML/CSS/JavaScript applications in WebBlocks CMS pages without requiring a bespoke plugin for every application.

**Status: Phase 1 implemented.** Core now ships the manifest-backed Application Registry, Application Block, inline and same-origin iframe rendering, deduplicated assets, manifest-schema settings validation in the panel and content API, and read-only discovery guarded by `applications.read`. Operator-authored registry mutation, plugin providers, authenticated application backends, and an iframe message bridge remain future work.

The first intended consumers are:

- the multilingual typing test for `fklavye.net`, rendered inline inside the normal CMS public layout;
- WebBlocks Play games, rendered in controlled same-origin iframes;
- future calculators, quizzes, visualizations, media tools, and small interactive widgets.

The feature name is **Embedded Applications**. The page-builder block is **Application Block**, with the proposed core block slug `application`.

## Decision Summary

An embedded application is not necessarily a plugin.

- **Application Block** places and configures an application on a page.
- **Application Registry** describes reusable applications and their runtime requirements.
- **Application Runtime** loads assets, creates inline or iframe hosts, supplies safe context, and coordinates lifecycle events.
- **Application Manifest** lets a trusted local application register without PHP or a CMS plugin.
- **Plugin registration** is an optional integration path for applications that need backend routes, database tables, permissions, custom admin UI, or other server-side behavior.

The CMS stores an application reference and validated configuration. It does not normally store executable source code inside page content.

The supported order of preference is:

1. a registered application discovered from a validated local manifest;
2. a registered application declared by an enabled plugin;
3. a trusted operator-defined application using allowlisted local HTML/CSS/JS paths;
4. a controlled iframe application;
5. `html` (Trusted HTML) only as the existing human-only escape hatch.

The Application Block must be writable through the Internal Content API. The existing `html` block remains human-only and API non-writable.

## Why This Is A Core CMS Capability

Interactive applications are a general presentation need, not one business domain. Requiring a plugin for every static game, calculator, quiz, or browser-only tool creates packaging and lifecycle work without adding meaningful safety.

The reusable CMS concern is narrower:

- identify an installed or locally available application;
- validate its declared files and settings;
- place an instance in a page slot;
- load each asset once in a deterministic order;
- render a safe inline or iframe host;
- pass locale, theme, page, and block context;
- expose the same contract to the admin panel and Internal Content API;
- keep arbitrary executable code behind an explicit trusted-operator boundary.

Domain behavior remains outside core. A booking application, payment flow, user progress store, or leaderboard may still need a plugin, but the visual application can continue to be placed through the same Application Block.

## Conceptual Model

```text
Application provider
  |-- local manifest
  |-- enabled CMS plugin
  `-- trusted operator definition
          |
          v
Application Registry
  |-- identity and version
  |-- inline or iframe runtime
  |-- asset declarations
  |-- settings schema
  |-- capabilities and context needs
  `-- integrity/readiness status
          |
          v
Application Block instance
  |-- application handle
  |-- validated instance settings
  |-- layout/presentation settings
  `-- optional locale-owned editorial copy
          |
          v
Application Runtime
  |-- public host markup
  |-- deduplicated CSS/JS
  |-- locale/theme context
  |-- mount lifecycle
  `-- iframe bridge when applicable
```

An application definition can have many block instances. Updating the registered application updates its code centrally; it does not copy source code into every page.

## Application Sources

### Local Manifest

A static application may live under an approved public application root and provide a manifest. No PHP plugin is required.

Example layout:

```text
public/site/{site_handle}/apps/typing/
  application.json
  app.css
  app.js
```

Example manifest:

```json
{
  "schema_version": 1,
  "handle": "typing-test",
  "name": "Typing Test",
  "version": "1.0.0",
  "render_mode": "inline",
  "mount": {
    "element": "div",
    "class": "wb-typing-app"
  },
  "assets": {
    "css": [
      {"path": "app.css"}
    ],
    "js": [
      {"path": "app.js", "type": "module", "load_position": "body_end"}
    ]
  },
  "supports": {
    "locale": true,
    "theme": true,
    "multiple_instances": false,
    "authentication_context": false
  },
  "settings_schema": {
    "duration": {
      "type": "enum",
      "values": [30, 60, 120],
      "default": 60
    },
    "keyboard_layout": {
      "type": "string",
      "max_length": 32,
      "default": "locale-default"
    },
    "show_live_stats": {
      "type": "boolean",
      "default": true
    }
  }
}
```

Relative manifest paths resolve only inside the manifest directory. Traversal, backslashes, query strings, fragments, protocol-relative paths, executable server files, and undeclared remote origins are rejected.

The first implementation should support site-owned roots only. Package/plugin-owned roots may join the same registry when their lifecycle and public publishing behavior is defined. Automatic filesystem-wide scanning is not allowed; discovery must use configured roots or an explicit refresh operation.

### Plugin Declaration

An enabled plugin may register the same logical definition through PHP or its plugin manifest. This path is appropriate when the application also owns:

- migrations or database records;
- authenticated backend APIs;
- public or admin routes;
- custom permissions;
- server-side validation;
- custom admin configuration UI;
- scheduled jobs, mail, webhooks, or external service credentials.

A plugin application and local-manifest application use the same public registry and Application Block contract. The block must not care which provider registered the application.

Disabling a provider plugin makes its application definition unavailable for new placement. Existing blocks remain stored and render a safe operator-visible readiness failure in admin; public rendering follows the failure behavior defined below.

### Trusted Operator Definition

Super admins may define an application by entering approved local asset paths without writing a plugin or manifest. This supports development, migration, and one-off internal integrations.

The definition form may accept:

- a safe mount element and allowlisted attributes for inline mode;
- local CSS asset paths;
- local JavaScript asset paths and declarative load attributes;
- a local iframe entry path;
- a typed settings schema;
- presentation defaults.

It must not accept arbitrary initialization JavaScript, inline `<script>`, inline `<style>`, event-handler attributes, `javascript:`/`data:` URLs, or unrestricted remote asset URLs.

Trusted operator definitions are registry resources. They are not duplicated into each Application Block.

## Render Modes

### Inline

Inline applications render a controlled mount element in the CMS page DOM and inherit the public layout, WebBlocks UI, public theme variables, locale, header, and footer.

Example output:

```html
<section
  class="wb-application"
  data-wb-public-block-type="application"
  data-wb-application="typing-test"
  data-wb-application-instance="application-block-184"
>
  <div class="wb-typing-app" data-wb-application-mount></div>
</section>
```

Inline mode is preferred for:

- typing tests;
- calculators;
- quizzes and learning tools;
- forms and configurators whose server behavior is supplied elsewhere;
- charts and interactive visualizations;
- small widgets that should follow the site theme.

Inline applications must:

- scope CSS to their application root;
- avoid global resets and unqualified element rules;
- avoid global mutable JavaScript state;
- support more than one instance when `multiple_instances` is declared;
- initialize from the runtime lifecycle rather than assuming one fixed DOM id;
- clean up event listeners if the runtime later supports editor-side remounting.

The runtime dispatches a standard mount event rather than evaluating author-supplied JavaScript:

```js
document.addEventListener('webblocks:application:mount', (event) => {
  if (event.detail.handle !== 'typing-test') {
    return;
  }

  mountTypingTest(event.detail.element, event.detail.context, event.detail.settings);
});
```

The exact JavaScript API remains an implementation decision, but it must be versioned and documented before applications depend on it.

### Iframe

Iframe applications run as separate documents and are appropriate for games, canvas runtimes, legacy applications, third-party embeds, or applications needing strong CSS/JavaScript isolation.

Example output:

```html
<div
  class="wb-application wb-application--iframe"
  data-wb-public-block-type="application"
  data-wb-application="blockfall"
>
  <iframe
    class="wb-application__frame"
    src="/play-assets/games/blockfall/index.html"
    title="Blockfall"
    loading="eager"
    sandbox="allow-scripts allow-same-origin"
    allow="fullscreen"
  ></iframe>
</div>
```

Editors select named sandbox and permission profiles. Free-form `sandbox` and `allow` strings are not ordinary authoring fields.

Initial profiles should be deliberately small:

| Profile | Intended use | Notes |
| --- | --- | --- |
| `strict` | isolated third-party or unknown static content | no same-origin privilege; minimal features |
| `same_origin_app` | trusted local browser application | scripts and same-origin access; no popup/navigation powers by default |
| `game` | trusted local game | same-origin app plus fullscreen and explicitly documented media/input needs |

Adding permissions such as popups, downloads, clipboard, camera, microphone, geolocation, or top navigation requires a future explicit profile and security review.

Iframe applications may participate in a versioned `postMessage` bridge for:

- ready state;
- locale and theme changes;
- measured content height;
- fullscreen requests;
- application completion events;
- non-sensitive user/authentication state when explicitly permitted.

Every message validates `origin`, application handle, instance id, protocol version, and message type. Arbitrary message forwarding is prohibited.

## Application Block Contract

Proposed core catalog entry:

| Field | Value |
| --- | --- |
| Slug | `application` |
| Name | Application |
| Category | `content` |
| `source_type` | `application` |
| `is_system` | `true` |
| `is_container` | `false` |
| Translation family | optional application presentation copy only |
| API readable | `true` |
| API writable | `true` |
| Authoring | `structured` |

The block is not a container. The embedded runtime owns its internal DOM; CMS child blocks would create two competing content models.

### Required Settings

| Key | Type | Notes |
| --- | --- | --- |
| `application_handle` | registered handle | Required; never a display label or database id |

### Instance Settings

| Key | Type | Notes |
| --- | --- | --- |
| `application_settings` | object | Validated against the selected application's `settings_schema`; unknown keys rejected |
| `width` | enum `content` \| `wide` \| `full` | CMS presentation concern |
| `loading` | enum `lazy` \| `eager` | Defaults by render mode; eager should be intentional |
| `aspect_ratio` | approved ratio or `auto` | Primarily iframe mode |
| `min_height` | bounded integer | Rendered through a safe CSS custom property or class, not inline free-form CSS |
| `show_loading_state` | boolean | CMS-owned loading UI |
| `show_failure_state` | boolean | Public behavior remains generic and non-sensitive |

Render mode, asset paths, sandbox profile, context permissions, multiple-instance support, and runtime capabilities belong to the registered application definition. Ordinary block authors cannot override them.

### Translation Ownership

Application content has three owners:

1. **System interface copy** such as Start, Retry, Accuracy, or Loading belongs to the application's language files or application-owned translation resources.
2. **Editorial application data** such as typing passages, quiz questions, or calculator explanations belongs to normal CMS/plugin records with locale-aware storage.
3. **Placement copy** such as an optional block title or fallback message may belong to block translations if the first version chooses to expose it.

The settings JSON must not become an unstructured store for translated editorial content. The registry schema must explicitly mark locale-owned setting values if they are ever supported.

## Registry Contract

Each definition exposes at least:

| Field | Purpose |
| --- | --- |
| `handle` | stable machine identity |
| `name` | operator-facing label |
| `description` | concise operator guidance |
| `provider_type` | `manifest`, `plugin`, or `operator` |
| `provider` | safe provider identifier |
| `version` | provider application version |
| `schema_version` | manifest/runtime contract version |
| `render_mode` | `inline` or `iframe` |
| `settings_schema` | typed authoring and API contract |
| `supports` | locale, theme, multiple instances, auth context, fullscreen, resize bridge |
| `assets` | normalized local asset declarations |
| `security_profile` | runtime-owned profile, especially for iframe mode |
| `readiness` | valid, missing assets, disabled provider, incompatible schema, or invalid manifest |
| `checksum` | definition checksum for concurrency and refresh diagnostics |

Registry handles are unique within an installation. A later namespace convention may allow `provider::application`; the first implementation must choose and document collision behavior before accepting more than one provider source.

Definitions are normalized before storage or runtime use. Public renderers never interpret unvalidated raw manifests.

## Internal Content API

API support is a release requirement, not a follow-up. Admin and API authoring must use the same definitions, validation rules, normalized settings, permissions, and readiness checks.

### Capabilities

Proposed explicit capabilities:

- `applications.read` — list/read application definitions and readiness;
- `applications.write` — create/update trusted operator definitions that reference approved local files;
- `applications.delete` — delete unused trusted operator definitions;
- `applications.refresh` — rescan configured manifest roots and refresh normalized definitions.

Normal page-building with an already registered application continues to require the existing content capabilities:

- `content.read` for discovery and reads;
- `content.validate` for plan validation;
- `content.apply` for draft placement and updates;
- `content.publish` for explicit publication.

`applications.write`, `applications.delete`, and `applications.refresh` are advanced operator capabilities and are not part of the default page-building token. A token that can place an existing application must not thereby gain power to register executable files.

Site asset writes remain separate:

- `site-assets.read` reads approved files;
- `site-assets.write` creates or changes approved files;
- `applications.write` registers those existing files as an application.

No single application endpoint uploads arbitrary executable archives in phase 1.

### Discovery Endpoints

Proposed resources:

```text
GET /webadmin/api/applications
GET /webadmin/api/applications/{application}
GET /webadmin/api/applications/{application}/schema
```

List filters may include:

```text
site
provider_type
render_mode
readiness
search
```

Example list item:

```json
{
  "handle": "typing-test",
  "name": "Typing Test",
  "version": "1.0.0",
  "provider": {
    "type": "manifest",
    "handle": "site:fklavye"
  },
  "render_mode": "inline",
  "supports": {
    "locale": true,
    "theme": true,
    "multiple_instances": false
  },
  "readiness": {
    "status": "ready",
    "issues": []
  },
  "links": {
    "self": "/webadmin/api/applications/typing-test",
    "schema": "/webadmin/api/applications/typing-test/schema"
  }
}
```

Read responses expose normalized public paths, declarations, schemas, supported features, and readiness. They do not expose absolute filesystem paths, server internals, executable file contents, secrets, plugin credentials, or unrestricted raw manifest input.

### Operator Definition Endpoints

Proposed resources:

```text
POST   /webadmin/api/applications
PATCH  /webadmin/api/applications/{application}
DELETE /webadmin/api/applications/{application}
POST   /webadmin/api/applications/refresh
```

These endpoints manage only trusted operator definitions or manifest discovery. They do not modify plugin-owned definitions. Provider-owned definitions are updated through their provider lifecycle.

Example operator definition request:

```json
{
  "handle": "shipping-calculator",
  "name": "Shipping Calculator",
  "render_mode": "inline",
  "mount": {
    "element": "div",
    "class": "wb-shipping-calculator"
  },
  "assets": {
    "css": [
      {"path": "/site/fklavye/apps/shipping/app.css"}
    ],
    "js": [
      {
        "path": "/site/fklavye/apps/shipping/app.js",
        "type": "module",
        "load_position": "body_end"
      }
    ]
  },
  "supports": {
    "locale": true,
    "theme": true,
    "multiple_instances": true
  },
  "settings_schema": {
    "country": {
      "type": "string",
      "max_length": 2,
      "default": "DE"
    }
  }
}
```

Writes use optimistic concurrency, for example `expected_checksum`, so an operator tool cannot silently overwrite a definition changed in the admin.

### Block Discovery

Once implemented, block-type discovery for `application` must publish:

- `api_readable: true`;
- `api_writable: true`;
- `authoring: structured`;
- the `application_handle` field;
- safe CMS-owned presentation settings;
- a link or templated link to application discovery;
- guidance that `application_settings` is validated against the selected application's schema;
- an explicit statement that `html` remains human-only and is not an alternative API path.

Because the settings schema depends on `application_handle`, generic block discovery cannot enumerate one fixed set of nested keys. Clients must:

1. discover the `application` block contract;
2. select a registered application from `GET /applications`;
3. read `GET /applications/{application}/schema`;
4. validate or apply a block using that handle and schema-conforming settings.

### Content Plan Example

The existing draft-first content workflow should be able to place an application:

```json
{
  "type": "application",
  "settings": {
    "application_handle": "typing-test",
    "application_settings": {
      "duration": 60,
      "keyboard_layout": "locale-default",
      "show_live_stats": true
    },
    "width": "wide",
    "loading": "eager",
    "aspect_ratio": "auto",
    "show_loading_state": true,
    "show_failure_state": true
  }
}
```

`POST /webadmin/api/content/validate` validates both layers:

- the core Application Block fields;
- the selected application's settings schema and readiness.

`POST /webadmin/api/content/apply` repeats validation and stores only normalized settings. It does not copy application source or manifest data into the block.

Direct `PATCH /webadmin/api/blocks/{block}` uses the same rules. Changing `application_handle` validates all submitted application settings against the new application and rejects settings that belong only to the old definition.

### Stable API Errors

Proposed stable validation codes include:

| Code | Meaning |
| --- | --- |
| `application_not_found` | no registered definition for the submitted handle |
| `application_not_ready` | definition exists but provider/assets/schema are not renderable |
| `application_provider_disabled` | owning plugin or provider is disabled |
| `application_schema_incompatible` | unsupported manifest/runtime schema version |
| `application_setting_unknown` | submitted setting is not declared |
| `application_setting_invalid` | submitted value fails its declared type or constraints |
| `application_asset_path_invalid` | path is outside an approved root or otherwise unsafe |
| `application_asset_missing` | declared local asset does not exist or is not readable |
| `application_remote_origin_not_allowed` | undeclared or forbidden remote URL/origin |
| `application_render_mode_immutable` | a block attempts to override the registered render mode |
| `application_multiple_instances_not_supported` | another instance violates the definition contract |
| `application_definition_conflict` | stale checksum or handle/provider collision |
| `application_definition_in_use` | delete refused because blocks still reference the definition |

Errors follow the existing JSON validation shape and include discovery, OpenAPI, and documentation links where appropriate.

### OpenAPI And Examples

The feature is incomplete until OpenAPI and discovery expose:

- application list/read/schema endpoints;
- operator definition mutation endpoints and their capabilities;
- Application Block schema and dynamic-schema workflow;
- all stable error codes;
- manifest and operator-definition examples;
- inline typing and iframe game block examples;
- publish behavior and failure behavior.

At least these API examples should ship:

```text
GET /webadmin/api/examples/application-inline
GET /webadmin/api/examples/application-iframe
```

Examples must use local placeholder paths and must never include tokens, absolute filesystem paths, secrets, or executable inline code.

## Asset Runtime

Application assets differ from current page assets. Page assets are operator-attached files whose paths are stored per page. Application assets belong to a reusable application definition and should load automatically only when an enabled Application Block needs them.

The runtime must:

- deduplicate identical normalized asset paths across multiple blocks;
- preserve declaration order within one application;
- resolve dependency order deterministically if dependencies are later supported;
- render CSS in the public `<head>`;
- render JavaScript according to the validated declaration;
- support classic and module scripts;
- reject contradictory `async`/ordering requirements;
- avoid loading assets for unpublished, disabled, missing, or non-rendered blocks;
- expose no absolute local paths;
- integrate with plugin public assets without copying their contents into page settings.

Inline CSS and JavaScript are excluded from phase 1. CSP nonces and hashes can be designed later if a real application cannot operate without them.

The runtime should keep page assets intact for general page customization. Application assets are not a replacement for `page_assets`, and page assets must not become a hidden application registry.

## Public Context

Every mounted instance receives a bounded context object:

```json
{
  "runtime_version": 1,
  "handle": "typing-test",
  "instance": "application-block-184",
  "locale": "tr",
  "theme": "auto",
  "page": {
    "id": 42,
    "path": "/hizli-yazma"
  },
  "user": {
    "authenticated": false
  }
}
```

Rules:

- context contains no bearer token, API token, password, session id, secret, or private user data;
- CSRF material is exposed only when a same-origin application has an explicit need and the endpoint contract requires it;
- authentication context is opt-in in the definition and exposes the minimum useful state;
- public page and block identifiers may be omitted if no consumer requires them;
- site theme and locale are values from the actual render context, not author-entered duplicates;
- iframe context uses the validated message bridge rather than query-string secrets.

## Admin Experience

The Application Block form should lead with a registered application selector. After selection it renders the definition's settings schema as ordinary WebBlocks UI controls.

Suggested flow:

1. Select application.
2. Review readiness, provider, version, and render mode.
3. Configure application-owned settings.
4. Configure CMS-owned width, loading, ratio, and fallback presentation.
5. Save the block.

Only super admins with the corresponding application-management authority see registry management. Ordinary page editors can place approved registered applications but cannot add executable paths, loosen iframe permissions, change provider definitions, or register remote origins.

The editor should distinguish clearly between:

- **Registered applications** — normal page-building choice;
- **Application registry management** — trusted system operation;
- **HTML (Trusted)** — separate human-reviewed escape hatch.

The Application Block must never show an arbitrary HTML/CSS/JS editor to ordinary content authors.

## Readiness And Failure Behavior

Registry readiness is evaluated before placement and again at render time because files or providers may disappear after a block is saved.

Admin behavior:

- show the application handle, provider, version, and exact readiness issue;
- preserve stored settings even when the provider is unavailable;
- prevent new placement when not ready;
- allow replacing or removing an unavailable application block;
- never expose absolute server paths or stack traces.

Public behavior:

- never emit a broken `<script>` or unsafe iframe;
- never render raw manifest data or exception details;
- default to rendering nothing when the application is unavailable;
- optionally render a generic translated fallback when `show_failure_state` is enabled;
- log a safe diagnostic with page/block/application identifiers and a stable reason code.

A missing application must not prevent the remainder of the CMS page, header, or footer from rendering.

## Security Boundaries

Embedded Applications are trusted executable code. The feature makes that trust explicit and narrower; it cannot make arbitrary JavaScript harmless.

Required controls:

- registration and placement are separate permissions;
- API placement cannot register or modify executable code;
- local paths are canonicalized and checked against approved roots;
- manifests and registry records are normalized before render;
- inline mount markup uses an allowlist, not raw HTML;
- no author-supplied JavaScript expressions are evaluated;
- remote assets and iframe origins are denied by default;
- iframe permissions come from named profiles;
- public context excludes secrets and private user data;
- application definitions have checksums and safe audit history;
- deletion is refused while definitions are referenced;
- provider disable/uninstall leaves recoverable block data;
- all API writes are capability-checked and audited without request bodies or source contents;
- future archive upload, if added, requires separate extraction, file type, size, traversal, symlink, integrity, and executable-server-file controls.

Application code must not be accepted through the normal `content.apply` request. That endpoint places registered handles and settings only.

## Relationship To Existing Features

### Trusted HTML

Trusted HTML remains a human-only escape hatch. It may continue to render historical iframes and reviewed snippets, but it is not the migration path for applications and remains API non-writable.

Application Block closes a different gap: structured, discoverable, reusable, API-writable application placement with controlled executable assets.

### Page Assets

Page assets remain useful for one page's presentation or behavior. They do not identify an application, define settings, provide readiness, deduplicate by application, or carry a runtime contract.

An Application Block should not require an operator to attach the same CSS and JavaScript manually on every page.

### Plugins

Plugins remain the extension mechanism for backend and product behavior. An application may be:

- manifest-only;
- plugin-provided;
- an operator definition referencing local assets;
- frontend-only at first and later paired with a plugin backend without changing its block placement model.

### Media Library

Application source code is not ordinary editorial media. JavaScript and HTML files must not become assignable Media records merely to reuse upload UI. A future application-package uploader needs its own security and lifecycle contract.

## Initial Use Cases

### Typing Test

```text
provider: local manifest
render mode: inline
theme: inherited from CMS
locale: inherited from page
assets: application-owned CSS and module JavaScript
settings: duration, keyboard layout, text source, live statistics
plugin required initially: no
plugin potentially required later: user result storage, progress history, protected APIs
```

The standalone typing project should be refactored into a mountable runtime: no independent header/footer, no separate mode switch, no global CSS reset, and no assumption that it owns the page.

### WebBlocks Play Game

```text
provider: local manifest per game or generated catalog manifest
render mode: iframe
entry: /play-assets/games/{slug}/index.html
profile: game
settings: eager/lazy loading, ratio, fullscreen, optional game configuration
plugin required: no for static games
plugin potentially required later: authenticated scores, achievements, moderation, tournaments
```

Existing game-detail Trusted HTML iframe blocks can migrate to Application Blocks after the iframe mode ships. Supporting CMS content around each game remains structured CMS blocks.

## Delivery State

### Phase 0 — Contract And Tests — implemented

- finalize handle namespace and storage model;
- finalize manifest schema version 1;
- finalize capabilities and API error codes;
- define approved local roots;
- add contract-focused tests before runtime work;
- keep all discovery hidden until usable end to end.

### Phase 1 — Registered Inline Applications — implemented

- Application Registry and local manifest refresh;
- read-only application discovery API;
- core Application Block;
- dynamic application settings validation in admin and API;
- inline mount runtime;
- CSS/JS asset deduplication;
- locale and theme context;
- typing test pilot.

### Phase 2 — Iframe Applications — partial

- same-origin iframe renderer with the core sandbox profile;
- ratio, loading, and fullscreen controls;
- versioned message bridge with ready/resize/theme/locale (not implemented);
- Play game pilot;
- migration guidance for existing Trusted HTML iframe blocks.

### Phase 3 — Operator Definitions

- registry management admin UI;
- `applications.write/delete/refresh` API capabilities;
- trusted local path selection;
- optimistic concurrency and audit presentation;
- no archive upload and no remote assets yet.

### Phase 4 — Optional Provider Extensions

- plugin declarations through the same registry;
- application-owned custom admin setting renderer if schema controls are insufficient;
- approved remote origins if a concrete integration needs them;
- application package upload only after a separate security design;
- richer authenticated application APIs where real products require them.

## Acceptance Criteria

The first release is complete only when:

1. A super admin can make a local manifest application available without writing a plugin.
2. An editor can place it with Application Block without seeing or editing executable source.
3. An API token with normal content capabilities can discover and place an already registered application.
4. That token cannot register files or expand runtime permissions without explicit application capabilities.
5. `content/validate`, `content/apply`, and direct block patch use the same nested settings validation as the admin form.
6. Inline typing renders inside the CMS public layout and inherits locale/theme behavior.
7. Assets load once even when multiple compatible instances request them.
8. Missing or disabled applications do not break the page.
9. API discovery, OpenAPI, examples, inventory, and block contracts describe the shipped behavior accurately.
10. Trusted HTML remains API non-writable.
11. No response or diagnostic exposes absolute filesystem paths, secrets, source contents, or bearer token material.
12. Tests cover registry collisions, invalid paths, missing assets, schema validation, capabilities, draft-first placement, publish separation, asset deduplication, multiple-instance rules, disabled providers, and safe public failure.

## Remaining Design Decisions

1. **Handle namespace:** global handles such as `typing-test`, or provider-qualified handles such as `site-fklavye::typing-test`.
2. **Storage:** normalized registry table, cached manifest catalog, or a hybrid where provider definitions are synchronized similarly to plugin block types.
3. **Manifest refresh:** explicit admin/API action only, or also safe refresh during System Update.
4. **Site scope:** whether a site manifest application is visible only to its owning site or can be shared intentionally.
5. **Mount contract:** DOM event, global runtime registry, ES module export convention, or a small combination.
6. **Settings schema vocabulary:** reuse an existing CMS internal field schema or define a narrow JSON-schema-like subset.
7. **Inline HTML:** generated mount element only for phase 1, or a sanitized mount-fragment vocabulary. Generated mount element is the safer default.
8. **Definition revisions:** audit-only history or restorable revisions for trusted operator definitions.
9. **Public fallback:** render nothing by default, or always render a generic translated unavailable state. Rendering nothing by default best matches existing derived-block behavior.
10. **Plugin application assets:** reuse current plugin public asset declarations directly or introduce application-scoped asset declarations with conditional loading.

Items already resolved by Phase 1 use global handles, an on-demand manifest catalog, a DOM mount event, a narrow settings-schema vocabulary, generated inline mount elements, and an opt-in public failure state. The remaining items must be resolved before their corresponding later phase; they are not reasons to weaken the API requirement or fall back to Trusted HTML.
