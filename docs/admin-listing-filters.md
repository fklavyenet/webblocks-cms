# Admin listing filters

The shared `admin.partials.listing-filters` view uses WebBlocks UI 2.25.0's
labelled filter form. The UI runtime owns spacing, wrapping, sizing, and action
alignment; CMS does not supply filter layout CSS.

Each search, select, or date control is a direct `wb-field` child of
`wb-filter-bar-fields`. Search also uses `wb-filter-bar-search`. Actions use
`wb-filter-bar-actions` containing `wb-action-group`, so buttons occupy the
control row even when labels wrap. Do not add `wb-stack` or gap utilities to
these fields: their margins would add to the component's label spacing.

The grid adapts to the filter container width and field count. Long select
options cannot widen columns. Wrapped rows have more space than labels and
controls. Search occupies its own row in compact containers and spans two
columns in wide containers. Hosts retain GET parameters, translated labels,
optional date auto-submit, and existing action visibility and order.
