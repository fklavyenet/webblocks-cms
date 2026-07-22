# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## 1.40.26

- Finish the `public.css` drift cleanup: the stylesheet now holds only the public theme palettes plus a handful of deliberate host-glue rules (~250 lines, down from ~650 before the gallery round).
  - Public navbar mobile menu moves onto the shipped `wb-navbar-drawer` contract (UI 2.15.0): the navigation block renders a shipped `wb-navbar-toggle` wired through the generic `data-wb-collapse` runtime, pushes its drawer through the new `PublicNavbarDrawerRegistry`, and the navbar container renders it directly after its own `</nav>`. The dropdown-based `wb-cms-navbar-mobile-*` layer and its media queries are deleted. Mobile menus now open as a full-width drawer under the navbar.
  - Icon tones consume the shipped `wb-icon-tone-*` axis: the theme token block feeds `--wb-icon-tone-*` from the public tone palette and the six local tone classes are deleted; rendered markup is unchanged.
  - Theme component overrides (`.wb-card`, `.wb-badge`, `.wb-btn-primary`, `.wb-navbar`/`.wb-sidebar`, `.wb-text-muted`) are deleted — the token remap already cascades the same values — after adding the missing `--wb-primary`/`--wb-primary-dark`/`--wb-primary-soft` remaps (these aliases resolve at `:root`, which is why the button override had been load-bearing). The body-link `accent-text` rule stays as a documented, deliberate contrast choice.
  - Small helpers land on shipped equivalents: honeypot wrappers use `wb-sr-only`, cluster gap "none" uses the new `wb-gap-0`, items "stretch" uses the new `wb-items-stretch`, the card header icon row uses `wb-icon-card`, and the card-footer cluster span plus the vestigial link-list icon rule move to (or are covered by) the shipped source. Dead `.wb-public-footer-fallback` removed.
  - New `NavbarDrawerRenderingTest` covers the drawer contract (toggle wiring, drawer after `</nav>`, menu content in both lists, group label rows). Bump the pinned UI version to v2.15.0.

## 1.40.25

- Migrate the Gallery block onto the shipped WebBlocks UI `wb-gallery` pattern (UI 2.14.0) and delete the local gallery CSS layer (~170 lines) from `public/cms/css/public.css`. The editor-facing options are unchanged — column count, gap, media ratio, masonry/collage variants, and below/overlay/on-hover captions now render through the shipped modifiers (`wb-gallery--cols-*`, `--gap-*`, `--aspect-*`, `--masonry`/`--collage`, `--captions-overlay`/`--captions-hover` with `--overlay-solid`/`--overlay-none`) instead of a parallel `wb-*` reimplementation. Overlay captions use the shipped `wb-gallery-caption` scrim with a nested `wb-gallery-meta`; both lightbox and direct-link items now share the styled `wb-gallery-trigger`. Bump the pinned UI version to v2.14.1.

## 1.40.24

- Finish the WebBlocks UI conformance follow-ups from the block-renderer review. Remove the dead `wb-link` class from the remaining public renderers (Contact Info, Card Grid, Showcase List, and the fallback renderer) — plain anchors are already styled by the UI foundation, so output is unchanged. Delete the now-unused `public/cms/js/public/header-actions.js` and its package asset manifest entries; the shipped WebBlocks UI theme behavior owns the mode toggle. Refresh the block documentation (`public-block-render-markup.md`, `block-ui-renderer-contract.md`, `inventory.md`, `public-assets.md`) to match the shipped UI 2.13.0 vocabulary and the current renderers: real `wb-stat-meta` and `wb-cluster` kicker classes instead of retired `wb-stat-detail` / `wb-cms-public-kicker`, base `wb-rich-text` instead of the retired readable modifier, the direct-child Callout alert anatomy, and the neutral `wb-btn wb-btn-ghost wb-btn-icon` Header Actions markup with host-localized mode labels.

## 1.40.23

- Consume WebBlocks UI 2.13.0 and migrate the navbar/topbar utility controls onto its shared, context-neutral vocabulary, removing the CMS's local reimplementation. The admin topbar's icon actions (system-update indicator, color mode, theme settings, language, and user menu) now use the shipped `wb-btn wb-btn-ghost wb-btn-icon` primitive inside a `wb-cluster` instead of the project-local `wb-navbar-iconbar` / `wb-navbar-icon-trigger` classes, and the update indicator's status dot uses the shipped `wb-btn-dot` — so those local classes are gone from `admin.css`. The public Header Actions block and the admin color-mode toggle now drive theming through the shipped `data-wb-mode-cycle` behavior with host-localized `data-wb-mode-label-{light,dark,auto}` labels (English, German, Turkish), which retires the duplicated mode/accent logic in `public/cms/js/public/header-actions.js` (now inert, pending a later file removal). The bundled UI pin moves from 2.11.0 to 2.13.0.

## 1.40.22

- Align public block renderers with shipped WebBlocks UI 2.11.0 primitives and remove dead CSS classes, with no visual regressions. Stat Card now uses the real `wb-stat-meta` slot instead of the non-existent `wb-stat-detail`, drops the dead `wb-link` class, and shows a translatable "Learn more" label (English, German, Turkish) instead of hardcoded English. Rating stops putting the `wb-rating` primitive on its `wb-card` shell — a primitive-boundary violation that forced a flex column onto the card — and removes the unused `wb-public-rating` and `wb-public-rating-title` classes; the star display and input keep working through their own custom-property defaults. Rich Text drops the retired `wb-rich-text-readable` modifier and keeps the base `wb-rich-text` readable typography. Columns removes the dead `wb-public-contact-columns` class and its detection logic. Column Item's stats variant no longer renders the same text as both the label and the value when a subtitle is not set. Callout and Testimonial now match the shipped alert and card anatomy: alert title and body are direct `wb-alert` children, and the testimonial renders `wb-card` on its `blockquote` with a muted attribution footer.

