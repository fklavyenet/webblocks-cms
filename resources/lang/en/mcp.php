<?php

return [
  'connect' => 'Connect a client to WebBlocks CMS',
  'consent_read' => 'Allow :client to find pages, read their headings and request private preview links for the site you choose.',
  'consent_write' => 'Allow this client to edit localized headings in update drafts. This connection cannot publish pages.',
  'consent_duration' => 'Access expires after one hour.',
  'site' => 'Site',
  'select_site' => 'Choose a site',
  'allow' => 'Allow access',
  'deny' => 'Deny access',
  'invalid_request' => 'The client sent an invalid request.',
  'read_description' => 'Find a published page by its exact localized path (use / for the home page) or page ID. Returns its supported heading blocks and the current source and draft version.',
  'write_description' => 'Change a localized header, hero or content header title in the update draft for a published page. Read the page first and use the returned version and the selected source heading ID. Review any existing draft heading before editing. Returns a private preview link.',
  'preview_description' => 'Get a private preview link for the current update draft of a published page in the requested locale. The browser must be signed in to the CMS.',
  'auth_required' => 'Connect to WebBlocks CMS with the required access before running this tool.',
  'page_missing' => 'No eligible page matches the requested site, locale and page reference.',
  'draft_missing' => 'There is no active update draft for this page.',
  'conflict' => 'The published page or its update draft changed or is under review. Read the page again before editing.',
  'unverified_base' => 'The original source version cannot be verified or the published page has changed since the draft was created. Review the draft in the CMS before continuing.',
  'heading_unavailable' => 'Choose an editable heading in a managed page-owned slot. Source-driven headings and pages containing human-only blocks need to be handled in the CMS.',
  'forbidden' => 'Your CMS account lacks permission for this operation on the selected site.',
];
