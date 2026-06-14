# WebBlocks Admin Migration Checklist

Use this checklist when migrating another project or plugin admin surface to the WebBlocks CMS admin standard. Every item is intentionally checkable so project AIs can audit screens consistently.

Implementation note: The WebBlocks Advisor gate was checked for this documentation cleanup on 2026-06-14. This checkout does not expose an Advisor/knowledge Artisan command, so this checklist is based on the current CMS implementation and the companion standards in this directory.

## Project Audit Checklist

- [ ] Admin screens use the canonical admin prefix expected by the project, and CMS-owned screens use `/webadmin` rather than assuming `/admin`.
- [ ] Admin screens extend the correct WebBlocks admin layout or shell.
- [ ] The shell root is `wb-dashboard-shell`.
- [ ] The sidebar uses `wb-sidebar`, `wb-sidebar-link`, `wb-nav-group`, and WebBlocks icon classes.
- [ ] The sidebar brand uses the approved inline/product mark rather than a custom image/mask/logo workaround.
- [ ] The sidebar footer shows product name and version in compact muted text.
- [ ] The topbar contains project/install identity separately from the fixed product brand.
- [ ] Main content renders in `wb-dashboard-main`.
- [ ] Exactly one `#wb-overlay-root.wb-overlay-root` exists in the admin layout.
- [ ] Modals, pickers, and confirmations render into the shared overlay root.
- [ ] Screens begin with the shared page header pattern.
- [ ] Flash/status feedback uses the shared flash or `wb-alert` pattern.
- [ ] List filters use the shared compact listing filter toolbar when filters change the result set.
- [ ] Search is the first filter and context selectors follow it.
- [ ] List create/upload/import actions live in the listing card header when that is the screen pattern.
- [ ] List cards use `wb-card > wb-card-body > wb-table-wrap > table.wb-table`.
- [ ] Table action headers are normal `<th>Actions</th>`.
- [ ] Row action cells use `td.wb-table-actions`.
- [ ] Row action groups use `.wb-action-group` inside the action cell.
- [ ] Action columns are left-aligned and do not use `wb-text-end`.
- [ ] Destructive row actions use destructive visual treatment.
- [ ] Browser `confirm()` is absent from destructive admin flows.
- [ ] Destructive confirmations use WebBlocks modal confirmation under the shared overlay root.
- [ ] Bulk actions, if present, select only visible rows unless the UI and backend explicitly support all-filtered selection.
- [ ] Bulk action forms submit IDs to a server-side validated endpoint.
- [ ] Pagination uses the shared pagination partial or matching WebBlocks pagination markup.
- [ ] Detail screens use grouped `wb-card` sections and status/meta primitives.
- [ ] Create/edit forms use `wb-card` with header, body, and footer action rows.
- [ ] Form fields use WebBlocks form classes and accessible field-level errors.
- [ ] Danger zones are visually separated and explain the destructive effect.
- [ ] Empty states use `wb-empty` or `wb-empty wb-empty-sm`.
- [ ] Validation and blocking errors use inline `wb-alert`.
- [ ] Transient success/info feedback uses `wb-toast` only when the runtime owns lifecycle.
- [ ] Admin JavaScript is page-scoped or layout-approved shared runtime only.
- [ ] Custom CSS/JS has a documented reason and narrow selector scope.
- [ ] No Tailwind, Vite, React, Vue, Livewire, npm build step, `public/build`, or hot-file runtime dependency is introduced.
- [ ] Admin copy is operational, short, and tied to the task.
- [ ] Auth screens, if touched, use `wb-auth-shell` and the approved CMS auth form structure.
- [ ] Logout is POST-backed and invalidates/regenerates the session.
- [ ] Password reset copy does not reveal whether an account exists.
- [ ] Tests or visual checks cover representative list, form, modal, empty, and auth states for the migrated surface.

## Per-Screen Audit Format

Copy this format for each audited screen.

```markdown
## Screen: {screen name}

- Route/URL:
- View file(s):
- Controller/action:
- Permission/middleware:
- Screen type: dashboard | index/list | detail | create/edit | settings | update | auth | other
- Current shell:
- Expected shell:
- Overlay root present once: yes/no
- Uses shared page header: yes/no
- Uses shared flash/alerts: yes/no
- Uses listing filters: yes/no/not applicable
- Table structure compliant: yes/no/not applicable
- Action column compliant: yes/no/not applicable
- Destructive actions modal-confirmed: yes/no/not applicable
- Empty state compliant: yes/no/not applicable
- Pagination compliant: yes/no/not applicable
- Custom CSS/JS found:
- Forbidden dependencies found:
- Copy issues:
- Accessibility issues:
- Data/authorization risks:
- Required changes:
- Tests/checks to run:
- Migration status: pending | partial | compliant | blocked
```

## Summary Format

```markdown
# {Project} WebBlocks Admin Standard Audit

Date:
Auditor:
Reference standard:

## Scope

- Screens reviewed:
- Screens excluded:
- Code paths reviewed:

## Findings

| Priority | Screen | Issue | Required change | Owner/status |
| --- | --- | --- | --- | --- |
| P0 | | | | |
| P1 | | | | |
| P2 | | | | |

## Compliance Snapshot

- Shell/layout:
- Sidebar/topbar:
- Lists/tables/actions:
- Forms/details:
- Modals/overlays:
- Auth:
- Assets/dependencies:
- Copy:
- Tests:

## Blockers

-

## Recommended Migration Order

1.
2.
3.
```
