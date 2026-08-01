<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The details modal is included from the Sites index, which scopes its own
 * $adminText to the bare "admin." namespace. The partial must resolve its
 * own keys under "site_form." (matching where the strings actually live) or
 * every label falls through CmsTranslator's missing-key fallback and the
 * modal renders raw keys like "admin.site_details" instead of text.
 */
class SiteDetailsModalCompilesTest extends TestCase
{
  private function source(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/sites/partials/details-modal.blade.php'
    );
  }

  #[Test]
  public function the_partial_scopes_its_own_translator_to_site_form(): void
  {
    $this->assertStringContainsString(
      "admin('site_form.'.\$key",
      $this->source(),
      'The partial must not rely on the unscoped $adminText it inherits from the Sites index include.'
    );
  }

  #[Test]
  public function every_key_the_modal_references_exists_under_site_form_in_every_shipped_locale(): void
  {
    preg_match_all('/\$adminText\(\'([a-z_]+)\'/', $this->source(), $matches);
    $keys = array_unique($matches[1]);

    $this->assertNotEmpty($keys, 'Expected to find $adminText(...) calls in the details modal.');

    foreach (['en', 'tr', 'de'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ($keys as $key) {
        $this->assertArrayHasKey($key, $lang['site_form'], sprintf('Missing site_form.%s in the %s strings.', $key, $locale));
        $this->assertNotSame('', trim((string) $lang['site_form'][$key]));
      }
    }
  }
}
