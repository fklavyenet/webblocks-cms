---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/hosting-capacity-validation
cms_title: Hosting Capacity Validation
cms_layout: docs
cms_source_id: webblocks-cms:docs/hosting-capacity-validation.md
---

# Hosting Capacity Validation

This protocol explains how WebBlocks CMS establishes defensible PHP memory, execution-time, upload, and disk requirements. A value becomes a published minimum only after the complete acceptance workload passes repeatedly on a constrained production-like server. A hosting control-panel label or an idle installation is not evidence.

Use this protocol when qualifying a hosting plan and whenever a CMS release materially changes media processing, backup/restore, import/export, installation, or update behavior.

## What the result means

Capacity results are workload-specific:

- the **core profile** covers installation, administration, page editing, publishing, and public rendering without GD transformations or package-native updates;
- the **media profile** adds the reference image workload and GD transformations;
- the **operations profile** adds application backups, restore rehearsal, site transfer, and package-native System Update; and
- the **traffic profile** measures concurrency separately from the single-request runtime floor.

Do not collapse these profiles into one unexplained number. For example, a core-only shared host can be valid while failing the operations profile, provided deployment, update, and backup are handled externally.

## Controlled test environment

Run the candidate release artifact in a fresh Laravel 13 consumer using the production PHP SAPI, web server, MySQL 8.0, filesystem type, and process restrictions of the target host. Do not use the package's SQLite testbench result as hosting evidence.

Record:

- CMS, Laravel, PHP, database, web server, and operating-system versions;
- CPU allocation or throttling, PHP worker count, and storage type;
- PHP `memory_limit`, `max_execution_time`, `upload_max_filesize`, and `post_max_size` for both web and CLI;
- enabled extensions and GD codec support;
- application, database, media, and backup sizes before each run; and
- cold/warm cache state and OPcache configuration.

Build a resource matrix instead of changing several limits at once. Test memory at descending candidate limits such as 512, 256, and 128 MiB. Test request time independently at values offered by realistic providers. The lowest passing cell is a candidate, not yet a published minimum.

## Reference dataset

Keep a versioned, checksum-recorded fixture outside the public release package. It should contain no customer data and should create at least:

- 3 sites, 3 locales, and representative navigation and Shared Slot relationships;
- 250 published pages and 50 drafts distributed across the native block types;
- 5,000 blocks, including nested layout, gallery, navigation, search, and form content;
- 500 media records with at least 2 GiB of total originals for the operations profile;
- JPEG, PNG with alpha, and WebP reference images, including portrait, landscape, small-image, and high-pixel-count cases;
- enough revisions, contact messages, and search records to exercise real listings; and
- a database dump and site-transfer package generated from that same state.

The fixture manifest must record row counts, byte sizes, image dimensions, MIME types, and SHA-256 checksums. Changing the fixture starts a new benchmark series; do not silently compare results from different workloads.

## Acceptance workload

Run each applicable scenario once as warm-up and then five measured times. All five measured runs must pass.

### Core profile

1. Install the release into a clean Laravel consumer and run the CMS install flow.
2. Sign in, open the dashboard, and paginate and filter the largest page and media listings.
3. Create, edit, preview, publish, and publicly request a representative nested page.
4. Render the home page, a content-heavy page, search results, and localized navigation.
5. Run the application's normal config, route, view, and application cache clears.

### Media profile

1. Upload files immediately below the CMS 50 MiB validation ceiling through the real HTTPS web path.
2. Upload each reference raster image and generate every system variant from a cold transform cache.
3. Regenerate the full reference media set in the documented bounded workflow.
4. Confirm fallback behavior for an unsupported codec without a fatal error or incomplete output.

High-pixel-count raster images matter more to GD memory than compressed file size. The media fixture therefore records dimensions as well as bytes. A 50 MiB upload limit does not prove that every 50 MiB compressed image can be transformed within a particular memory limit.

### Operations profile