## 1.40.21

- Make Container a width-only, layout-neutral primitive by default. Unset, legacy, `none`, and unknown flow values no longer add `wb-stack`; editors and API clients must select `Flow: Stack` explicitly when the Container itself should own vertical child rhythm. Existing explicit stack choices remain unchanged, while Grid, Cluster, and Stack children can now compose inside old Containers without an inherited flex-column layout fighting them.

## 1.40.20

- Upgrade WebBlocks UI to 2.11.0 and render block background images through its native opt-in `wb-background-media` primitive. Hero, Section, Card, CTA, Content Header, and Slide keep their existing Media Library, position, and overlay settings, while WebBlocks UI now owns cover and overlay presentation. Remove the duplicate CMS background-media CSS; CMS remains responsible only for safe media URLs and allowlisted settings.

## 1.40.19

- Add a **Head Code** tab to Site Settings, so the custom head HTML added in 1.40.17 can be read and edited in the admin instead of only through the API. It shipped API-only, which left the markup on a site invisible to anyone working in the panel — a setting that renders on every public page should not be editable exclusively by a token. The tab carries the same field, the same ~64 KB cap, and the same blank-clears behaviour as `PATCH /webadmin/api/sites/{site}/head`, and it is gated by the existing site-settings permission, so the API and the panel stay two doors to one setting rather than two behaviours. The panel states plainly that the markup is inserted verbatim and can run scripts on every page, because that is the point of the field and also its risk. English, German, and Turkish strings included.

## 1.40.18

- Fix the 1.40.17 custom head HTML column never reaching existing installs. The `custom_head_html` column shipped in `database/migrations` and `database/migrations/fresh`, but a package consumer install runs neither: System Update only runs `database/migrations/updates`. So 1.40.17 delivered the endpoint and the renderer with no column behind them, and `PATCH /webadmin/api/sites/{site}/head` answered every request with its "not available until the latest site schema has been applied" guard — code without schema, which is the failure the three-directory split exists to prevent. Adds the missing idempotent ensure-migration under `database/migrations/updates`, so the column arrives on upgrade. A test now drops the column and drives that update migration directly, asserting the upgrade path adds it and that re-running is a no-op, because a fresh-schema test can only ever prove the clean-install half.

## 1.40.17

- Add per-site **custom head HTML**, so operator-authored markup can be injected into the public `<head>` of every page on a site. Until now there was no way to place a raw head tag — an ownership/verification `<meta>`, an SEO tag, or an analytics/tag-manager snippet — through the CMS: branding covered only favicon and social image, and site assets covered only CSS/JS files, neither of which reaches `<head>` as markup. A new `custom_head_html` column on the site is emitted verbatim just before `</head>`, after the site CSS/JS, and is written through `PATCH /webadmin/api/sites/{site}/head` with `custom_head_html` under the existing `site-settings.write` capability. Sending an empty value clears it. The markup is raw and unescaped by design (that is the point of a verification tag or a script snippet), so it is trusted operator input and must never be populated from untrusted or visitor sources; it is capped at ~64 KB. The API discovery catalog, OpenAPI paths, and AI guide advertise the endpoint as the single supported way to inject head markup, so a client is not pushed toward hand-written content blocks or site CSS/JS that cannot carry a `<head>` tag.

## 1.40.16

- Upgrade the bundled WebBlocks UI to 2.10.3, so titles in a card-framed Link List use the stronger card-heading typography while the existing `span` markup and standard Link List typography stay unchanged. Structured CMS Link Lists such as the **Try next** card now match the visual emphasis of the older hand-written card links without requiring site-specific CSS or HTML blocks.

## 1.40.15

- Let a Link List show landscape artwork instead of only a small square. The row thumbnail was a fixed 4rem square, so 4:3 artwork was cropped by `object-fit: cover` and wide rows looked sparse next to their copy. A new **Thumbnail Size** setting on the Link List block adds `wb-link-list--thumb-wide` (WebBlocks UI 2.10.2), which gives the leading column a share of the row width and renders the image at a 4:3 ratio, so it grows with the list instead of staying pinned to a fixed size. The default stays square, so existing lists keep their current look, and the setting composes with the Row Layout and List Frame styles added in 1.40.10. Rows that show an icon rather than a thumbnail are deliberately left on the narrow column, because a wide track would strand the icon in empty space.
- `settings.thumb_size` is writable through `PATCH /webadmin/api/blocks/{block}` from the start, taking `wide` or clearing to the square default, so it ships advertised-and-writable rather than repeating the contract drift 1.40.12 was written to prevent.

## 1.40.14

- Open the remaining block settings to the API, and derive the endpoint's gate from the value rules instead of a hand-written list. `PATCH /webadmin/api/blocks/{block}` now accepts 58 settings fields across the block catalog, including Alert and Sidebar Footer variants, Cluster, Container, Section and Card layout settings, Header alignment and anchor, Code language, navigation `menu_key` and active matching, Rating title and controls, and Comments form settings. Each field takes exactly the values the admin form allows, and anything else clears the setting rather than storing a value no renderer reads. `BlockSettingsPatchPolicy` is now the single owner of both which fields are writable and what values they accept, so the gate cannot drift from the sanitizer the way it did in 1.40.10.
- Keep four settings closed on purpose, for the same reason rather than by omission. `contact_form.recipient_email`, `send_email_notification`, and `store_submissions` decide where form submissions are delivered and whether they are retained, and `comments.show_author_name` decides whether commenter names appear publicly. Those are decisions about other people's data, not presentation, so they stay with the admin rather than an API token. `rating.scale` stays closed for a different reason: the admin form hard-codes it to 5, so opening it through the API alone would let a value be stored that the admin can neither produce nor show.

