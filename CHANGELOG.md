# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## 1.64.0

- Replace the fixed stack of empty Application Block setting cards with a compact settings table on the Embedded Application form.
- Add and edit typed settings in a shared WebBlocks modal, cancel without changing the table, and remove rows through the standard icon action column. The database and Internal API settings contract remain unchanged.
- Ship the setting editor as a page-scoped CMS admin asset and document the interface and persistence boundary.

## 1.63.1

- Fix System Update installations returning HTTP 500 on Embedded Applications by shipping the required existing-install update migration, and include the same table in the consolidated fresh-install schema.
- Show `applications.read`, `applications.write`, and `applications.delete` in the API Token capability editor so operators can grant the registry permissions introduced in 1.63.0.

## 1.63.0

- Replace the Embedded Applications filesystem pilot with a system-admin database registry. Operators define every HTML entry, CSS/JavaScript asset, render option, supported context, and editor setting under System → Embedded Applications; no `application.json` upload or directory scan is used.
- Keep host application paths out of CMS core. Same-origin URL validation, stable unique handles, enabled state, and guarded deletion replace hard-coded roots such as a product-specific `/play-assets` directory.
- Add `applications.read`, `applications.write`, and destructive `applications.delete` API capabilities. Content validate/apply and direct block updates use the same database-backed settings validation as the admin editor.

## 1.62.0

- Add Embedded Applications as a first-class CMS capability. A trusted local HTML/CSS/JavaScript application can declare a versioned `application.json` manifest and be placed with the new Application Block without requiring a bespoke PHP plugin.
- Support inline mounts and controlled same-origin iframe applications inside the normal public layout. Application CSS and JavaScript load only on pages that use them and are deduplicated across repeated instances.
- Validate every application handle, local asset path, render mode, mount declaration, and instance setting before rendering. Missing files, path traversal, invalid manifests, and duplicate handles fail closed without exposing server filesystem paths.
- Add `applications.read` API discovery for registered definitions and their settings schemas. Content validate/apply and direct block updates use the same manifest-backed settings validation as the admin editor, while executable source and registry mutation remain outside the content API.
- Add Application Block administration and public fallback translations in English, Turkish, German, Spanish, French, and Italian, plus the Embedded Applications architecture and API documentation.

## 1.61.1

- Fix the System Plugins index and detail screens returning HTTP 500. The plugin requirements integration called a nonexistent `PluginRegistry::find()` method; it now uses the registry's canonical `get()` lookup and is covered through the same registry path used by the admin screens.

## 1.61.0

- Navbar Navigation now renders the active catalog icon configured on each navigation item in both the desktop navigation and mobile drawer. The visible label remains beside the icon by default, preserving ordinary navigation semantics and giving plugin-owned progressive enhancements a stable icon-and-label anatomy to adapt into a header utility.
- Pin WebBlocks UI 2.24.0, which supplies the numeric `wb-btn-badge` icon-action overlay and admits `shopping-cart` to the curated navigation icon catalog.

## 1.60.0

- Add first-class Stack and Split layout blocks backed only by shipped WebBlocks UI primitives. Stack owns vertical child rhythm; Split owns a two-sided row whose first child grows and whose second child stays content-sized.
- Make the distinction visible in the editor: the block catalog and forms explain when to use Stack, Split, Cluster, and nested Stack composition. Split accepts exactly two direct children; admin and Internal Content API authoring reject a third side.

## 1.59.1

- **A Columns, Feature Grid or Link List block can no longer lose its items on save.** Those three blocks author their children from a repeating list on the parent's own form, and the save step skips any row it cannot use. The sweep that follows read "skipped" as "the editor removed it" and deleted the block behind the row — and when no row survived, it deleted every child of that type at once. It now removes only what was actually taken off the list.
- The way to hit it was an item block type missing from the block catalog. Each row carries its type as a hidden field filled from the published catalog; an unpublished or missing `column_item`, `feature-item` or `link-list-item` row leaves that field empty on every row, so nothing survives and the whole list went. The save now stops with a message naming `php artisan webblocks:catalog-repair --all` instead of quietly emptying the block.
- Nothing changes for a healthy install: rows you delete are still deleted, and a row you take off the list is still a removal.

## 1.59.0

- **Hero and CTA buttons are ordinary blocks now.** Both forms carried Primary CTA and Secondary CTA fields, and filling them created two Button Link blocks underneath — which then appeared in the block tree, could be edited from their own form, and could be dragged somewhere else entirely. Two places owned the same button, so a button moved out of a Hero left the Hero's form still claiming it was there. The fields are gone. A Hero's buttons are the Button Link children you add to it from the block tree, the same way a Slider holds Slides, and there is no longer a second owner to disagree with.
- Nothing has to be migrated: the buttons on existing pages were already real Button Link blocks, and they keep rendering exactly as before. What changes is where you edit them — open the button itself rather than the Hero.
- **A button you added by hand now actually renders.** Hero and CTA looked for the link in the `url` column, but Button Link stores its link in settings and the admin form deliberately leaves that column empty. Only the two buttons the old CTA fields generated wrote both, so they worked and a button added through *Add child* silently rendered as nothing at all. Both renderers now read the link the block type actually owns.
- **The two-button limit is lifted.** The renderers cut the list at two, so a third button could be created and saved but never appeared on the page. Hero and CTA render every published button child that has a label and a link.
- The block picker offered `button` for a Hero child — an unpublished legacy type — while the CTA fields produced `button_link`. Adding a child by hand gave you a different block from the one the form made. Hero and CTA now accept `button_link`, with the legacy type still allowed for content that already uses it.
- Internal Content API callers can keep sending `primary_cta` and `secondary_cta`; they remain accepted as a shorthand that writes the first two button children. Declaring the buttons as explicit `children` is the canonical form and the only way to author more than two.

## 1.58.1

- **Documentation only.** No code changed in this release, and no install behaves differently after updating. The docs carry `cms_sync` frontmatter, so publishing is what gets the corrections onto the docs site.
- **`translatedFields()` is documented.** `1.58.0` shipped per-locale copy for plugin-declared blocks and the plugin system guide never mentioned it — a capability a plugin author could not find is as good as one that does not exist, and it is the same failure as a manifest key nothing reads. The new section leads with what opts a block in, because the risk is not missing the feature but assuming it applies: declaring is the switch, and undeclared fields stay shared, which is right for a recipient address or a service id.
- It also records the three things worth knowing before adopting it — translations become authoritative and the settings column stops carrying a copy, existing blocks migrate on their next write rather than losing their wording, and a locale with any row counts as translated so a blank field falls back instead of blanking the default — and that adoption is not obligatory.
- The appointments and forms plan documents are brought up to date with what has actually shipped. Both still described core gaps `0.4` and `0.6` as open, one still opened with "nothing here has shipped yet", and one still quoted a CMS floor three bumps out of date. Phase 0 is now recorded as closed end to end: every gap those plans opened is fixed in core **and** in use by the plugin that found it.

## 1.58.0

- **Plugin blocks can own translated copy.** `BlockTranslationRegistry` was a fixed `match` over core slugs, so a block declared by a plugin had no translation family and no field map: its copy lived in the settings column and was shared across every locale. A plugin release could ship several languages of its own strings, but an operator could never give one block they placed a second language.
- A plugin block type now declares `translatedFields([...])`, and core stores each named field per locale. Fields it does not name stay in settings and stay shared, which is the right home for an operational choice such as a recipient address — declaring is what opts a block in, so nothing acquires per-locale storage by accident.
- Every other family is a table of fixed columns, because core knows the fields before the migration is written. A plugin's are declared at install time by a package core has never seen, so the plugin family is rows: one per block, locale and field name. That gives up the type safety of a column and buys the only thing that matters here — a plugin naming its own copy without core shipping a release first.
- Translations are authoritative once a field is declared, the same rule Contact Form already follows: the settings column stops carrying a second copy that nothing reads and nothing updates. A locale with any row counts as translated, because a plugin declaring five fields where the operator filled two has still been translated. A blank translation falls back rather than blanking the original, so an unfinished locale shows the default heading rather than an empty one.
- A disabled plugin's block reports no family. It contributes nothing anywhere else either, and writes would otherwise go to storage nothing could read back, since the field list that gives those rows meaning is gone with it.

## 1.57.0

- **Plugins can now ship CSS and JavaScript.** `PluginPublicAsset` could always emit a `<link>` or `<script>` for an enabled plugin, and the manifest always documented an `assets` key — but nothing ever copied a plugin's files anywhere a browser could reach, so the tag pointed at a 404. The appointments plugin hit this while building its booking form and shipped a fully server-rendered flow instead; every plugin since has worked around the same hole.
- A plugin puts what it wants served in `resources/public` and declares it under `assets` with a handle, a type, and a path. The files are copied to `public/cms/plugins/{handle}` on every install, enable, disable, setup and update, removed on uninstall, and the emitted tag carries `?v={version}` so a plugin update is never served from a stale browser cache.
- A plugin declares a path, never a URL: one that could write its own URL could point every page on the site at another origin. Publishing is a copy rather than a symlink, because a symlinked plugin directory would expose `src/`, migrations and everything else beside the CSS through the web server.
- Only an allowlist of extensions is published — stylesheets, scripts, images, fonts, `json` and `txt`. `.php` is refused for the obvious reason; `.html`, `.svg` and `.xml` are refused because a browser will execute script from them on the site's own origin, which is also the admin's. Symlinks and dotfiles are skipped, and the target directory is cleared before each publish so a file dropped from a release stops being served rather than lingering for ever.

## 1.56.0

- **Support: report a problem or request a change without leaving the admin.** A new `Support` item in the sidebar lets any signed-in admin file a request, follow its status, and reply to the team. Requests are typed as Problem, Suggestion, or Question, and the screens are translated into all six admin languages. It is a top-level item rather than a System one on purpose: `access-system` belongs to whoever maintains the installation, and the editor who cannot get a page to publish is exactly the person with something to report.
- Requests are filed on WebBlocks Workbench, the shared tracker behind every product in the fleet, through a server-to-server call. Nobody needs a second account, and the CMS sends only an opaque reference and a display name — no email address leaves the installation.
- **Each installation holds its own token** (`WORKBENCH_TICKET_TOKEN`, issued per install and pinned to the WebBlocks CMS project). That is what makes Workbench's per-token monthly quota bound one site's reporting without touching anyone else's.
- **Installs are told apart by an id the CMS generates, not by the token.** The CMS is installed independently on many sites, so local user ids restart at 1 everywhere; a bare id would have put every install's "user 1" in one bucket, and the ticket list is keyed on exactly that. `WebBlocks\Cms\Support\Tickets\InstallId` writes a random id once and folds it into the reporter reference, and sends it as `install_ref` so Workbench enforces the same boundary server-side (Workbench 0.1.89). It is a random UUID rather than a hash of `APP_KEY` or the site URL, so a key rotation or a domain change cannot silently re-identify the install and detach every reporter from their own history.
- Tickets also carry the running version, the site URL and the environment. None of it can be backfilled once a ticket exists, and without a version a report cannot be answered.
- The screens degrade quietly: an installation with no token says so in plain language, an unreachable tracker explains itself instead of failing, and a request that could not be sent is handed back with its text still in the form.

- **Plugins: the manifest's `requires` key is now read.** It has been documented, and written by every catalog plugin, since the beginning — and until now nothing looked at it. A plugin that depended on another degraded in silence: the operator who disabled the dependency saw a feature stop working and had nothing anywhere to read about why. Unmet requirements now appear on the plugin detail screen and as a health warning.
- They are **reported, never enforced**. Hard dependency resolution needs a resolver this CMS does not have, in an install model where plugins arrive as manually uploaded ZIPs, and it produces the deadlock where A cannot be enabled because B is disabled and B cannot be disabled because A is enabled. The corollary is now documented in the plugin system guide: every cross-plugin dependency must stay soft at runtime, and a genuinely optional one should not be declared at all — the warning would fire on installs that deliberately do without it.
- Installed-but-disabled is reported separately from never-installed, because a disabled plugin's classes are never loaded: to the depending plugin it is exactly as absent, and the operator's next action differs. Version comparison now understands two-segment constraints such as `>=8.3` and `^1.45`, which is what real manifests write. A `requires` entry the CMS cannot parse is reported rather than thrown — a third-party manifest must not be able to take an admin screen down — while the CMS's own `required_cms_version` still fails loudly, because that one is the CMS's own contract.

### Fixed

- Two Turkish admin strings sat under the wrong parent: `page_layouts.system` and `page_layouts.custom` where English carries `shared_slots.system` and `shared_slots.custom`. The Shared Slots screen fell back to English for both labels while the Page Layouts screen carried two keys nothing rendered.

## 1.55.0

- **Every page can say something different in a listing.** When a Page List block shows a page, it used to describe it with that page's SEO description — a sentence written for Google and social cards. If you wanted the card to read differently, your only option was to spoil the version search engines use. Each page translation now has its own **List description** under a new *Listing* heading on the page's language screen: one sentence, shown under the title on a listing card. Leave it blank and nothing changes — the SEO description is still used, shortened, exactly as before.
- The list description is per language, like every other visible text on a page, so a Turkish card reads Turkish. What you write for a card is shown as written rather than cut mid-sentence; the field allows up to 300 characters so it cannot break a grid. A borrowed SEO description is still shortened to fit. The field travels with the page: duplicating a page, moving it between sites, cloning or exporting a site, promoting a staging site and restoring a revision all carry it.
- Existing sites get the new field automatically on System Update; nothing has to be filled in for current pages to keep working.

