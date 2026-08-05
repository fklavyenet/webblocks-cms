<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The Sites list showed every site's domain as plain text, so opening a site
 * meant copying the hostname into the address bar. Pages already carries a View
 * column; this pins the same affordance on Sites, pointing at the site home
 * page on its own canonical domain.
 */
class SiteIndexViewColumnTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('app.url', 'https://cms.example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_site_home_url_uses_the_sites_own_canonical_domain(): void
  {
    $site = $this->makeSite('shop.example.test');

    $this->assertSame('https://shop.example.test/', $site->publicHomeUrl());
  }

  #[Test]
  public function the_view_column_links_to_the_site_home_page_in_a_new_tab(): void
  {
    $this->makeSite('shop.example.test');

    $html = $this->renderIndex();

    $this->assertStringContainsString('<th>View</th>', $html);
    $this->assertStringContainsString('data-column="view"', $html);
    $this->assertStringContainsString('href="https://shop.example.test/"', $html);
    $this->assertStringContainsString('target="_blank"', $html);
    $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('wb-action-btn wb-action-btn-view', $html);
    $this->assertStringContainsString('wb-icon-globe', $html);
  }

  #[Test]
  public function the_view_column_leads_the_row(): void
  {
    $this->makeSite('shop.example.test');

    $html = $this->renderIndex();

    $this->assertLessThan(
      strpos($html, '<th>Name</th>'),
      strpos($html, '<th>View</th>'),
      'The View column heads the table.'
    );
    $this->assertLessThan(
      strpos($html, '<td><strong>Shop</strong></td>'),
      strpos($html, 'data-column="view"'),
      'The View cell opens the row.'
    );
  }

  #[Test]
  public function details_and_edit_are_row_icons_rather_than_dropdown_entries(): void
  {
    $site = $this->makeSite('shop.example.test');

    $html = $this->renderIndex();

    // Both reachable in one click from the row, next to the globe.
    $this->assertStringContainsString('wb-icon-panel-right', $html);
    $this->assertStringContainsString('wb-action-btn wb-action-btn-edit', $html);
    $this->assertStringContainsString(route('admin.sites.edit', $site), $html);
    $this->assertStringContainsString('details_site='.$site->id, $html);

    // ...and gone from the Manage dropdown, which keeps the rest.
    $this->assertStringNotContainsString('class="wb-dropdown-item">View details</a>', $html);
    $this->assertStringNotContainsString('class="wb-dropdown-item">Edit site</a>', $html);
    $this->assertStringContainsString('class="wb-dropdown-item">Manage domains</a>', $html);
    $this->assertStringContainsString('class="wb-dropdown-item">Clone site</a>', $html);
  }

  #[Test]
  public function the_view_strings_exist_in_every_shipped_locale(): void
  {
    foreach (['en', 'tr', 'de'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      $this->assertArrayHasKey('view', $lang['sites']['columns'], 'Missing sites.columns.view in the '.$locale.' strings.');
      $this->assertNotSame('', trim((string) $lang['sites']['columns']['view']));

      $this->assertArrayHasKey('open_home_new_tab', $lang['sites'], 'Missing sites.open_home_new_tab in the '.$locale.' strings.');
      $this->assertStringContainsString(':name', (string) $lang['sites']['open_home_new_tab']);
    }
  }

  private function makeSite(string $domain): Site
  {
    $locale = Locale::query()->firstOrCreate(
      ['code' => 'en'],
      ['name' => 'English', 'is_default' => true, 'is_enabled' => true]
    );

    $site = Site::query()->create([
      'name' => 'Shop',
      'handle' => 'shop',
      'domain' => $domain,
      'is_primary' => true,
    ]);

    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    return $site->refresh();
  }

  private function renderIndex(): string
  {
    $sites = Site::query()
      ->with(['locales' => fn ($query) => $query->orderBy('name')])
      ->withCount('pages')
      ->paginate(AdminPagination::perPage());

    return view('webblocks-cms::admin.sites.index', [
      'sites' => $sites,
      'exportablePages' => [],
      'siteDeleteReports' => [],
      'siteCount' => $sites->total(),
      'totalCount' => $sites->total(),
      'canExportSites' => false,
    ])->render();
  }
}
