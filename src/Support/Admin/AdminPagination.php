<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\System\SystemSettings;

class AdminPagination
{
  public static function perPage(): int
  {
    return app(SystemSettings::class)->adminListingPerPage();
  }

  /**
   * A remembered or bookmarked page number outlives the result set it was taken
   * from: delete rows, tighten a filter, and the listing renders its empty state
   * while the header still counts the rows sitting on page one. Returns the page
   * worth redirecting to, or null when the current page holds real results.
   */
  public static function outOfRangePage(LengthAwarePaginator $paginator): ?int
  {
    return $paginator->currentPage() > $paginator->lastPage()
      ? $paginator->lastPage()
      : null;
  }

  /**
   * Sends the listing back to its last real page, keeping every other filter in
   * the query string. Throws the redirect rather than returning it so callers
   * keep their `View` return type; a no-op when the page is in range.
   */
  public static function redirectOutOfRange(LengthAwarePaginator $paginator, ?Request $request = null, string $pageName = 'page'): void
  {
    $target = self::outOfRangePage($paginator);

    if ($target === null) {
      return;
    }

    $request ??= request();
    $query = $request->query();

    if ($target > 1) {
      $query[$pageName] = (string) $target;
    } else {
      unset($query[$pageName]);
    }

    redirect()
      ->to($request->url().($query === [] ? '' : '?'.http_build_query($query)))
      ->throwResponse();
  }
}