## 1.54.3

- **Undo works after pasting, not only after a toolbar button.** 1.54.1 stopped the editor rebuilding its field on every command, which is what had been throwing away the browser's undo stack — but pasting still lost it. The sanitizer always answers in blocks, so a clipboard holding a few words came back as `<p>words</p>`, and inserting that as a block split the paragraph it landed in. The browser's resulting markup no longer matched the sanitized form, so the field was rebuilt after all and Ctrl+Z did nothing. Content worth a single paragraph is now inserted as the inline content it actually is: the paragraph stays whole, nothing is rebuilt, and undo behaves. Formatting inside the pasted text is kept, and the paste is sanitized exactly as before.
- Pasting several paragraphs, or a list, still inserts blocks and can still restructure the field — the browser decorates inline runs with its own styling spans in that path, which the sanitizer strips, so the two can never agree. Undo after a multi-paragraph paste is therefore still lost; a single word, sentence, or URL — the common case — is not.

## 1.54.2

- **List blocks get their bullets back.** WebBlocks UI is now pinned to v2.23.0. The shipped reset reads `ul, ol { list-style: none }` so that nav, breadcrumb, pagination, and every other structural list does not have to opt out one by one — but nothing opted a hand-authored list back in, so the List block rendered its items as bare indented lines, with a nested level indistinguishable from its parent. Ordered lists lost their numbers the same way. The new `wb-marker-list` primitive is the opt-in, and both the List block and the fallback renderer's list case now use it: real bullets, real numbers, and the browser's disc → circle → square ladder for nesting. Rich text lists were fixed upstream in v2.21.0 and already publish correctly.
- **The plugin catalog's requirement list stops floating.** `wb-list` used to mean two things at once — the framed list-group surface every document describes, and an undocumented element rule that restored markers — and the element rule won on specificity, bolting a left indent and a row gap onto a surface built to be flush. v2.23.0 removes the element rule, so the requirements list on a plugin's catalog page sits flush against the card for the first time. No markup change was needed.
- The pin also carries v2.20.1, which fixes responsive columns collapsing to one twelfth between their own breakpoint and the mobile stack, and v2.22.0's `wb-slider-overlay-medium`, the missing middle rung of the slider scrim ladder. The bundled icon catalog is re-vendored for the new pin at 184 glyphs; the icon set itself is unchanged.

## 1.54.1

- **The rich text editor stops fighting the writer.** Every toolbar command rewrote the field's entire HTML, which dropped the browser's undo stack: bold a word, press Ctrl+Z, and nothing came back. The field is now rebuilt only when editing pauses — on paste, on leaving the field, and on save — so undo and redo behave the way they do in any other text box, while the value that actually gets stored is still sanitized on every keystroke. A second bug went with it: the caret jump after pasting. The helper that was meant to leave the cursor at the end of the pasted text had its argument inverted and sent it to the very top of the field instead.
- **More of what body copy actually needs.** Strikethrough, block quotes, and lists that nest are now part of the editor's vocabulary, alongside the bold, italic, inline code, links, and flat lists it already had. `Tab` and `Shift + Tab` change a list item's level, and typing `- `, `1. `, or `> ` at the start of a line starts a list or a quote. Headings deliberately stay out: they are the Header block's job, which is also the only way a heading reaches the Table of Contents block.
- **The toolbar shows where the caret is.** Bold, italic, strikethrough, code, link, list, and quote buttons now light up when the cursor sits inside that formatting, so the toolbar reports state instead of only accepting commands. `Ctrl/Cmd + B`, `I`, and `K` are wired to bold, italic, and link.
- **Links get a real dialog.** Adding a link used a browser `prompt()` — one unlabelled field, no way to set the visible text, no way to remove a link except clearing the box. It is now a normal modal with a URL field, a link text field, an inline error for a URL the sanitizer would reject, and a Remove link action. Selecting a phrase and keeping its text keeps whatever formatting the phrase carries, so bold inside a linked phrase survives.
- Everything the editor can now write, `SafeRichTextRenderer` accepts on the way to the page: the two sanitizers are a pair, and a tag allowed by one and not the other disappears silently between saving and publishing. The renderer also stops discarding a paragraph merely wrapped in a `div`, and keeps a list level that arrives as `ul > ul` — malformed HTML that browsers and other editors produce routinely.

## 1.54.0

- **Pages can list themselves.** The CMS had no collection, query, or archive block, so an index page — a guides listing, a blog front page — was a card grid built by hand, and it went stale the moment a page was added. Two things looked like the missing feature and were not: the Blog and Archive page types that no public renderer ever read, and `navigation-auto`, which sounds automatic but replays a menu an editor built by hand. The new **Page List** block is the real thing. It lists published pages of a page type, of a path such as `/guides`, or below the page it sits on, as a card grid or a link list, with a sort order, a cap between 1 and 48, and toggles for the thumbnail and the description. Titles come from each page's name, descriptions from its SEO description, and thumbnails from its social sharing image, so nothing new has to be filled in per page to get a working index.
- **A listing can only show what a visitor should see.** Five rules are enforced by the query itself and are not settings anyone can switch off: only published pages, only this site, only pages actually translated into the language being viewed, never the internal Shared Slot source pages, and — by default — not the page the block sits on. An empty or half-configured list renders nothing at all rather than an editor-facing "no pages found" notice on a live page.
- Page List is available to the Internal Content API, so a tool can build an index page instead of generating a card per page and rebuilding the grid on every change. There is no pagination: a curated or hand-ordered set of links is still what `link-list` is for.

## 1.53.0

- **Cookie consent has a visitor-facing half.** The server half has shipped for a long time — `POST /privacy-consent/sync` validated the decision and returned the consent cookie, `VisitorConsent` gated analytics on it, System Settings already carried a *Show the public privacy settings banner* toggle in all six panel languages, and the package even published `cms/js/privacy-consent-sync.js` to bridge the two. What never shipped was anything visible: no view rendered a banner, and no view defined the config object that script reads on its first line, so it returned immediately and the endpoint was unreachable from a real site. Public pages now render WebBlocks UI's Cookie Consent pattern — banner plus a reopenable preference centre with the necessary/preferences/analytics/marketing categories the endpoint validates — and wire the bridge. It appears only while Visitor Reports and the toggle are both on and the visitor has not decided; the banner copy ships in all six site languages.
- **The contact form can record consent.** A form that stores submissions often has to prove the visitor agreed to that storage, and the only option was to demote the notice into the intro text — prose beside the form, attached to nothing. There is now a *Require a consent checkbox* setting plus a translated *Consent notice*, because the wording is the notice. An accepted submission stores the agreement time and a **copy** of the wording, so editing the block afterwards cannot change what a past visitor is recorded as having agreed to. The requirement is re-read from the block on submit, so removing the field from the page does not bypass it, and a required consent with no wording for the visitor's language renders no checkbox rather than an unlabelled one. Existing forms are untouched: the setting defaults to off.
- **`medium` overlays stop rendering as `strong`.** The slide and slider-root overlay settings each offered four levels but mapped them in two separate places that had drifted apart — the slide collapsed `medium` onto `strong`, the slider dropped it to no overlay at all. One stored word, three renderings, and anyone who picked a mid scrim silently shipped a full-strength one. WebBlocks UI is pinned to v2.22.0, which adds the missing `wb-slider-overlay-medium`, and both surfaces now share a single mapping. Slides already saved as `medium` lighten to the level that was asked for. The bundled icon catalog is re-vendored for the new pin; the icon set itself is unchanged.
- **`GET /content-contract` no longer contradicts `openapi.json` about media.** The contract published a hand-maintained list calling upload, remote fetch, delete, replace and move unsupported, while all five were live endpoints named in the OpenAPI document. Tools are told to trust the contract rather than guess, so the stale list invented a capability gap and cost real work. The media section is now derived from the registered route table — the same source the OpenAPI document describes — and reports each supported operation with the capability its route enforces. The two lists cannot overlap, and a test holds that.
- **Content plans can express real navigation menus.** The plan normalizer hard-coded every item to `custom_url` and discarded `page_id`, then failed on the now-empty `url` — an error pointing at a field the caller never sent, which reads as "fix your URL" rather than "this is not supported". Plans now honour `link_type` of `page`, `group` or `custom_url` with the same rules the navigation endpoints enforce, and a `group` accepts nested `children` that are created already linked to their parent. A dropdown that previously took a POST per item plus a PATCH per item to re-link is one plan.
- Hero and CTA are clearer about their actions. Authoring them through `primary_cta`/`secondary_cta` has worked since 1.40.4, but `inventory.md` still carried the older "use a sibling Cluster with Button Link" workaround, and `GET /block-types` advertised `button` as the only allowed child without mentioning it has no catalog row. The docs are corrected and the endpoint now publishes `managed_action_fields` alongside `unreachable_child_handles`, so the apparent dead end describes itself.

## 1.52.18

- **Table blocks are edited in a grid, not a text field.** The Table block offered one textarea where a row is a line and cells are separated by `|`. Adding a column meant retyping every line and counting bars, and a table copied out of a spreadsheet had to be reformatted by hand before it would paste. There is now a spreadsheet-style grid above that field: one input per cell, add/remove/move for rows and columns, Enter to walk down a column, and a multi-cell paste from Sheets or Excel that expands the grid in place. When the block is set to `First row is header`, the grid shows that first row as the header it will render as. A `Text view` button switches back to the pipe-separated form and back again, both directions in sync, for anyone who would rather type it.
- Storage did not change. The pipe-separated `content` field is still the only source of truth, so the public renderer, the request layer, per-locale table translations, and every table already saved are untouched — the grid reads and writes the same string. With JavaScript off the plain textarea is what renders, as before.

## 1.52.17

- **French is now a panel language, not only a site language.** 1.52.10 shipped `resources/lang/fr` for the public site, so a French site rendered its contact form, search overlay, and 404 page in French — while the people publishing it still worked in an English panel, because the admin catalog was never written and the topbar dropdown, which is fed by the supported-locale list, did not offer French at all. The admin catalog now ships complete: every key the English one carries, so the panel reads French from the dashboard to the block editor, and `FR Français` sits in the language menu beside the other five. Existing installs pick it up on update; nothing to configure.

## 1.52.16

- **Admin table columns stop shredding words.** WebBlocks UI is now pinned to v2.20.0, which changes `.wb-table td` from `overflow-wrap: anywhere` to `break-word`. `anywhere` dropped a cell's min-content width to a single character, so `table-layout: auto` was free to squeeze any column below the width of its longest word — a name arriving as `Os / man`, a date as `2026-08- / 06`. Narrow viewports now scroll the table wrapper, which is what it is for, rather than breaking the copy. This is the upstream fix for what 1.52.13 could only work around column by column; those scoped rules stay, because the base rule still sets `white-space: normal` and a nowrap column still has to outrank it.
- The runtime also adds `wb-table-break`, an opt-in for a cell or row holding unbroken machine strings — URLs, tokens, hashes — that should wrap rather than widen the table. The class snapshot the admin is checked against records it; no admin table uses it yet.
- The bundled icon catalog is re-vendored for the new pin; the icon set itself is unchanged.

## 1.52.15

- **The language switcher shows what it does.** WebBlocks UI is now pinned to v2.19.0, which stops hiding the chevron on the `wb-language-switcher--code` trigger — the recommended variant, and the one both the admin topbar and the public Header Actions block use. Until now that trigger was a bare `EN` with nothing to say it opened a menu. Both switchers already ship the full trigger anatomy, so the pin is the whole change: no markup, and nothing to do per site. The release also restates the menu item rule the CMS already follows — a locale code followed by the language's own name, `Deutsch` rather than `German`.
- The bundled icon catalog is re-vendored for the new pin; the icon set itself is unchanged.

## 1.52.14

- **Block ids read like page ids.** The id column in the page slot and Shared Slot block tables rendered its number inside a `<code>` element, so it arrived as a tinted chip while the Pages table shows plain muted digits for the same thing. It is now the same plain treatment in both places.

## 1.52.13

- **The Pages table columns stop wrapping, this time for real.** 1.52.12 gave the id column `white-space: nowrap` and it kept breaking `#54` in half, because webblocks-ui's own `.wb-table td { white-space: normal; overflow-wrap: anywhere }` is a class *and* a type selector and outranks a lone class however late it loads. The rule is now scoped by the table class, and so are the five sibling columns — count, status, view, actions, last edited — which carried the same defect since they were written and had been wrapping just as quietly. A test now refuses an admin table cell override that cannot win against the base rule.
- **Page ids lost the `#` prefix.** The column heading already says ID.

## 1.52.12

- **Page ids stop wrapping onto two lines.** The Pages table gave its id column no width of its own, so once the page cell filled up with translated paths the browser took the slack from the id and broke `#54` across two lines. It now holds one line, like the id column in the block tables. The column heading, which was the only hard-coded one in that table, now comes from the admin catalog in all five panel languages.

## 1.52.11

- **A block that is not being moved can be edited again.** Placement rules decide where a block may be *put*, but the admin re-ran them on every save, so a block whose parent no longer satisfies them was locked out of editing entirely — a Navbar Brand sitting in a footer, placed by a content plan and rendering publicly every day, answered "This block type requires a supported parent block" when only its label was changed. The parent select now always offers the block its own current parent (it previously fell back to "no parent", which is what turned a field edit into a detach request), and the placement rules are skipped while both the parent and the block type stay exactly as stored. Moving a block, or changing its type in place, is validated as strictly as before.
- **Block lists show the block ID.** The page slot and Shared Slot block tables now open with an ID column, so the id in a URL, an API call, or a support question can be matched to a row without opening each block in turn.

