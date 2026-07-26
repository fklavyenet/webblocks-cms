<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Theme\BrandPaletteRenderer;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Brand palette over the branding endpoint: the four colours and two font
 * stacks an operator picks, the derived tokens returned for preview, and the
 * public style block rendered from them. See docs/brand-palette.md.
 */
class SiteBrandPaletteApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function controller(): InternalSiteController
  {
    return app(InternalSiteController::class);
  }

  private function site(): Site
  {
    return Site::create(['name' => 'Play', 'handle' => 'play', 'is_primary' => true]);
  }

  #[Test]
  public function it_stores_the_palette_and_returns_the_derived_tokens(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateBranding(
      Request::create('/', 'PATCH', [
        'brand_accent' => '#6A0F25',
        'brand_accent_secondary' => '#c7795b',
        'brand_surface' => '#fffdf7',
        'brand_text' => '#2d2d2d',
        'brand_font_heading' => 'Libre Baskerville, Georgia, serif',
        'brand_font_body' => 'IBM Plex Sans, system-ui, sans-serif',
      ]),
      $site,
    );

    $this->assertSame(200, $response->getStatusCode());
    $payload = $response->getData(true);

    $this->assertTrue($payload['ok']);
    // Stored normalized: uppercase hex is folded to lowercase.
    $this->assertSame('#6a0f25', $payload['site']['brand_accent']);
    $this->assertSame('#6a0f25', $site->fresh()->brand_accent);

    $palette = $payload['site']['brand_palette'];
    $this->assertSame('#6a0f25', $palette['light']['--wb-public-accent']);
    $this->assertArrayHasKey('--wb-public-accent-hover', $palette['light']);
    $this->assertArrayHasKey('--wb-public-page-bg', $palette['dark']);
    $this->assertSame(
      'Libre Baskerville, Georgia, serif',
      $palette['fonts']['--wb-public-font-heading']
    );
    $this->assertGreaterThan(0, $palette['accent_contrast']);
  }

  #[Test]
  public function it_rejects_a_colour_that_is_not_hex(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateBranding(
      Request::create('/', 'PATCH', ['brand_accent' => 'rebeccapurple']),
      $site,
    );

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('brand_accent', $response->getData(true)['errors'][0]['path']);
    $this->assertNull($site->fresh()->brand_accent);
  }

  #[Test]
  public function it_rejects_a_font_stack_that_could_escape_the_declaration(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateBranding(
      Request::create('/', 'PATCH', ['brand_font_body' => 'Arial;}body{display:none}']),
      $site,
    );

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('brand_font_body', $response->getData(true)['errors'][0]['path']);
    $this->assertNull($site->fresh()->brand_font_body);
  }

  #[Test]
  public function an_unconfigured_site_renders_no_style_block(): void
  {
    $this->assertSame('', app(BrandPaletteRenderer::class)->render($this->site()));
    $this->assertSame('', app(BrandPaletteRenderer::class)->render(null));
  }

  #[Test]
  public function the_public_style_block_carries_light_dark_and_typography_rules(): void
  {
    $site = $this->site();
    $site->forceFill([
      'brand_accent' => '#6a0f25',
      'brand_surface' => '#fffdf7',
      'brand_text' => '#2d2d2d',
      'brand_font_heading' => 'Libre Baskerville, Georgia, serif',
      'brand_font_body' => 'IBM Plex Sans, system-ui, sans-serif',
    ])->save();

    $css = app(BrandPaletteRenderer::class)->render($site->fresh());

    $this->assertStringContainsString('body[data-wb-public-theme]{', $css);
    $this->assertStringContainsString('--wb-public-accent:#6a0f25;', $css);
    $this->assertStringContainsString('html[data-mode="dark"] body[data-wb-public-theme]{', $css);
    $this->assertStringContainsString('@media (prefers-color-scheme:dark)', $css);
    $this->assertStringContainsString(
      'body.wb-public-body{font-family:var(--wb-public-font-body);}',
      $css
    );
    $this->assertStringContainsString('main :is(h1,h2,h3,h4)', $css);
    // The renderer only ever emits token scopes and the two typography rules.
    $this->assertStringNotContainsString('</style', $css);
  }
}