## 1.40.13

- Let the API change Hero layout and Grid layout settings on an existing block. `settings.layout` and `settings.title_tag` on `hero`, and `settings.layout_name`, `settings.columns`, `settings.gap`, `settings.alternate_media_text_sections`, and `settings.alternate_start` on `grid`, were declared by the published contract and refused by `PATCH /webadmin/api/blocks/{block}`. The split Hero layout added in 1.40.6 could be chosen when a hero was created and never afterwards. Each field takes the values the admin form already allows, and anything else clears the setting rather than storing a value no renderer reads. Turning Grid alternating off drops the alternating start with it, matching the admin.

## 1.40.12

- Let the API change an existing block's icon and badge. `PATCH /webadmin/api/blocks/{block}` refused `settings.icon_slug`, `settings.icon_tone`, and `settings.badge_tone` on all five icon-enabled block types, so an icon could be set when a block was created and never changed afterwards, even though the published contract advertised the fields. The endpoint now delegates to the icon normalizers `InternalContentApiOperations` has owned since 1.40.7 rather than growing a second set of icon rules, so an unknown slug is still refused and `icon_tone: default` still clears the tone.
- Record which block settings the API may write, and why the rest are refused. The PATCH allowlist and the published contract are separate hand-written lists that had drifted far apart: the contract declared 125 settings fields across 37 block types while the endpoint accepted a fraction of them, with no record of which gaps were deliberate. `BlockSettingsPatchPolicy` now names every refused field with a reason, separating the three `contact_form` delivery settings, which stay closed because an API token should not change where form submissions are sent or whether they are retained, from the fields that are only closed for want of a value rule. A contract sweep asserts every declared field is either patchable or recorded as closed, so a new setting can no longer ship advertised-but-unwritable the way the Link List styles did in 1.40.10.

## 1.40.11

- Fix the Link List styles being unwritable through the API. `PATCH /webadmin/api/blocks/{block}` keeps its own hand-written allowlist of settings fields, separate from the contract registry the published contract is built from, so the styles added in 1.40.10 were advertised by `content-contract` and then refused with `unsupported_block_settings_fields`. An API client was told to use fields the API rejected, and could only get the design by hand-writing a raw `html` block. The endpoint now accepts and sanitizes `settings.row_layout` and `settings.list_frame` for `link-list`; unknown values clear the style rather than store it, and fields the block type does not support are still refused.
- Fix two `docs/inventory.md` entries that had been wrong since the audit was taken at 1.40.2. It stated that no supported Card visual variant field existed, which stopped being true in 1.40.5 when the Card style select was added, and it predated both the Link List Item thumbnail and the Link List styles. The corrected entries are listed under a new Amendments section, so the audit baseline stays honest about what has and has not been re-checked.

## 1.40.10

- Add Row layout and List frame styles to the Link List block, so a structured link list can render as a compact card list instead of only as a directory index. Row layout `stacked` moves each row description under its title, beside any thumbnail or icon, replacing the wide description column. List frame `cards` gives each row its own card with spacing instead of one shared frame with separators. The two are independent and both default to the current look, so existing lists are unchanged. Previously this design could only be built by hand-writing a raw `html` block, which put the content outside the Media Library, translations, and the Internal Content API; the styles are settings, so the API can select them through `settings.row_layout` and `settings.list_frame`. Upgrades the bundled WebBlocks UI to v2.10.1, which adds the matching `wb-link-list--stacked` and `wb-link-list--cards` modifiers.

## 1.40.9

- Fix media pickers that silently discarded the chosen asset on save. `link-list-item` (the thumbnail added in 1.40.8), `cta`, and `content_header` all resolved `media_id` correctly and then re-added `asset_id => null` at the end of the admin request payload. `asset_id` is fillable and its setter writes `media_id`, so the trailing null was applied last and wiped the selection: the picker showed the image, the save reported success, and the block came back with no media. CTA and Content Header background images had been affected since before the thumbnail work; `hero`, `section`, `card`, and `image` were never affected. The three block branches no longer re-add `asset_id`, and a shared media assignment is now preserved rather than re-read from the locale form on a translated edit.
- Fix `link-list-item` media assignment through the Internal Content API, which still failed after 1.40.8 added the block type to the media rules. `InternalContentPlanService` kept its own hand-written copy of the direct media kind rules, so the plan path went on rejecting the thumbnail with "this block type does not support direct Media Library assignment". `InternalContentApiOperations` now owns the canonical rules and the plan service delegates to them, closing the same drift the icon list had in 1.40.7.

## 1.40.8

- Add an optional thumbnail to the Link List Item block, so a link row can lead with an image instead of an icon. The item editor gains a Media picker restricted to images, the thumbnail is stored on the canonical block `media_id` column, and the public renderer emits it as a `wb-link-list-thumb` image in the row's leading column. A thumbnail and an icon both claim that single column, so an uploaded thumbnail wins and the icon is skipped. The Internal Content API can assign the thumbnail through the existing `media_id` field, which previously rejected `link-list-item` outright.
- Fix link list rows that lead with an icon. The icon rendered into the row's main column and pushed the description onto its own line, because the renderer never emitted the leading-visual modifier. Rows with a thumbnail or an icon now carry `wb-link-list-item--media` and the icon carries `wb-link-list-icon`. Upgrades the bundled WebBlocks UI to v2.10.0, which adds the dedicated leading column.

