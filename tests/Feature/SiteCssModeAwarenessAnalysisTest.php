<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The site.css analyzer warns about CSS that paints the page outside the public
 * theme tokens. Its page-wide checks used to match any selector merely
 * containing the token, so a purely local rule under a class such as
 * .wb-public-body -- or a .wb-card-title -- was reported as a page-wide
 * anti-pattern. A warning nobody can act on is worse than no warning: the API
 * guidance tells tools to clear these before calling a migration finished.
 */
class SiteCssModeAwarenessAnalysisTest extends TestCase
{
  private function analyze(string $css): array
  {
    $controller = $this->app->make(InternalSiteController::class);
    $method = new ReflectionMethod($controller, 'analyzeCssModeAwareness');

    return $method->invoke($controller, $css);
  }

  #[Test]
  public function a_token_only_stylesheet_passes(): void
  {
    $analysis = $this->analyze(<<<'CSS'
      .wb-public-body { display: flex; flex-direction: column; }
      .wb-slot-main { flex: 1 0 auto; }
      .wb-slot-footer {
        background-color: var(--wb-public-surface-strong);
        border-top: 1px solid var(--wb-public-border);
        color: inherit;
      }
      CSS);

    $this->assertSame('pass', $analysis['status']);
    $this->assertSame([], $analysis['warnings']);
    $this->assertSame(0, $analysis['signals']['literal_color_declarations']);
    $this->assertTrue($analysis['signals']['uses_public_theme_tokens']);
  }

  #[Test]
  public function a_class_that_merely_ends_in_body_is_not_a_page_wide_repaint(): void
  {
    $analysis = $this->analyze('.wb-public-body { background: #ffffff; color: #111111; }');

    $this->assertSame([], $analysis['anti_patterns'], 'A class name is not the body element.');
  }

  #[Test]
  public function painting_one_descendant_is_not_a_page_wide_repaint(): void
  {
    $analysis = $this->analyze('body .promo-badge { background: #ffffff; }');

    $this->assertSame([], $analysis['anti_patterns'], 'The token must be the last compound of the selector.');
  }

  #[Test]
  public function a_longer_class_name_is_not_the_component_token(): void
  {
    $analysis = $this->analyze('.wb-card-title { background: #ffffff; } .wb-section-label { background: #eeeeee; }');

    $this->assertSame([], $analysis['anti_patterns']);
  }

  #[Test]
  public function the_real_page_wide_repaints_are_still_caught(): void
  {
    foreach ([
      'body_theme_background' => 'body { background: #ffffff; }',
      'body_theme_color' => 'body.wb-public-body { color: #111111; }',
      'main_background' => '#main-content { background-color: #ffffff; }',
      'section_background' => '.wb-section { background: #ffffff; }',
      'card_background' => 'div.wb-card { background: #ffffff; }',
    ] as $expected => $css) {
      $analysis = $this->analyze($css);

      $this->assertContains($expected, $analysis['anti_patterns'], $css);
      $this->assertSame('warning', $analysis['status'], $css);
    }
  }

  #[Test]
  public function a_scoped_theme_selector_is_still_caught_in_a_selector_list(): void
  {
    $analysis = $this->analyze(<<<'CSS'
      html[data-mode="dark"] body[data-wb-public-theme="prism"],
      .wb-preview { background: #000000; }
      CSS);

    $this->assertContains('body_theme_background', $analysis['anti_patterns']);
  }

  #[Test]
  public function a_color_quoted_in_a_comment_is_documentation_not_paint(): void
  {
    // Taken from a live site.css: a token-only stylesheet whose comment quotes
    // the shipped UI rule it repairs. It warned about a color it never set.
    // WebBlocks UI 2.18.0 ships that reset itself, so the rule below is now
    // redundant rather than wrong -- it stays here as real analyzer input.
    $analysis = $this->analyze(<<<'CSS'
      /* WebBlocks UI declares `.wb-slider-text-light { color: #fff }` on the
         slider root only, so nested blocks never receive it. */
      .wb-slider-text-light .wb-slide-content { color: inherit; }
      .wb-slot-footer { background: var(--wb-public-surface-strong); }
      CSS);

    $this->assertSame('pass', $analysis['status']);
    $this->assertSame(0, $analysis['signals']['literal_color_declarations']);
  }

  #[Test]
  public function a_dark_mode_scope_named_only_in_a_comment_does_not_count(): void
  {
    $analysis = $this->analyze(<<<'CSS'
      /* Pairs with html[data-mode="dark"] body[data-wb-public-theme] elsewhere. */
      .promo { background: #ffffff; }
      CSS);

    $this->assertFalse($analysis['signals']['has_dark_mode_scope']);
    $this->assertSame('warning', $analysis['status']);
  }

  #[Test]
  public function a_commented_out_rule_is_not_analyzed(): void
  {
    $analysis = $this->analyze('/* body { background: #ffffff; } */ .promo { color: var(--wb-public-text); }');

    $this->assertSame('pass', $analysis['status']);
    $this->assertSame([], $analysis['anti_patterns']);
  }

  #[Test]
  public function literal_colors_without_tokens_or_a_dark_scope_still_warn(): void
  {
    $analysis = $this->analyze('.promo { background: #ffffff; }');

    $this->assertSame('warning', $analysis['status']);
    $this->assertSame([], $analysis['anti_patterns']);
    $this->assertSame(1, $analysis['signals']['literal_color_declarations']);
    $this->assertFalse($analysis['signals']['has_dark_mode_scope']);
  }
}
