<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * On a short page the footer used to end up mid-viewport. public.css makes the
 * public shell a flex column and lets main absorb the leftover height, which
 * only works while main stays a direct child of the body -- the Docs shell
 * nests it and is excluded on purpose, since .wb-dashboard-body already owns a
 * 100vh height model.
 *
 * The shell states its own viewport height. WebBlocks UI's reset used to set
 * body { min-height: 100vh } and this rule leaned on it as the pre-dvh
 * fallback; 2.18.0 dropped that declaration, so both floors are declared here.
 */
class PublicShellStickyFooterTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function publicCss(): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');
  }

  #[Test]
  public function public_css_makes_the_shell_a_flex_column_that_main_fills(): void
  {
    $css = $this->publicCss();

    $this->assertMatchesRegularExpression(
      '/\.wb-public-body\s*\{[^}]*display:\s*flex;[^}]*flex-direction:\s*column;[^}]*\}/s',
      $css,
      'The public body must be a flex column, otherwise its min-height stays inert.'
    );
    $this->assertMatchesRegularExpression(
      '/\.wb-public-body\s*>\s*\.wb-slot-main\s*\{[^}]*flex:\s*1 0 auto;[^}]*\}/s',
      $css,
      'Main must absorb the leftover height and must not shrink below its content.'
    );
    // Since UI 2.18.0 nothing else sets a viewport floor on the body, so the
    // shell declares both: 100vh for browsers without dvh, then 100dvh.
    $this->assertMatchesRegularExpression(
      '/\.wb-public-body\s*\{[^}]*min-height:\s*100vh;[^}]*min-block-size:\s*100dvh;[^}]*\}/s',
      $css,
      'The shell must carry its own 100vh floor before the 100dvh answer.'
    );
  }

  #[Test]
  public function the_rules_hang_off_framework_hooks_a_catalog_resync_cannot_touch(): void
  {
    $css = $this->publicCss();

    // wb-public-body comes from the layout, wb-slot-main from
    // SlotWrapperResolver's fixed class -- neither is stored css_classes.
    $this->assertStringNotContainsString('.wb-public-main {', $css);
    $this->assertStringNotContainsString('.wb-public-footer', $css);
  }

  #[Test]
  public function the_default_shell_keeps_main_a_direct_child_of_the_body(): void
  {
    $body = $this->renderBody($this->seedPage('default'));

    $this->assertSame(
      1,
      $this->countDirectBodyChildren($body, 'wb-slot-main'),
      'The flex rule targets a direct child; main must render straight into the body.'
    );
    $this->assertSame(
      1,
      $this->countDirectBodyChildren($body, 'wb-slot-footer'),
      'The footer must be a sibling flex item, not nested inside main.'
    );
  }

  #[Test]
  public function the_docs_shell_nests_main_and_is_left_untouched(): void
  {
    $body = $this->renderBody($this->seedPage('docs'));

    $this->assertStringContainsString('wb-dashboard-body', $body);
    $this->assertSame(
      0,
      $this->countDirectBodyChildren($body, 'wb-slot-main'),
      'Docs keeps main inside .wb-dashboard-body, so the direct-child rule must not reach it.'
    );
  }

  private function countDirectBodyChildren(string $html, string $class): int
  {
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_use_internal_errors($previous);

    $nodes = (new DOMXPath($document))->query(
      '/html/body/*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]'
    );

    return $nodes === false ? 0 : $nodes->length;
  }

  private function renderBody(Page $page): string
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['format' => 'html']);
    $html = $this->app->make(InternalPageRenderController::class)->show($request, $page)->getContent();

    $this->assertStringContainsString('class="wb-public-body', $html);

    return $html;
  }

  private function seedPage(string $publicShell): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$publicShell, 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'about-'.$publicShell,
      'status' => Page::STATUS_PUBLISHED,
      'settings' => ['public_shell' => $publicShell],
    ]);
    $page->translations()->create([
      'locale_id' => $locale->id,
      'name' => 'About',
      'slug' => 'about-'.$publicShell,
      'path' => '/about-'.$publicShell,
    ]);

    foreach (['main', 'footer'] as $index => $slug) {
      $slotType = SlotType::query()->firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'status' => 'published', 'sort_order' => $index],
      );
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => $index]);

      Block::create([
        'page_id' => $page->id,
        'type' => 'header',
        'slot_type_id' => $slotType->id,
        'sort_order' => 0,
        'title' => ucfirst($slug).' heading',
        'variant' => 'h2',
        'status' => 'published',
      ]);
    }

    return $page->fresh();
  }
}
