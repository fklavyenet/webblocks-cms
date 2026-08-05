<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Plan normalization reads the keys it knows and ignored the rest, so a plan
 * carrying an unsupported field such as page.seo_title applied cleanly and
 * wrote none of it: ok => true, nothing changed, and no way for the caller to
 * tell. The fields the API cannot write are missing from its read payloads
 * too, so reading the page back did not reveal it either.
 *
 * Rejecting unknown keys is what makes the API boundary discoverable.
 */
class ContentPlanUnsupportedFieldsTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_rejects_page_seo_fields_the_api_cannot_write(): void
  {
    $result = $this->validatePlan([
      'site' => 'test',
      'locale' => 'en',
      'page' => [
        'title' => 'About',
        'path' => '/about',
        'seo_title' => 'About us',
        'seo_description' => 'Who we are.',
      ],
    ]);

    $this->assertFalse($result->ok);
    $this->assertSame(
      ['plan.page.seo_title', 'plan.page.seo_description'],
      $this->unsupportedPaths($result),
    );
  }

  #[Test]
  public function it_promotes_a_stable_code_to_the_error_envelope(): void
  {
    $result = $this->validatePlan([
      'page' => ['title' => 'About', 'path' => '/about', 'seo_title' => 'About us'],
    ]);

    $this->assertSame(
      InternalContentPlanService::UNSUPPORTED_KEY_ERROR_CODE,
      $result->toArray()['code'] ?? null,
    );
  }

  #[Test]
  public function it_rejects_unknown_top_level_plan_keys(): void
  {
    $result = $this->validatePlan([
      'site' => 'test',
      'meta_description' => 'ignored today',
      'page' => ['title' => 'About', 'path' => '/about'],
    ]);

    $this->assertSame(['plan.meta_description'], $this->unsupportedPaths($result));
  }

  #[Test]
  public function it_scopes_accepted_keys_to_the_plan_mode(): void
  {
    // replace_slots means nothing while creating a page, and everything while
    // replacing one. Neither used to say so.
    $creating = $this->validatePlan([
      'page' => ['title' => 'About', 'path' => '/about'],
      'replace_slots' => ['main' => []],
    ]);

    $replacing = $this->validatePlan([
      'mode' => InternalContentPlanService::MODE_REPLACE_EXISTING_DRAFT_PAGE,
      'page' => ['id' => 1, 'expected_path' => '/about'],
      'replace_slots' => ['main' => []],
    ]);

    $this->assertSame(['plan.replace_slots'], $this->unsupportedPaths($creating));
    $this->assertSame([], $this->unsupportedPaths($replacing));
  }

  #[Test]
  public function it_leaves_plans_built_only_from_known_keys_alone(): void
  {
    // These plans still fail validation -- no site, no slots -- but they must
    // never fail for carrying an unsupported field.
    $plans = [
      [
        'site' => 'test',
        'locale' => 'en',
        'layout' => 'default',
        'page' => ['title' => 'About', 'path' => '/about', 'status' => 'draft'],
        'slots' => ['main' => []],
        'navigation_menus' => [],
        'shared_slots' => [],
        'page_slot_shared_slots' => [],
      ],
      [
        'mode' => InternalContentPlanService::MODE_CREATE_STAGED_UPDATE,
        'source_page' => ['id' => 1, 'expected_path' => '/about'],
        'managed_slots' => ['main'],
      ],
      [
        'mode' => InternalContentPlanService::MODE_PROMOTE_STAGED_UPDATE,
        'staged_page' => ['id' => 2],
        'source_page' => ['id' => 1],
        'promote_slots' => ['main'],
      ],
    ];

    foreach ($plans as $index => $plan) {
      $this->assertSame([], $this->unsupportedPaths($this->validatePlan($plan)), 'plan #'.$index);
    }
  }

  #[Test]
  public function it_accepts_the_wrapper_and_restore_point_keys_the_controller_owns(): void
  {
    $result = $this->validatePlan([
      'create_restore_point' => true,
      'page' => ['title' => 'About', 'path' => '/about'],
    ]);

    $this->assertSame([], $this->unsupportedPaths($result));
  }

  private function validatePlan(array $plan)
  {
    return $this->app->make(InternalContentPlanService::class)->validate($plan);
  }

  /**
   * @return list<string>
   */
  private function unsupportedPaths($result): array
  {
    return collect($result->errors)
      ->filter(fn (array $error) => ($error['code'] ?? null) === InternalContentPlanService::UNSUPPORTED_KEY_ERROR_CODE)
      ->pluck('path')
      ->values()
      ->all();
  }
}