## 1.52.10

- **The API page render now renders the locale it was asked for.** `GET /webadmin/api/pages/{page}/render?locale=it` resolved the page translation but never told the block renderer which locale it was rendering, and the route carries no locale prefix for the resolver to read on its own — so the page identity was Italian while every block on it stayed in the site default language. Locale selection is the reason this endpoint exists over the browser preview, so a tool checking its own translation work was told the work had not landed. The public site was always correct; only this preview was wrong.
- **French is now a shipped language for public pages.** The package carried visitor-facing catalogs for English, Turkish, German, Spanish, and Italian only, so a site publishing in French fell back to English for everything the CMS itself renders: contact form field labels, the storage note under it, the search overlay, the theme mode labels, and the branded 404 page. Site content was unaffected and stayed French, which is what made the gap look like a handful of stray strings. `resources/lang/fr` now ships alongside the others, and a parity test refuses a catalog that does not carry every key English does.
- **Screen-reader labels follow the page language.** The nav, breadcrumb, slider, and gallery viewer labels were hard-coded English in the blades, so `aria-label="footer navigation"` and `aria-label="Toggle navigation"` were announced that way on every translated page in every language, including the five that were otherwise complete. They now come from the shipped catalogs, menu labels included, and a test refuses a literal aria-label in the public blades.

## 1.52.9

- **Contact form button and confirmation text can be translated through the API.** `PATCH /webadmin/api/blocks/{block}` only understood the text and image translation families, so a contact form's `submit_label` and `success_message` had no API path at all and a translated page kept its English "Send message" button. The endpoint now routes every translated field to the family its block type belongs to — text, button, image, or contact form — writes it through the same writer the admin uses (so the default-locale row is kept alongside the new one), and exposes contact form rows under `block.translations` so a tool can read what it wrote.
- **A translated field the block cannot store is now refused instead of silently misfiled.** Sending `translations.title` to a button or contact form previously answered `200` while writing the value into the text translation table, which the renderer never reads. Fields outside the block's family are rejected with `422` and code `unsupported_block_translation_fields`, a block type with no translation family rejects `translations` with `unsupported_block_translations`, and a locale the block's site has not enabled returns `invalid_block_translation_locale` rather than failing as a server error.
- **The API's rate limit is documented where clients read it.** The budget was enforced but never published, so a bulk client such as a full-site translation pass discovered it by hitting `429`. `GET /webadmin/api/openapi.json` now carries `x-rate-limit`, and the AI guide states the same number; it stays 120 requests per minute per token and IP and is configurable with `CMS_INTERNAL_API_RATE_LIMIT_PER_MINUTE`. Hosting or CDN layers in front of a site may still enforce less.

## 1.52.8

- **Menus stop linking to pages that are no longer published.** The navbar always hid page-linked menu items whose page left the published state, but the footer/legal menus (`navigation-auto`) and the sidebar menu only checked the item's own visibility — so archiving a page removed its link from the navbar while the footer kept a dead link straight to the 404. Both renderers now apply the navbar's rule: a page-linked item renders only while its page is published.
- **Pages can be archived through the Internal Content API.** `POST /webadmin/api/pages/{page}/archive` applies the admin workflow's archive transition under the same `content.publish` capability that gates publishing: allowed from `in_review` and `published`, draft pages are rejected, re-archiving is a no-op success, and staged update pages are refused (promote or delete those instead). A page revision is captured, capability discovery advertises `archive_page`, and the OpenAPI schema, AI guide, and docs describe the endpoint. Publishing the page again reverses the archive.

## 1.52.7

- **The browser tab shows the same CMS icon on every page.** The packaged `favicon.svg` drew a different design (a solid tile) than the PNG icons (the monoline grid mark), and Chrome picks its favicon candidate per tab — so the icon could look different, and seemingly smaller, from one tab to the next. The SVG now draws the identical grid mark the PNGs carry, so every candidate renders the same icon. The PNG files themselves are untouched.

## 1.52.6

- **API discovery now documents menu label translations.** Writing a navigation label for a non-default locale (`PATCH` the item with `locale` plus `label`) has worked since 1.52.0, but the live OpenAPI schema and AI guide never mentioned it, so API tools had to discover the format from source. The item PATCH spec now lists `locale` with the translation-row semantics and the `title_translations` read map, the AI guide and the navigation workflow describe the same write, and the spec states that untouched fields pass through without re-validation.

## 1.52.5

- **Navigation items from older sites can be updated through the API again.** Updating a menu item re-validated fields the caller never sent against the item's stored values, so rows predating today's rules were locked out entirely: items still carrying sort order 0 from before the tree editor, and children nested under a page-type parent from before the groups-only rule. Even writing a title translation failed. Untouched fields now pass through as they are; a sort order or parent you actually send is validated exactly as before.

## 1.52.4

- **The 404 page's browser tab keeps its icon.** The branded 404 builds its own `<head>` and emitted no favicon links, so the tab icon disappeared exactly on error pages. The page now resolves the favicon the same way every other public page does: the site's uploaded favicon wins, the packaged brand icons are the fallback.

## 1.52.3

- **The 404 page now opens with the site's own brand line.** The site's display name sits above the 404 code, linking to the home page of the locale the visitor was browsing, so the page answers "whose 404 is this" at a glance. It inherits the page's text color rather than the accent link tone, and hides itself when no site name resolves. Deliberately the site's brand and not the CMS's — self-hosted CMSes keep their own name off visitor-facing error pages.

## 1.52.2