## 1.40.7

- Fix public icon handling on the incremental block endpoints, which drifted from content apply. Adding a single block through `POST /webadmin/api/pages/{page}/slots/{slot}/blocks` or `POST /webadmin/api/shared-slots/{sharedSlot}/blocks` did not normalize or validate `settings.icon_slug` at all, so an unknown icon survived normalization and the public renderer silently skipped it, and `settings.icon_tone` was wrongly rejected on `feature-item` because the incremental block-type list omitted it. Icon normalization now has one owner: `InternalContentApiOperations` holds the canonical icon-enabled block type list plus the shared slug and tone normalizers, and the full content plan delegates to them instead of keeping a duplicate copy.

## 1.40.6

- Add a split Hero layout so a marketing intro can place its image beside the copy instead of behind it. Selecting the new Split layout renders the hero media as a `wb-promo-media` foreground image using the new WebBlocks UI `wb-promo--split` modifier, and skips the background image and overlay for that layout. The left and centered layouts keep using the same media as a background, so no new media field, relation, or migration was needed and the Internal Content API can select the layout through `settings.layout` with the existing `media_id`. Upgrades the bundled WebBlocks UI to v2.9.0.

## 1.40.5

- Fix Hero and CTA managed actions, which never rendered. The managed CTA buttons are created as `button_link` blocks, but the Hero renderer, the CTA renderer, the shared actions partial, and both admin editors filtered children for the unpublished `button` type, so every managed call to action was dropped before rendering and the admin CTA fields never prefilled from existing buttons. All five filters now accept `button` and `button_link`.
- Fix managed CTA storage shape. `button_link` resolves its href and target from block settings, but managed CTAs only wrote the legacy `button` columns, so an action that survived the filter still rendered without a URL. The shared `ManagedCtaSynchronizer` now writes `settings.url` and `settings.target` when the resolved button type is `button_link`, which also makes the Hero/CTA actions added through the Internal Content API in 1.40.4 render and stay editable.
- Add a visual style setting to the Card block. Cards now expose a Card style select (default, flat, muted, highlighted, accent) that renders the matching WebBlocks UI card variant class, mirroring how the Hero renderer already maps variants. The variant column was already accepted by validation and the API but was ignored by the card renderer and missing from the editor.

## 1.40.4

- Let the Internal Content API author Hero and CTA actions. `hero` and `cta` block payloads now accept optional `primary_cta` and `secondary_cta` objects (`{label, url}`, or `null` to clear), validated for a safe internal path or http(s) URL. They create the same managed `button_link` children the admin Page editor maintains, so an AI-built hero keeps its buttons editable in the normal block editor. Previously the API could not add a call to action to a Hero at all, because Hero only accepts managed `button` children and that type is not published in the catalog. The managed CTA logic moved out of the admin block controller into a shared `ManagedCtaSynchronizer` so the admin and every API create path share one behavior.
- Expose the Column Item subtitle field in the Columns editor. The Columns `stats` variant renders the child subtitle as the large stat value, but the editor never offered the field, so stat values silently fell back to the title.
- Document the `navigation-auto` block in the shared contract registry so it is discoverable through `GET /webadmin/api/block-types` and `GET /webadmin/api/content-contract`. It was a published catalog row with an admin form and public renderer but no documented contract.

## 1.40.3

- Ship `docs/inventory.md`, the AI-facing per-block design and authoring contract, and serve it to trusted tools through the new `GET /webadmin/api/inventory` endpoint as Markdown. API discovery links to it, recommends reading it first, and documents it in the AI guide and OpenAPI schema; the docs check now fails if the document goes missing.
- Make the `html` block human-only for the Internal Content API through one central product policy (`BlockTypeApiAuthoringPolicy`). Operators keep creating and editing Trusted HTML in the CMS admin and existing published blocks keep rendering, but no API mutation can create, update, replace, move, reorder, publish, or delete an HTML block, and no token capability overrides it. Rejections happen before any write, return HTTP 422 with the stable code `block_type_not_api_writable`, and leave no partial changes. The policy guards both block normalizers, existing-block PATCH, page and Shared Slot incremental create, reorder, subtree delete, Shared Slot clear-all, page and Shared Slot publish, draft slot replacement, staged update creation and promotion, Shared Slot assignment, and API page delete.
- Report `api_readable`, `api_writable`, and `authoring` for every block type in `GET /webadmin/api/block-types` and `GET /webadmin/api/content-contract` from the central policy, including the stable rejection code and restriction for `html`, and stop publishing writable examples for human-only blocks.

## 1.40.2

- Add Page Assets endpoints to the Internal Content API so trusted tools can list, attach, update, and detach a page's own `/site` CSS and JS files: `GET /webadmin/api/pages/{page}/assets`, `POST .../assets/{type}` (css or js), `PATCH .../assets/{pageAsset}`, and `DELETE .../assets/{pageAsset}`. Writes require the new opt-in `page-assets.write` capability. Paths reuse the existing page asset path validator, so only local `/site/...` paths with a matching `.css`/`.js` extension are accepted and external URLs, `javascript:`/`data:` paths, traversal, query strings, and fragments are rejected; the endpoint only attaches an existing file and never writes file contents. Every write captures a page revision.
- Document that content plans already support media by existing Media Library ID (`media_id`/`asset_id` plus Gallery `gallery_media_ids`/`gallery_items`, validated for existence and block-type kind compatibility), and mark the corresponding Phase 3 roadmap items delivered.

## 1.40.1

