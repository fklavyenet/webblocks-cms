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

## Access and caching

A source that contains restricted data attaches a class implementing
`ContentSourceAccessPolicy` with `accessPolicy(...)`. The policy receives the
site, page, locale, preview state, and authenticated actor before any resolver
is called. A denied source behaves like an unavailable source and the public
block keeps its editorial fallback.

Plugins can opt into bounded result caching with `cacheFor($seconds)`. Cache
keys include the source, site, page, locale, record/query arguments, and source
settings. Preview requests bypass the cache so editors always inspect current
data. After a domain write, plugins can call
`app(ContentSourceRuntime::class)->invalidate('plugin-handle::source')` to make
all cached variants of that source stale immediately. A zero duration, the
default, disables caching.

## Editor behavior

Supported existing blocks expose a **Content source** control in their Settings tab. The editor can retain the literal value entered in Block Fields or select a source record and a type-compatible field. No binding expression is typed by hand.

The supported field bindings include:

- Header `title` from a `text` source field;
- Plain Text `content` from a `text` source field;
- Rich Text `content` from a `text` or `rich_text` source field.
- Image source, caption, alternative text, and link from compatible fields.
- Button and Button Link labels and URLs.
- Link List Item title, secondary text, description, and URL.

At public render time the binding wins when it resolves to a non-empty value. Missing records, disabled plugins, unavailable sources, and empty values safely retain the editorial block value. Resolver failures are reported without taking the page down.

Bindings live under `settings.content_bindings`, so existing page duplication, revision, export, and import behavior preserves them without a domain-specific database column.

## Collection sources

A plugin may register `ContentSourceDefinition::collection(...)` with a resolver implementing `ContentCollectionSourceResolver`. Slider, Grid, and Stack can select that collection and one existing direct child as the repeated template. At render time CMS clones that subtree for each record, supplies the record as the current collection item, and resolves descendant field bindings against it. Slider templates remain Slide blocks; Grid and Stack can repeat any child type they already accept, such as Card.

Other children remain ordinary editorial content and keep their position. The selected template is replaced in place by its resolved records, so one container can deliberately mix manual and dynamic content without a plugin-owned carousel, grid, or card renderer. Editors can preview up to three source records, cap records, filter by one source field and value, sort by a source field in either direction, and paginate Grid or Stack results. Resolution is capped at 50 records. A missing source, disabled plugin, invalid template, or resolver failure safely restores the ordinary stored block tree.

For small sources, `ContentCollectionSourceResolver` remains sufficient and CMS
applies filtering, sorting, and pagination to its iterable. Large sources should
implement `QueryableContentCollectionSourceResolver`. CMS then passes a typed
`ContentCollectionQuery` to the plugin and consumes a `ContentCollectionResult`,
allowing database/API filtering, sorting, limits, totals, and page windows
without loading the full collection.

Editors choose separately whether a valid but empty result and a resolver error
hide the repeated template or show its editorial fallback. Denied or removed
sources retain stored content. The Settings panel reports bindings whose plugin,
source, or declared field disappeared, so plugin upgrades and removals do not
silently strand content.

Bindings and collection configuration live in the ordinary block `settings`
payload. Page revisions and site export/import already copy that payload
verbatim; no plugin-owned table or executable resolver output enters a CMS
revision or transfer package.
