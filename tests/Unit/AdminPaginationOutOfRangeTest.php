<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The Pages listing remembers the page number you were last on. Delete rows or
 * narrow a filter and that number can outlive its result set: the header keeps
 * counting every matching page while the table renders "No pages found",
 * because page 2 of a one-page result really is empty.
 */
class AdminPaginationOutOfRangeTest extends TestCase
{
  #[Test]
  public function a_page_number_past_the_last_page_reports_the_page_worth_redirecting_to(): void
  {
    $paginator = $this->paginator(total: 15, perPage: 15, currentPage: 2);

    $this->assertSame(1, AdminPagination::outOfRangePage($paginator));
  }

  #[Test]
  public function an_empty_result_set_falls_back_to_the_first_page(): void
  {
    $paginator = $this->paginator(total: 0, perPage: 15, currentPage: 3);

    $this->assertSame(1, AdminPagination::outOfRangePage($paginator));
  }

  #[Test]
  public function a_page_that_still_holds_results_is_left_alone(): void
  {
    $paginator = $this->paginator(total: 40, perPage: 15, currentPage: 3);

    $this->assertNull(AdminPagination::outOfRangePage($paginator));
  }

  #[Test]
  public function the_redirect_keeps_every_other_filter_and_drops_the_stale_page(): void
  {
    $request = Request::create('https://cms.example.test/webadmin/pages', 'GET', [
      'site' => '1',
      'status' => 'published',
      'page' => '2',
    ]);

    $this->assertSame(
      'https://cms.example.test/webadmin/pages?site=1&status=published',
      $this->redirectTarget($this->paginator(total: 15, perPage: 15, currentPage: 2), $request)
    );
  }

  #[Test]
  public function a_listing_paginated_under_its_own_page_name_redirects_on_that_name(): void
  {
    $request = Request::create('https://cms.example.test/webadmin/site-transfers', 'GET', [
      'exports_page' => '9',
    ]);

    $this->assertSame(
      'https://cms.example.test/webadmin/site-transfers?exports_page=3',
      $this->redirectTarget($this->paginator(total: 45, perPage: 15, currentPage: 9), $request, 'exports_page')
    );
  }

  #[Test]
  public function an_in_range_page_is_rendered_rather_than_redirected(): void
  {
    $request = Request::create('https://cms.example.test/webadmin/pages', 'GET', ['page' => '2']);

    $this->assertNull($this->redirectTarget($this->paginator(total: 40, perPage: 15, currentPage: 2), $request));
  }

  private function redirectTarget(LengthAwarePaginator $paginator, Request $request, string $pageName = 'page'): ?string
  {
    try {
      AdminPagination::redirectOutOfRange($paginator, $request, $pageName);
    } catch (HttpResponseException $exception) {
      return $exception->getResponse()->headers->get('location');
    }

    return null;
  }

  private function paginator(int $total, int $perPage, int $currentPage): LengthAwarePaginator
  {
    return new LengthAwarePaginator([], $total, $perPage, $currentPage);
  }
}
