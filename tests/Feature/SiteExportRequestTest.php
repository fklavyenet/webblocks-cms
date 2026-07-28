<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Requests\Admin\SiteExportRequest;
use WebBlocks\Cms\Tests\TestCase;

/**
 * What the export form actually submits, run through the rules it hits.
 *
 * The picker always submits one empty `page_ids[]` so that ticking nothing
 * arrives as an explicit empty selection rather than as no selection at all,
 * which would mean the whole site. That marker is not an id, and it failed
 * `page_ids.*|integer` on every single export — "The page_ids.0 field must be
 * an integer" — while the tests around it, which only read source strings,
 * stayed green. Hence this: the payload the form sends, validated.
 */
class SiteExportRequestTest extends TestCase
{
  /**
   * @param  array<string, mixed>  $payload
   * @return array{0: bool, 1: array<string, mixed>}
   */
  private function validate(array $payload): array
  {
    $request = SiteExportRequest::create('/webadmin/site-transfers/exports', 'POST', $payload);
    $request->setContainer($this->app)->setRedirector($this->app['redirect']);

    $prepare = new \ReflectionMethod($request, 'prepareForValidation');
    $prepare->setAccessible(true);
    $prepare->invoke($request);

    // The exists: rules would query tables this test has no need for; what is
    // under test is the shape the form submits, not whether those ids are real.
    $rules = array_map(
      static fn (array $rules) => array_values(array_filter(
        $rules,
        static fn ($rule) => ! is_string($rule) || ! str_starts_with($rule, 'exists:'),
      )),
      $request->rules(),
    );

    $validator = Validator::make($request->all(), $rules);

    return [$validator->passes(), $request->all()];
  }

  #[Test]
  public function the_empty_marker_the_form_always_sends_does_not_fail_validation(): void
  {
    [$passes, $input] = $this->validate([
      'site_id' => '1',
      'includes_media' => '1',
      'page_ids' => ['', '4', '9'],
    ]);

    $this->assertTrue($passes, 'The form the picker renders must validate as submitted.');
    $this->assertSame(['4', '9'], $input['page_ids']);
  }

  #[Test]
  public function ticking_nothing_is_an_empty_selection_rather_than_no_selection(): void
  {
    [$passes, $input] = $this->validate([
      'site_id' => '1',
      'page_ids' => [''],
    ]);

    $this->assertTrue($passes);

    // The key survives as an empty array. Dropping it entirely would read as
    // "the caller did not choose", which the exporter treats as every page —
    // the opposite of what clicking None asked for.
    $this->assertArrayHasKey('page_ids', $input);
    $this->assertSame([], $input['page_ids']);
  }

  #[Test]
  public function a_caller_that_sends_no_selection_still_means_the_whole_site(): void
  {
    [$passes, $input] = $this->validate(['site_id' => '1']);

    $this->assertTrue($passes);
    $this->assertArrayNotHasKey('page_ids', $input);
  }

  #[Test]
  public function a_page_id_that_is_not_a_number_is_still_rejected(): void
  {
    [$passes] = $this->validate([
      'site_id' => '1',
      'page_ids' => ['', 'not-an-id'],
    ]);

    $this->assertFalse($passes, 'Filtering the marker must not stop real rubbish from being caught.');
  }
}
