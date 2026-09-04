# Admin listing filters

The shared `admin.partials.listing-filters` view uses WebBlocks UI 2.25.2's
labelled filter form. The UI runtime owns spacing, wrapping, sizing, and action
alignment; CMS does not supply filter layout CSS.

Each search, select, or date control is a direct `wb-field` child of
`wb-filter-bar-fields`. Search also uses `wb-filter-bar-search`. Actions use
`wb-filter-bar-actions` containing `wb-action-group`. The complete group uses
its intrinsic width: it stays beside fields when space permits and moves
together to the next line otherwise. Both single Apply and Apply + Clear
Filters states use the same flow. Controls and buttons share a 2.75rem height.

Do not add `wb-stack` or gap utilities to these fields: their margins would
add to the component's label spacing. Wrapped rows have more space than
labels and controls. Long select options cannot widen fields. Search occupies
its own row in compact containers and grows from an 18rem basis in wide ones;
other fields grow from a 10rem basis. Hosts retain GET parameters, translated
labels, optional date auto-submit, and existing action visibility and order.
