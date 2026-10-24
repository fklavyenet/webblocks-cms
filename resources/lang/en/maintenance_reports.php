<?php

return [
  'refresh_completed' => 'Checks and maintenance reports refreshed.',
  'refresh_pending' => 'Another report refresh is already in progress. Wait for it to finish, then reload this page.',
  'refreshing' => 'Refreshing checks…',
  'cleanup_report_unavailable' => 'Storage and history figures are not available until the first report completes. Refresh checks to generate the report. Retention settings remain available in the next tab.',
  'cleanup_actions_unavailable' => 'Cleanup previews are not available until the first report completes. Refresh checks to calculate eligible items before choosing a cleanup action.',
  'refresh_failed' => 'Reports could not be refreshed. The last completed report has been retained.',
  'missing' => 'Report not available yet.',
  'stale' => 'Last completed report: :time. These figures may be out of date.',
  'fresh' => 'Last completed report: :time.',
  'help' => 'Reports refresh through the Laravel scheduler. Open System Health and use Refresh checks to refresh them manually.',
  'validation_pending' => ':count backup archive validation(s) are pending. Cleanup eligibility is withheld until validation completes.',
];
