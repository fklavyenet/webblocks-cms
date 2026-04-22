## Summary

- Add Visitor Reports Phase 2 to the stable `0.3.x` line so installed CMS sites can receive campaign-aware reporting through the updater.
- Capture sanitized `utm_source`, `utm_medium`, and `utm_campaign` values on tracked public page views without changing the existing V1 tracking boundary.
- Expand admin reporting with campaign/source/medium breakdowns and add a compact 7-day Visitor Summary widget on `/admin`.

## Notes

- Visitor reports remain lightweight, privacy-aware CMS-native analytics rather than a full external analytics suite.
- UTM capture can be toggled with `CMS_VISITOR_UTM_ENABLED=true`.
- Published releases from this tag are intended to be consumed by the in-app CMS updater.