- **Every site now ships a branded 404 page.** A missing page used to fall through to Laravel's plain default — unbranded, English-only, ignoring the site's theme. The package now renders its own 404 for public HTML requests: the site's display name, public theme preset, brand palette, and `site.css` all apply, and the copy comes in the request's locale (resolved from the URL's locale prefix, falling back to the default locale) from a new `errors.not_found` public catalog shipped in all five languages. The "back to homepage" button resolves the locale's real home path, and the page carries `noindex, nofollow`.
- JSON requests keep their JSON 404 untouched, and a host app that ships its own `resources/views/errors/404.blade.php` keeps winning — the package view is a fallback, not a takeover. Every lookup in the view is wrapped defensively, so a 404 can never escalate into a 500.

## 1.52.1

- **Button Link internal URLs now follow the page's language.** A button stored as `/products` sent every locale's visitor to the default-locale page; the public renderer now rewrites an internal path to the same page's translated path in the render locale (`/es/productos` on the Spanish render). An already-prefixed pasted path (`/tr/urunler`) resolves back through the page, and a query string or `#fragment` rides along. External URLs, paths that don't resolve to a published page, and pages without a translation in the render locale keep the stored URL untouched — the rewrite is never worse than the stored link. The stored value itself stays shared and raw: the admin form and the managed-CTA synchronizer read it exactly as entered.

## 1.52.0

- **Multilingual sites get a public language switcher.** The Header Actions block now renders a compact dropdown — the same `wb-language-switcher--code` component the admin topbar already uses, so no new CSS — between the search trigger and the mode toggle. Each locale links to the current page's real translated path (the ES link on `/products` goes to `/es/productos`), falling back to that locale's home page when the page has no resolvable translation. It only renders when more than one enabled locale resolves a link, so single-locale sites are untouched, and a new `show_language_switcher` setting (default on) lets operators hide it per block — wired through the admin form, validation, the block contract, the internal content API, and all five admin languages.
- **Every public page now emits hreflang.** The head carries `<link rel="alternate" hreflang>` for each enabled locale the page resolves an absolute URL for, plus `x-default` pointing at the default locale — closing the duplicate-content and wrong-language-indexing gap on multilingual sites. Emission is keyed on an explicit variable only the public layout passes, because the head-meta partial is shared with the admin and guest layouts, whose views have their own `$page` in scope; and it is skipped entirely when fewer than two locales resolve, so single-locale sites render byte-identical heads.
- **A page ships exactly one `<h1>` no matter how many Content Header blocks it stacks.** Only the first one rendered per request keeps `<h1>`; later ones — slider slides, additional sections — drop to `<h2>`. The counter lives on the request's attribute bag, so it is Octane-safe with no global state.
- **System Update's catalog repair no longer wipes a site's own slot classes.** Repair used to force every layout slot's `css_classes` back to the catalog value — `NULL` for slots the catalog states none for, which is the footer in every layout — erasing operator-set classes on each update. The column is now only repaired when the catalog actually states a canonical value, so `wb-public-main` still gets restored while custom footer classes survive.
- All of this ports the three site-local vendor view overrides fklavye.net had accumulated into the package, where export/import (correctly) never carried them; the overrides can now be deleted.

## 1.51.0

- **The admin panel now speaks Spanish and Italian.** `es` and `it` join en, de, and tr as admin interface languages, selectable from the topbar language menu, the profile language preference, and the system admin-locale setting. All four translation catalogs — admin (3,714 keys), blocks, public, and validation — ship complete for both languages, with key sets, key order, and every `:placeholder` verified identical to the English source, so nothing falls back mid-screen. Technical vocabulary follows the same conventions the German catalog established: slot, layout, plugin, token, and slug stay in English; the informal register (tú/tu) matches de and tr.
- **A fresh install in Spanish or Italian gets a starter home page in that language.** `home.es.json` and `home.it.json` sit beside the shipped starter blueprints and are picked up by the existing locale lookup, exactly as the German and Turkish variants were in 1.50.7.
- The locale-parity test suite — updates vocabulary, shared-slot confirmation copy, listing empty states, site form structure — now iterates all five admin locales, so a key added to English without its four counterparts fails the build.

## 1.50.7

- **The filter bar's Reset button is finally translated.** The shared listing-filters partial fell back to a hard-coded "Reset" that no listing overrode, so the button stayed English in the German and Turkish admin while the Apply button beside it was already localized. Every listing that shows the button — Pages, Contact Messages, Blocks, Block Types, Media, Shared Slots, Icons, Backups, Users, both Engagement listings, and the slot block picker — now passes a translated label, and the partial itself resolves translated defaults for both buttons, so a future caller that passes neither still renders localized text.
- The label is standardized on one key, `clear_filters`: Block Types' "Reset" and Media's "Reset filters" (both also used by their filtered empty states) were renamed to it, so the same action no longer reads differently from screen to screen.
- **A fresh install in Turkish or German gets a starter home page in that language.** `home.tr.json` and `home.de.json` sit beside the shipped `home.json` and are picked up by the starter-content locale lookup that already existed; installs in any other default locale keep the English page. The copy follows the informal address the admin translations already use.

## 1.50.6

- **A listing that hides everything behind a filter no longer tells you to create your first one.** Ten admin listings shared a single empty-state message written for a genuinely empty install, so searching Pages on a site with fifteen of them answered "Adjust the filters or create your first page", and searching Contact Messages answered "No messages yet — published Contact Form blocks will save new submissions here". Each listing now tells the two states apart: nothing exists yet keeps its original invitation, while a filtered-away result says so and offers a way back to the unfiltered list.
- Covers Pages, Contact Messages, Blocks, Shared Slots, Media, Icons, Backups, and both Engagement listings. Users already did this and is the pattern the rest now follow.
- Sorting, view mode, and the site scope deliberately do not count as active filters: they do not hide rows, and a site with no pages really should be told to create its first one.
- Contact Messages and Backups also switch their empty-state *title*, because "No messages yet" and "No backup history yet" are claims about time that a filtered result contradicts.
- `ListingEmptyStateContractTest` holds the pattern in place for all ten listings and checks every new string is translated in English, German, and Turkish, so a listing added later cannot quietly fall back to one message.

## 1.50.5

- **Pinned to WebBlocks UI 2.18.0**, which brings two fixes to every site on the next update.
- **Slide copy follows the slider's text colour.** UI's element selectors set `color` on `h1`–`h6` and `p` directly, which beat the colour inherited from `.wb-slider`, so neither the default white nor the Light/Dark text modifiers ever reached headings and paragraphs inside a slide — a Light-mode hero drew dark theme text over dark artwork. Sites that repaired this in their own `site.css` need no change: the shipped rule does the same thing, so the local one is now redundant rather than wrong.
- **The phantom scroll on short mobile pages is gone at the source.** UI's reset no longer puts `min-height: 100vh` on `body`. It filled no height there, but it did force the body box to the *largest* mobile viewport, so a page with nothing to scroll still scrolled by the height of retracted browser chrome. **If your `site.css` or a hand-written layout relied on `body` being at least viewport-height, set the height on your own root element instead** — the shipped shells already do. `.wb-public-body` now declares both floors itself, `min-height: 100vh` for browsers without `dvh` and `min-block-size: 100dvh` over it, so the CMS public shell is unaffected; the admin and Docs shells were never affected, since `.wb-dashboard-shell` states its own `height: 100vh`.
- The bundled icon catalog was re-vendored for 2.18.0 (183 icons, unchanged set), and the UI class snapshot the admin contract test reads moved with the pin. No class was added or removed between 2.17.0 and 2.18.0.

## 1.50.4

- **The Sites row actions sit where they belong.** 1.50.3 put View details and Edit site in the leading View column, next to the globe; View now carries the globe alone, and the two icons moved into Actions, ahead of the Manage button. Manage also gained a chevron, so a button that opens a menu now looks like one. Nothing moved out of the dropdown that was not already out of it.

## 1.50.3

- **The Sites list opens with its row actions instead of ending with them.** The View column moved to the front of the table and now carries three icons: the globe that opens the site's home page in a new tab, plus **View details** and **Edit site**, which were entries in the Manage dropdown and cost two clicks each. Manage keeps the rest — domains, clone, export, promote, delete.

## 1.50.2

- **The Sites list gained a View column.** Each row now carries the same globe button Pages has, opening that site's `/` home page on its own canonical domain in a new tab. Until now the domain was printed as text and reaching a site meant retyping it.
- **Admin listings no longer show an empty table while the header counts rows.** Pages remembers the page number you were last on, so deleting rows or narrowing a filter could leave that number past the end of its own result set: the listing rendered "No pages found" with a count of 15 beside it, and the only way out was clearing the filters. Every paginated admin listing — Pages, Blocks, Media, Sites, Block Types, Slot Types, Shared Slots, Layouts, Locales, Users, Contact Messages, Engagement, Icons, API Tokens, Backups, Site Transfers — now redirects to its last real page, keeping the rest of the query string. Pages also repairs the page number it had remembered, so the stale value does not come back on the next visit.

## 1.50.1

- **The public shell now sizes itself against the visible viewport.** WebBlocks UI's reset uses `min-height: 100vh`, which on mobile is the *largest* viewport — it reserves space for browser chrome that may already be retracted, so a page with nothing to scroll still scrolls a little. `.wb-public-body` adds `min-block-size: 100dvh`; a browser without `dvh` support drops the declaration and keeps the reset's `100vh`, so there is nothing to fall back to manually.
- **`site.css` analysis no longer reads comments as CSS.** The mode-awareness signals were computed over the raw file, so a stylesheet that documents itself by quoting the rule it repairs — `/* WebBlocks UI declares .wb-slider-text-light { color: #fff } */` — was reported as carrying a literal color while setting none, and the better the CSS was commented the more likely it was to warn. The reverse held too: a dark-mode scope named only in a comment counted as present and suppressed a warning that was real.
- **The page-wide anti-pattern checks stop reporting local rules.** They matched any selector merely containing the token, so `.wb-public-body { background: #fff }` counted as a body repaint, `body .promo {}` as a page-wide one, and `.wb-card-title` as `.wb-card`. Each token now has to be the last compound of its selector and to end where the name ends. Real page-wide repaints, including scoped `html[data-mode="dark"] body[...]` selectors inside a selector list, are still caught. Tools that treat `mode_awareness.status = warning` as work to clear before finishing a migration were being handed warnings nobody could act on.

## 1.50.0

- **Short pages no longer float their footer in the middle of the viewport.** WebBlocks UI's reset gives `body` a `min-height: 100vh`, but leaves `display: block`, so that height pushed nothing down: on a page whose content ran out early the footer sat wherever the content ended. `public/cms/css/public.css` now makes `.wb-public-body` a flex column and lets `.wb-public-body > .wb-slot-main` absorb the leftover height. Pages taller than the viewport are unaffected — `main` is `flex: 1 0 auto`, so it never shrinks below its content. The direct-child selector deliberately excludes the Docs shell, where `main` renders inside `.wb-dashboard-body`: that pattern is `height: 100%` inside an `overflow: hidden`, `100vh` shell and already owns its own height model, and letting the rule reach it could clip long docs pages. `position: fixed` layers (`.wb-overlay-root`, `.wb-sidebar-backdrop`) are out of flow and unchanged, and a sticky navbar stays sticky.
- **The footer slot ships a surface of its own: `--wb-public-surface-strong` with a `--wb-public-border` top rule.** Until now the footer rendered on the page background with nothing separating it, and there was no block setting that could change that — WebBlocks UI's footer pattern (`wb-footer-grid`, `wb-footer-nav`, `wb-footer-copy`, …) is layout only, carries no surface, and no CMS renderer emits those classes, so they are reachable only from hand-written layouts. Every site was writing this rule into its own `site.css`. **This is a visible change on existing sites**: a footer that had no styling of its own now has a distinct background and a top border. It is set with `background-color` rather than the `background` shorthand, so a background image on the same element survives; and `site.css` loads after `public.css` at equal specificity, so a site that already styles `.wb-slot-footer` keeps winning.
- Both rules hang off `.wb-public-body` and the fixed `wb-slot-*` classes, which are code-owned and never stored `css_classes`, so a System Update catalog re-sync cannot detach them.

## 1.49.1

- **Mobile navigation works again on sites using the shipped shared navbar.** A header slot holding a single `sticky-navbar` is promoted by `PublicPagePresenter`: the slot wrapper becomes the `<nav>` and the navbar's children render in its place, so the `sticky-navbar` block never runs — and it is the only thing that flushes the pushed mobile drawer. The burger's `data-wb-collapse` pointed at an element id that was never rendered, so tapping it did nothing. The public layout now flushes the drawer registry after the slot content as well, landing the drawer directly after the navbar element per the `wb-navbar-drawer` contract. Slots that were not promoted were already drained, so nothing changes for them.
- The existing drawer tests rendered the navbar template directly and never took the promotion path, which is how this survived. New coverage goes through the presenter and the public layout the way the page controller does.
- **The AI guide now documents the endpoints 1.49.0 added.** `GET /webadmin/api/ai-guide` and the packaged guide described none of page translations and page SEO, page rendering, site SEO defaults, the contact recipient, locale assignment, media folders, Shared Slot correction, the domain capabilities, or the new `unsupported_plan_fields` rejection — so an agent reading the guide would not have found them, and would have met the plan rejection as an unexplained error. A test now fails if the guide drifts from the endpoints again.
- Corrected the 1.49.0 note for `GET /webadmin/api/pages/{page}/render`. It was described as making page rendering reachable for API tools; `GET /webadmin/pages/{page}/preview` already accepted a Bearer token with `content.read` and still does. What the endpoint adds is locale selection, since the browser preview always renders the default translation, plus a JSON envelope and presence in discovery and OpenAPI.

## 1.49.0

- **Page SEO can now be set through the API.** Page-level `seo_title`, `seo_description`, `seo_keywords` and the Open Graph overrides live on the page translation row, which content apply wrote once at page creation and nothing could touch afterwards. `GET`/`POST`/`PATCH /webadmin/api/pages/{page}/translations` write them, add a second locale to an existing page, and rename or re-path a page — and the page payload now reads them back, where they were previously absent from both the write and the read side.
- **Pages render through the API, in a chosen locale.** `GET /webadmin/api/pages/{page}/render` performs the same render as the browser admin preview: draft blocks included, noindex, never through the public route. `format=html` returns the markup, `locale` picks the translation. The admin preview route already accepted a Bearer token, so what this adds is locale selection — the preview always renders the default translation — plus discovery and OpenAPI presence.
- **Content plans reject fields they do not understand instead of dropping them.** A plan carrying an unsupported field such as `page.seo_title` used to apply cleanly and write none of it — `ok: true`, nothing changed, no way to tell. Unknown keys now fail with `422` and the code `unsupported_plan_fields`, scoped per plan mode. Tools that sent extra fields alongside a valid plan must stop sending them.
- **Site domain API writes now require capabilities.** The `/admin-api` domain routes checked only that a token was valid, so a token issued for page building could repoint or delete a production domain. Adding, updating and promoting a domain now require `domains.write`, deleting one requires the destructive `domains.delete`, and reads require `content.read`. Grant the new capabilities to any provisioning tool that manages domains before upgrading it.
- The domain routes also gained a canonical home under `/webadmin/api`, where the path-based CSRF exemption actually covers them, plus the `update` and `set primary` operations that were browser-admin-only. The `/admin-api` prefix keeps working.
- **Shared Slots can be corrected and removed through the API.** `PATCH` and `DELETE /webadmin/api/shared-slots/{sharedSlot}` change the label, handle, slot type, layout and active status, or delete one — the latter behind the new destructive `shared-slots.delete` and the browser admin's own guard, which refuses to remove a Shared Slot any page slot still references and lists the referencing slots.
- **Three site settings the API could not reach.** `PATCH /webadmin/api/sites/{site}/seo` writes the site SEO defaults every page inherits, `PATCH .../contact-recipient` sets where contact submissions are mailed — an API-built contact form did nothing useful without it — and `PUT .../locales` assigns locales to a site, without which a page translation for a new locale cannot be saved at all. Detaching a locale that still has page translations is refused rather than silently orphaning them.
- **Media folders can be created.** Uploads could always be filed into a folder, but only one an operator had already made by hand. `GET`/`POST /webadmin/api/media/folders` closes that, and refuses a duplicate name under the same parent by returning the folder that already has it, so a retrying tool reuses it instead of leaving a copy per attempt.
- [API and Panel Alignment](docs/api-panel-alignment.md) is a new document recording, domain by domain, where the API and the browser admin agree, where the API covers less, and which gaps are deliberate boundaries rather than unfinished work.

## 1.48.13

- **Icons are now chosen in a picker modal that shows them, instead of a dropdown listing their names.** The field became a trigger showing the current icon; the modal renders every icon's real glyph beside its name, searches by name and slug, and moved both tone controls inside next to a live preview that draws the chosen icon and the block's actual badge label in the chosen tones. It is built on the same pieces as the media picker (`wb-modal` through `WBModal`, an overlays push, `data-wb-` hooks, its own admin script), and the submitted field names are unchanged, so nothing about saving a block moved.
- One modal serves the whole page. Item editors repeat the icon field and can add rows after load, so triggers carry their own state, the modal writes back to whichever one opened it, and the badge preview reads the label from that field's own row.
- **A fresh install now has the full icon catalog without an operator step.** Install and the catalog repair a System Update runs both seeded 20 hand-written navigation slugs — duplicated in `IconCatalogSeeder` and `CatalogRepairer` — so every content icon field was empty until someone found `System -> Icons`. The package now carries the WebBlocks UI icon manifest for its pinned version (183 icons, 55 KB) and both paths seed from it, with no outbound network. Sites installed earlier pick it up on their next System Update. Pulling a set newer than the pinned one stays the explicit remote sync.
- **`composer icons:vendor` refreshes that manifest, and `release:prepare` refuses to build without it.** A release whose bundled manifest is missing, or differs from the one published for the pinned UI version, now fails with the command to run; an unreachable CDN warns rather than blocking. The file stays committed because `composer require` installs from the GitHub tag and never sees the release artifact.
- **Content blocks accept any active icon, and an icon outside its context no longer disappears silently.** The context tag comes from the UI manifest and records where an icon is used in the product's own chrome, not what it depicts, so filtering content by it left 11 of 183 icons reachable — and `PublicIconPresenter` applied the same filter again at render, so an icon set outside the context rendered as nothing with no error anywhere. Rendering now shows any active icon, the picker leads with the context's own under "Suggested for this block" and offers the rest under "All icons", and validation matches what the picker offers. Navigation icons keep their curated rule.
- `GET /webadmin/api/icon-catalog?context=content` reports the full accepted set and marks the context's own with `suggested: true`, so API callers see the rule the admin enforces.

## 1.48.12

- **The starter page's logo now sits beside the heading as a brand lockup, instead of stacked above it.** 1.48.11 gave the mark its own block above the hero, which read as a separate element rather than as branding attached to the title.
- `hero` cannot express logo-left/title-right at all: its media renders either as a background (left and centered layouts) or as a foreground image on the *right* (split). `cluster` is the horizontal primitive — `display: flex` with `align-items` — so the first section is now a cluster holding the `image` and a `content_header`, with the action buttons in a cluster below it. `content_header` carries the copy: its eyebrow renders as the badge, its title as the `h1`, its subtitle as the intro paragraph.
- The cluster needs `wrap: nowrap`, because the header is a block-level flex item and default wrapping lets it claim the whole row — which drops the logo onto its own line, the very thing being fixed. `items_alignment: start` lines the mark up with the badge and heading instead of centring it against the full paragraph height. `database/content/starter/README.md` records this so the next edit does not reach for `hero` again.

## 1.48.11

- **The starter page's logo now renders at brand size instead of page width.** 1.48.9 and 1.48.10 bound the mark to the hero's split layout, whose media column is CSS-sized at `width: 100%` — on a desktop viewport that meant a 490px logo dominating the page. It is now its own `image` block above the hero, and the hero returns to its plain full-width copy layout.
- The `image` block renders at the file's own pixel size and exposes no display-size setting, so the shipped asset decides how large the mark appears: 96x96, next to the `3rem` the admin sidebar brand uses. `database/content/starter/README.md` records why the size lives in the file and warns against putting the mark back into a CSS-sized media slot.

## 1.48.10

- **The starter page's logo is now the canonical brand mark itself rather than a redrawn copy of it.** 1.48.9 shipped a PNG drawn with ImageMagick primitives transcribed from `public/cms/brand/logo-mark.svg`, because converting that SVG directly produced a blank image on a machine whose ImageMagick has no SVG delegate. The transcription followed the source geometry, but a brand mark has to be exact: measured against a correct raster of the same file it differed on 3.5% of pixels, all of it stroke-edge antialiasing. The shipped file is now a raster of `logo-mark.svg` itself on the same clearspace canvas.
- `database/content/starter/README.md` records where the artwork comes from and says to regenerate it from the SVG rather than edit the PNG, so the two cannot drift apart the next time the mark changes.

## 1.48.9

- **The starter home page now shows the product mark beside its headline, served from the site's own Media Library.** Native blocks bind images by media id, so shipped artwork has to become a real library record first: the blueprint names a file bundled beside it, `StarterMediaImporter` copies it onto the site's public disk once, and the installer binds the record to the hero. The record is an ordinary library entry — retitle it, replace the file, or delete it from the admin like any other image.
- **The logo is deliberately not hot-linked from a canonical URL on the docs site.** A remote image in content would make every visitor of a customer's public page issue a third-party request to us — a privacy exposure created on the customer's behalf — and would tie their home page to another host's uptime. It is also what `docs/ai-page-building-guide.md` already rules out; the sanctioned remote path there is `media/fetch`, which downloads into the library exactly like this does, minus the outbound network many production hosts do not have.
- The mark ships as a 1200x600 PNG with the brand clearspace baked in, rendered through the hero's documented split layout where the media column is CSS-sized, so the file carries retina detail without dictating its displayed size. PNG rather than SVG keeps the shipped asset off the SVG upload path the media pipeline disables by default (`allow_svg_uploads`).
- Nothing here can fail an install: a missing file or a read-only disk leaves the block without media, and the hero renders its copy alone.
- Like the other starter content changes, this reaches a site the next time starter content is written — a fresh install, or `php artisan webblocks:starter-content` on a home page that is still empty.

## 1.48.8

- **The starter page now leads with the product name at headline size instead of hiding it in the eyebrow.** "WebBlocks CMS" sat in the hero's subtitle field, which that block's contract renders as its small kicker, so the one thing a fresh install should announce was the least visible text on the page — Laravel and Craft both open on their own mark at full size. The brand takes the `h1` and the install confirmation moves to the kicker above it. No new blocks and no renderer change: each string simply moved into the field the hero contract already defines for that role, so an operator editing the hero sees the same two inputs as before.
- The starter hero's secondary action opens `cms.webblocksui.com` instead of the GitHub docs tree.
- Both changes are content, so they reach a site the next time starter content is written — a fresh install, or `php artisan webblocks:starter-content` on a home page that is still empty. A home page already filled by 1.48.6 or 1.48.7 keeps what it has; the two strings and the link are ordinary block copy that can be edited under Pages.

## 1.48.7

- **`php artisan webblocks:starter-content` fills an empty home page with the starter content, replacing a `db:seed --class=...` instruction that could not be typed into a hosting panel.** 1.48.6 added the starter page but only runs it during install, by design — System Update must never write content into a live site — so an install made before it keeps its empty home page and needs one manual run. The documented seeder invocation failed twice on a real panel's Artisan screen: first on the production confirmation, which a non-interactive runner cannot answer (`Command cancelled.`), then, with `--force`, on the class name itself, because the panel stripped the backslashes and Laravel went looking for `Database\Seeders\WebBlocksCmsDatabaseSeedersStarterContentSeeder`.
- The command takes no class name and nothing else that can be mangled by a runner's quoting. `--site=handle` picks the site on a multisite install; without it the primary site is used. It reports how many blocks it wrote, or why it wrote none.
- It deliberately skips Laravel's production confirmation, because the prompt cannot be answered by the runners it exists for and the guarantee it would protect is already structural: starter content is only ever written into a page that has no blocks at all. On a site whose home page already has content the command is a no-op that says so, it never touches any other page, and running it twice is safe.
- The seeder is unchanged and still what `db:seed` runs on a fresh install. `docs/installation.md` keeps it as the alternative, now with the `--force` a production install needs.

## 1.48.6

- **A fresh install now lands on a real home page instead of an empty one.** The installer always created the page and its layout slots but never any content, so `/` rendered an empty shell and the first page a new admin opened in the editor was blank; `php artisan db:seed` was worse, creating no page at all and letting `/` fall through to the host application's Laravel welcome view. The shipped starter page is a hero, a three-item feature grid, and a closing call to action — ordinary blocks with ordinary translations, written through the same `BlockPayloadWriter` the block editor and the Internal Content API use, so what lands in the database is what an editor would have built by hand and can be rewritten, reordered, or deleted like any other content.
- Starter content is written **only into a page that has no blocks at all**. It cannot overwrite existing content, it is a no-op on every run after the first, and System Update never adds or restores it. An install created before this release keeps its empty home page; to fill it once, run `php artisan db:seed --class="WebBlocks\Cms\Database\Seeders\StarterContentSeeder"` on an install whose home page is still empty.
- Nothing about the starter page can fail an install. A missing, unreadable, or malformed blueprint is reported and skipped, and a block type the catalog does not have skips that block and its children instead of aborting.
- The page content lives in `database/content/starter/home.json` under the `webblocks.cms.starter-content.v1` schema, using the same nested-children block vocabulary `docs/ai-page-building-guide.md` teaches. A `home.{locale}.json` beside it wins for an install whose default locale matches, and `WEBBLOCKS_CMS_STARTER_CONTENT_PATH` points the lookup at a product's own blueprints. `WEBBLOCKS_CMS_STARTER_CONTENT=false`, or `php artisan webblocks:install --skip-starter-content` for one run, installs the empty published home page instead.
- The home page's provisioning moved out of the install command into `DefaultHomepageProvisioner`, so `db:seed` and `webblocks:install` produce the same page. It resolves that page by its default-locale path of `/` rather than by "first published default page of this site" as the install command did — identical on a fresh install, but this code is now reachable from a seeder an operator can run on a live site, where the loose match would have adopted an unrelated published page and rewritten its slug and path to the home page's.

## 1.48.5

- **The Slide block's Overlay setting is now applied; it was stored but never rendered.** Slide shares the admin Background Media panel with section, hero, cta, card and content_header, and its `background_overlay` was saved like theirs — but the public slide template only read the image URL and Background Position. Position worked and Overlay did nothing, on the one block type whose whole job is text over a photograph. Slides cannot use the `wb-background-media` primitive the others use: their image is a real `<img class="wb-slide-media">`, and the darkening comes from `.wb-slide::after` painting `var(--wb-slider-overlay)`. Since the `wb-slider-overlay-*` classes only define that custom property, one on the slide now overrides the slider's overlay for that slide alone — no new CSS, and nothing needed from webblocks-ui.
- The field offers four levels where the slider pattern defines three, so `medium` resolves to `wb-slider-overlay-strong`. Moving off soft is a request for more cover; rounding down would leave the change invisible, which is the defect being fixed.
- **Slide's Overlay gains an "Inherit from slider" option, and it is the default.** An absent setting emits no class, which is what leaves the slider in charge — so a slide saved without touching Overlay must not write one. The shared partial defaulted the field to Soft, which would have quietly overridden a slider set to Strong on the next save of any slide. Inherit makes that state explicit, and slides now store an explicit `soft` (dropped elsewhere as the render default, but a real choice here, where the absent key means "inherit"). Section, hero, cta, card and content_header keep the previous four-option, soft-default behaviour exactly.

## 1.48.4

- **Saving one Shared Slot block no longer reindexes every page using that slot, once per written row.** 1.43.2 identified this cost and fixed it for the site import, which is a bulk writer that knows what to rebuild afterwards. Its commit already recorded the case left open: "a header block save reindexes all 22 published pages that use the slot." An editor save turns out to be a bulk writer too — it is never one row, but a block row plus up to four translation families plus whatever child blocks a builder field syncs, and each of those save hooks asks for the same full sweep. On a site header, used by every published page, a single "Save Block" therefore rebuilt the whole set several times over and took seconds on a site with almost no content in it.
- `PublicSearchIndexer::coalescing()` sits beside `deferring()` and needs nothing from the caller afterwards. The save hooks still name their targets — they are the ones that know which pages a write affects — but while a scope is open those targets queue instead of running, and the outermost exit rebuilds each page exactly once. A page queued for one locale and then for another is promoted to a full rebuild; a Shared Slot expands to its pages at flush time, after the slot assignments have finished being rewritten. `deferring()` still outranks it, so an import's rows do not queue up behind the rebuild it already does itself.
- `CoalesceSearchIndexing` opens one scope per non-cacheable request on the admin and Internal Content API route groups, and flushes in `terminate()` — after the response has been sent. Nothing in the controllers changed, so every write path gets this, including the ones that were worst off: a Shared Slot revision restore deletes and recreates the entire block tree.
- A write that throws discards its queue rather than flushing it: the rows never landed, so there is nothing to reindex. Verified against a three-page site sharing one slot — the same block save costs 6 index writes live and 3 coalesced, producing a byte-identical index.

## 1.48.3

- **Fixes a Chrome accessibility audit warning ("Incorrect use of `<label for=FORM_ELEMENT>`") on every asset picker paired with an external `<label>`.** The label's `for` pointed at the picker's hidden input, which Chrome correctly refuses to accept as a label target since it's never visible or focusable. The picker's trigger button ("Choose/Replace ...") now carries `id="{inputId}_open"` in all three layout branches, and the three affected callers (Sites form: favicon, social image; page translations form: og image) point at that instead.

## 1.48.2

- **Switching tabs on the Edit Site and Edit Page forms no longer discards unsaved edits.** Tab buttons were `<a href="?tab=...">` links: clicking one fired a full page navigation, so any edit made in the currently-loaded page — on the tab being left or any other tab — was gone once the new page loaded, even though the single "Save Changes" button implied one shared save across every tab. Tabs now switch client-side via the shipped `wb-tabs` widget (`data-wb-tab`/`data-wb-tabs`), so nothing reloads and nothing is lost.
- **The Sites form's disabled Delete button now explains why.** `SiteDeleteResult` already computes the blocking reason (primary site, last remaining site, linked contact messages); the button now carries it as a `title` instead of just sitting disabled with no explanation.
- **Bumps the pinned UI runtime to `v2.17.0`.** wb-tabs gains an opt-in `data-wb-tabs-field="<selector>"` attribute: the widget itself now writes the active tab's id into a declared form field on every change, closing the gap that pushed the Sites and Pages edit forms to each hand-roll their own `wb:tabs:change` listener just to keep a hidden "last active tab" input in sync (`page-assets.js`, and a short-lived `site-settings-tabs.js`) — both are deleted, replaced by the declarative attribute. `Site::normalizeAdminFormTab()` centralizes unwrapping the synced panel id back into `Site::ADMIN_FORM_TABS`'s bare keys, read by both `SiteController` and the form Blade.

## 1.48.1

- **Renaming a site's handle no longer strands its `site.css`/`site.js` override files under the old handle's directory.** `SiteAssetResolver` always resolves these by the site's *current* `handle` column and returns `null` on a miss, so a handle change left the public layout silently omitting the `<link>`/`<script>` tags with nothing anywhere telling the operator why — the files were still on disk, just under `public/site/{old-handle}/` instead of `public/site/{new-handle}/`. `SiteController::update()` now relocates the directory to the new handle (merging into any already-created `css`/`js` scaffold) before `ensureAssetDirectories()` runs.
- **The Sites list's "See details" modal rendered raw translation keys (`admin.site_details`, `admin.handle_label`, etc.) instead of text.** The modal partial inherited the Sites index's `$adminText`, which resolves keys under the bare `admin.*` namespace, but the modal's strings live under `admin.site_form.*` — every label fell through the translator's missing-key fallback. The partial now scopes its own `$adminText` to `site_form.`, matching every other Sites admin view that renders these strings.

## 1.48.0

- **The header slot's `wb-public-site-header` class is retired in favor of the fixed `wb-slot-header` class introduced in 1.47.0.** `public/cms/css/public.css`'s two rules — the header's bottom margin, and the extra top padding `main` gets when it immediately follows the header — now target `.wb-slot-header` and `.wb-slot-header + .wb-slot-main` instead of `.wb-public-site-header` and the mixed class/attribute selector that mismatch required. Since `wb-slot-header`/`wb-slot-main` render on every layout (Default, Article, and Docs alike), this spacing now also applies to the Docs shell's navbar header, which `wb-public-site-header` never reached before — a deliberate, previously-inconsistent gap being closed, not an incidental side effect. `header`'s `css_classes` catalog default is removed from Default and Article layouts (matching `footer`/`sidebar`, which never had one) since the class it used to carry is now the code-owned fixed class; an operator's own custom `css_classes` value for header is completely unaffected; it's still read and appended after `wb-slot-header` exactly as before. Also removes `.wb-public-footer .wb-footer-cookie-settings-link`, a rule left orphaned by 1.46.13's dead-code removal with no matching markup left anywhere.

## 1.47.0

- **`wbcms_page_layout_slots.html_classes` is renamed to `css_classes`, matching the admin-facing "CSS Classes" field it has always been.** Purely a naming fix — the admin form, its validation, and every code path that reads or writes this field behave exactly as before, just under a name that says what it's for instead of what HTML attribute it becomes. Existing installs pick this up via a package update migration (`database/migrations/updates`); already-installed sites are unaffected until their next System Update, at which point the rename runs once, guarded and reversible.
- **Every public slot (`header`, `main`, `sidebar`, `footer`, and any future custom slot type) now always renders a fixed `wb-slot-{name}` class, ahead of whatever `css_classes` holds.** This class lives in `SlotWrapperResolver`, not the database, so `catalog-repair --all` — which force-syncs `css_classes` to the package's catalog default on every run, silently discarding whatever an operator had set — can never remove it. An operator's own `css_classes` value is never replaced by this, only ever appended after the fixed class. Rationale: a site whose custom CSS depended on a slot's `css_classes` value (set once by hand, then silently reset by a later System Update) had no way to give itself a stable hook that survives every future sync; `wb-slot-{name}` is that hook, going forward, for every install.

## 1.46.13

- **Removes ~270 lines of unreachable "chrome" fallback markup from the header, footer, main, and sidebar slot partials.** Each of the four carried a legacy branch (`$slot['chrome']` populated, or `$renderShell` true) from an auto-generated site-chrome system that predates the current admin-managed Page Layout Slots (`html_classes`, block-driven rendering) introduced 2026-05-13. Nothing has populated `chrome` or passed `renderShell: true` since, so the branches — including a whole "site introduction banner" section, dropdown primary/mobile navigation, and a rich branded header — never rendered on any page; confirmed against every test, doc, and the plugin system's own extension contracts, none of which reference either mechanism. Each partial is now just its live, block-driven rendering path. `tests/fixtures/known-unstyled-classes.txt` drops the 22 class names that existed only in that dead code.

## 1.46.12

- **Restores a bare `wb-public-main` on the `main` slot's `html_classes`, without any width class.** 1.46.11 reverted 1.46.9's mistaken `wb-container wb-container-lg` by clearing `main`'s `html_classes` entirely on `Default Layout` and `Article Layout`, but `docs/block-ui-renderer-contract.md`'s compliance matrix and `docs/inventory.md` both document `wb-public-main` as an "acceptable" primitive for the main slot and the public layout shell, independent of the `Container` block's own `wb-container` width tokens — the same documented contract that already covers `wb-public-body` and the `wb-public-block` wrapper. `main`'s `html_classes` is `wb-public-main` again on both layouts; `SlotWrapperResolver`'s legacy fallback mapping matches. Width remains exclusively the `Container` block's job, unchanged from 1.46.11.

## 1.46.11

- **Reverts the `main` slot `html_classes` change shipped in 1.46.9.** That release added `wb-public-main wb-container wb-container-lg` to `main` on `Default Layout` and `Article Layout`, on the assumption that `main` was missing an intended width constraint. Further review found that was the wrong fix: `wb-container-lg` is the `Container` block's own width primitive (`Block::containerWidthClass()`), which the CMS's own documentation (`docs/getting-started.md`) says is deliberately opt-in per block — forcing it onto `main` itself would silently cap any `Container` block placed inside at `lg` width, defeating its `Width: Full` option. `html_classes` is also a fully admin-editable per-slot field (Admin -> Page Layouts), whose own in-product help text lists `wb-sidebar`, `wb-dashboard-main`, and `wb-stack` as the intended kind of value — structural/shell markers, not content-width tokens — and `CatalogRepairer` force-syncs the catalog default onto every installed site's row on each update, so shipping the wrong value here would have silently overwritten any operator's own customization, not just seeded fresh installs. `main`'s `html_classes` is back to unset on both layouts, matching its state before 1.46.9; the `SlotWrapperResolver` legacy fallback mapping is reverted to match. Sites that already picked up the 1.46.9 value via `catalog-repair` will clear it the same way, next time that command runs.

## 1.46.10

- **A System Update that failed after replacing package files (for example a broken `composer` step, or any later post-install failure) had no way to undo the file swap.** The existing automatic recovery only restores the database and uploads from the pre-update backup, never the package code itself, so a failed run could leave `vendor/fklavyenet/webblocks-cms` on the new version while migrations, cache clears, and catalog repair never ran — a silent code/schema mismatch. Rather than growing that backup to snapshot all of `vendor/` (mostly unrelated, unchanged dependencies, fully reproducible from `composer.lock`), `UpdateInstaller` now keeps the pre-update package directory it already sets aside during the swap (previously deleted immediately after a successful `rename()`) until the whole update flow verifies successfully. Any failure between the file swap and that verification now rolls the package back to its exact pre-update contents; a successful run clears the kept-around backup once it's no longer needed.

## 1.46.9

- **The public `main` slot never received its width-constraining container class on `Default Layout` and `Article Layout`, so page content rendered edge-to-edge instead of matching the header/footer width.** `PageLayoutCatalog`'s `main` managed-slot definition had no `html_classes` at all for either layout (every other slot — header, sidebar, docs' own `main` — has one), and the legacy fallback mapping in `SlotWrapperResolver` had the identical gap for pages without a managed-slot row. Both now carry `wb-public-main wb-container wb-container-lg`, matching the class pairing the (dead) hardcoded shell markup in `pages/partials/slots/main.blade.php` always intended. Existing sites pick this up the next time `php artisan webblocks:catalog-repair --all` runs (automatically on a successful in-app update, or manually once beforehand).
- **A System Update could fail invisibly at the dependency-install step and leave the site in a half-updated state: package files already replaced, but migrations, cache clears, and catalog repair never run.** `UpdateInstaller::installDependencies()` invoked a bare `composer` command, unlike every `php artisan` call in the same flow, which already resolves an absolute PHP binary to survive php-fpm's stripped subprocess `PATH`. Under php-fpm, that bare `composer` shim's own `#!/usr/bin/env php` shebang can't resolve `php` and fails with `env: php: No such file or directory` — after `applyPackage()` has already swapped in the new version's files. `UpdateCommandRunner` now runs Composer as `php <resolved-composer-entry-point>` instead of executing its shim directly, sidestepping the shebang lookup entirely; the entry point is found via an optional `WEBBLOCKS_UPDATES_COMPOSER_BINARY` config override, `PATH` lookup, or a few common install locations, with a clear error if none resolve.

