# Guide Media

This directory owns screenshots and other media used by `docs/guides/*.md`.

Store each guide's files under its stable slug:

```text
docs/guides/media/create-a-page/01-pages-list.webp
docs/guides/media/create-a-page/02-new-page-form.webp
```

Reference them relative to `docs/guides/`:

```markdown
![Pages list with the New Page action visible](media/create-a-page/01-pages-list.webp)
```

The documentation sync command uploads or replaces the file in the CMS Media Library and creates a native Image block with the Markdown alt text. These files never belong to the `webblocksui.com` sandbox or another project.

Screenshots must contain fictional, non-sensitive data and use the documented viewport, scale, locale, role, and theme.
