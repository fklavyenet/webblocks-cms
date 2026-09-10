# Public submission protection

WebBlocks CMS provides one local protection pipeline for visitor-facing forms. The
native Contact Form and Comments blocks use it directly, and plugins such as WebBlocks
Forms resolve the same service from the CMS container. Signals therefore follow a site
across form types instead of each form defending itself in isolation.

The pipeline makes no network requests and requires no CAPTCHA, API key, daemon,
browser-fingerprinting service, optional PHP extension, or third-party moderation
product. It uses the application's database, configured Laravel cache, and `APP_KEY`.

## Processing model

Protection is deliberately layered:

1. Laravel validation and CSRF reject malformed or cross-origin requests normally.
2. The renderer supplies a signed form identity, timestamp, and generated honeypot.
3. Invalid proof, a filled honeypot, or submission before the form's minimum time takes
   the generic success path without storage or delivery.
4. Valid submissions are scored with content, timing, repetition, sender, source,
   network-prefix, form, and learned-reputation signals.
5. The score becomes `allow`, `quarantine`, or `spam`.
6. The owning form stores the review record; only `allow` may run delivery actions.

Visitors never receive the score, reasons, notification result, or confirmation that a
trap recognized them. The generic response prevents bots from using the endpoint to
tune their payloads.

## Decisions and delivery

`inspect()` returns `score`, unique `reasons`, `decision`, and the compatibility flag
`is_spam`. The default thresholds are:

| Score | Decision | Storage | Delivery actions |
| ---: | --- | --- | --- |
| `0–19` | `allow` | Stored normally | May run |
| `20–59` | `quarantine` | Stored for review | Suppressed |
| `60–100` | `spam` | Stored as spam | Suppressed |

For Contact Messages, quarantine and spam suppress email notification. For WebBlocks
Forms they suppress every action, including business notification, autoresponder,
webhook, and optional Campaigns integration. Comments never publish automatically:
clean and quarantined comments remain pending, while a spam decision is stored as spam.

## Signals and default weights

Signals are additive and the final score is capped at 100. Several weak signals can
therefore quarantine a request without relying on one brittle keyword rule.

| Signal | Default score | Notes |
| --- | ---: | --- |
| Three or more links | `+40` | Two links score `+25` |
| Any link in a comment | `+50` | Comments use a stricter content contract |
| Email address or phone number in a comment | `+40` | Contact details are not allowed in comment bodies |
| BBCode/HTML link markup | `+60` | Detects `[url]` and anchor markup |
| Known commercial outreach wording | `+40` | Conservative local phrase list |
| Unbroken run of 100+ characters | `+20` | Common obfuscation/automation shape |
| Submission under eight seconds | `+10` | Scoring after the hard minimum-time check |
| Exact repeated content | `+30` | Fourth and later repeat scores `+60` |
| Same exact IP burst | `+20` | Eighth and later request scores `+40` |
| Same sender-address burst | `+20` | Eighth and later request scores `+40` |
| Same network-prefix burst | `+20` | Thirtieth and later request scores `+40` |
| Same form burst | `+20` | Eighth and later request in 60 seconds |
| Exact learned spam fingerprint | `+60` | Site-local operator feedback |
| Near learned spam fingerprint | `+35` | SimHash distance is configurable |
| Exact learned legitimate fingerprint | `−40` | False-positive correction |
| Near learned legitimate fingerprint | `−20` | False-positive correction |

Email addresses are removed before content fingerprinting, so sender rotation does not
hide a repeated message and changing an address does not create a new campaign copy.

## Scope, windows, and privacy

Every counter and reputation lookup is site-scoped. Activity on one hosted site cannot
raise another site's score.

- Exact IP, sender, content, form, and network cache keys use keyed HMAC; submitted
  values do not appear in cache keys.
- IPv4 addresses are reduced to `/24` and IPv6 addresses to `/64` before the
  network-prefix HMAC. This detects rotating hosts without storing the address or using
  geolocation.
- Network thresholds are higher than exact-IP thresholds so offices, schools, carrier
  gateways, and other shared networks are not penalized too early.
- Similarity rows contain an exact HMAC, a 64-bit SimHash, counters, and timestamps—not
  the submitted message.
- Daily metrics contain only site, date, surface, and aggregate decision counts.

The owning submission table may still store information its product needs—for example,
a Contact Message stores the visitor-supplied address and source details. That product
record is separate from protection reputation and metrics.

## Learned reputation and corrections

`wbcms_submission_fingerprints` keeps local, site-scoped reputation. Exact matches have
the strongest effect; near-duplicates use a bounded SimHash comparison over at most the
200 most recently seen fingerprints from the preceding 90 days.

