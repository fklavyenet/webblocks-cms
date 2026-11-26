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

    $this->assertStringNotContainsString('<th>View</th>', $html);
    $this->assertStringNotContainsString('data-column="view"', $html);
    $this->assertStringContainsString('href="https://shop.example.test/"', $html);
    $this->assertStringContainsString('target="_blank"', $html);
    $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('wb-action-btn wb-action-btn-view', $html);
    $this->assertStringContainsString('wb-icon-globe', $html);
  }

  #[Test]
  public function identity_and_status_lead_the_row_and_public_view_is_a_final_action(): void
  {
    $site = $this->makeSite('shop.example.test');
    $document = new \DOMDocument;
    @$document->loadHTML($this->renderIndex([$site->id]));
    $xpath = new \DOMXPath($document);
    $headers = $xpath->query('//table/thead/tr/th');
    $this->assertSame('Name', trim($headers->item(0)->textContent));
    $this->assertSame('Status', trim($headers->item(1)->textContent));
    $cells = $xpath->query('//table/tbody/tr[@data-site-id="'.$site->id.'"]/td');
    $titleLink = $xpath->query('.//a', $cells->item(0))->item(0);
    $this->assertSame('Shop', $titleLink->textContent);
    $details = $xpath->query('//td[contains(@class, "wb-table-actions")]//a[@aria-controls="siteDetailsModal"]')->item(0);
    $this->assertSame($details->getAttribute('href'), $titleLink->getAttribute('href'));
    $this->assertSame('dialog', $titleLink->getAttribute('aria-haspopup'));
    $public = $xpath->query('//td[contains(@class, "wb-table-actions")]//a[@target="_blank"]')->item(0);
    $this->assertSame($site->publicHomeUrl(), $public->getAttribute('href'));
  }

  #[Test]
  public function the_public_view_action_carries_the_globe_and_preserves_its_window(): void
  {
    $this->makeSite('shop.example.test');
    $document = new \DOMDocument;
    @$document->loadHTML($this->renderIndex());
    $xpath = new \DOMXPath($document);
    $public = $xpath->query('//td[contains(@class, "wb-table-actions")]//a[@target="_blank"]')->item(0);
    $this->assertSame('noopener noreferrer', $public->getAttribute('rel'));
    $this->assertSame(1, $xpath->query('.//i[contains(@class, "wb-icon-globe")]', $public)->length);
  }

  #[Test]
  public function details_and_edit_are_actions_column_icons_rather_than_dropdown_entries(): void
  {
    $site = $this->makeSite('shop.example.test');

    $html = $this->renderIndex();

    // Both reachable in one click, ahead of the overflow menu.
    $this->assertStringContainsString('wb-icon-panel-right', $html);
    $this->assertStringContainsString('wb-action-btn wb-action-btn-edit', $html);
    $this->assertStringContainsString(route('admin.sites.edit', $site), $html);
    $this->assertStringContainsString('details_site='.$site->id, $html);
    $this->assertLessThan(
      strpos($html, 'data-wb-target="#site-actions-'.$site->id.'"'),
      strpos($html, 'wb-icon-panel-right'),
      'The two icons lead the Actions cell, with the overflow menu last.'
    );

    // The overflow menu holds the secondary actions.
    $this->assertStringNotContainsString('class="wb-dropdown-item">View details</a>', $html);
    $this->assertStringNotContainsString('class="wb-dropdown-item">Edit site</a>', $html);
    $this->assertStringContainsString('class="wb-dropdown-item">Manage domains</a>', $html);
    $this->assertStringContainsString('class="wb-dropdown-item">Clone site</a>', $html);
  }

  #[Test]
  public function the_overflow_button_uses_icons_and_names_its_site(): void
  {
    $site = $this->makeSite('shop.example.test');
    $menuId = 'site-actions-'.$site->id;

    $document = new \DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$this->renderIndex());
    $xpath = new \DOMXPath($document);
    $trigger = $xpath->query('//td[@class="wb-table-actions"]//button[@data-wb-toggle="dropdown" and @data-wb-target="#'.$menuId.'"]')->item(0);

    $this->assertInstanceOf(\DOMElement::class, $trigger);
    $this->assertSame('Manage Shop', $trigger->getAttribute('aria-label'));
    $this->assertSame($menuId, $trigger->getAttribute('aria-controls'));
    $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
    $this->assertSame('⋯', trim($trigger->textContent));
    $this->assertSame(1, $xpath->query('./i[@class="wb-icon wb-icon-chevron-down" and @aria-hidden="true"]', $trigger)->length);
    $this->assertSame(1, $xpath->query('//*[@id="'.$menuId.'"]')->length);
  }

  private function cellAfter(string $html, string $needle): string
  {
    $start = strpos($html, $needle);

    $this->assertNotFalse($start, 'Expected to find '.$needle.' in the rendered table.');

    return substr($html, $start, strpos($html, '</td>', $start) - $start);
  }

  #[Test]
  public function the_view_strings_exist_in_every_shipped_locale(): void
  {
    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $locale) {
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

  private function renderIndex(array $editableSiteIds = []): string
  {
    $sites = Site::query()
      ->with(['locales' => fn ($query) => $query->orderBy('name')])
      ->withCount('pages')
      ->paginate(AdminPagination::perPage());

    return view('webblocks-cms::admin.sites.index', [
      'sites' => $sites,
      'editableSiteIds' => $editableSiteIds,
      'exportablePages' => [],
      'siteDeleteReports' => [],
      'siteCount' => $sites->total(),
      'totalCount' => $sites->total(),
      'canExportSites' => false,
    ])->render();
  }
}