## 1.46.8

- **Most admin screens resolved their UI copy through Laravel's global `__()` helper, which always renders in the single install-wide `app.locale` — an operator with their own `admin_locale` preference set (or the system admin locale) still saw every admin screen in the install's default language.** Only a handful of files (the block-edit modal, the admin shell/sidebar) had been migrated to the per-user `AdminLocaleResolver` + `CmsTranslator::admin()` path. Migrated the remaining ~36 admin Blade files — block types, pages, navigation, domains, and plugins screens and their partials — to resolve the authenticated admin's own locale instead, including a few "half-migrated" files that mixed both paths in the same template. Verified with `webblocks:admin-translation-audit --strict` against the existing baseline and the full test suite; no admin translation domain files still call `__('webblocks-cms::admin.*')` directly.

## 1.46.7

- **The shared `admin.partials.listing-filters` component (used by Pages, Media, Comments, and Navigation) had no date field type**, only a search box and dropdowns — a plugin wanting a date filter had nothing to reuse and no path but a one-off filter UI of its own. Adds `dates`, the same shape as `selects` (id/name/label/value, optional `submitOnChange`), fully optional so every existing caller renders unchanged.
- **Clarified that `PluginMenuItem::group()` is an exact-match label, not a picklist.** Two unrelated plugins independently reaching for the same generic-sounding but undocumented group name (`Content`) silently shared one sidebar heading — working as designed (identical strings merge), but nothing said so. Added a docblock explaining the shared-bucket-vs-dedicated-section behavior, and a `docs/plugin-system.md` example showing a large plugin surface claiming its own section by passing its own name.