- Add an optional `create_restore_point` flag to `POST /webadmin/api/content/apply`. When set, the Internal Content API takes a full system backup (database plus uploads) restore point before applying the plan, so an operator can roll back from System -> Backups if an AI-generated apply goes wrong. It requires the new opt-in `backups.create` capability, validates the plan first so an invalid plan does not create a wasted backup, and aborts the apply with JSON 409 if the backup fails so content is never applied without the requested safety net. Successful responses include a `restore_point` summary, and the backup is recorded with a dedicated `content_apply` type. The API intentionally exposes only restore-point creation; restoring, downloading, and deleting backups stay in the operator admin UI.

## 1.40.0

- Add Shared Slot block topology endpoints to the Internal Content API (Phase 2B): `PATCH /webadmin/api/shared-slots/{sharedSlot}/blocks/reorder` reorders a sibling group (requires `shared-slots.write`), `DELETE .../blocks/{block}` removes one block subtree, and `DELETE .../blocks` clears every block for clear-and-replace (both deletes require `shared-slots.write` plus `content.blocks.delete`). Existing Shared Slot block content edits keep using `PATCH /blocks/{block}`. Every write rebuilds the slot's page assignments and captures a Shared Slot revision. Because Shared Slots have no draft-page concept, changes to already-published Shared Slot blocks affect every assigned page immediately, which is why deletion is gated behind the destructive `content.blocks.delete` capability.

## 1.39.0

- Redesign the public Rating block to use the new WebBlocks UI `wb-rating` star component: a read-only average shown as partially filled stars plus count, and a no-JS interactive star input that fills on hover up to the pointed star (each star still submits its own value, so the safe no-JavaScript flow is preserved). Upgrades the bundled WebBlocks UI to v2.8.0.
- Add an optional `Heading` setting to the Rating block so editors can show a title above the stars; leaving it empty keeps the previous behavior of composing a heading with a separate Header block.

## 1.38.1

- Fix the API token capabilities counter so it shows selected-of-total instead of selected-of-selected, and register the `content.blocks.delete` capability in the "Publishing and destructive actions" group so it is selectable in the token editor and counted in group and header totals.
- Collapse all API token capability groups by default on the Create Token form; previously the "Page building" group was always expanded and stretched the page.
- Add an Engagement overview landing page with Comments and Ratings summary cards (counts, pending review, average rating) and links to each list, and point the Engagement navigation item at it instead of opening Comments directly.
- Add search and rating-value filters to the Engagement Ratings page.

## 1.38.0

- Add draft-safe page block topology endpoints to the Internal Content API so trusted AI/operator tools can edit a draft page incrementally without sending a full content plan: `POST /webadmin/api/pages/{page}/slots/{slot}/blocks` adds a single block (with optional children), `PATCH .../blocks/reorder` renumbers a slot sibling group, and `DELETE .../blocks/{block}` removes a block subtree. Create and reorder require `content.apply`; deletion requires the new opt-in `content.blocks.delete` capability that is not part of the default page-building set. The endpoints operate only on draft pages and page-owned slots, reject Shared Slot-backed slots and Shared Slot source blocks, and capture a page revision on every write.

## 1.37.4

- Sync the shipped block type, slot type, page layout, and icon catalog automatically during System Updates by running `webblocks:catalog-repair --all` in the post-install flow, so a release can add catalog rows such as the engagement Rating and Comments block types without an operator running a manual command. The sync runs after cache clears, preserves custom catalog rows, and is best-effort so it cannot fail an otherwise successful update.

## 1.37.3

- Report site CSS and JavaScript assets as writable when `public/site` does not exist yet but CMS can create it through a writable parent directory, matching the first-write behavior of the asset API.
- Add the public session-cart bridge and SumUp webhook endpoint required by WebBlocks Commerce 0.8.0, while keeping every route inert unless the plugin is enabled.

## 1.37.2

- Restore OpenAPI schema generation for Plugin Catalog endpoints.

## 1.37.1

- Make the package-only Composer publishing wrapper load `WEBBLOCKS_PUBLISHER_TOKEN` from the project `.env` into its isolated Testbench process without sourcing or exposing unrelated environment values.
- Preserve signed update publishing in the package-only wrapper by passing `WEBBLOCKS_PUBLISHER_SIGNING_KEY` into Testbench and validating it during Publisher dry-runs.
- Expose bearer-authenticated Plugin Catalog list, detail, and checksum-verified install endpoints through the Internal Content API, using existing `plugins.read` and `plugins.install` capabilities and installing catalog packages disabled.
- Add one-click copy controls for the one-time CMS API token and local `.env` example, with localized accessible feedback and a legacy clipboard fallback.
- Repair package-native Engagement schema readiness during System Updates by idempotently renaming legacy unprefixed tables or creating the required `wbcms_comment_entries` and `wbcms_content_ratings` tables automatically.
- Harden image variants with accurate responsive widths, safe small-source cropping and codec fallback, focused cache invalidation, Gallery and social-image integration, and operational regeneration/pruning.
- Rate-limit admin sign-in and password-reset requests: failed logins lock per email+IP after a configurable threshold (cleared on success), with a per-IP backstop across the login, forgot-password, and reset-password endpoints. Tunable via `WEBBLOCKS_CMS_MAX_LOGIN_ATTEMPTS` and `WEBBLOCKS_CMS_LOGIN_DECAY_SECONDS`.
- Keep SVG out of the default media upload and remote-fetch allowlist; operators who trust every media-uploading account can opt back in with `WEBBLOCKS_CMS_ALLOW_SVG_UPLOADS=true`. Consolidate the accepted MIME allowlist into one place so uploads, the Internal Content API, and remote fetch stay in sync.
- Clarify plugin install wording in the README: catalog releases are checksum-verified and manual ZIPs are validated on upload and disabled by default.

