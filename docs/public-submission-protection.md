# Public submission protection

WebBlocks CMS provides one local protection service for visitor-facing forms. Core
Contact Form and comment submissions use it directly, and plugins may resolve
`WebBlocks\Cms\Support\PublicSubmissions\PublicSubmissionProtection` from the
container so every public form shares the same scoring and site-scoped frequency
signals.

The service makes no network requests and needs no CAPTCHA, API key, daemon, or
optional PHP extension. It combines content, timing, repeated-content, source-burst,
and form-burst signals. Counters use the host application's configured Laravel cache
store; the values are HMAC hashes rather than submitted content or plain IP addresses.

## Decisions

`inspect()` returns an explanatory score, reason codes, and one of three decisions:

- `allow`: persist the submission and run its normal delivery actions;
- `quarantine`: persist it for review but do not email, auto-reply, subscribe, or
  invoke a webhook;
- `spam`: retain it as spam without running delivery actions.

Visitors receive the same success response for trapped and accepted posts. Public
responses never expose the score or reasons.

The default quarantine and spam thresholds are 20 and 60. Install operators can set
`CMS_SUBMISSION_QUARANTINE_SCORE` and `CMS_SUBMISSION_SPAM_SCORE`. The quarantine
threshold must stay below the spam threshold. Frequency window lengths are controlled
by `CMS_SUBMISSION_FINGERPRINT_WINDOW` and `CMS_SUBMISSION_SOURCE_WINDOW`.
`CMS_SUBMISSION_SIMILARITY_DISTANCE` controls the maximum 64-bit SimHash distance
for a near-duplicate match and defaults to 10.

## Local feedback and similarity

The CMS stores site-scoped exact HMAC hashes and 64-bit SimHash fingerprints in
`wbcms_submission_fingerprints`. It never copies submitted content into that table.
Exact fingerprints carry the strongest reputation; near-duplicates receive a smaller
signal. Comparisons are limited to the 200 most recently seen fingerprints from the
same site within 90 days.

Call `recordOutcome($siteId, $answers, 'spam')` when an operator explicitly marks a
submission as spam. Call it with `ham` when an operator restores spam or quarantine to
a legitimate workflow status. Archiving is not feedback: an operator may archive spam
without asserting that the classifier was wrong.

## Renderer proof

`SubmissionProof` issues an HMAC-signed timestamp bound to a surface and form identity,
plus a signed honeypot name. A missing, altered, future-dated, or cross-form stamp is
invalid. Renderers should post `_form_stamp`, `_form_check_name`, and the empty field
named by `fieldName()`. Controllers silently accept the visitor-facing request while
discarding submissions with invalid proof.

## Plugin integration

Plugins remain responsible for validating their fields, storing their own records,
and deciding how their statuses are represented. They must inspect only the validated
answers, persist the returned score and reasons, and run external actions only for an
`allow` decision. A plugin must not copy the CMS scorer or weaken the install-wide
thresholds.
