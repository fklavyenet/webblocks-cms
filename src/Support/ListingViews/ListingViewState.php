<?php

namespace WebBlocks\Cms\Support\ListingViews;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Queries\ListingViews\ListingViewQuery;

final class ListingViewState
{
  public function columns(Request $request, string $listing): array
  {
    $definition = app(ListingViewDefinition::class);
    $columns = $request->query->has('columns') ? $definition->normalizeColumns($listing, $request->query('columns')) : $definition->columns($listing);
    if ($request->query->has('columns')) {
      $request->query->set('columns', ($columns === [] ? 'none' : implode(',', $columns)));
    }

    return $columns;
  }

  public function stored(object $view, $user): array
  {
    $definition = app(ListingViewDefinition::class);
    if (count($view->filters) !== count($definition->defaults($view->listing))) {
      throw ValidationException::withMessages(['saved_view' => __('webblocks-cms::listing_views.invalid')]);
    }

    return [$definition->normalize($view->listing, $view->filters, $user), $definition->normalizeColumns($view->listing, $view->columns)];
  }

  public function context(Request $request, string $listing, array $filters, array $columns): array
  {
    $query = app(ListingViewQuery::class);
    $selected = $request->integer('saved_view') ? $query->owned($request->user(), $listing, $request->integer('saved_view')) : null;
    $blocked = false;
    $changed = false;
    if ($selected) {
      try {
        [$storedFilters, $storedColumns] = $this->stored($selected, $request->user());
        $definition = app(ListingViewDefinition::class);
        $raw = array_intersect_key($request->query(), $definition->defaults($listing));
        $definition->normalize($listing, [...$filters, ...$raw], $request->user());
        $changed = $storedFilters !== $filters || $storedColumns !== $columns;
      } catch (ValidationException) {
        $blocked = true;
      }
    }

    return ['listing' => $listing, 'views' => $query->listing($request->user(), $listing), 'selected' => $selected, 'blocked' => $blocked, 'changed' => $changed, 'filters' => $filters, 'columns' => $columns, 'columnOptions' => app(ListingViewDefinition::class)->columns($listing)];
  }

  public function presentationQuery(Request $request): array
  {
    $query = [];
    if ($request->integer('saved_view') > 0) {
      $query['saved_view'] = (string) $request->integer('saved_view');
    }
    if ($request->query->has('columns')) {
      $query['columns'] = (string) $request->query('columns');
    }

    return $query;
  }
}