## 1.37.0

- Make the public GitHub repository package-only: the repository root is now the `fklavyenet/webblocks-cms` Composer package, not a complete deployable Laravel application. New source installations should use Composer through Packagist.
- Keep existing Publisher/System Updates installations supported, including normal upgrades from the `1.36.1` compatibility release, without changing the CMS schema or pinned WebBlocks UI `v2.7.18` assets.
- Require historical clone-based installations to preserve their host `.env`, database, storage/uploads, plugins, project content, application files, and public overrides while following `UPGRADING.md`; the package repository must not replace host-owned state.

## 1.36.1

- Prepare existing installations for the future package-only repository layout with stricter update-archive safety checks and standalone Composer package readiness.
- Before the repository transition, install this compatibility release through the documented update path. A future repository checkout will no longer be a complete deployable Laravel application, so clone-based installations must not assume that a normal `git pull` across the cutover is safe.
- Preserve application-owned `.env`, database, storage, uploads, plugins, project content, and host files during any staged clone-to-Composer migration. Composer/package-native installs remain the supported source-consumption model, while Publisher/System Updates users should continue using the checksum/signature-verified update flow.

## 1.36.0

- Add CMS-managed image variants with responsive public image output, cached focal-point-aware crops, optimized media-picker thumbnails, and safe original-image fallbacks.
- Add focal-point editing and variant previews/regeneration to the Media edit screen in English, German, and Turkish.

## 1.35.5

- Render public Column Item icons beside their copy with the shipped WebBlocks UI `wb-icon-card` composition, reducing unnecessary card height without CMS-specific layout CSS.

## 1.35.4

- Pin WebBlocks UI to `v2.7.18`, add the canonical admin language switcher with immediate per-user locale updates, and migrate the authenticated topbar account control to `wb-user-menu`.

## 1.35.3

- Ship the first cryptographically signed WebBlocks CMS release now that the update publisher stores and serves the release signature, so installs that pin the maintainer public key verify update authenticity end to end.

## 1.35.2

- Enforce Ed25519 signature verification on System Updates by pinning the maintainer public key, so installs now reject any update package that is not signed by the release key in addition to the existing checksum check.
- Fix the System Plugins index table so the meta columns no longer break mid-word: the plugin name keeps its word boundaries, the version, source, and status stay on one line, and the health message wraps within its own column.

## 1.35.1

- Show the admin flash banner on the System Plugins index and detail pages so catalog updates, enable, disable, setup, and uninstall report their success or error outcome instead of completing silently.
- Confirm a plugin catalog update before it runs with a modal showing the installed and target version, and lock the confirm button with a spinner and progress label while the update is in flight so it cannot be double-submitted. Localized for English, German, and Turkish.

## 1.35.0

- Verify a detached Ed25519 signature and package checksum before applying a System Update, so tampered or corrupted update packages are rejected.
- Add the documented `cms_trans()` helper and plugin translation loading so first-party plugins can ship locale-aware `resources/lang` catalogs.
- Move visible WebBlocks Commerce and WebBlocks UI Manager admin surface copy onto plugin translation keys for English, German, and Turkish.
- Let the Internal Content API existing-block update endpoint write Image block `alt_text` and `caption` translation rows.
- Harden in-app System Updates so updater result and failure handling classes stay available after the package root is replaced and before Composer autoload metadata has fully settled.
- Add plugin API extensibility hooks so enabled plugins own their whole internal API surface: `PluginDefinition::apiRoutes()` mounts plugin route files under `/webadmin/api`, `apiDiscovery()` self-advertises endpoints in API discovery and OpenAPI, and `apiCapabilities()` contributes token capabilities and a token-UI permission group.
- Make Internal API token capabilities plugin-extensible: the grantable set, token permission groups, and OpenAPI/discovery now merge CMS core with capabilities contributed by enabled plugins, and commerce capabilities are no longer hardcoded in the CMS. Retire the CMS-core commerce API controller in favor of the plugin-owned one.
- Grow the WebBlocks Commerce plugin into an AI-first store: guarded order state machine with atomic inventory reservation and a stale-order expiry command, country-agnostic VAT snapshotted onto orders, a server-side multi-line cart with hosted checkout, multilingual product content sharing the CMS Site+Locale system, and a plugin-owned product/order/cart/translation API.

## 1.34.11

- Bumped CMS to `1.34.11`.
- Fix Engagement comment search so page matches use page translations instead of removed legacy page columns.
- Keep admin page-title lookups translation-aware on Engagement and Blocks listings, and align page slug accessors with the current translation.

## 1.34.10

- Bumped CMS to `1.34.10`.
- Add modal-confirmed bulk deletion to CMS Users so super admins can remove selected managed users faster.

## 1.34.9

- Bumped CMS to `1.34.9`.
- Scope `/webadmin/users` to CMS-managed users so host-only coexistence accounts stay out of CMS user management.

## 1.34.8

- Bumped CMS to `1.34.8`.
- Align CMS static icon classes with the pinned WebBlocks UI icon manifest and add a regression test for unknown `wb-icon-*` usage.

## 1.34.7

- Bumped CMS to `1.34.7`.
- Localize Locales, Page Layouts, and CMS API token capability screens through the authenticated admin locale.

## 1.34.6

- Bumped CMS to `1.34.6`.
- Localize Profile success flash messages through structured admin locale keys.

## 1.34.5