## 1.46.6

- **The Video block's external-link fallback was unreachable: any URL that wasn't a recognized YouTube/Vimeo embed rendered a broken native `<video>` tag instead of the documented "Open video" link.** `$videoSource` was computed as `$assetUrl ?: ($embedUrl ? null : $safeUrl)`, so a plain webpage or any other unsupported host fell all the way through to `$safeUrl` and produced a `<video><source>` pointing at something that isn't a video file and never plays. `$videoSource` is now only ever `$assetUrl`: an uploaded Media Library file is the sole source for the native `<video>` tag, recognized YouTube/Vimeo URLs still render their iframe embed, and everything else now reaches the existing link fallback, matching the renderer's own documented contract.

## 1.46.5

- **The Internal Content API could read a site's resolved timezone in rendered content but never write it.** `PATCH /webadmin/api/sites/{site}/timezone` closes that, under `site-settings.write`: it accepts a standard IANA identifier such as `Europe/Berlin`, validated against the same set `Sites -> Edit Site` offers, and an empty value clears it back to the install-wide system timezone — the same convention the admin edit form uses, so the two surfaces cannot disagree about what blank means. A site's timezone is what anything resolving local wall-clock time for that site — a plugin's booking availability windows, for one — is interpreted against.

## 1.46.4

- **A new `Article Layout` gives a TOC block a sticky rail beside the article instead of stacking it inline.** The reference "On this page" panel sits in a two-column CSS grid next to the article body, not above it. TOC's own slot-scoped scan (1.46.2) means it has to keep living inside `main` to see `main`'s own headings, so the split happens at render time around the unmoved block: when `main` has a top-level `toc` block, `Article Layout` pulls that one block into `wb-settings-nav.wb-docs-rail`, wraps the rest of `main` in `wb-settings-body`, and wraps both in `wb-settings-shell wb-docs-layout` — every class already shipped in the pinned `webblocks-ui.css`, no new CSS. A page on this layout with no `toc` block, or a `toc` nested under something other than a direct child of `main`, renders identically to `Default Layout`; the split is entirely opt-in and non-breaking.
- **The Internal Content API could set a page's Page Layout only at creation, never on an existing page.** `PATCH /webadmin/api/pages/{page}/layout` closes that: it writes `public_shell` under `content.apply`, validates against the same active-layout allowlist the admin edit form uses, normalizes the legacy `dashboard` alias to `docs`, and — matching that same admin contract — does not mutate Page Slots on its own; call `sync-layout-slots` separately if the new layout defines slots the page does not have yet.

## 1.46.3

- **TOC rendered as `wb-link-list`, a plain link row with a hardcoded English "Jump to section" / "Jump to subsection" line that never went through any translator, whatever the site's locale.** It now renders `wb-section-nav`: a self-contained WebBlocks UI primitive with its own border, background, and padding — confirmed directly against the pinned CDN stylesheet, no dependency on the Settings Shell docs pattern it is normally seen inside.
- The `wb-docs-rail` / `wb-settings-nav` modifier classes are deliberately not added. Both belong to that two-column docs shell: they pin the element into a CSS grid position and cap it to viewport height with its own internal scrollbar, which would clip a long TOC sitting inline in a normal content flow instead of beside it.
- `wb-section-nav` is also what the shipped `WBSectionNav` module in `webblocks-ui.js` already keys off — the exact bundle the public layout already loads. It self-initializes on any `.wb-section-nav` it finds and live-updates `.is-active` / `aria-current="location"` on scroll, purely by matching a link's `href="#id"` against `document.getElementById(id)`. Using the right class name is the entire change: no JavaScript is owned by this package, and the hardcoded English chrome is gone with it — the primitive has no description line to begin with.

## 1.46.2

- **TOC scanned the whole page instead of the slot it was placed in.** A TOC in `sidebar` happily listed headings that actually live in `main` — the block described the page, not the slot it was in, which is why a TOC placed in `sidebar` on a `default`-layout page rendered at the bottom: sidebar comes after main in that shell, and TOC never noticed its headings weren't part of that content. `publicTocHeadingBlocks()` now scopes to the TOC's own `slot_type_id`.
- **TOC links could come back in the wrong order once an article had more than one section.** Heading order was a flat sort by `(sort_order, id)`, but `sort_order` is scoped per `(page, slot, parent)` everywhere blocks get created — two headings under different `section` containers both start counting from 0. TOC now walks its slot's block tree in real document order: each parent's own children first, each level sorted by `(sort_order, id)`.
- `toc` is a system block type now, joining `comments`/`rating`/`breadcrumb`/`navigation-auto`/`header-actions` — blocks whose content is derived from context rather than freely authored. It stays exactly as placeable and deletable on a page as before; `is_system` only makes the block *type* read-only in `Admin -> Block Types`.
- A TOC placed inside a Shared Slot now renders empty rather than doing anything unexpected: a Shared Slot's block tree lives on a separate hidden source page, never on the consuming page's own blocks, so there is nothing in scope to scan. Not a supported combination either way.
- Eight documentation lines describing the old "same page" scan are corrected to "same slot," including `docs/inventory.md`, the API-served AI authoring contract.

## 1.46.1

- **The Internal Content API could bind a page slot to a Shared Slot and never release it.** `source_type` was writable only on the session-authenticated admin route, so a token client could create a reference that nothing in its own API could remove: the slot stayed bound and the Shared Slot stayed undeletable until someone opened every consuming page by hand. `PUT /webadmin/api/pages/{page}/slots/{slot}/source` writes all three source types — `page`, `shared_slot`, `disabled` — so the field has one endpoint rather than a write path per value.
- `content.apply` covers it; `source_type=shared_slot` additionally requires `shared-slots.write` and delegates to the existing assign endpoint, so the compatibility rules, the human-only block guard, and the capability gate stay in one place instead of being restated.
- Detaching clears `shared_slot_id` and leaves page-owned blocks untouched: `page` renders them again and `disabled` keeps the slot wrapper with nothing inside. Discovery and the OpenAPI schema advertise the endpoint, and the assign endpoint gained the `x-required-capability` it had been missing.

## 1.46.0

