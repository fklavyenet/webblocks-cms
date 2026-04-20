### Improvements

* Added first-party backup restore workflows to WebBlocks CMS

  * Introduced explicit backup restore orchestration for database and uploads recovery
  * Added archive validation, pre-restore safety backups, restore logging, and post-restore maintenance steps
  * Added `php artisan system:backup:restore` for practical local recovery workflows
  * Added guarded admin restore actions on backup detail pages

* Improved backup administration UX

  * Added safe cleanup actions for failed and stale running backup records
  * Switched backup list row actions to compact icon actions
  * Fixed admin menu icons to use valid WebBlocks UI icons

* Added filtering and sorting to the Pages list

  * Added search, status filter, sort field, and direction controls
  * Tightened the Pages list layout so filters and row actions stay compact

### Notes

* Existing backup creation behavior remains unchanged
* Published releases from this tag are ready to be consumed by the in-app CMS updater