- Bumped CMS to `1.34.5`.
- Remove the legacy admin HTML localization bridge now that admin screens use structured native translation keys.
- Polish remaining Turkish and German admin locale diacritic artifacts found after the 1.34.4 locale pass.

## 1.34.4

- Bumped CMS to `1.34.4`.
- Polish Turkish and German admin locale copy for native diacritics, natural wording, and corrected fallback lookup keys.

## 1.34.3

- Bumped CMS to `1.34.3`.
- Preserve Turkish and German diacritics in admin locale copy instead of ASCII transliterations.

## 1.34.2

- Bumped CMS to `1.34.2`.
- Move Page details, duplicate, layout slot summary, and slot block delete modal copy onto structured admin locale keys.
- Move Navigation Items and Locales admin copy onto structured admin locale keys.
- Move column item editor and Page Layout admin copy onto structured admin locale keys.
- Move Page asset, import, inline block, and page form helper copy onto structured admin locale keys.
- Move Plugin Catalog, block type, domain, page move, and block form admin copy onto structured admin locale keys.
- Move Blocks, Block Types, and System Plugins listing copy onto structured admin locale keys.
- Move Block Type contract modal catalog, storage, translation, renderer, and gap copy onto structured admin locale keys.
- Move Page Layout Slot form identity, wrapper markup, trusted HTML, and status copy onto structured admin locale keys.
- Move Media asset picker controls, filters, empty states, upload, and modal actions onto structured admin locale keys.
- Move Page Slot block picker modal, tabs, search, table, and empty-state copy onto structured admin locale keys.
- Move Contact Messages listing filters, table, row delete, and bulk delete copy onto structured admin locale keys.
- Move System Plugin detail lifecycle, capabilities, settings, health, and uninstall copy onto structured admin locale keys.
- Move Contact Message detail, notification, classification, technical detail, and delete modal copy onto structured admin locale keys.
- Move Media edit preview, usage, metadata, file details, and delete modal copy onto structured admin locale keys.
- Move Page Slots card, source modal, and delete confirmation copy onto structured admin locale keys.
- Move Page Revision history copy onto structured admin locale keys.
- Move Page Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Page Translation form routing, SEO, Open Graph, and action copy onto structured admin locale keys.
- Move Shared Slot revision history and snapshot detail copy onto structured admin locale keys.
- Move Shared Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Shared Slots index, create, edit, and form copy onto structured admin locale keys.
- Move Search Index admin screen copy onto structured admin locale keys.
- Move Slot Types index copy onto structured admin locale keys.
- Move Site Clone and Delete admin copy onto structured admin locale keys.
- Move Site Domains, Site details, Site Assets, Public Theme, and Site Variables admin copy onto structured admin locale keys.
- Move Sites create/edit form tabs, branding, SEO, contact, and footer action copy onto structured admin locale keys.
- Move Page edit management, overview, publish modal, and translation table copy onto structured admin locale keys.
- Move System Settings general, project identity, mail, diagnostics, privacy, and runtime copy onto structured admin locale keys.
- Move Media Library listing, grid, preview, upload, fetch, folder, and bulk-delete copy onto structured admin locale keys.
- Move Visitor Reports admin screen copy onto structured admin locale keys for native translation readiness.
- Add a native-only admin translation audit mode that ignores the legacy HTML fallback map and blocks `LocalizeAdminHtml` removal until direct structured-key migration is complete.
- Move Profile, Slot Types, flash, and page action partial copy onto structured admin locale keys, bringing the admin translation audit to 100% coverage.
- Move System Icons index and edit modal copy onto structured admin locale keys.
- Move fallback, layout shell, content header, stat card, inline media/link, and shared icon badge block editor copy onto structured admin locale keys.
- Move Sidebar Nav Item, Sidebar Nav Group, and Sidebar Footer block editor copy onto structured admin locale keys.
- Move Users admin listing and form copy onto structured admin locale keys.
- Move Hero block editor copy onto structured admin locale keys.
- Move Gallery Items and Rich Text editor partial copy onto structured admin locale keys.
- Move Page Converter admin screen copy onto structured admin locale keys.
- Move Site Promotion admin screen copy onto structured admin locale keys.
- Move Plugin Catalog detail screen copy onto structured admin locale keys.
- Move CMS API Tokens main admin screen copy onto structured admin locale keys.
- Harden admin translation auditing so new admin Blade view families are discovered automatically and strict baseline checks fail on newly uncovered UI phrases.
- Add an admin translation quality gate script for German and Turkish admin locales.
- Move System Updates blocker copy and Export / Import admin screen copy onto structured admin locale keys.
- Move Columns, Link List, Feature Grid, and Contact Form block editor copy onto structured admin locale keys.
- Move Header Actions, Audio, Breadcrumb, and Download block editor copy onto structured admin locale keys.
- Move Link List Item, List, Table, Container, and Grid block editor copy onto structured admin locale keys.
- Move Runtime Status, Search Form, Sticky Navbar, Header, and Sticky Navbar settings copy onto structured admin locale keys.
- Move Accordion, Callout, Column Item, Download Inline, and Feature Item block editor copy onto structured admin locale keys.
- Document that the `LocalizeAdminHtml` bridge must be removed when admin translation migration is complete.
- Move Button Link, Trusted HTML, Section, Tabs, FAQ, Text, and TOC block editor copy onto structured admin locale keys.
- Move Slide and Gallery block settings copy onto structured admin locale keys.
- Move Sidebar and Navbar brand/navigation block editor copy onto structured admin locale keys.
- Move shared pagination and small block presentation settings copy onto structured admin locale keys.
- Move File, Video, Quote, Text Inline, and Feature Grid editor copy onto structured admin locale keys.
- Move Cluster and Slider block settings copy onto structured admin locale keys.
- Move CTA, Button, Navigation Auto, and shared background media editor copy onto structured admin locale keys.
- Move Image, Button Inline, Slide, Navigation Auto Inline, and API token capability copy onto structured admin locale keys.

