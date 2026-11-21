<?php

return [
  'title' => 'Update database schema',
  'missing' => 'The update chain needs a database repair before the CMS can update.',
  'ready' => 'The update chain database schema is ready.',
  'unavailable' => 'The CMS could not verify the update chain database schema.',
  'support' => 'Give your hosting support the installed CMS version and this migration name: 2026_11_02_200000_add_update_chains.',
  'repair' => 'Repair installation',
  'repair_help' => 'The CMS will create a backup, then apply the update chain migration from the installed package. Keep this tab open until you see the result.',
  'backup_and_repair' => 'Back up and repair',
  'backup_label' => 'Before update chain schema repair',
  'backup_failed' => 'The CMS could not complete a verified backup. The repair was stopped.',
  'repair_failed' => 'The repair could not be verified. Backup #:backup is available in Maintenance → Backups.',
  'repaired' => 'The update chain schema is ready. Backup #:backup is available in Maintenance → Backups.',
];
