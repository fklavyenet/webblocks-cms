<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Actions\ListingViews\WriteListingView;
use WebBlocks\Cms\Http\Requests\Admin\ListingViewRequest;
use WebBlocks\Cms\Policies\ListingViewPolicy;
use WebBlocks\Cms\Queries\ListingViews\ListingViewQuery;
use WebBlocks\Cms\Support\ListingViews\ListingViewState;

final class ListingViewController extends Controller
{
  public function store(ListingViewRequest $request, string $listing): RedirectResponse
  {
    $id = app(WriteListingView::class)->save($request->user(), $listing, $request->validated());

    return $this->applied($request, $listing, $id)->with('status', __('webblocks-cms::listing_views.saved'));
  }

  public function update(ListingViewRequest $request, string $listing, int $listingView): RedirectResponse
  {
    app(WriteListingView::class)->save($request->user(), $listing, $request->validated(), $listingView);

    return $this->applied($request, $listing, $listingView)->with('status', __('webblocks-cms::listing_views.saved'));
  }

  public function apply(Request $request, string $listing, int $listingView): RedirectResponse
  {
    return $this->applied($request, $listing, $listingView);
  }

  private function applied(Request $request, string $listing, int $id): RedirectResponse
  {
    abort_unless(app(ListingViewPolicy::class)->listing($request->user(), $listing), 403);
    $view = app(ListingViewQuery::class)->owned($request->user(), $listing, $id);
    try {
      [$filters, $columns] = app(ListingViewState::class)->stored($view, $request->user());
      $parameters = [...$filters, 'columns' => $columns === [] ? 'none' : implode(',', $columns), 'saved_view' => $id];
    } catch (ValidationException) {
      $parameters = ['saved_view' => $id];
    }

    return redirect()->route('admin.'.$listing.'.index', $parameters);
  }

  public function destroy(Request $request, string $listing, int $listingView): RedirectResponse
  {
    abort_unless(app(ListingViewPolicy::class)->listing($request->user(), $listing), 403);
    app(WriteListingView::class)->delete($request->user(), $listing, $listingView);

    return redirect()->route('admin.'.$listing.'.index', $listing === 'pages' ? ['reset' => 1] : [])->with('status', __('webblocks-cms::listing_views.deleted'));
  }
}
