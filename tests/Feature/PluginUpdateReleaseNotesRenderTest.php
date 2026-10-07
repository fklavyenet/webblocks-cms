<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogRelease;
use WebBlocks\Cms\Tests\TestCase;

class PluginUpdateReleaseNotesRenderTest extends TestCase
{
  public function test_release_notes_render_as_separate_escaped_list_items(): void
  {
    $release = CatalogRelease::fromArray(['summary' => 'Readable update summary', 'notes' => "- Show badges.\n- <script>alert('unsafe')</script>\n- <img src=x onerror=alert(1)>\n- Keep state.", 'details_url' => 'https://plugins.example.test/releases/0.4.0']);
    $html = $this->render($release);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $this->assertSame(4, $xpath->query('//ul[contains(@class,"wb-marker-list")]/li')->length);
    $this->assertSame(0, $xpath->query('//script|//img')->length);
    $this->assertStringContainsString('&lt;script&gt;', $html);
    $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    $this->assertSame(1, substr_count($html, 'Readable update summary'));
  }

  public function test_missing_notes_and_unsafe_links_have_safe_fallbacks(): void
  {
    $html = $this->render(CatalogRelease::fromArray(['details_url' => 'javascript:alert(1)']));
    $this->assertStringContainsString('Notes unavailable', $html);
    $this->assertStringNotContainsString('<a ', $html);
    $this->assertStringNotContainsString('<ul', $html);
  }

  private function render(CatalogRelease $release): string
  {
    return view('webblocks-cms::admin.system.plugins.partials.update-release-notes', [
      'updateModalId' => 'plugin-update-example',
      'plugin' => ['catalog_update' => ['summary' => $release->summary, 'note_items' => $release->noteItems(), 'details_url' => $release->detailsUrl]],
      'systemPluginsIndexText' => static fn ($key) => ['update_release_notes' => "What's new", 'update_release_notes_unavailable' => 'Notes unavailable', 'update_release_notes_link' => 'Release details'][$key],
    ])->render();
  }
}
