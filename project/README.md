# Project Layer

`project/` is reserved for install-specific WebBlocks CMS customizations that must survive core updates.

Examples include local providers, routes, config, views, commands, import helpers, and other site-specific code.

Reusable CMS behavior belongs in core.

Product releases must not ship site-specific `project/` content.
