# Admin UI ownership

The administration panel loads the pinned WebBlocks UI runtime before the CMS
admin stylesheet and scripts. A panel screen should use the shipped `wb-*`
component, utility, and `data-wb-*` behavior contracts whenever they express the
required interface.

CMS-owned CSS and JavaScript are reserved for domain behavior that WebBlocks UI
cannot know about, such as block-tree persistence, media selection, table and
rich-text authoring, update requests, and import/export workflows. They must not
reimplement a shipped component state merely to rename its classes or events.

Current examples include:

- password visibility uses `data-wb-password-toggle` and `WBPasswordToggle`;
- listing filters use `wb-filter-bar`, its start/end regions, and
  `wb-filter-select`;
- pagination composition uses `wb-pagination` and normal cluster utilities;
- page-slot source choices use `wb-btn-check-group` and `wb-btn-check`;
- Media folder and view state uses normal button and radius utilities.

When a required primitive is missing, record the gap and add it to WebBlocks UI
first when it is generic. Add CMS-local presentation only when the requirement
is genuinely CMS-specific, and cover that boundary with a focused test.