- **CMS core no longer knows the name of any plugin.** Two first-party plugins were wired into core by handle: `PluginRouteRegistrar` registered nine WebBlocks UI Manager admin routes itself instead of loading the plugin's route file, `PluginRouteFallbackController` carried a method per plugin naming its controller classes and restating each route's permission check, and `routes/public.php` hardcoded the whole WebBlocks Commerce storefront. Every one of them named a class in the plugin's package, so a plugin that renamed its own namespace — as both have now done — turned its own pages into 404s with nothing in core to say why.
- The plugin route fallback is generic. It still exists for the two cases that need it, a cached route table and a provider left over from the version an update replaced, but it now rehydrates the plugin's own routes and runs whichever one matches, under that route's own middleware. An authorization rule is enforced where it is declared instead of being copied into core, and the fallback serves every plugin rather than the two it had been taught.
- **`routes.webhooks`: a plugin can own a third-party callback.** A payment gateway calling back after a customer pays carries no session, so it cannot carry a CSRF token, and it is not a bearer-token client either — it fits neither `routes.public` nor `routes.api`. Previously the only way to a working callback was for core to hardcode the endpoint and add its path to a global CSRF exemption list. A plugin now declares the file, and the registrar drops the check from that group alone: same prefix, same throttle, same `install.required`, CSRF and nothing else relaxed.
- The exemption is attributable. It is applied by removing the middleware from one route group rather than by adding paths to a list, so it covers the routes the plugin declared and cannot widen to a path that merely resembles them.
- Verifying the caller stays with the plugin. A webhook is a notification, not proof of payment, and core is not in a position to check a signature it has no key for.
- Removed the `commerce` reserved prefix from the redirect-manager catch-all protection and from the reserved page-slug segments. Reserving a first segment for a plugin that no longer has one there only stopped someone publishing a page at `/commerce`.
- **`plugins` is a reserved page-slug segment now, and was not before.** Every plugin public route mounts under `/plugins/{handle}`, and public pages are served by a dynamic `{slug}` route — so a page published at `/plugins/anything` and a plugin's own endpoint were two routes competing for one path, with the winner decided by registration order. The segment that was reserved was the one belonging to a single plugin's storefront; the one shared by every plugin was not.
- **A Shared Slot tells you which pages it serves.** The delete confirmation reports that fifteen page slots still reference the slot and stops there, which makes the block visible but not actionable — you know the delete is refused and have no way to reach the fifteen pages standing in the way. The list grows a Usage column with a page count, and the Actions column an icon opening a modal that lists the consuming pages: title linked to Edit Page, path, which slot it fills, and page status. The action is inert at zero rather than opening an empty modal, and the blocked delete warning now points at it.
- The slot's own hidden source page is filtered out of that list — it has no slot source an operator can change — and the page slots are eager loaded onto the paginated collection, since every row renders its own modal and this would otherwise be a query per row.

## 1.45.7

- **Deleting a Shared Slot asked through the browser's own dialog.** "Delete this Shared Slot?" — no name, no handle, and no hint that the server refuses the delete while a page slot still references it, which you found out by pressing OK and landing on a validation error. Ten destructive actions now open the CMS confirmation modal and name the record they are about to act on: Shared Slot delete and revision restore, block delete from the list and from the page outline, locale delete, navigation item delete, page revision restore, backup restore, and restore-history delete.
- The Shared Slot delete modal reports how many page slots still reference the slot and disables its own submit when there are any, so the block the controller already enforces is visible before the click rather than after it.
- The backup restore acknowledgement moved into the modal. It is the checkbox the server actually validates, so it belongs in the form that posts rather than sitting on the page behind a `confirm()` that duplicated the same question.
- `form-actions` dropped its `deleteConfirm` prop. Nothing in the package passed it, and leaving it in place would keep a supported route back to `window.confirm`.
- `DestructiveConfirmationModalTest` sweeps every Blade view for a `confirm(` call — that is what caught the two Shared Slot revision screens after the delete itself was already done — and asserts each converted screen registers the modal id its trigger targets, since a trigger whose modal is never pushed is a dead button.

## 1.45.6

- **The capability badges on CMS API Tokens were server-rendered and then never updated.** Ticking every box in a group left its badge reading `0/5`, and the `8/28 selected` total beside the Capabilities heading stayed at whatever the page loaded with — so the only way to know what a token was about to get was to open all six accordions and count. `api-token-capabilities.js` recomputes each group badge and the total on every change, in the Create Token card and in each Edit API Token modal, through one delegated listener so modals in the overlay root are covered too.
- Create Token now starts with every grantable capability ticked instead of just the eight in Page building. Building a token meant opening each accordion and ticking its boxes one at a time; unticking what a token must not have is the shorter path. Publishing / destructive actions and System safety start ticked as well — they carry their "grant only when explicitly needed" copy, and that is now a prompt to untick rather than to tick.

## 1.45.5

- **A plugin could declare a block type and still have no way to place it.** Block pickers read the `wbcms_block_types` catalog, and `PluginBlockCatalog` only ever filtered that list — it hid a plugin's blocks while the plugin was disabled, but nothing anywhere wrote the row in the first place. A plugin could ship a block, both its views, and its render path, and the block simply never appeared in any picker. `PluginBlockTypeCatalogSyncer` writes the rows now, and `PluginRuntimeRefresher` runs it, so install, enable, disable, setup, and update all end with the catalog matching what the installed plugins declare.
- Rows are written for every installed plugin, enabled or not. Placement is already gated by the catalog filter, so a disabled plugin's block still stays out of pickers — and a block already placed on a page keeps a type row to resolve through instead of losing it the moment its plugin is switched off.
- A re-sync corrects what the plugin owns (`name`, `description`, `source_type`, `is_system`, `is_container`) and leaves `category`, `sort_order`, and `status` as the operator left them. Repairing a catalog should not silently republish a block someone set to draft to hide, or drag it back out of the tab they moved it to.
- `webblocks:catalog-repair --plugin-block-types`, included in `--all`, repairs installs that predate this — which is every install with a plugin block on it today. Updates already run `--all`, so the rows appear without an operator doing anything.
- The syncer refuses to write over a shipped core slug. A namespaced plugin handle cannot collide with one by accident, but "cannot happen" is a poor reason to let a malformed plugin rewrite the Hero block.

## 1.45.4

- **Every export failed validation.** The page picker always submits one empty `page_ids[]`, so that ticking nothing arrives as an explicit empty selection rather than as no selection at all — which means the whole site. That marker is not an id, and it hit `page_ids.*|integer`: "The page_ids.0 field must be an integer", on every export, whatever was ticked. The marker is filtered before validation now, and an empty selection still reaches the exporter as an empty selection.
- The tests around the picker read source strings and never submitted the form, which is exactly why they stayed green. `SiteExportRequestTest` validates the payload the form actually sends, including the marker, a real id, and rubbish that must still be rejected.

## 1.45.3

- **Every checkbox in the admin had been unstyled.** The views wrote `wb-checkbox` in 17 files; the UI's primitive is `wb-check`, one of `wb-check` / `wb-radio` / `wb-switch`, and `wb-checkbox` matches no rule anywhere. A class name that matches nothing fails silently — the markup renders and the page looks nearly right — so it took a table of seventy of them collapsing into wrapped text for anyone to notice. Renamed, and the CMS is no longer inconsistent with itself: it already used `wb-check` correctly in two places, and Herne Panel has used it in 17 all along.
- `UiClassContractTest` now fails on any `wb-` class in an admin view that no stylesheet defines. The admin loads the UI from a CDN, so it compares against `tests/fixtures/webblocks-ui-classes.txt`, a snapshot of the pinned runtime's class names; moving `Herne::UI_VERSION`'s counterpart `WebBlocks::UI_VERSION` without regenerating the snapshot fails too, so the check can never silently drift from the stylesheet the admin actually loads.
- The 56 class names still matching nothing are frozen in `tests/fixtures/known-unstyled-classes.txt` as a baseline that may only shrink — a name that becomes defined, or stops being used, fails the test rather than lingering. Some are probably JS hooks rather than style hooks; each needs its own look, which is not this release.

## 1.45.2

- The export page picker gives its list the room. The selected count moved up beside the heading, the standing paragraph under the table is gone — the archived-pages rule is visible in the table as unticked rows — and the media hint is a field hint rather than a paragraph. Eight rows are in view instead of five.

## 1.45.1

- **The export page picker was unreadable.** It stacked `wb-checkbox` labels, and that class has no styles anywhere in the product — seventy of them collapsed into wrapped inline text that ran over the fields below. The picker is a `wb-table` now: a row per page, a column each for the tick, the title, the status badge and the path, in a scrolling card. `wb-scroll-y` and `wb-badge-sm`, also used and also undefined, are gone with it.
- Both export screens show the same picker. Export / Import had it and Sites did not, which is the kind of difference nobody notices until an export from one of them quietly contains something the other would have excluded. The page list moved into `ExportablePages` so there is one source for both.

## 1.45.0

- **A site transfer carried the site's content and almost none of the site.** The export wrote seven fields for the site row — id, name, handle, domain, is_primary and timestamps — so five of the nine Edit Site tabs never travelled. An imported site arrived with no brand palette, no theme preset, no SEO defaults, no head code, no contact address and no branding, then rendered in the product default theme while the admin showed a complete import. All of those fields are exported now, and the importer applies them.
- Favicon and social image are media ids in the source install, and the site row is written before its media exists, so they are rebound in their own `site_branding` phase once the asset map is populated.
- **The export shipped `site.css` and `site.js` and nothing else under the site's directory.** A stylesheet declaring `@font-face` therefore arrived without a single font file. The whole of `public/site/{handle}` travels now, bounded by `webblocks-cms.export.site_asset_max_bytes` (50 MB by default) so an oversized directory stops the export with a message instead of producing a package nobody can upload.
- Copied stylesheets are rebased onto the importing site's handle. Site assets reference each other by absolute public path, so a site imported under a new handle previously had every font present on disk and 404 in the browser — indistinguishable from not shipping them. Only `.css` and `.js` are rewritten, and only when the handle actually changed.
- The two-filename allowlist behind that restriction existed in **three** places — the export builder, the archive builder and the importer. Generalising two of them was not enough; a test now asserts none of the three restricts site assets by filename.
- **A failed site-asset write no longer reports success.** `mkdir()` and `file_put_contents()` had their results discarded and the file was counted as copied either way, so a site could import with none of its assets on disk and nothing anywhere saying so. Both are checked, and an entry whose path the importer cannot resolve raises instead of being skipped.
- **The export screen lets you choose which pages go into the package.** Archived pages start unticked, with All / Published only / None shortcuts. On a site built through staged updates the discarded drafts are the bulk of the package: on this project's own site, 49 of 74 pages carried 73% of the blocks and translations, and excluding them took the import from 28.8s to 11.3s. Omitting the selection entirely still exports the whole site, so the CLI and the API are unchanged.

## 1.44.0

- **A site import now runs as resumable steps with a progress modal, instead of one transaction inside one request.** The old shape had no way to report progress even in principle: all fifteen phases ran inside a single `DB::transaction`, so nothing was visible to another connection until it committed and the import record read `validated` from start to finish. Working and hung looked identical, and behind Nginx a long import ended as a bare 504 with the transaction rolled back and the copied media left orphaned.
- Run import opens a modal that drives the import a step at a time and reports the phase it is on with real row counts — "Importing blocks, 12480 / 28607 (43%)". It uses the `wb-progress-bar` primitive from WebBlocks UI, so it adds no CSS of its own.
- Every step commits. Closing the tab pauses the import where it is rather than destroying it: the import record carries its own cursor (`resume_phase`, `resume_offset`, `resume_state`) and the screen offers **Resume import** or **Discard the partial site**. `site:import --resume={id}` does the same from the CLI.
- `SiteImportPlan` holds the phase order, and two positions in it are load-bearing. `domains` runs **last**: a site is only reachable through a `SiteDomain` row and `Site` has no published flag, so an interrupted import is never addressable on its real hostname. `search_index` runs after all content and before domains, as the one pass that builds the index now that writes defer it.
- The fifteen phase methods are unchanged; the step runner calls them with a sliced payload. Two passes had to be split out because they are whole-map work that a slice must not repeat: linking block parents (`wireBlockParents`), and normalising canonical translation storage. The second one was a real defect found in testing — run per slice, it gives every block still awaiting its translation a placeholder canonical row, and the next slice then collides with that placeholder on the `(block_id, locale_id)` unique index.
- Discarding a partial import deletes its site through `SiteDeleteService` — the one audited deletion path, blockers included — plus the media rows and copied files, which are install-scoped and would otherwise be collected by nothing. The package stays and can be imported again.
- Verified against this project's own 22-page site (7726 blocks, 4526 text translations): 28.8s uninterrupted, and an import killed at `blocks` offset 4000 and resumed in a fresh process produced an identical result — same block, translation, navigation and search-index counts, with all 22 index rows matching the hand-built site byte for byte.

## 1.43.2

- **Importing a site was quadratic, and the cost was the search index.** Every block, translation and slot save reindexes the whole page it belongs to — correct for an editor changing one block, ruinous for a bulk writer. Importing this project's own site (7726 blocks and 4526 text translations over 72 pages) therefore walked each page's full block tree once per row it wrote, and took **7m54s of pure CPU**. Behind a web request that is a 504 with a rolled-back transaction and orphaned media files; the import never had a chance to finish.
- `PublicSearchIndexer::deferring()` runs a bulk write with the reactive path switched off. It is a nesting-safe counter released in a `finally`, so a failed import resumes indexing. Only `refreshPage()` and `refreshSharedSlot()` — the entry points the ten model save hooks call — honour it. `rebuild()` and `rebuildPage()` never do: they are what the bulk writer calls when it is finished, and gating them would leave the imported pages out of the index permanently.
- The site import now defers its transaction and rebuilds the index **once after the commit**, so the work reads committed rows and stays outside the transaction. Same import: **28s**, 16.6x faster, with an index that is byte-identical to the incrementally built one (22 rows, same 215592 characters of content, zero rows differing in either direction).
- The gate lives in the indexer rather than in the models, so no save hook changed and any future bulk writer gets the same escape.

## 1.43.1

- Give each site its own timezone. `System Settings` held one timezone for the whole install, which is wrong for a multisite install whose sites run in different regions and blocks anything time-bound from being correct. Sites now carry a nullable `timezone` column with a picker on the Edit Site form; blank keeps following the install.
- Read it through `Site::resolvedTimezone()`, which returns the site value or falls back to the system setting. The raw `timezone` attribute stays null when unset, so "follow the install" remains distinguishable from an explicit choice that happens to match the install default — a distinction that matters when the install timezone later changes.
- Ship the column in all three migration paths: the alter migration for source-maintained installs, the `updates/` ensure migration for System Updates consumers, and the fresh-install schema.