1. Create and download a full application backup from the reference dataset.
2. Restore it into an isolated target and verify database and public-media checksums.
3. Export and import the reference site with files included.
4. apply the candidate release through package-native System Update, including its mandatory pre-update backup and rollback workspace; and
5. run cleanup and verify that retained backups and current media transforms remain intact.

Destructive restore testing belongs on an isolated copy, never on the production installation.

### Traffic profile

Run a separate load test against cached and uncached public pages plus authenticated admin reads. State the request mix, concurrency, duration, worker count, database size, and cache driver. Report throughput and latency; do not convert a concurrency result into a PHP memory minimum.

## Measurements and pass criteria

Collect server-side evidence for every run:

- exit/HTTP status and application log errors;
- peak PHP memory for CLI work and peak worker memory or provider telemetry for web requests;
- wall-clock duration and timeouts;
- database errors and slow queries;
- free disk before, lowest free disk during, and free disk after cleanup;
- output counts, archive integrity, and fixture checksums; and
- p50, p95, and maximum latency for the traffic profile.

A candidate profile passes only when:

- every operation completes correctly in all five measured runs;
- there are no out-of-memory errors, timeouts, truncated uploads, partial archives, permission failures, or new error-level log entries;
- measured peak memory is at most 80% of `memory_limit`;
- measured duration is at most 80% of the applicable execution timeout;
- disk usage never enters the reserved free-space margin; and
- a clean restore reproduces the recorded database and file evidence.

The 20% runtime margin absorbs ordinary variation; it is not a capacity plan for traffic growth. If one candidate passes and the next lower candidate fails, repeat both cells on a second clean environment before publishing the passing value.

## Upload settings

The current CMS media request accepts at most 51,200 KiB (50 MiB) per file. To expose that full product limit, the web SAPI and every proxy in front of it must allow the request. `upload_max_filesize` must be at least 50 MiB and `post_max_size` must be larger than the entire multipart request. A practical starting configuration is 64 MiB for both limits, subject to the host application's policy.

If a deployment intentionally chooses a lower upload limit, document that as an installation limit; it does not change the CMS validation ceiling. Validate uploads just below the chosen limit and rejection just above it. Test through the browser/API path, because a CLI filesystem copy bypasses PHP and proxy body limits.

## Disk calculation

There are two different disk decisions:

1. **Steady-state capacity** must cover application releases, the live database, original media, generated variants, logs, temporary files, site-transfer packages, and the configured backup retention.
2. **Operation headroom** must cover the largest temporary overlap during backup, extraction, update, import, or rollback.

Package-native System Update currently enforces an absolute 500 MiB free-space preflight floor. That is a safety gate, not a promise that 500 MiB is enough for a media-heavy installation. For acceptance, measure the lowest free space during the operations profile and retain at least the larger of:

- 500 MiB; or
- 125% of the measured peak temporary disk consumption for the reference dataset.

Size steady-state storage from measured data:

```text
application releases
+ live database allocation
+ original media
+ generated variants
+ retained backup archives
+ retained site-transfer packages
+ expected logs and temporary files
+ at least 25% operational growth reserve
```

Recalculate after material growth in media, database size, backup retention, or plugin data. Configure monitoring to alert before the operation headroom is consumed.

## Publishing a minimum

Store the raw result with the release qualification record and publish a summary in [Hosting Capacity Results](hosting-capacity-results.md) containing the immutable CMS commit or release tag, qualification and fixture versions, stable raw-evidence location, server profile, tested limits, five-run worst case, margin, and date. A minimum may be changed only by a new passing qualification series. If raw evidence was not retained, say so in the result and keep the profile provisional.

Until that series exists, [Hosting Requirements](hosting-requirements.md) must say that the value is not product-certified. Once it exists, replace that statement with a profile table; keep the workload version beside every number so cms.webblocksui.com does not present a context-free guarantee.

Use the [Hosting Readiness Checklist](hosting-readiness-checklist.md) to attach the selected tested profile and evidence to an individual deployment.
