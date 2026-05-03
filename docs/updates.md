# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.

## Operational Expectations

- Run updates from published releases only.
- Keep install-specific files in preserved paths such as `.env`, `storage/`, and `project/`.
- Treat development and release workflows separately.

## Related Docs

- [Operations](operations.md)
- [Installation](installation.md)
- [Development Workflow](../DEVELOPMENT.md)