## 1.34.1

- Bumped CMS to `1.34.1`.
- Move System Updates and Backups screen card, body, modal, action, and status copy onto the selected admin locale.
- Add regression coverage for localized System Updates and Backups admin screen body copy.

## 1.34.0

- Bumped CMS to `1.34.0`.
- Add an admin translation audit command for measuring hard-coded Blade UI copy coverage against the admin HTML fallback map.
- Broaden German and Turkish admin HTML fallback coverage to 100% for audited admin Blade UI copy across media, plugins, settings, visitor reports, contact messages, page/slot, site, revision, and system screens.

## 1.33.1

- Bumped CMS to `1.33.1`.
- Make Sites and Pages admin listing screens resolve primary screen, filter, table, and action copy from the selected admin locale.
- Add an admin HTML localization fallback so resource, system, media, user, locale, and report screens use the authenticated admin locale beyond the sidebar/topbar while deeper Blade migrations continue.

## 1.33.0

- Bumped CMS to `1.33.0`.
- Add the first file-based CMS translations layer for admin, public system copy, and block defaults.
- Add an install-wide admin panel language setting and use it in the admin shell/sidebar/topbar.
- Add per-user admin panel language preferences on the Profile screen, with system admin language fallback.
- Make public Search UI and Search Form defaults resolve copy from the current public locale.
- Make Contact Form default visitor labels resolve from the block translation catalog.
- Make public Comments and Rating system block copy and engagement success states resolve from the current public locale.
- Make CMS auth and password reset screens, auth validation copy, and reset email copy resolve from the admin locale.
- Make Dashboard and Engagement admin screens resolve visible interface copy from the admin locale.
- Make Contact Form, Comments, and Rating validation feedback resolve from the active public locale and keep engagement validation redirects on the relevant block.
- Make Engagement admin comment status flash messages resolve from the admin locale.
- Make the admin block type picker and Comments/Rating system block editor settings resolve copy from the admin locale.

## 1.32.246

- Bumped CMS to `1.32.246`.
- Render public page `<html lang>` from the page translation locale instead of the Laravel app fallback locale.

## 1.32.245

- Bumped CMS to `1.32.245`.
- Replace the Add/Edit Locale picker with a short standard language list and keep country variants/custom BCP 47 style tags behind custom locale details.
- Show the same locale picker on Edit Locale, with the current standard locale selected when available.
- Simplify Internal Content API locale options to the same curated standard language list.

## 1.32.244

- Bumped CMS to `1.32.244`.
- Add a searchable standard locale picker to the Add Locale admin form and expose the same locale option catalog through the Internal Content API.
- Broaden locale code validation to accept route-safe BCP 47 style tags such as `zh-hant-hk` while preserving custom locale support for operator cases.

## 1.32.243

- Bumped CMS to `1.32.243`.
- Fix the Site edit screen so site-level Branding media pickers render outside a block editor context instead of raising a 500 error.
- Preserve Contact Form submit and success copy from Internal Content API content plans, and use German default public form labels when rendering German locale pages.
- Add Internal Content API locale create/update endpoints with `site-settings.write` capability checks so migration tools can correct install locales before applying localized content.

## 1.32.242

- Bumped CMS to `1.32.242`.
- Preserve authored Gallery media order when Internal Content API plans assign `gallery_items` or `gallery_media_ids`.

## 1.32.241

- Bumped CMS to `1.32.241` and pinned WebBlocks UI to `v2.7.17`.
- Show a non-dismissible System Updates progress modal with the version path and shared WebBlocks UI spinner when an operator starts or continues an update.

## 1.32.240

- Bumped CMS to `1.32.240`.
- Center the Gallery lightbox `Viewer title` in the viewer header.

## 1.32.239

- Bumped CMS to `1.32.239`.
- Add a Gallery `Viewer title` setting so lightbox modals can show the current image collection name without restoring legacy public Gallery headings.
- Stop public Gallery rendering from exposing technical import notes such as `Imported from ... during ... migration` as item captions, overlay meta, or lightbox metadata.

## 1.32.238

- Bumped CMS to `1.32.238`.
- Make sibling alternating media/text Grid blocks share one parent sequence so reordering adjacent profile grids no longer preserves editor-selected per-grid left/right placement.

## 1.32.237

- Bumped CMS to `1.32.237`.
- Make alternating media/text Grid ordering work when the Grid directly contains a Slider and a text Section, matching existing editorial page structures.

## 1.32.236

- Bumped CMS to `1.32.236`.
- Keep alternating media/text Grid blocks on the normal `wb-grid` wrapper while reordering direct Section columns by detected media/text content.

## 1.32.235

- Bumped CMS to `1.32.235`.
- Add a Grid setting that renders direct Section children as alternating media/text rows, so editors can reorder sections without manually maintaining left/right slider and copy placement.

## 1.32.234

- Bumped CMS to `1.32.234`.
- Add mode-awareness analysis to canonical site CSS API responses so migration and new-site tools can catch hard-coded light palette regressions before marking a site complete.
- Pin WebBlocks UI `v2.7.16` and add native Navbar Navigation active indicator and active matching settings so current-page menu state can be made visible without site-specific CSS.

## 1.32.233

- Bumped CMS to `1.32.233`.
- Allow existing `header-actions` blocks to update search, mode, and accent toggle settings through the Internal Content API.
