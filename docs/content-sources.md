# Content Sources and Block Field Bindings

Content Sources let an enabled plugin expose typed domain data to existing CMS blocks without owning public markup. A catalog, commerce, events, or news plugin supplies records; CMS pages retain ownership of layout, block composition, translation, preview, and rendering.

The boundary is deliberate:

- a plugin source answers **which data** is available;
- a CMS block answers **how that data is presented**;
- the page and slot tree answer **where it appears**.

Plugins must not recreate core Header, Rich Text, Card, Grid, Slider, or Slide renderers merely to show plugin-owned records.

## Entity source contract

An enabled plugin registers an entity source on its definition:

```php
PluginDefinition::make('plugin-catalog')
  ->contentSources([
    ContentSourceDefinition::entity('plugin-catalog::plugin')
      ->label('Plugin')
      ->resolver(PluginSource::class)
      ->fields([
        'name' => ['type' => 'text', 'label' => 'Name'],
        'description' => ['type' => 'rich_text', 'label' => 'Description'],
        'download_url' => ['type' => 'url', 'label' => 'Download URL'],
      ]),
  ]);
```

The resolver implements `ContentSourceResolver`. `options()` supplies safe human-readable preview choices to the block editor; `resolve()` returns one record for the requested stable key and current site, page, locale, and preview context.

The plugin remains the sole owner of its tables and domain models. CMS stores only the source handle, stable record key, selected field, and the block's ordinary editorial fallback.

## Editor behavior

Supported existing blocks expose a **Content source** control in their Settings tab. The editor can retain the literal value entered in Block Fields or select a source record and a type-compatible field. No binding expression is typed by hand.

The pilot supports:

- Header `title` from a `text` source field;
- Plain Text `content` from a `text` source field;
- Rich Text `content` from a `text` or `rich_text` source field.

At public render time the binding wins when it resolves to a non-empty value. Missing records, disabled plugins, unavailable sources, and empty values safely retain the editorial block value. Resolver failures are reported without taking the page down.

Bindings live under `settings.content_bindings`, so existing page duplication, revision, export, and import behavior preserves them without a domain-specific database column.

## Next contract slice

This entity-field pilot intentionally precedes collection repetition. The next slice will let a generic CMS collection context repeat existing Slide/Card child compositions for source records while keeping Slider/Grid rendering in core.
