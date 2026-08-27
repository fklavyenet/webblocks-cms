---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/hosting-capacity-results
cms_title: Hosting Capacity Results
cms_layout: docs
cms_source_id: webblocks-cms:docs/hosting-capacity-results.md
---

# Hosting Capacity Results

This page records measured WebBlocks CMS capacity evidence. Read it with [Hosting Capacity Validation](hosting-capacity-validation.md): a partial qualification supports only the workload it actually ran.

## 2026-08-26 local memory qualification

**Status:** provisional core PHP-memory candidate and 24-megapixel media worker profile. This is not yet the complete MySQL production qualification because the core run used the package's SQLite feature-test environment and the operations and traffic profiles were not run.

### Environment

| Item | Value |
| --- | --- |
| Qualification ID | `2026-08-26-local-v1` |
| CMS source | WebBlocks CMS `v1.73.1`, commit `464d20872a64c304ec4e440a6eb6f112fc9534ef` |
| PHP | 8.4.2 CLI, NTS |
| Framework test harness | Laravel 13 package test environment through PHPUnit 12.5.31 |
| Operating system | Darwin 25.5.0, x86_64 |
| Database for core run | SQLite `:memory:` |
| GD | Enabled for JPEG, PNG, and WebP |
| Initial PHP memory limit | 1 GiB; constrained per child process for the matrix |

The summarized run outputs and fixture checksums below were recorded, but the raw command logs and process-sampling files were not retained as an immutable qualification artifact. That evidence gap is an additional reason this run is provisional and must not be promoted to a production-certified minimum. A certification rerun must retain those raw files outside the public package and record their stable location in this page.

### Core feature-suite result

The complete Feature suite ran in one PHP process: 567 tests and 3,076 assertions. This is deliberately more cumulative than an ordinary isolated HTTP request, but it is not a substitute for the production MySQL/HTTP fixture.

| PHP `memory_limit` | Result | Reported PHP peak | Evidence |
| --- | --- | --- | --- |
| 96 MiB | Fail | Limit exhausted | Failed during `SiteCustomHeadApiTest` after 453 completed tests |
| 112 MiB | Pass, one run | 101 MiB | No 20% safety margin |
| 128 MiB | Pass, five of five runs | 101 MiB each | Worst duration 39.505 seconds; four subsequent runs were 33.146–33.267 seconds |

Under the protocol's 80% utilization rule, 101 MiB requires 126.25 MiB. Therefore **128 MiB is the current provisional core PHP `memory_limit` candidate**. It becomes a production-certified minimum only after the corresponding Laravel consumer, MySQL 8.0, real HTTP, install, and representative content fixture pass.

### Media transformation result

The real `MediaTransformService::regenerate()` path generated all seven system variants from cold transform storage. Each source was 6,000 × 4,000 pixels (24 MP). The small compressed byte sizes do not weaken the decode-memory test: decoded pixel dimensions drive the dominant GD allocation.

| Format | Fixture bytes | PHP-reported peak | Observed process peak RSS |
| --- | ---: | ---: | ---: |
| JPEG | 657,408 | 32.5 MiB | 180.30 MiB |
| PNG | 80,838 | 32.5 MiB | 253.24 MiB |
| WebP | 103,394 | 32.5 MiB | 312.26 MiB worst of five measured runs |

The five WebP process peaks were 296.80, 310.20, 301.77, 312.26, and 300.96 MiB. All seven variants were generated in every run; measured transform time was 2.717–3.149 seconds. Applying the 20% margin to the 312.26 MiB worst case produces 390.33 MiB. The practical tested profile is therefore:

- PHP `memory_limit`: at least the provisional 128 MiB core candidate; and
- actual memory capacity: **512 MiB per simultaneously active PHP worker that may perform a 24 MP GD transformation**.

The worker capacity is not the same as `memory_limit`. GD's native allocations were visible in process RSS but not in PHP's 32.5 MiB peak and were not stopped by `memory_limit=128M`. Total server RAM must additionally cover the operating system, web server, MySQL, caches, and other concurrent PHP workers.

Fixture checksums:

| File | SHA-256 |
| --- | --- |
| `reference-6000x4000.jpg` | `a9fbf43349cc525164efb256aabde15c3f7fe86c4679400f5f982b4977696d83` |
| `reference-6000x4000.png` | `6e1876a3b91f63d1565822b448c9fbfc07e1e38bc21eb20c7c8d595fb68917e8` |
| `reference-6000x4000.webp` | `e3c8b2bbd4dfee01d64ca2380106ab18dc98acd82ee1eee9b5bb7ae1a269de3f` |

### Product limit discovered by the measurement

The CMS limits an uploaded media file to 50 MiB but does not currently limit raster pixel dimensions. Compressed file size does not provide a finite upper bound on decoded GD memory. Consequently:

- the 512 MiB media worker profile is qualified for the documented 24 MP fixture, not for every file below 50 MiB;
- no finite full-contract media memory maximum can be certified while raster dimensions are unbounded; and
- a product-owned maximum pixel count or width/height policy is required before the media profile can become a universal minimum.

Until that guard exists, hosting documentation must state the supported media workload beside the memory value. Operators accepting larger images need proportionally more worker memory or an external image-processing policy.

## Remaining qualification work

Before replacing “provisional” with “certified,” run and retain:

- a production-like Laravel 13 consumer on MySQL 8.0 through the real HTTPS/FPM path;
- the versioned core reference content fixture described by the validation protocol;
- install, backup, restore, site transfer, and package-native update profiles;
- disk low-water measurements and timeout measurements;
- traffic tests with declared worker concurrency; and
- a media rerun after a maximum raster-dimension contract is implemented.

The [Hosting Readiness Checklist](hosting-readiness-checklist.md) should reference the exact result profile selected for a deployment.
