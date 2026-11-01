<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Admin\AdminPagination;

class AdminPaginationBoundsTest extends TestCase
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

  private function paginator(int $total, int $perPage, int $currentPage): LengthAwarePaginator
  {
    return new LengthAwarePaginator([], $total, $perPage, $currentPage);
  }
}