## 1.43.0

- Let a plugin own a visitor-facing surface. `PluginDefinition::publicRoutes()` (manifest key `routes.public`) mounts a plugin's public route file under `/plugins/{handle}`, with names under `webblocks.plugins.{plugin_handle}.public.*`. The prefix is one reserved first segment shared by all plugins, so a plugin endpoint cannot shadow a page slug — public pages are served by dynamic `{slug}` routes, and an unprefixed plugin route would compete with real content. Until now the only way to ship a public plugin endpoint was to hardcode it in core `routes/public.php`, which is how the commerce bridge got there.
- Apply the public middleware stack in the registrar rather than trusting each plugin to assemble it: `web`, `install.required`, and a `plugin-public-routes` throttle default of 60/minute per IP and plugin, configurable through `webblocks-plugins.public_routes.rate_limit_per_minute`. A plugin can add a stricter per-route throttle and both apply. CSRF stays on — these serve browser forms, not the bearer-token clients `routes.api` serves.
- Honor the `admin_view` and `public_view` a plugin block type declares. Both were already parsed off the manifest and then ignored, so a plugin block could only render by mirroring the core view directory layout and guessing the filename that matches its catalog slug. `Block::publicRenderView()` and `Block::adminFormView()` now consult the plugin block registry first; a declared view that does not resolve falls back to the old convention instead of throwing mid-render.
- Memoize the enabled plugin block lookup in `PluginBlockCatalog`. `PluginRegistry::enabled()` deep-clones every definition it returns, which is affordable on an admin screen and not on a per-block render path. `PluginRuntimeRefresher` already forgets this singleton, so the memo cannot outlive a plugin install, enable, or update.
- Document the appointments plugin design in `docs/appointments-plugin-plan.md`. Booking ships as a plugin, not core: scheduling is a business domain, and the plugin boundary already forbids domain capabilities in core. The two extension points above are the first two phases of that plan.

## 1.42.8

- Fold the Update history accordion into the System Updates card. It used to render outside `section.wb-card` as an unframed strip orphaned below the card; it is now the last element of the card body, so the screen reads as one card in order: preflight → state → release notes → Update history. Run-log `wb-modal`s stay outside the card so overlays keep their own stacking context.
- Move the failing-preflight callout to the top of the card body, matching the order the shared `webblocks-publisher-client` view already used — the two System Updates surfaces in the fleet no longer disagree about where the pre-run warning goes.
- Stop rendering the history accordion when no runs are recorded. A fresh install used to show an empty `Update history (0)` accordion whose only content was "No update runs have been recorded yet."; the accordion is now omitted entirely and the `updates.no_update_runs` string is retired from all three locales.

## 1.42.7

- Move the pinned WebBlocks UI runtime from `v2.16.2` to `v2.16.3`, where `WBUpdateIndicator` reports a failed status fetch — `console.warn` naming the endpoint, plus `data-wb-update-indicator-state="error"` on the element — instead of swallowing it in an empty `catch`. A 404, a redirect to a login page (which arrives as 200 HTML and throws on parse) and a genuine "no update available" used to be indistinguishable: the navbar badge simply never appeared.

## 1.42.6

- Fix the navbar "update available" badge outliving the update it advertised. The badge is cached for an hour, and while the update controller already cleared it on a successful run, a request served between the apply and the worker recycling still runs the pre-update code: it re-checks, still reports itself as the old version, and re-caches the finished update for another hour. `AdminUpdateIndicator` now drops and recomputes a cached `update_available` whose version is not newer than the installed one, using the same lenient normalization as the update check (`v1.2.3` == `1.2.3`). This is the port of the guard shipped in `webblocks-publisher-client` 1.0.4 — the CMS runs its own engine and does not consume that package, so it needed the fix separately.

## 1.42.5

- Fix the Appearance tab, which 1.42.4 shipped broken: the font-picker setup used a block `@php`, Blade left the opening directive in the compiled view as text, and the tab rendered with `$fontOptions` and `$installedFontCount` undefined. The assignments use the inline `@php(...)` form now.
- Add `SiteFormCompilesTest`, which compiles the site form, the theme tab and the admin layout and fails if any directive survives compilation. The structure tests read the Blade as text, so nothing in the suite had ever compiled it.

## 1.42.4

- Render the brand colour fields as a fixed swatch beside a hex field. They carried `wb-input`, which stretched the native colour well to full width and made it read as a rule above the box rather than a colour control.
- Turn the typeface fields into pickers. `InstalledFonts` reads the `@font-face` families out of the site CSS asset and offers those alongside the system stacks that need no download; a hand-written stack stays available behind a Custom option. A site that loads no webfonts now says so and points at Assets instead of expecting the operator to type a family from memory.
- Move Assets before Appearance in the Edit Site tab strip, matching the order the two are used in: declare the faces, then choose them.
- Make the theme preview follow the preset select. The admin layout did not load `cms/css/public.css`, so the `[data-wb-public-theme-preview]` blocks that colour the preview never applied and changing the preset showed nothing. The layout loads it now — every rule in it is scoped to `[data-wb-public-theme]`, `[data-wb-public-theme-preview]` or `.wb-public-site-header`, none of which exist in admin chrome — and the preview island, its badge and its body-hook line update on change.

## 1.42.3

- Fix the Edit Site tab strip: 1.42.2 left the brand palette panel without its closing `</div>`, so every panel to its right nested inside it and never appeared, and `SiteController` kept a second literal tab list that never learned the new key, so the tab itself fell back to Site. Both are gone.
- Merge the brand palette and the theme preset into one `Appearance` tab, in the order the layers apply: preset first, palette below it overriding the roles it covers. Two separate tabs hid that relationship — a preset change looked like it did nothing when the palette was quietly overriding it. Branding keeps the site's name, tagline, favicon and social image.
- Make `Site::ADMIN_FORM_TABS` the single source for the strip; the controller whitelists against it and the form renders from it, so a new tab can no longer render a panel the controller refuses to select.
- Extend `SiteFormStructureTest` with the two guards that would have caught the regression: every panel must close its own markup, and the controller must not carry a second literal tab list.

## 1.42.2

- Give the brand palette its own `Brand palette` tab in `Sites -> Edit Site`, next to Branding. It shipped as a second card inside the Branding tab, where operators looked for it in the tab strip and did not find it. Branding keeps the site's name, tagline, favicon and social image; the palette tab owns the four brand colours and two font stacks. The tab is labelled in full rather than "Brand" so the two cannot be confused.

## 1.42.1

- Keep the brand palette card inside the branding tab of `Sites -> Edit Site`. It shipped in a second `wb-tabs-panel` carrying the branding tab key, leaving ten panels for nine tab buttons; the tab strip owns one panel per key, so a duplicate is a structural defect even where the browser renders both. Installs that still show the old form after updating are serving compiled Blade views from cache — clear them with `php artisan view:clear`.
- Ship the fifteen brand palette strings in Turkish and German; 1.42.0 added them in English only, so non-English admins fell back to English labels.
- Add `SiteFormStructureTest`: tab buttons and panels must line up one to one, the palette fields must live inside the branding panel, and every shipped locale must carry the palette strings.

## 1.42.0

- Add the site brand palette: `Sites -> Edit Site -> Branding` now takes four brand colours (accent, secondary accent, page background, text) and two font stacks (heading, body), and derives the rest of the public theme from them — hover/active states, soft tints, borders, muted text, surface layers, a readable foreground for every filled surface, and the complete dark-mode palette. Derivation is a pure function (`WebBlocks\Cms\Support\Theme\BrandPalette`) using sRGB mixing and WCAG relative luminance, so operators no longer hand-write `--wb-public-*` overrides into the site CSS asset or maintain a second palette for dark mode. Empty fields keep the selected public theme preset, so presets and partial palettes both keep working.
- Emit the resolved palette as one `<style id="wb-public-brand">` block in the public head, after `cms/css/public.css` and before the site CSS asset, so presets stay the base layer and hand-written site CSS can still override. The block also introduces `--wb-public-inverse-surface` / `--wb-public-inverse-text` for filled bands.
- Accept the six brand fields on `PATCH /webadmin/api/sites/{site}/branding` under the existing `site-settings.write` capability, and return a `brand_palette` object with the derived light/dark/font tokens plus the accent contrast ratio so operator tools can preview values without reimplementing the maths. Colours must be hex; font stacks are restricted to font names, quotes and commas so a stack cannot escape its declaration.
- Warn in the admin when the accent colour falls below a 4.5:1 contrast ratio against the page background instead of blocking the save, matching the existing site CSS mode-awareness warning model.

## 1.41.5

- Align the auth screens with the fleet's binding canonical string set (§5b pixel parity, 2026-07-26) in all three locales: subtitle becomes "Sign in to your :product account.", "Remember me" replaces "Remember this device", the forgot link gains its question mark, "Create an account" replaces "Create one", the forgot screen reads "Forgot your password?" and the reset screen "Choose a new password" with a dedicated "New password" label and a "Reset password" submit. Turkish strings also lose several long-standing i/ı typos. `guest.css` drops its wb-auth brand/mark sizing rules (geometry is owned by WebBlocks UI) and keeps only the temporary `wb-auth-brand-mark-on-surface` color rule until the UI ships that class.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## 1.41.4

- Align the auth screens with the fleet-standard §5b contract: password visibility on the login and reset screens now rides the WebBlocks UI runtime toggle (`data-wb-password-toggle`) instead of a hand-rolled inline script in the guest layout (script removed — the guest layout ships no JS of its own), the reset screen's two password fields gain the toggle, and failed inputs now carry the `wb-input-error` class alongside `aria-invalid`, so they actually render the error border. Markup only; no behavioral change to authentication.

## 1.41.3

- System update checks now include the runtime PHP and Laravel versions in the existing anonymous telemetry ping, so the Publisher's fleet analytics can chart the PHP distribution across installs. Runtime versions only — no domains, paths, or user data are sent, and telemetry can still be disabled with WEBBLOCKS_TELEMETRY=false.

## 1.41.2

- Fix the System Updates "What's new" panel repeating a release's description up to three times. A single-bullet release no longer re-renders its summary as a "Highlights" item, and the raw release-notes text is no longer shown in addition to the structured summary and groups. Rendering only; once installed it also cleans up how earlier releases display.

## 1.41.1

- Simplify the admin topbar user menu to an avatar-only trigger: the operator's name and email no longer render inline in the bar; clicking the avatar opens the same dropdown with profile (when available) and logout. Aligns the CMS operator admin with the fleet-standard topbar contract; no functional change.

## 1.41.0

- Make in-app System Updates a one-click flow: a single `Update to X` action now downloads, backs up, applies, migrates, and verifies the release in one run. The old two-phase prepare/continue/cancel flow and its separate pre-update backup download step are retired, along with the `system/updates/{continue,cancel,support-report}` admin endpoints and the super-admin support-report download.
- Take an automatic pre-update backup before every apply and automatically restore it when the apply fails. A failed-then-restored run is recorded with the new `restored` run status (`Failed, backup restored`); if the restore itself fails, the run stays `failed` with both error trails in the run log. Pre-update backups remain available on the Backups screen for manual download and restore.
- Redesign the System Updates screen to the fleet-standard v3 layout: a single status card, a folded "What's new" area with a per-version changelog accordion built from cumulative update-server changelog entries, one-click update with a backup note, and a non-dismissible interstitial that polls the update indicator until the updated app answers again.
- Add a server-backup advisory line next to the update action that links to the Backups screen, so operators are nudged to take a fresh full backup before a major update.
- Retire the source-maintained apply mode: `WEBBLOCKS_UPDATES_MIGRATION_STRATEGY` is ignored, in-app updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms`, and package update migrations under `database/migrations/updates` always run when present. Source-maintained maintenance checkouts update through git/Composer, not the in-app updater.
- Reduce preflight to the checks that matter and surface them on the screen: database connection, ZIP and sodium extensions, PHP/Composer/process execution, application-root and workspace write access, and free disk space. The update action is available only when every check passes; the old blocker state machine is gone.

## 1.40.27

- Clean the drift out of `admin.css` (and `guest.css`) the same way `public.css` was cleaned, against WebBlocks UI 2.16.x:
  - Delete the local `.wb-action-group`/`.wb-table-actions` copies (shipped since 2.15/2.16.2, including the new inline-form rule) and the local `.wb-btn.is-busy` spinner block — the busy state plus its `data-wb-busy` submit-lock behavior now ship in UI (`WBBusySubmit`); the admin binder delegates to it for dynamic rebinds.
  - Delete the `wb-navbar-breadcrumb`/`-wrap` glue and its markup classes — the shipped breadcrumb base already handles shrink/wrap, and UI 2.16.0 added the missing long-word breaking on `wb-breadcrumb-link`.
  - Reduce the `#wb-overlay-root` patch block to the deliberate CMS backdrop policy only; the duplicated shipped pointer-events rules are gone, and the drawer `display:none` deviation is replaced by UI 2.16.1's accessible closed-drawer hiding (restoring the slide animation in admin).
  - Drop the dead `--wb-primary-hover`/`--wb-accent-contrast` tokens from the brand remaps; the one consumer now reads the shipped `--wb-accent-on`.
  - Bump the pinned UI version to v2.16.2.

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
