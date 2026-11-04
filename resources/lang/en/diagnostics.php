<?php

return [
  'title' => 'Diagnostic report',
  'help' => 'Prepare a limited installation report for review or download. Nothing is sent to a support provider. Reports expire after 30 minutes.',
  'categories' => 'Include in the report',
  'category' => [
    'system_summary' => 'System and runtime versions',
    'update_run' => 'Selected or latest update run',
    'plugin_health' => 'Installed plugin versions and activation state',
    'saved_health' => 'Previously saved health check counts',
    'recent_application_errors' => 'Recent error timestamps and fingerprints',
  ],
  'errors_help' => 'Error diagnostics contain timestamps, severity and fingerprints only. Messages, request data, credentials, paths, content and full logs are excluded. Other log channels are not collected.',
  'consent' => 'Prepare the selected diagnostic information for my review.',
  'prepare' => 'Prepare diagnostic report',
  'review' => 'Review the exact report below. To remove categories, change the selection and prepare a new report. Downloading does not collect new data.',
  'download' => 'Download reviewed report',
  'expired' => 'This report has expired or belongs to another user. Prepare a new report.',
];
