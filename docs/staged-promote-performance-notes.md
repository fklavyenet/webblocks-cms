---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/staged-promote-performance-notes
cms_title: Staged Promote Performance Notes
cms_layout: docs
cms_source_id: webblocks-cms:docs/staged-promote-performance-notes.md
---

# Staged Promote Performance Notes

This note captures a field problem observed while promoting a large published-page staged update through the Internal Content API. It is intentionally written as reusable CMS product guidance, not as an install-specific incident report.

## Problem

Large `promote_staged_page_update` operations can exceed the web request execution limit before the CMS returns a structured JSON response.

The staged promote path is logically correct: it validates the staged update, captures a pre-promote revision, replaces selected page-owned source slots with the staged slot block tree, syncs page metadata, marks the staged page as promoted, captures a post-promote revision, and returns API response data.

The operational issue is that a large block tree can make that path slow enough for PHP-FPM or the web server to terminate the HTTP request. When that happens, the caller may see a raw `500` or connection failure instead of a controlled `422` or success payload.

## Why It Matters

Timeouts are especially risky during publish-like operations:

- the operation may have already written some or all intended data
- the client cannot trust the response status alone
- retrying blindly can duplicate work or obscure the real state
- operators lose the precise step that consumed the time
- API users cannot distinguish validation failure, transactional write failure, response serialization cost, and web timeout

The homepage conversion playbook already requires post-apply state verification after `500`, timeout, or connection failure. This document records the product improvement needed so that staged promote itself becomes fast and observable enough for normal web/API use.

## Immediate Investigation Plan

Add structured timing around the staged promote path before changing architecture. The first useful timings are:

- validation and normalized plan creation
- pre-promote page revision capture
- deletion of existing source slot blocks
- clone/copy of staged slot blocks into the source page
- page metadata/status sync
- staged page promoted/archive status update
- post-promote page revision capture
- relation reloads after write
- API presenter serialization for `data.page` and `data.staged_page`

Log timings only as safe operational metadata: mode, page IDs, slot count, block count, elapsed milliseconds, and phase names. Do not log content bodies, tokens, secrets, host paths, request headers, or private environment values.

## Short-Term Product Fix

Make successful staged promote responses lightweight by default.

For `promote_staged_page_update`, the API does not need to return the fully presented source page and staged page block trees on every successful write. A default lightweight response should include:

- `ok`
- `writes`
- `normalized_plan`
- `warnings`
- `errors`
- source `page_id`
- staged `page_id`
- source page status/path summary
- staged page status/state summary
- edit/preview/public URLs when available

Full page presentation can remain available behind an explicit opt-in such as `response=full`, `include=page`, or a documented request field. This keeps backward compatibility possible for clients that truly need the full response while preventing the normal promote path from spending significant time serializing data the caller will usually re-read separately.

The focused regression test should cover both forms:

- default promote returns a lightweight success payload
- opt-in full promote still returns full presented page data

## Medium-Term Performance Work

After timings identify the slow phases, optimize the expensive phase rather than guessing.

Likely candidates:

- block clone logic doing many Eloquent writes one record at a time
- translation copies that could be batched
- recursive block tree reads that are not eager-loaded
- source slot deletion walking trees row-by-row
- page revision capture serializing more data than needed twice
- final presenter calls loading and serializing the full page again

Potential improvements:

- eager-load the staged block tree once before cloning
- batch insert cloneable block translations where practical
- delete replaced source slot trees with fewer queries while preserving safety constraints
- avoid relation reloads unless the response mode needs them
- make revision capture timing visible and evaluate whether staged promote needs a lighter snapshot strategy

## Long-Running Action Option

If optimized staged promote can still exceed normal web request limits for legitimately large pages, introduce a long-running operation model instead of forcing the HTTP request to stay open.

A future design could:

- create a `content_operation_runs` style record
- return `202 Accepted` with an operation ID
- run promote in a queue or supervised background worker
- expose operation status, phase, warnings, errors, and final page IDs
- show progress/status in the admin panel
- keep the existing guarded plan validation and capability checks before enqueueing

This is a larger product feature and should follow measurement plus lightweight-response work. Queueing should not hide an inefficient promote path that can be fixed directly.

## Recommended Priority

1. Add timing instrumentation for staged promote phases.
2. Make promote success responses lightweight by default with an explicit full-response opt-in.
3. Use timing data to optimize block clone, deletion, revision, or presenter phases.
4. Add long-running operation support only if optimized promote still cannot reliably fit normal web request limits.

## Operator Guidance Until Fixed

When a staged promote request returns `500`, timeout, or connection failure:

1. Do not retry blindly.
2. Re-read the source page.
3. Re-read the staged page.
4. Fetch the public page HTML.
5. Confirm whether the intended content was promoted.
6. Check whether the staged page state is still draft or has moved to promoted/archived.
7. Record the mismatch as a CMS defect if public content and staged state disagree.

If an operator must complete a critical promote before the product fix exists, they may use the same CMS service path from a controlled CLI context with the correct runtime environment and database configuration. That should remain an operational workaround, not the permanent product design.
