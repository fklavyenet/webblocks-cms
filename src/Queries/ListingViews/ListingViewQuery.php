<?php

namespace WebBlocks\Cms\Queries\ListingViews;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Policies\ListingViewPolicy;

final class ListingViewQuery
{
  public function owned($user, string $listing, int $id): object
  {
    $view = DB::table('wbcms_listing_views')->where('user_id', $user->getAuthIdentifier())->where('listing', $listing)->find($id);
    abort_unless($view && app(ListingViewPolicy::class)->owned($user, $view, $listing), 404);

    return $this->hydrate($view);
  }

  public function listing($user, string $listing)
  {
    return DB::table('wbcms_listing_views')->where('user_id', $user->getAuthIdentifier())->where('listing', $listing)->orderBy('name')->get();
  }

  private function hydrate(object $view): object
  {
    $view->filters = DB::table('wbcms_listing_view_filters')->where('view_id', $view->id)->pluck('filter_value', 'filter_name')->all();
    $view->columns = DB::table('wbcms_listing_view_columns')->where('view_id', $view->id)->orderBy('position')->pluck('column_name')->all();

    return $view;
  }
}