Marking a Contact Message or WebBlocks Forms submission as spam records `spam`
feedback. Restoring a spam or quarantined item to New, Read, or Replied records `ham`
feedback and offsets later false positives. Archiving is organizational and teaches
nothing. Feedback affects later submissions only; it does not retroactively reclassify
existing records.

Plugins call `recordOutcome($siteId, $validatedAnswers, 'spam')` or use `ham` for a
correction.

## Thirty-day admin summary

Every scored submission increments one row in `wbcms_submission_daily_totals` for its
site, date, and surface. Contact Messages displays the rolling summary across sites the
signed-in admin may access. WebBlocks Forms displays it for the selected site.

- **Checked:** every valid submission that reached scoring;
- **Allowed:** normal delivery was permitted;
- **Quarantined:** stored for review with delivery suppressed;
- **Spam:** stored as spam with delivery suppressed.

Trap-discarded or too-fast requests never reach scoring, are not stored, and are not in
these totals. The summary is operational context rather than a visitor count or an
accuracy report. `summary($siteIds, $days)` returns zeros while the metrics table is not
installed, keeping plugin screens safe during an incomplete update.

## Configuration reference

All settings are optional environment overrides. Defaults are conservative.

| Environment variable | Default | Meaning |
| --- | ---: | --- |
| `CMS_SUBMISSION_QUARANTINE_SCORE` | `20` | Minimum quarantine score |
| `CMS_SUBMISSION_SPAM_SCORE` | `60` | Minimum spam score |
| `CMS_SUBMISSION_FINGERPRINT_WINDOW` | `86400` | Exact-content window, seconds |
| `CMS_SUBMISSION_SOURCE_WINDOW` | `3600` | Exact-IP window, seconds |
| `CMS_SUBMISSION_EMAIL_WINDOW` | `86400` | Sender-address window, seconds |
| `CMS_SUBMISSION_EMAIL_QUARANTINE_COUNT` | `4` | Sender count that adds `+20` |
| `CMS_SUBMISSION_EMAIL_SPAM_COUNT` | `8` | Sender count that adds `+40` |
| `CMS_SUBMISSION_NETWORK_WINDOW` | `3600` | `/24` or `/64` window, seconds |
| `CMS_SUBMISSION_NETWORK_QUARANTINE_COUNT` | `12` | Network count that adds `+20` |
| `CMS_SUBMISSION_NETWORK_SPAM_COUNT` | `30` | Network count that adds `+40` |
| `CMS_SUBMISSION_SIMILARITY_DISTANCE` | `10` | Maximum 64-bit SimHash distance |

Keep the quarantine threshold below the spam threshold, each first burst threshold
below its second threshold, and windows positive. After changing environment values on
an installation with cached configuration, run `php artisan optimize:clear`.

## Renderer proof contract

`SubmissionProof` binds its values to both surface and form identity. Renderers post
`_form_stamp`, `_form_check_name`, and the empty field returned by
`fieldName($surface, $form)`. Missing, altered, future-dated, or cross-form values fail
closed.

The proof is not a one-time token: full-page and CDN caches may serve the same form to
multiple real visitors. Its purpose is form binding, integrity, honeypot naming, and
elapsed-time measurement—not replay prevention.

## Plugin integration contract

Plugins remain responsible for validation, authorization, rate limiting, storage,
status names, and rendering. A compatible plugin must:

1. render signed proof and an empty generated honeypot;
2. silently discard invalid proof, filled traps, and submissions below its hard minimum;
3. pass only validated answers to `inspect()`;
4. use the real site ID and stable surface/form identities;
5. persist score and reasons with its submission;
6. run actions only when the decision is `allow`;
7. send operator spam/ham corrections to `recordOutcome()`;
8. use `summary()` rather than querying CMS metric tables directly.

Do not copy the scorer into a plugin, weaken installation-wide thresholds, expose
reason codes to visitors, or transmit answers to another service. WebBlocks Forms is
the reference implementation.

## Operational guidance

- Review quarantine and spam regularly; the learning loop depends on deliberate status
  corrections.
- Mark real campaigns as **Spam**. Use **Archive** only for filing because it does not
  train reputation.
- Restore false positives to a legitimate workflow status.
- Change thresholds only after observing site traffic. Lower sender/network counts can
  harm shared-office or event traffic.
- A rise in **Checked** without inbox email can be expected when quarantine or spam
  suppresses delivery. Notification and editorial status remain separate.
- Back up before changing `APP_KEY`. Rotation makes existing HMAC fingerprints and live
  cache keys incomparable with new ones until old influence ages out.

## Related documentation

- [Contact Forms And Messages](contact-forms-and-messages.md)
- [Comments And Ratings](guides/comments-and-ratings.md)
- [Security](security.md)
- [Operations](operations.md)
- [Plugin System](plugin-system.md)
