<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

/**
 * Structural guards for the Edit Site form.
 *
 * The tab strip pairs one button with one panel by tab key. A second panel
 * carrying an existing key silently changes what the strip shows, which is how
 * the brand palette card first shipped hidden behind the branding tab.
 */
class SiteFormStructureTest extends TestCase
{
  private function form(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/sites/form.blade.php'
    );
  }

  /**
   * @return list<string>
   */
  private function tabKeys(string $form): array
  {
    return Site::ADMIN_FORM_TABS;
  }

  /**
   * @return list<string>
   */
  private function panelKeys(string $form): array
  {
    preg_match_all('/wb-tabs-panel \{\{ \$siteTab === \'([a-z-]+)\'/', $form, $matches);

    return $matches[1] ?? [];
  }

  #[Test]
  public function every_tab_has_exactly_one_panel(): void
  {
    $form = $this->form();
    $tabs = $this->tabKeys($form);
    $panels = $this->panelKeys($form);

    $this->assertNotEmpty($tabs);
    $this->assertSame(
      $panels,
      array_values(array_unique($panels)),
      'A tab key owns exactly one panel; a duplicate panel hides its own content.'
    );
    $this->assertSame($tabs, $panels, 'Tab buttons and panels must line up one to one.');
  }

  #[Test]
  public function the_brand_palette_fields_live_in_the_appearance_tab(): void
  {
    $form = $this->form();

    $brandStart = strpos($form, "wb-tabs-panel {{ \$siteTab === 'theme'");
    $nextPanel = strlen($form);

    $this->assertNotFalse($brandStart);
    $this->assertLessThan($nextPanel, $brandStart);

    $brandingPanel = substr($form, $brandStart, $nextPanel - $brandStart);

    foreach ([
      'brand_palette',
      'brand_accent',
      'brand_accent_secondary',
      'brand_surface',
      'brand_text',
      'brand_font_heading',
      'brand_font_body',
    ] as $field) {
      $this->assertStringContainsString($field, $brandingPanel);
    }
  }

  #[Test]
  public function the_brand_palette_strings_exist_in_every_shipped_locale(): void
  {
    $keys = [
      'brand_palette',
      'brand_palette_help',
      'brand_accent',
      'brand_accent_help',
      'brand_accent_secondary',
      'brand_accent_secondary_help',
      'brand_surface',
      'brand_surface_help',
      'brand_text',
      'brand_text_help',
      'brand_contrast_warning',
      'brand_font_heading',
      'brand_font_heading_help',
      'brand_font_body',
      'brand_font_body_help',
    ];

    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ($keys as $key) {
        $this->assertArrayHasKey(
          $key,
          $lang['site_form'],
          sprintf('Missing %s in the %s admin strings.', $key, $locale)
        );
        $this->assertNotSame('', trim((string) $lang['site_form'][$key]));
      }
    }
  }

  #[Test]
  public function each_panel_closes_its_own_markup(): void
  {
    $form = $this->form();
    preg_match_all(
      '/wb-tabs-panel \{\{ \$siteTab === \'([a-z-]+)\'/',
      $form,
      $matches,
      PREG_OFFSET_CAPTURE
    );

    $panels = $matches[0];
    $keys = $matches[1];

    // Every panel except the last is bounded by the next one. An unbalanced
    // panel swallows the panels after it, which then never render.
    for ($index = 0; $index < count($panels) - 1; $index++) {
      $segment = substr(
        $form,
        (int) $panels[$index][1],
        (int) $panels[$index + 1][1] - (int) $panels[$index][1]
      );

      $this->assertSame(
        preg_match_all('/<div\b/', $segment),
        preg_match_all('/<\/div>/', $segment),
        sprintf('The %s panel does not close its own markup.', $keys[$index][0])
      );
    }
  }

  #[Test]
  public function the_controller_and_the_form_share_one_tab_list(): void
  {
    $controller = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Controllers/Admin/SiteController.php'
    );

    // Both read the "last active tab" back through Site::normalizeAdminFormTab,
    // the one place that unwraps wb-tabs' synced panel id and validates it
    // against ADMIN_FORM_TABS. A second, independent in_array/ADMIN_FORM_TABS
    // check in the controller would drift from it silently.
    $this->assertStringContainsString('Site::normalizeAdminFormTab(', $controller);
    $this->assertStringContainsString('Site::normalizeAdminFormTab(', $this->form());
    $this->assertStringNotContainsString('Site::ADMIN_FORM_TABS', $controller);
    $this->assertDoesNotMatchRegularExpression(
      "/in_array\(\\\$requestedTab, \['site'/",
      $controller,
      'The tab list belongs to Site::ADMIN_FORM_TABS, not a second literal array.'
    );
  }

  #[Test]
  public function normalize_admin_form_tab_unwraps_the_synced_panel_id(): void
  {
    foreach (Site::ADMIN_FORM_TABS as $tab) {
      $this->assertSame($tab, Site::normalizeAdminFormTab('site-settings-'.$tab.'-panel'));
      $this->assertSame($tab, Site::normalizeAdminFormTab($tab), 'A bare tab key (from ?tab=) must still resolve.');
    }

    $this->assertSame('site', Site::normalizeAdminFormTab(null));
    $this->assertSame('site', Site::normalizeAdminFormTab(''));
    $this->assertSame('site', Site::normalizeAdminFormTab('not-a-real-tab'));
  }

  /**
   * Tab buttons used to be <a href="?tab=..."> links: clicking one navigated
   * away and dropped every unsaved edit on every other tab, even though the
   * card's single "Save Changes" button implied one shared save. The strip
   * must stay a client-side toggle (the shipped wb-tabs widget, driven by
   * data-wb-tab/data-wb-tabs) so switching tabs never reloads the page. The
   * "last active tab" hidden field must be synced by the widget itself via
   * data-wb-tabs-field, not by a CMS-authored wb:tabs:change listener — that
   * class of hand-rolled workaround is exactly what shipped in webblocks-ui
   * 2.17.0 to close, and CMS must not reintroduce a local copy of it.
   */
  #[Test]
  public function the_tab_strip_switches_client_side_instead_of_navigating(): void
  {
    $form = $this->form();

    $this->assertStringContainsString('data-wb-tabs', $form);
    $this->assertStringContainsString('data-wb-tabs-field="[data-wb-site-settings-tab-input]"', $form);
    $this->assertStringContainsString('data-wb-site-settings-tab-input', $form);
    $this->assertStringNotContainsString('$tabUrl', $form);
    $this->assertDoesNotMatchRegularExpression(
      '/<a\b[^>]*class="wb-tabs-btn/',
      $form,
      'A tab button rendered as a link navigates the page instead of toggling a panel.'
    );
  }

  #[Test]
  public function every_tab_button_targets_its_own_panel_by_id(): void
  {
    $form = $this->form();

    // The button loop derives data-wb-tab from the same $tabKey it labels
    // with, so it stays in lockstep with Site::ADMIN_FORM_TABS by
    // construction; combined with the one-panel-per-tab guard above, every
    // panel below just needs the matching literal id to exist.
    $this->assertStringContainsString(
      'data-wb-tab="site-settings-{{ $tabKey }}-panel"',
      $form,
      'The tab button must derive its target panel id from the loop key, not a hardcoded one.'
    );

    foreach (Site::ADMIN_FORM_TABS as $tab) {
      $panelId = 'site-settings-'.$tab.'-panel';

      $this->assertStringContainsString(
        'id="'.$panelId.'"',
        $form,
        sprintf('No panel carries %s.', $panelId)
      );
    }
  }

  /**
   * A disabled Delete button with no explanation looks broken rather than
   * blocked. SiteDeleteResult already computes why a site cannot be
   * deleted (primary site, last remaining site, linked contact messages);
   * the form must surface that as a title so the disabled state reads as
   * "blocked, here's why" instead of dead UI.
   */
  #[Test]
  public function the_disabled_delete_button_explains_why_via_its_blockers(): void
  {
    $form = $this->form();

    $this->assertStringContainsString('siteDeleteReport->canDelete', $form);
    $this->assertStringContainsString(
      "'title' => implode(' ', \$siteDeleteReport->blockers)",
      $form,
      'The disabled Delete button must surface SiteDeleteResult::$blockers as its title.'
    );
  }

  /**
   * A CMS-authored "keep the active tab synced to a hidden field" listener
   * (site-settings-tabs.js) shipped here briefly before webblocks-ui 2.17.0
   * added data-wb-tabs-field to do this natively. Reintroducing a local copy
   * — for this form or any other — is the standing violation to guard
   * against: fix gaps in webblocks-ui, don't re-implement them downstream.
   */
  #[Test]
  public function no_cms_authored_tab_sync_script_exists_for_this_form(): void
  {
    $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/public/cms/js/admin/site-settings-tabs.js');
    $this->assertStringNotContainsString('site-settings-tabs.js', $this->form());
    $this->assertNotContains('cms/js/admin/site-settings-tabs.js', WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES);
    $this->assertNotContains('cms/js/admin/site-settings-tabs.js', WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES);
  }
}
