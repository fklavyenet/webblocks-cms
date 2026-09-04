<?php

namespace WebBlocks\Cms\Support\SitePromotion;

class SitePromotionPreservePolicy
{
  public function preservedAreas(): array
  {
    return [
      'Users and roles',
      'Sessions and runtime cache',
      'Jobs and queues',
      'Backups and backup history',
      'Update history and installed version',
      'Visitor reports',
      'Contact submissions',
      'Live domains and site domain records',
      'Internal API tokens and install secrets',
      'Environment configuration',
      'Derived public search index rows',
    ];
  }

  public function blockedArchiveEntries(): array
  {
    return [
      'data/users.json',
      'data/roles.json',
      'data/sessions.json',
      'data/jobs.json',
      'data/queues.json',
      'data/system_backups.json',
      'data/system_backup_restores.json',
      'data/update_history.json',
      'data/contact_messages.json',
      'data/visitor_events.json',
      'data/visitor_daily_totals.json',
      'data/public_search_index.json',
      'data/api_tokens.json',
    ];
  }
}
